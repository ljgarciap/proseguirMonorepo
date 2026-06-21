<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DocumentType;
use App\Models\ClientUpload;
use App\Jobs\ProcessUploadJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OcrMigrationTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.mistral.api_key' => 'test-api-key']);

        $this->docType = DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test.user@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => '222222',
            'tipo_documento_id' => $this->docType->id,
            'roles' => ['cliente']
        ]);
    }

    public function test_upload_dispatches_process_upload_job()
    {
        Queue::fake();
        Passport::actingAs($this->user);

        $file = UploadedFile::fake()->create('cartera_test.pdf', 200, 'application/pdf');

        $response = $this->postJson('/api/uploads', [
            'file' => $file,
            'categoria' => 'cartera',
            'active_role' => 'cliente'
        ]);

        $response->assertStatus(200);

        $upload = ClientUpload::first();
        $this->assertNotNull($upload);

        Queue::assertPushed(ProcessUploadJob::class, function ($job) use ($upload) {
            return $job->timeout === 240;
        });
    }

    public function test_job_processes_cartera_data_successfully()
    {
        // 1. Create client upload
        $file = UploadedFile::fake()->create('cartera_test.pdf', 200, 'application/pdf');
        $path = Storage::putFile('client_uploads', $file);

        $upload = ClientUpload::create([
            'user_id' => $this->user->id,
            'upload_role' => 'cliente',
            'category' => 'cartera',
            'filename' => $path,
            'original_name' => 'cartera_test.pdf',
            'status' => 'pendiente'
        ]);

        config(['services.gemini.api_key' => 'test-gemini-key']);

        // 2. Fake Gemini and Mistral APIs
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'fecha_consulta' => '15/06/2026',
                                        'registros' => [
                                            [
                                                'Cliente' => 'JUAN PEREZ',
                                                'Identificacion' => '12345678',
                                                'Ciudad' => 'BOGOTA',
                                                'ActividadEconomica' => 'COMERCIO',
                                                'Operacion' => 'OP-100',
                                                'SaldoTotal' => '10,000,000',
                                                'PlazoMeses' => '12',
                                                'TasaInteres' => '1.5',
                                                'PlanAmortizacion' => 'MENSUAL',
                                                'GarantiaDetalle' => 'Garantía 1: HIPOTECA - Lote 123',
                                                'EstadoGarantia' => 'VIGENTE',
                                                'TipoGarantia' => 'HIPOTECA',
                                                'FechaDesembolso' => '01/01/2026',
                                                'NumeroRadicado' => 'RAD-999',
                                                'EstadoCapital' => 'AL DIA',
                                                'FechaVencimientoCapital' => '01/01/2027',
                                                'ValorDesembolso' => '10,000,000',
                                                'SaldoCapital' => '9,000,000',
                                                'Vencido' => 'NO',
                                                'DiasVencido' => '0',
                                                'ValorVencido' => '0',
                                                'TieneMora' => 'NO',
                                                'ValorMora' => '0',
                                                'FechaUltimoAbono' => '01/05/2026',
                                                'ValorUltimoAbono' => '1,000,000'
                                            ]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // 3. Dispatch and run job synchronously
        ProcessUploadJob::dispatchSync($upload->id, 'cartera');

        // 4. Assert data is in DB
        $this->assertDatabaseHas('operacion_carteras', [
            'client_upload_id' => $upload->id,
            'cliente' => 'JUAN PEREZ',
            'identificacion' => '12345678',
            'valor_desembolso' => 10000000.0,
            'saldo_capital' => 9000000.0,
            'estado_capital' => 'AL DIA'
        ]);

        $this->assertDatabaseHas('system_logs', [
            'categoria' => 'cartera',
            'action' => 'OCR dual-provider processed successfully'
        ]);
    }
}
