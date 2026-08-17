<?php
// Seed manual para validación visual de SCRUM-193 (Registro de Crédito en
// CYF) + SCRUM-205 (Formalización de Garantías). Identificable por el
// prefijo GC193205-PW- y el cliente "Cliente Playwright 193-205". Reusa el
// usuario de portal 'cliente' puro ya sembrado por UserSeeder (doc 2345/2345).

use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;

$docCC = DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
$tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
$tipoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
$amortMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
$admin = User::where('numero_documento', '1234')->first();

$usuarioCliente = User::where('numero_documento', '2345')->first();
if (!$usuarioCliente) {
    echo "Falta el usuario cliente de prueba (doc 2345) — correr UserSeeder primero.\n";
    exit(1);
}

$cliente = Cliente::firstOrCreate(
    ['numero_documento' => '2345'],
    [
        'tipo_persona_id' => $tipoNatural->id,
        'tipo_documento_id' => $docCC->id,
        'identificacion' => '2345',
        'nombre' => 'Cliente Playwright 193-205',
        'nombres' => 'Cliente', 'primer_apellido' => 'Playwright 193-205',
        'correo_electronico' => 'cliente@test.com',
        'telefono' => '3000000003',
        'direccion' => 'Calle Playwright 5',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

$preset = DocumentPreset::firstOrCreate(['nombre' => 'Preset Playwright 193-205'], ['descripcion' => 'Garantías de prueba SCRUM-193/205']);
$requirement = DocumentRequirement::firstOrCreate(['nombre' => 'Pagaré Playwright 193-205'], ['activo' => true]);
$preset->requirements()->syncWithoutDetaching([$requirement->id]);

$numero = 'GC193205-PW-1';
if (!CreditoOrdinario::where('numero_solicitud', $numero)->exists()) {
    $solicitud = SolicitudCredito::create([
        'cliente_id' => $cliente->id,
        'usuario_registra_id' => $admin->id,
        'tipo_credito_id' => $tipoOrdinario->id,
        'monto_solicitado' => 20000000,
        'plazo_meses' => 12,
        'amortizacion_id' => $amortMensual->id,
        'destino_recurso' => 'Capital de trabajo',
        'garantia' => 'Pagaré',
        'fuente_pago' => 'Ingresos operacionales',
        'correo_notificacion' => 'cliente@test.com',
        'asunto_notificacion' => 'Documentación',
        'mensaje_notificacion' => 'Adjunta los archivos.',
    ]);

    CreditoOrdinario::create([
        'numero_solicitud' => $numero,
        'cliente_id' => $usuarioCliente->id,
        'solicitud_credito_id' => $solicitud->id,
        'monto' => 20000000,
        'plazo_meses' => 12,
        'estado' => 'aprobada_garantias',
        'resultado_origen' => 'comite_aprobado',
        'documentos' => [],
    ]);
    echo "Creado {$numero}\n";
} else {
    // Reset idempotente para poder re-correr el spec desde el principio.
    $c = CreditoOrdinario::where('numero_solicitud', $numero)->first();
    $solicitudCreditoId = $c->solicitud_credito_id;
    DocumentRequestItem::whereIn('document_request_id', DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->pluck('id'))->delete();
    DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->delete();
    $c->estado = 'aprobada_garantias';
    $c->resultado_origen = 'comite_aprobado';
    $c->solicitud_gestionada = false;
    $c->fecha_gestion = null;
    $c->fecha_registro_cyf = null;
    $c->radicado_cyf = null;
    $c->documentos = [];
    $c->save();
    echo "Reseteado {$numero} a aprobada_garantias (sin DocumentRequest previo).\n";
}
