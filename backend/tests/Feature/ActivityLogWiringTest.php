<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-246 — Verifica que los puntos de conexión reales (login/logout de
 * AuthController) efectivamente registran actividad, no solo que el
 * servicio funcione en aislamiento (eso ya lo cubre ActivityLogTest).
 */
class ActivityLogWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->usuario = User::create([
            'name' => 'Usuario Wiring Test',
            'email' => 'wiring.act@test.com',
            'password' => bcrypt('clave-correcta'),
            'numero_documento' => 'wiring_act_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);
    }

    public function test_login_exitoso_registra_actividad(): void
    {
        // /api/login llama a $user->createToken(), que necesita un client
        // Passport de tipo 'personal_access' real en BD — el resto de la
        // suite usa Passport::actingAs() (fake auth), que no lo necesita;
        // este es el primer test que golpea el endpoint real de login.
        Client::factory()->asPersonalAccessTokenClient()->create();

        $this->postJson('/api/login', [
            'numero_documento' => 'wiring_act_1',
            'password' => 'clave-correcta',
        ])->assertOk();

        $log = ActivityLog::where('accion', 'auth.login')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->usuario->id, $log->usuario_id);
    }

    public function test_login_fallido_registra_actividad_sin_usuario(): void
    {
        $this->postJson('/api/login', [
            'numero_documento' => 'wiring_act_1',
            'password' => 'clave-incorrecta',
        ])->assertUnprocessable();

        $log = ActivityLog::where('accion', 'auth.login_fallido')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->usuario_id);
        $this->assertSame('wiring_act_1', $log->metadata['numero_documento_intentado']);
    }

    public function test_logout_registra_actividad(): void
    {
        Passport::actingAs($this->usuario);

        $this->postJson('/api/logout')->assertOk();

        $log = ActivityLog::where('accion', 'auth.logout')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->usuario->id, $log->usuario_id);
    }
}
