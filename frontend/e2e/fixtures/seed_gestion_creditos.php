<?php
// Seed manual para validación visual de SCRUM-178 (Gestión de Créditos).
// Identificable por prefijo GC-PW- para poder limpiarlo después.

use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
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

$cliente = Cliente::firstOrCreate(
    ['numero_documento' => '900111222'],
    [
        'tipo_persona_id' => $tipoNatural->id,
        'tipo_documento_id' => $docCC->id,
        'identificacion' => '900111222',
        'nombre' => 'Cliente Playwright GC',
        'nombres' => 'Cliente',
        'primer_apellido' => 'Playwright GC',
        'correo_electronico' => 'gc.playwright@test.com',
        'telefono' => '3000000000',
        'direccion' => 'Calle Playwright 1',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

function crearSolicitud($cliente, $tipoOrdinario, $amortMensual, $admin, $sufijo) {
    return SolicitudCredito::create([
        'cliente_id' => $cliente->id,
        'usuario_registra_id' => $admin->id,
        'tipo_credito_id' => $tipoOrdinario->id,
        'monto_solicitado' => 25000000,
        'plazo_meses' => 12,
        'amortizacion_id' => $amortMensual->id,
        'destino_recurso' => 'Capital de trabajo',
        'garantia' => 'Pagaré',
        'fuente_pago' => 'Ingresos operacionales',
        'correo_notificacion' => 'gc.playwright@test.com',
        'asunto_notificacion' => 'Documentación',
        'mensaje_notificacion' => 'Adjunta los archivos.',
    ]);
}

$casos = [
    ['sufijo' => 'APR', 'estado' => 'aprobada_garantias', 'origen' => 'comite_aprobado'],
    ['sufijo' => 'SAR', 'estado' => 'rechazado', 'origen' => 'sarlaft'],
    ['sufijo' => 'REC', 'estado' => 'rechazado', 'origen' => 'comite_rechazado'],
    ['sufijo' => 'PEN', 'estado' => 'pendiente_comite', 'origen' => 'comite_pendiente'],
];

foreach ($casos as $caso) {
    $numero = 'GC-PW-' . $caso['sufijo'];
    if (CreditoOrdinario::where('numero_solicitud', $numero)->exists()) {
        echo "Ya existe {$numero}, se omite.\n";
        continue;
    }

    $solicitud = crearSolicitud($cliente, $tipoOrdinario, $amortMensual, $admin, $caso['sufijo']);

    $documentos = [];
    if ($caso['origen'] === 'sarlaft') {
        $documentos['sintesis_oficial_cumplimiento'] = 'credito_documentos/seed-sintesis.pdf';
    }

    // CreditoOrdinario.cliente_id referencia `users` (login), distinto de
    // SolicitudCredito.cliente_id que referencia `clientes` (perfil de
    // negocio) — se usa el usuario admin de pruebas como dueño del login.
    $credito = CreditoOrdinario::create([
        'numero_solicitud' => $numero,
        'cliente_id' => $admin->id,
        'solicitud_credito_id' => $solicitud->id,
        'monto' => 25000000,
        'plazo_meses' => 12,
        'estado' => $caso['estado'],
        'resultado_origen' => $caso['origen'],
        'documentos' => $documentos,
    ]);

    if ($caso['origen'] === 'sarlaft') {
        $credito->sarlaft_concepto = 'desfavorable';
        $credito->sarlaft_observaciones = 'Coincidencia en lista OFAC (dato de prueba Playwright).';
        $credito->sarlaft_diligenciado_por_id = $admin->id;
        $credito->sarlaft_diligenciado_at = now();
        $credito->save();
    }

    echo "Creado {$numero} (credito_ordinario id={$credito->id})\n";
}
