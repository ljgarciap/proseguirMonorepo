<?php
// Seed manual para validación visual de SCRUM-339 (Solicitud de Documentos,
// Crédito Ordinario). Identificable por el prefijo SCRUM339-PW- y el
// requirement "RUT Playwright 339". Reusa el usuario de portal 'cliente'
// puro ya sembrado por UserSeeder (doc 2345/2345) y el coordinador (1234/1234).

use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\ClientUpload;
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

// Higiene compartida (mismo hallazgo que seed_scrum_193_205.php): doc 2345
// es el único usuario portal 'cliente' autenticable para e2e y lo reutilizan
// TODOS los fixtures de este directorio — una solicitud 'pendiente' que
// dejó otro fixture le gana el fallback de
// DocumentRequestController::activeRequest() ("sin match exacto, la más
// reciente pendiente de este cliente") a la de este test, y Mis Cargas
// termina mostrando datos de un fixture ajeno. Se cancelan acá todas las
// demás 'pendiente' de este cliente antes de crear/resetear la propia.
DocumentRequest::where('cliente_id', $usuarioCliente->id)
    ->where('estado', 'pendiente')
    ->update(['estado' => 'cancelado']);

$cliente = Cliente::firstOrCreate(
    ['numero_documento' => '234539'],
    [
        'tipo_persona_id' => $tipoNatural->id,
        'tipo_documento_id' => $docCC->id,
        'identificacion' => '234539',
        'nombre' => 'Cliente Playwright 339',
        'nombres' => 'Cliente', 'primer_apellido' => 'Playwright 339',
        'correo_electronico' => 'cliente@test.com',
        'telefono' => '3000000339',
        'direccion' => 'Calle Playwright 339',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

$preset = DocumentPreset::firstOrCreate(['nombre' => 'Preset Playwright 339'], ['descripcion' => 'Etapa 1 de prueba SCRUM-339']);
$requirement = DocumentRequirement::firstOrCreate(['nombre' => 'RUT Playwright 339'], ['activo' => true]);
$preset->requirements()->syncWithoutDetaching([$requirement->id]);

$numero = 'SCRUM339-PW-1';
$c = CreditoOrdinario::where('numero_solicitud', $numero)->first();

if ($c) {
    // Reset idempotente para poder re-correr el spec desde el principio.
    $solicitudCreditoId = $c->solicitud_credito_id;
    DocumentRequestItem::whereIn('document_request_id', DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->pluck('id'))->delete();
    DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->delete();
    $c->estado = 'revision_documental';
    $c->documentos = [];
    $c->save();
} else {
    $solicitud = SolicitudCredito::create([
        'cliente_id' => $cliente->id,
        'usuario_registra_id' => $admin->id,
        'tipo_credito_id' => $tipoOrdinario->id,
        'monto_solicitado' => 15000000,
        'plazo_meses' => 12,
        'amortizacion_id' => $amortMensual->id,
        'destino_recurso' => 'Capital de trabajo',
        'fuente_pago' => 'Ingresos operacionales',
        'correo_notificacion' => 'cliente@test.com',
        'asunto_notificacion' => 'Documentación',
        'mensaje_notificacion' => 'Adjunta los archivos.',
    ]);

    $c = CreditoOrdinario::create([
        'numero_solicitud' => $numero,
        'cliente_id' => $usuarioCliente->id,
        'solicitud_credito_id' => $solicitud->id,
        'monto' => 15000000,
        'plazo_meses' => 12,
        'estado' => 'revision_documental',
        'documentos' => [],
    ]);
}

$documentRequest = DocumentRequest::create([
    'cliente_id' => $usuarioCliente->id,
    'creado_por' => $admin->id,
    'solicitud_credito_id' => $c->solicitud_credito_id,
    'estado' => 'pendiente',
    'etapa' => 'inicial',
    'preset_id' => $preset->id,
    'preset_nombre' => $preset->nombre,
]);

// SCRUM-339 rebote (Juan Andrés): necesita un ClientUpload real detrás del
// item, no solo estado 'aprobado' — el bug de "Mis Créditos no deja
// re-cargar" solo se dispara cuando client_upload_id queda seteado (el
// archivo previo se conserva de referencia/auditoría al re-solicitar, ver
// CreditoOrdinarioController::transition()). Sin upload real, docFileCount()
// nunca lo contaba como "cargado" y el bug no se reproducía.
$uploadPrevio = ClientUpload::create([
    'user_id' => $usuarioCliente->id,
    'upload_role' => 'cliente',
    'category' => 'credito_ordinario',
    'filename' => 'rut-playwright-339-previo.pdf',
    'original_name' => 'rut-playwright-339-previo.pdf',
    'status' => 'aprobado',
]);

// Ya cargado y aprobado — el spec lo re-solicita desde el modal.
DocumentRequestItem::create([
    'document_request_id' => $documentRequest->id,
    'document_requirement_id' => $requirement->id,
    'client_upload_id' => $uploadPrevio->id,
    'estado' => 'aprobado',
]);

echo "Listo {$numero} (credito id {$c->id}) en revision_documental con DocumentRequest {$documentRequest->id}.\n";
