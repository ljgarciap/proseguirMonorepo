<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-246 — Cubre el servicio directo (registro de eventos) y la ruta
 * HTTP de lectura (solo superadmin). El wiring real en AuthController
 * (login/logout) y FirmaElectronicaService lo cubren los tests de esos
 * módulos indirectamente — ver ActivityLogWiringTest para eso.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->superadmin = User::create([
            'name' => 'Superadmin Activity Test',
            'email' => 'superadmin.act@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'act_super_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['superadmin'],
        ]);

        $this->coordinador = User::create([
            'name' => 'Coordinador Activity Test',
            'email' => 'coord.act@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'act_coord_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);
    }

    public function test_registrar_persiste_snapshot_de_usuario_y_metadata(): void
    {
        $log = app(ActivityLogService::class)->registrar(
            accion: 'test.evento',
            descripcion: 'Descripción de prueba',
            usuario: $this->coordinador,
            metadata: ['clave' => 'valor'],
        );

        $this->assertSame(1, ActivityLog::count());
        $this->assertSame($this->coordinador->id, $log->usuario_id);
        $this->assertSame('Coordinador Activity Test', $log->nombre_usuario);
        $this->assertSame('test.evento', $log->accion);
        $this->assertSame(['clave' => 'valor'], $log->metadata);
    }

    public function test_registrar_sin_usuario_permite_usuario_id_nulo(): void
    {
        $log = app(ActivityLogService::class)->registrar(
            accion: 'auth.login_fallido',
            descripcion: 'Intento fallido',
        );

        $this->assertNull($log->usuario_id);
        $this->assertNull($log->nombre_usuario);
    }

    public function test_superadmin_puede_listar_activity_logs(): void
    {
        app(ActivityLogService::class)->registrar('test.evento', 'Uno', $this->coordinador);
        app(ActivityLogService::class)->registrar('test.otro', 'Dos', $this->coordinador);

        Passport::actingAs($this->superadmin);

        $response = $this->getJson('/api/activity-logs');

        $response->assertOk();
        $this->assertSame(2, $response->json('total'));
    }

    public function test_no_superadmin_no_puede_listar_activity_logs(): void
    {
        Passport::actingAs($this->coordinador);

        $this->getJson('/api/activity-logs')->assertForbidden();
    }

    public function test_filtro_por_accion(): void
    {
        app(ActivityLogService::class)->registrar('auth.login', 'Login', $this->coordinador);
        app(ActivityLogService::class)->registrar('auth.logout', 'Logout', $this->coordinador);

        Passport::actingAs($this->superadmin);

        $response = $this->getJson('/api/activity-logs?accion=auth.login');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame('auth.login', $response->json('data.0.accion'));
    }
}
