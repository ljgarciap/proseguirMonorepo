<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // SCRUM-161: session_duration_options/default/max viven en la tabla
        // `configuraciones` (ver ConfiguracionSeeder), no en config('auth.*')
        // — hay que sembrarla para que AuthController tenga de dónde leer.
        $this->seed(ConfiguracionSeeder::class);

        $cc = DocumentType::where('codigo', 'CC')->first()
            ?? DocumentType::create(['nombre' => 'Cédula de Ciudadanía', 'codigo' => 'CC']);

        $this->user = User::create([
            'name' => 'Usuario Original',
            'email' => 'perfil.test.' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'perfil_' . uniqid(),
            'tipo_documento_id' => $cc->id,
            'roles' => ['cliente'],
        ]);
    }

    public function test_usuario_autenticado_puede_actualizar_su_nombre(): void
    {
        Passport::actingAs($this->user);

        $response = $this->patchJson('/api/profile', [
            'name' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Nombre Actualizado');

        $this->assertSame('Nombre Actualizado', $this->user->fresh()->name);
    }

    public function test_usuario_autenticado_puede_actualizar_duracion_de_sesion_con_valor_valido(): void
    {
        Passport::actingAs($this->user);

        $response = $this->patchJson('/api/profile', [
            'session_duration_minutes' => 60,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.session_duration_minutes', 60);

        $this->assertSame(60, $this->user->fresh()->session_duration_minutes);
    }

    public function test_rechaza_duracion_de_sesion_fuera_del_rango_permitido(): void
    {
        Passport::actingAs($this->user);

        // 90 días en minutos: muy por fuera de la lista cerrada de opciones.
        $response = $this->patchJson('/api/profile', [
            'session_duration_minutes' => 129600,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_duration_minutes']);

        // El valor original (default de la migración) no debe haber cambiado.
        $this->assertSame(480, $this->user->fresh()->session_duration_minutes);
    }

    public function test_rechaza_duracion_de_sesion_que_no_es_uno_de_los_valores_de_la_lista(): void
    {
        Passport::actingAs($this->user);

        // 45 no está en la lista cerrada [30, 60, 240, 480, 1440].
        $response = $this->patchJson('/api/profile', [
            'session_duration_minutes' => 45,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_duration_minutes']);
    }

    public function test_rechaza_nombre_vacio(): void
    {
        Passport::actingAs($this->user);

        $response = $this->patchJson('/api/profile', [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_requiere_autenticacion(): void
    {
        $response = $this->patchJson('/api/profile', [
            'name' => 'Sin Auth',
        ]);

        $response->assertStatus(401);
    }

    public function test_rechaza_request_sin_campos(): void
    {
        Passport::actingAs($this->user);

        $response = $this->patchJson('/api/profile', []);

        $response->assertStatus(422);
    }

    public function test_login_emite_token_usando_duracion_de_sesion_preferida_del_usuario(): void
    {
        // El grant "personal_access" que usa /api/login (createToken) requiere
        // un Client de Passport marcado como personal access client — no lo
        // seedea RefreshDatabase por defecto, hay que crearlo para este test.
        Client::factory()->asPersonalAccessTokenClient()->create();

        $this->user->update(['session_duration_minutes' => 30]);

        $response = $this->postJson('/api/login', [
            'numero_documento' => $this->user->numero_documento,
            'password' => 'password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token', 'user', 'roles']);

        // El JWT emitido por Passport codifica su expiración en el claim
        // 'exp'. Decodificamos el payload (sin validar firma, solo para
        // verificar que el TTL aplicado corresponde a la preferencia del
        // usuario, no al default de 1 año de Passport).
        $token = $response->json('token');
        $payloadSegment = explode('.', $token)[1];
        $payload = json_decode(base64_decode(strtr($payloadSegment, '-_', '+/')), true);

        $this->assertArrayHasKey('exp', $payload);

        $expiresAt = \Carbon\Carbon::createFromTimestamp($payload['exp']);
        $expectedExpiresAt = now()->addMinutes(30);

        // Tolerancia de unos segundos por el tiempo de ejecución del test.
        $this->assertTrue(
            $expiresAt->between($expectedExpiresAt->copy()->subSeconds(10), $expectedExpiresAt->copy()->addSeconds(10)),
            "Se esperaba que el token expirara cerca de {$expectedExpiresAt}, pero expira en {$expiresAt}"
        );
    }

    public function test_endpoint_de_opciones_de_duracion_refleja_lo_que_hay_en_la_tabla_configuraciones(): void
    {
        // SCRUM-161: prueba deliberadamente con valores DISTINTOS a los del
        // seeder — si el endpoint devolviera un array hardcodeado en el
        // controlador (en vez de leer la tabla), esta aserción fallaría.
        Configuracion::where('clave', 'SESSION_DURATION_OPTIONS')->update([
            'valor' => json_encode([
                ['value' => 15, 'label' => '15 minutos (custom)'],
                ['value' => 90, 'label' => '90 minutos (custom)'],
            ]),
        ]);
        Configuracion::where('clave', 'SESSION_DURATION_DEFAULT')->update(['valor' => '90']);
        Configuracion::where('clave', 'SESSION_DURATION_MAX')->update(['valor' => '90']);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/profile/session-duration-options');

        $response->assertStatus(200)
            ->assertJson([
                'options' => [
                    ['value' => 15, 'label' => '15 minutos (custom)'],
                    ['value' => 90, 'label' => '90 minutos (custom)'],
                ],
                'default' => 90,
            ]);
    }

    public function test_endpoint_de_opciones_excluye_valores_que_superan_el_maximo_configurado(): void
    {
        // El máximo (SESSION_DURATION_MAX) se baja por debajo de una de las
        // opciones sembradas por el seeder (1440) — esa opción no debe
        // aparecer en la respuesta aunque siga listada en SESSION_DURATION_OPTIONS.
        Configuracion::where('clave', 'SESSION_DURATION_MAX')->update(['valor' => '480']);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/profile/session-duration-options');

        $response->assertStatus(200);
        $values = collect($response->json('options'))->pluck('value');

        $this->assertTrue($values->contains(480));
        $this->assertFalse($values->contains(1440));
    }

    public function test_endpoint_de_opciones_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/profile/session-duration-options');

        $response->assertStatus(401);
    }
}
