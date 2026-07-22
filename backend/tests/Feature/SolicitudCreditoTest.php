<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\TipoPersona;
use App\Models\DocumentType;
use App\Models\TipoCredito;
use App\Models\Amortizacion;
use App\Models\Visita;
use App\Models\DocumentPreset;
use App\Models\DocumentRequirement;
use App\Models\SolicitudCredito;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Mail\SolicitudCreditoMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SolicitudCreditoTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $tipoNatural;
    private $tipoJuridica;
    private $docCC;
    private $docNIT;
    private $creditoOrdinario;
    private $tipoConstructor;
    private $amortizacionMensual;
    private $clientNatural;
    private $clientJuridico;
    private $preset;
    private $departamentoValle;
    private $ciudadCali;
    private $departamentoBogota;
    private $ciudadBogota;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Setup base parameters
        $this->docCC = DocumentType::create(['nombre' => 'Cédula', 'codigo' => 'CC']);
        $this->docNIT = DocumentType::create(['nombre' => 'NIT', 'codigo' => 'NIT']);
        $this->tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
        $this->tipoJuridica = TipoPersona::firstOrCreate(['codigo' => 'JURIDICA'], ['nombre' => 'Persona Jurídica']);
        
        $this->creditoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
        $this->tipoConstructor = TipoCredito::firstOrCreate(['codigo' => 'CONSTRUCTOR'], ['nombre' => 'Crédito Constructor']);
        $this->amortizacionMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);

        $this->departamentoValle = Departamento::create(['nombre' => 'Valle']);
        $this->ciudadCali = Ciudad::create(['nombre' => 'Cali', 'departamento_id' => $this->departamentoValle->id]);
        $this->departamentoBogota = Departamento::create(['nombre' => 'Bogotá D.C.']);
        $this->ciudadBogota = Ciudad::create(['nombre' => 'Bogotá', 'departamento_id' => $this->departamentoBogota->id]);

        // Create document preset and requirements
        $this->preset = DocumentPreset::create(['nombre' => 'Preset Crédito', 'descripcion' => 'Requisitos de crédito']);
        $req1 = DocumentRequirement::create(['nombre' => 'Formulario Solicitud', 'activo' => true]);
        $req2 = DocumentRequirement::create(['nombre' => 'Aviso Privacidad', 'activo' => true]);
        $this->preset->requirements()->attach([$req1->id, $req2->id]);

        // Create clients
        $this->clientNatural = Cliente::create([
            'tipo_persona_id' => $this->tipoNatural->id,
            'tipo_documento_id' => $this->docCC->id,
            'numero_documento' => '12345678',
            'identificacion' => '12345678',
            'nombre' => 'Juan Perez',
            'nombres' => 'Juan',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 123',
            'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true
        ]);

        $this->clientJuridico = Cliente::create([
            'tipo_persona_id' => $this->tipoJuridica->id,
            'tipo_documento_id' => $this->docNIT->id,
            'numero_documento' => '900123456',
            'identificacion' => '900123456',
            'nombre' => 'Acme Corp',
            'nombre_razon_social' => 'Acme Corp',
            'correo_electronico_empresarial' => 'info@acme.com',
            'telefono' => '601234567',
            'direccion' => 'Carrera 45',
            'pais' => 'Colombia', 'departamento' => 'Bogota', 'ciudad' => 'Bogota', 'activo' => true
        ]);

        // Create admin user
        $this->admin = User::create([
            'name' => 'Super Administrador',
            'email' => 'admin.test@test.com',
            'password' => bcrypt('password'),
            'numero_documento' => 'admin999',
            'tipo_documento_id' => $this->docCC->id,
            'roles' => ['superadmin']
        ]);
    }

    /**
     * Test list pending visits for credit registration.
     */
    public function test_can_list_pending_visits_for_credit(): void
    {
        // 1. Visit requiring credit (pending)
        $v1 = Visita::create([
            'fecha' => '2026-06-01',
            'ciudad' => 'Cali',
            'cliente_id' => $this->clientNatural->id,
            'asistentes' => 'Juan, Pedro',
            'requiere_credito' => true,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 10000000.00,
            'plazo' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Inversión',
            'fuente_pago' => 'Ventas'
        ]);

        // 2. Visit requiring credit but already registered (should not show up)
        $v2 = Visita::create([
            'fecha' => '2026-06-02',
            'ciudad' => 'Bogota',
            'cliente_id' => $this->clientJuridico->id,
            'asistentes' => 'Carlos, Luis',
            'requiere_credito' => true,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 50000000.00,
            'plazo' => 24,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital',
            'fuente_pago' => 'Ventas'
        ]);

        SolicitudCredito::create([
            'visita_id' => $v2->id,
            'cliente_id' => $this->clientJuridico->id,
            'usuario_registra_id' => $this->admin->id,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 50000000.00,
            'plazo_meses' => 24,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'info@acme.com',
            'asunto_notificacion' => 'Soporte',
            'mensaje_notificacion' => 'Mensaje',
        ]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/solicitudes-credito/pendientes');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals($v1->id, $response->json()[0]['id']);
    }

    /**
     * Test registering a credit request for a natural person client.
     */
    public function test_can_register_credit_request_for_natural_client(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 20000000.00,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'garantia' => 'Firma personal',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'juan_modificado@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'document_preset_id' => $this->preset->id,
            
            // Natural person fields update
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Perez',
            'segundo_apellido' => 'Gomez',
            'correo_electronico' => 'juan_modificado@test.com',
            'telefono' => '3119999999',
            'direccion' => 'Avenida Principal 12',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('solicitudes_credito', [
            'cliente_id' => $this->clientNatural->id,
            'monto_solicitado' => 20000000.00,
            'correo_notificacion' => 'juan_modificado@test.com'
        ]);

        // Assert Client profile got updated
        $this->assertDatabaseHas('clientes', [
            'id' => $this->clientNatural->id,
            'nombres' => 'Juan Carlos',
            'segundo_apellido' => 'Gomez',
            'correo_electronico' => 'juan_modificado@test.com'
        ]);

        // Assert User got synchronized
        $this->assertDatabaseHas('users', [
            'numero_documento' => '12345678',
            'email' => 'juan_modificado@test.com'
        ]);

        // Assert Document Request is registered
        $user = User::where('numero_documento', '12345678')->first();
        $this->assertDatabaseHas('document_requests', [
            'cliente_id' => $user->id,
            'estado' => 'pendiente'
        ]);

        $this->assertDatabaseHas('document_request_items', [
            'estado' => 'pendiente'
        ]);

        // Assert CreditoOrdinario BPMN process got initiated automatically
        $this->assertDatabaseHas('credito_ordinarios', [
            'cliente_id' => $user->id,
            'monto' => 20000000.00,
            'plazo_meses' => 12,
            'estado' => 'revision_documental'
        ]);

        // Assert Email was dispatched
        Mail::assertSent(SolicitudCreditoMail::class, function ($mail) {
            return $mail->hasTo('juan_modificado@test.com') && 
                   $mail->solicitud->monto_solicitado == 20000000.00;
        });
    }

    /**
     * SCRUM-152: cada SolicitudCredito debe tener su propio DocumentRequest,
     * con exactamente los documentos de SU preset — antes, si el cliente ya
     * tenía un DocumentRequest "pendiente" de una solicitud anterior (muy
     * común con clientes con varios créditos activos), la segunda solicitud
     * mezclaba sus requisitos en ese request ajeno y quedaba sin
     * documentRequest propio, mostrando el fallback genérico en Etapa 1 en
     * vez de los documentos del preset elegido.
     */
    public function test_store_creates_separate_document_request_per_solicitud_with_own_preset(): void
    {
        Passport::actingAs($this->admin);

        $presetB = DocumentPreset::create(['nombre' => 'Preset Otro', 'descripcion' => 'Otro set de requisitos']);
        $reqB = DocumentRequirement::create(['nombre' => 'Certificado Cámara de Comercio', 'activo' => true]);
        $presetB->requirements()->attach([$reqB->id]);

        $basePayload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'juan@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'nombres' => 'Juan',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle 123',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id,
        ];

        // Primera solicitud, con el preset A ($this->preset, 2 requisitos) —
        // se deja su DocumentRequest en 'pendiente' (nadie aprobó nada), tal
        // como ocurre en producción con un cliente con varios créditos activos.
        $resp1 = $this->postJson('/api/solicitudes-credito', $basePayload + [
            'monto_solicitado' => 10000000.00,
            'document_preset_id' => $this->preset->id,
        ]);
        $resp1->assertStatus(201);
        $solicitud1Id = $resp1->json('id');

        // Segunda solicitud del MISMO cliente, con el preset B (1 requisito).
        $resp2 = $this->postJson('/api/solicitudes-credito', $basePayload + [
            'monto_solicitado' => 20000000.00,
            'document_preset_id' => $presetB->id,
        ]);
        $resp2->assertStatus(201);
        $solicitud2Id = $resp2->json('id');

        $solicitud1 = SolicitudCredito::with('documentRequest.items')->findOrFail($solicitud1Id);
        $solicitud2 = SolicitudCredito::with('documentRequest.items')->findOrFail($solicitud2Id);

        $this->assertNotNull($solicitud1->documentRequest, 'La primera solicitud debe conservar su propio DocumentRequest.');
        $this->assertNotNull($solicitud2->documentRequest, 'La segunda solicitud debe tener su propio DocumentRequest, no compartir el de la primera.');
        $this->assertNotEquals($solicitud1->documentRequest->id, $solicitud2->documentRequest->id);

        $this->assertCount(2, $solicitud1->documentRequest->items, 'La solicitud 1 debe tener exactamente los 2 requisitos de su preset.');
        $this->assertCount(1, $solicitud2->documentRequest->items, 'La solicitud 2 debe tener exactamente el 1 requisito de su preset, sin mezclarse con el de la solicitud 1.');
        $this->assertEquals($reqB->id, $solicitud2->documentRequest->items->first()->document_requirement_id);
    }

    /**
     * Test registering a credit request for a juridical client triggers validations.
     */
    public function test_store_validates_representative_legal_fields_for_juridica(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientJuridico->id,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 150000000.00,
            'plazo_meses' => 36,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Maquinaria',
            'fuente_pago' => 'Ventas',
            'correo_notificacion' => 'info@acme.com',
            'asunto_notificacion' => 'Asunto',
            'mensaje_notificacion' => 'Mensaje',
            
            // Juridica fields without representative legal
            'nombre_razon_social' => 'Acme Corp Modificada',
            'tipo_empresa' => 'S.A.S',
            'actividad_economica' => 'Comercio',
            'correo_electronico_empresarial' => 'info@acme.com',
            'telefono' => '601999999',
            'direccion' => 'Calle Falsa 123',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoBogota->id,
            'ciudad_id' => $this->ciudadBogota->id
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'rep_tipo_documento_id',
            'rep_numero_documento',
            'rep_nombres',
            'rep_primer_apellido',
            'rep_cargo',
            'rep_correo_electronico',
            'rep_telefono'
        ]);
    }

    /**
     * SCRUM-118: la ciudad debe pertenecer al departamento elegido.
     */
    public function test_store_rejects_ciudad_that_does_not_belong_to_departamento(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->creditoOrdinario->id,
            'monto_solicitado' => 20000000.00,
            'plazo_meses' => 12,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Capital de trabajo',
            'fuente_pago' => 'Ingresos operacionales',
            'correo_notificacion' => 'juan@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3119999999',
            'direccion' => 'Avenida Principal 12',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadBogota->id, // pertenece a Bogotá D.C., no a Valle
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ciudad_id']);
    }

    /**
     * SCRUM-141: Crédito Constructor requiere la sección "Información del Proyecto".
     */
    public function test_store_requires_informacion_del_proyecto_for_constructor(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->tipoConstructor->id,
            'proyecto' => 'Torres del Valle',
            'monto_solicitado' => 500000000.00,
            'plazo_meses' => 24,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Construcción',
            'fuente_pago' => 'Ventas del proyecto',
            'correo_notificacion' => 'juan@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3119999999',
            'direccion' => 'Avenida Principal 12',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id,
            // sin proyecto_direccion / proyecto_departamento_id / proyecto_ciudad_id
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proyecto_direccion', 'proyecto_departamento_id', 'proyecto_ciudad_id']);
    }

    public function test_store_creates_constructor_request_with_proyecto_info(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->tipoConstructor->id,
            'proyecto' => 'Torres del Valle',
            'proyecto_direccion' => 'Avenida Libertador No. 96 - 50',
            'proyecto_departamento_id' => $this->departamentoBogota->id,
            'proyecto_ciudad_id' => $this->ciudadBogota->id,
            'monto_solicitado' => 500000000.00,
            'plazo_meses' => 24,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Construcción',
            'fuente_pago' => 'Ventas del proyecto',
            'correo_notificacion' => 'juan@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3119999999',
            'direccion' => 'Avenida Principal 12',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id,
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('solicitudes_credito', [
            'cliente_id' => $this->clientNatural->id,
            'proyecto' => 'Torres del Valle',
            'proyecto_direccion' => 'Avenida Libertador No. 96 - 50',
            'proyecto_departamento_id' => $this->departamentoBogota->id,
            'proyecto_ciudad_id' => $this->ciudadBogota->id,
        ]);
    }

    public function test_store_rejects_proyecto_ciudad_that_does_not_belong_to_proyecto_departamento(): void
    {
        Passport::actingAs($this->admin);

        $payload = [
            'cliente_id' => $this->clientNatural->id,
            'tipo_credito_id' => $this->tipoConstructor->id,
            'proyecto' => 'Torres del Valle',
            'proyecto_direccion' => 'Avenida Libertador No. 96 - 50',
            'proyecto_departamento_id' => $this->departamentoValle->id,
            'proyecto_ciudad_id' => $this->ciudadBogota->id, // pertenece a Bogotá D.C., no a Valle
            'monto_solicitado' => 500000000.00,
            'plazo_meses' => 24,
            'amortizacion_id' => $this->amortizacionMensual->id,
            'destino_recurso' => 'Construcción',
            'fuente_pago' => 'Ventas del proyecto',
            'correo_notificacion' => 'juan@test.com',
            'asunto_notificacion' => 'Documentación para Crédito',
            'mensaje_notificacion' => 'Por favor adjunta los archivos.',
            'nombres' => 'Juan Carlos',
            'primer_apellido' => 'Perez',
            'correo_electronico' => 'juan@test.com',
            'telefono' => '3119999999',
            'direccion' => 'Avenida Principal 12',
            'pais' => 'Colombia',
            'departamento_id' => $this->departamentoValle->id,
            'ciudad_id' => $this->ciudadCali->id,
        ];

        $response = $this->postJson('/api/solicitudes-credito', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proyecto_ciudad_id']);
    }
}
