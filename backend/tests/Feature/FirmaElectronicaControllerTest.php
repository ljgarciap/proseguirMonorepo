<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * SCRUM-245 — Solo el camino negativo es exigible end-to-end en este
 * ticket: FirmaElectronicaController::TIPOS_FIRMABLES queda vacío a
 * propósito (ningún módulo se conecta todavía), así que lo único
 * verificable por HTTP es que las rutas existen, exigen sesión, y que un
 * {tipo} no reconocido se rechaza — no un tipo real firmando de punta a
 * punta (eso lo cubre FirmaElectronicaServiceTest contra el servicio
 * directo).
 */
class FirmaElectronicaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);

        $this->usuario = User::create([
            'name' => 'Firmante Controller Test',
            'email' => 'firmante.fec@test.com',
            'password' => bcrypt('clave-correcta'),
            'numero_documento' => 'firma_fec_1',
            'tipo_documento_id' => $docCC->id,
            'roles' => ['coordinador_comercial'],
        ]);
    }

    public function test_tipo_no_reconocido_devuelve_404_en_lugar_de_resolver_una_clase_arbitraria(): void
    {
        Passport::actingAs($this->usuario);

        $this->postJson('/api/firmas/clase-arbitraria/1/firmar', [
            'metodo_validacion' => 'password_reauth',
            'password' => 'clave-correcta',
        ])->assertNotFound();
    }

    public function test_sin_autenticacion_las_rutas_de_firmas_exigen_login(): void
    {
        $this->postJson('/api/firmas/documento-de-prueba/1/firmar', [
            'metodo_validacion' => 'password_reauth',
            'password' => 'clave-correcta',
        ])->assertUnauthorized();
    }
}
