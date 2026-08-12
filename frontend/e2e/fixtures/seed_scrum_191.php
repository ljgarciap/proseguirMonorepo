<?php
// Seed manual para validación visual de SCRUM-191 (Gestión de Créditos —
// re-envío de documentos "Pendiente por Comité" + acceso del cliente al
// Acta). Identificable por el prefijo GC191-PW- y el cliente "Cliente
// Playwright 191".

use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentPreset;
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

// Usuario de portal 'cliente' puro, ya sembrado por UserSeeder (doc 2345/2345).
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
        'nombre' => 'Cliente Playwright 191',
        'nombres' => 'Cliente', 'primer_apellido' => 'Playwright 191',
        'correo_electronico' => 'cliente@test.com',
        'telefono' => '3000000003',
        'direccion' => 'Calle Playwright 4',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

$preset = DocumentPreset::firstOrCreate(['nombre' => 'Preset Playwright 191'], ['descripcion' => 'Documentos de prueba SCRUM-191']);
$requirement = DocumentRequirement::firstOrCreate(['nombre' => 'Certificado bancario Playwright 191'], ['activo' => true]);
$preset->requirements()->syncWithoutDetaching([$requirement->id]);

function crearSolicitud191($cliente, $tipoOrdinario, $amortMensual, $admin) {
    return SolicitudCredito::create([
        'cliente_id' => $cliente->id,
        'usuario_registra_id' => $admin->id,
        'tipo_credito_id' => $tipoOrdinario->id,
        'monto_solicitado' => 15000000,
        'plazo_meses' => 12,
        'amortizacion_id' => $amortMensual->id,
        'destino_recurso' => 'Capital de trabajo',
        'garantia' => 'Pagaré',
        'fuente_pago' => 'Ingresos operacionales',
        'correo_notificacion' => 'cliente@test.com',
        'asunto_notificacion' => 'Documentación',
        'mensaje_notificacion' => 'Adjunta los archivos.',
    ]);
}

// Caso A: pendiente_comite, para notificar SIN requerir documentos (vuelve
// directo a comite_evaluacion).
$numeroA = 'GC191-PW-SIN-DOCS';
if (!CreditoOrdinario::where('numero_solicitud', $numeroA)->exists()) {
    $solicitudA = crearSolicitud191($cliente, $tipoOrdinario, $amortMensual, $admin);
    CreditoOrdinario::create([
        'numero_solicitud' => $numeroA,
        'cliente_id' => $usuarioCliente->id,
        'solicitud_credito_id' => $solicitudA->id,
        'monto' => 15000000,
        'plazo_meses' => 12,
        'estado' => 'pendiente_comite',
        'resultado_origen' => 'comite_pendiente',
        'documentos' => [],
    ]);
    echo "Creado {$numeroA}\n";
} else {
    // Reset idempotente para poder re-correr el spec.
    $c = CreditoOrdinario::where('numero_solicitud', $numeroA)->first();
    $c->estado = 'pendiente_comite';
    $c->resultado_origen = 'comite_pendiente';
    $c->solicitud_gestionada = false;
    $c->fecha_gestion = null;
    $c->save();
    echo "Reseteado {$numeroA} a pendiente_comite.\n";
}

// Caso B: pendiente_comite, para notificar REQUIRIENDO documentos (el
// cliente reenvía, el Coordinador aprueba, vuelve a comite_evaluacion).
$numeroB = 'GC191-PW-CON-DOCS';
if (!CreditoOrdinario::where('numero_solicitud', $numeroB)->exists()) {
    $solicitudB = crearSolicitud191($cliente, $tipoOrdinario, $amortMensual, $admin);
    CreditoOrdinario::create([
        'numero_solicitud' => $numeroB,
        'cliente_id' => $usuarioCliente->id,
        'solicitud_credito_id' => $solicitudB->id,
        'monto' => 15000000,
        'plazo_meses' => 12,
        'estado' => 'pendiente_comite',
        'resultado_origen' => 'comite_pendiente',
        'documentos' => [],
    ]);
    echo "Creado {$numeroB}\n";
} else {
    $c = CreditoOrdinario::where('numero_solicitud', $numeroB)->first();
    $solicitudCreditoId = $c->solicitud_credito_id;
    // Limpia cualquier DocumentRequest de una corrida previa para partir en limpio.
    \App\Models\DocumentRequestItem::whereIn('document_request_id', \App\Models\DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->pluck('id'))->delete();
    \App\Models\DocumentRequest::where('solicitud_credito_id', $solicitudCreditoId)->delete();
    $c->estado = 'pendiente_comite';
    $c->resultado_origen = 'comite_pendiente';
    $c->solicitud_gestionada = false;
    $c->fecha_gestion = null;
    $c->save();
    echo "Reseteado {$numeroB} a pendiente_comite (sin DocumentRequest previo).\n";
}

// Caso C: crédito ya con Acta firmada, para validar que el cliente NO ve el
// link "Ver" ni el panel legacy del Comité.
$numeroC = 'GC191-PW-ACTA';
if (!CreditoOrdinario::where('numero_solicitud', $numeroC)->exists()) {
    $solicitudC = crearSolicitud191($cliente, $tipoOrdinario, $amortMensual, $admin);
    CreditoOrdinario::create([
        'numero_solicitud' => $numeroC,
        'cliente_id' => $usuarioCliente->id,
        'solicitud_credito_id' => $solicitudC->id,
        'monto' => 15000000,
        'plazo_meses' => 12,
        'estado' => 'formalizacion_garantias',
        'resultado_origen' => 'comite_aprobado',
        'documentos' => ['acta_comite_firmada' => 'actas-comite/seed/acta-playwright-191.pdf'],
    ]);
    echo "Creado {$numeroC}\n";
} else {
    echo "Ya existe {$numeroC}, se omite.\n";
}
