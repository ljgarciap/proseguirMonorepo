<?php

namespace Tests\Feature;

use App\Contracts\Firmable;
use App\Models\DocumentType;
use App\Models\FirmaElectronica;
use App\Models\User;
use App\Services\FirmaElectronica\FirmaElectronicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SCRUM-245 — Prueba el servicio directamente contra un Firmable de mentira
 * (DocumentoDePruebaFirmable, definido al final de este archivo) en vez de
 * pasar por HTTP: el allowlist del controller (TIPOS_FIRMABLES) queda
 * vacío a propósito en este ticket porque no se conecta ningún módulo
 * real todavía, así que la ruta HTTP no tiene ningún tipo válido que
 * ejercitar. Lo que sí es verificable end-to-end es la lógica de negocio
 * del servicio, que es exactamente lo que este ticket entrega.
 */
class FirmaElectronicaServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private FirmaElectronicaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->usuario = User::create([
            'name' => 'Firmante Test',
            'email' => 'firmante.fe@test.com',
            'password' => bcrypt('clave-correcta'),
            'numero_documento' => 'firma_fe_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);

        $this->service = app(FirmaElectronicaService::class);
    }

    public function test_password_incorrecta_bloquea_la_firma_y_no_persiste_nada(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 1);

        $this->expectException(ValidationException::class);

        try {
            $this->service->firmar(
                documento: $documento,
                usuario: $this->usuario,
                metodoValidacion: 'password_reauth',
                credenciales: ['password' => 'clave-incorrecta'],
                direccionIp: '127.0.0.1',
                userAgent: 'PHPUnit',
            );
        } finally {
            $this->assertSame(0, FirmaElectronica::count());
        }
    }

    public function test_password_correcta_firma_y_persiste_hash_del_pdf_congelado(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 2);

        $firma = $this->service->firmar(
            documento: $documento,
            usuario: $this->usuario,
            metodoValidacion: 'password_reauth',
            credenciales: ['password' => 'clave-correcta'],
            direccionIp: '10.0.0.5',
            userAgent: 'PHPUnit',
        );

        $this->assertSame(1, FirmaElectronica::count());
        $this->assertSame(DocumentoDePruebaFirmable::class, $firma->firmable_type);
        $this->assertSame(2, $firma->firmable_id);
        $this->assertSame($this->usuario->id, $firma->usuario_id);
        $this->assertSame('Firmante Test', $firma->nombre_firmante);
        $this->assertSame('firma_fe_1', $firma->numero_documento_firmante);
        $this->assertSame('coordinador_comercial', $firma->rol_firmante);
        $this->assertSame('password_reauth', $firma->metodo_validacion);
        $this->assertSame('10.0.0.5', $firma->direccion_ip);
        $this->assertSame(hash('sha256', $documento->generarPdfParaFirma()), $firma->documento_hash_sha256);

        Storage::disk('public')->assertExists($firma->documento_path);
    }

    public function test_verificar_detecta_que_el_archivo_fue_alterado_despues_de_firmar(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 3);

        $firma = $this->service->firmar(
            documento: $documento,
            usuario: $this->usuario,
            metodoValidacion: 'password_reauth',
            credenciales: ['password' => 'clave-correcta'],
            direccionIp: '127.0.0.1',
            userAgent: null,
        );

        $this->assertTrue($this->service->verificar($firma));

        Storage::disk('public')->put($firma->documento_path, 'contenido alterado despues de firmar');

        $this->assertFalse($this->service->verificar($firma));
    }

    public function test_verificar_detecta_que_el_archivo_fue_borrado(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 4);

        $firma = $this->service->firmar(
            documento: $documento,
            usuario: $this->usuario,
            metodoValidacion: 'password_reauth',
            credenciales: ['password' => 'clave-correcta'],
            direccionIp: '127.0.0.1',
            userAgent: null,
        );

        Storage::disk('public')->delete($firma->documento_path);

        $this->assertFalse($this->service->verificar($firma));
    }

    public function test_metodo_de_validacion_no_soportado_es_rechazado(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 5);

        $this->expectException(ValidationException::class);

        $this->service->firmar(
            documento: $documento,
            usuario: $this->usuario,
            metodoValidacion: 'otp_email',
            credenciales: [],
            direccionIp: '127.0.0.1',
            userAgent: null,
        );
    }

    public function test_rol_no_autorizado_bloquea_la_firma(): void
    {
        $documento = new DocumentoDePruebaFirmable(id: 6, rolesAutorizados: ['gerente']);

        $this->expectException(ValidationException::class);

        try {
            $this->service->firmar(
                documento: $documento,
                usuario: $this->usuario, // rol 'coordinador_comercial', no está en el allowlist del documento
                metodoValidacion: 'password_reauth',
                credenciales: ['password' => 'clave-correcta'],
                direccionIp: '127.0.0.1',
                userAgent: null,
            );
        } finally {
            $this->assertSame(0, FirmaElectronica::count());
        }
    }

    public function test_firmas_electronicas_es_append_only_update_rechazado_por_trigger(): void
    {
        $this->omitirSiNoEsMysql();

        $firma = $this->crearFirmaSimple();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('firmas_electronicas')
            ->where('id', $firma->id)
            ->update(['documento_hash_sha256' => str_repeat('0', 64)]);
    }

    public function test_firmas_electronicas_es_append_only_delete_rechazado_por_trigger(): void
    {
        $this->omitirSiNoEsMysql();

        $firma = $this->crearFirmaSimple();

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('firmas_electronicas')->where('id', $firma->id)->delete();
    }

    /**
     * Los tests locales corren sobre SQLite (ver phpunit.xml) y el trigger
     * de la migración solo se crea sobre MySQL (mismo criterio que
     * 2026_06_14_210000_add_visto_bueno_to_internal_documents_status.php)
     * — correrlos igual sobre SQLite daría un falso rojo, no un falso
     * verde, pero se salta explícito para que quede documentado por qué
     * no corre acá y quede claro que hay que correrlo contra MySQL real
     * (test/prod) antes de confiar en el trigger.
     */
    private function omitirSiNoEsMysql(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('El trigger append-only solo existe en MySQL; tests locales corren sobre SQLite.');
        }
    }

    private function crearFirmaSimple(): FirmaElectronica
    {
        return $this->service->firmar(
            documento: new DocumentoDePruebaFirmable(id: 99),
            usuario: $this->usuario,
            metodoValidacion: 'password_reauth',
            credenciales: ['password' => 'clave-correcta'],
            direccionIp: '127.0.0.1',
            userAgent: null,
        );
    }
}

/**
 * Doble de prueba de un documento firmable — deliberadamente NO es un
 * modelo Eloquent, para probar que Firmable no depende implícitamente de
 * Eloquent (ver docblock de Firmable::getKey()).
 */
class DocumentoDePruebaFirmable implements Firmable
{
    public function __construct(
        private int $id,
        private array $rolesAutorizados = [],
    ) {
    }

    public function getKey()
    {
        return $this->id;
    }

    public static function firmableSlug(): string
    {
        return 'documento-de-prueba';
    }

    public function generarPdfParaFirma(): string
    {
        return "%PDF-1.4 contenido de prueba id={$this->id}";
    }

    public function nombreArchivoFirma(): string
    {
        return "prueba-{$this->id}";
    }

    public function rolesAutorizadosParaFirmar(): array
    {
        return $this->rolesAutorizados;
    }
}
