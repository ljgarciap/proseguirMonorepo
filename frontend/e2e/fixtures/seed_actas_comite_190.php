<?php
// Seed manual para validación visual de SCRUM-189/190 (Actas Comité de
// Crédito + Gestión de Créditos). Identificable por prefijo AC-PW- (crédito
// de sistema) y por el cliente "Cliente Playwright Manual" (para probar el
// buscador de la solicitud manual). Limpiar borrando por esos identificadores.

use App\Models\ActaComite;
use App\Models\ActaComiteSolicitud;
use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\DocumentType;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\TipoPersona;
use App\Models\User;

// Limpia actas pendiente/borrador que hayan quedado de una corrida previa de
// Playwright interrumpida a mitad de camino — "Generar acta pendiente" está
// bloqueado mientras exista una (regla de concurrencia real del módulo).
$actasSinRegistrar = ActaComite::whereIn('estado', ['pendiente', 'borrador'])->get();
foreach ($actasSinRegistrar as $actaVieja) {
    ActaComiteSolicitud::where('acta_comite_id', $actaVieja->id)->delete();
    $actaVieja->delete();
    echo "Limpiada acta sin registrar id={$actaVieja->id} (corrida previa interrumpida).\n";
}

$docCC = DocumentType::firstOrCreate(['codigo' => 'CC'], ['nombre' => 'Cédula']);
$tipoNatural = TipoPersona::firstOrCreate(['codigo' => 'NATURAL'], ['nombre' => 'Persona Natural']);
$tipoOrdinario = TipoCredito::firstOrCreate(['codigo' => 'ORDINARIO'], ['nombre' => 'Crédito Ordinario']);
$amortMensual = Amortizacion::firstOrCreate(['codigo' => 'MENSUAL'], ['nombre' => 'Mensual']);
$admin = User::where('numero_documento', '1234')->first();

// Cliente para el crédito "de sistema" que genera el acta.
$clienteSistema = Cliente::firstOrCreate(
    ['numero_documento' => '900222333'],
    [
        'tipo_persona_id' => $tipoNatural->id,
        'tipo_documento_id' => $docCC->id,
        'identificacion' => '900222333',
        'nombre' => 'Cliente Playwright Sistema',
        'nombres' => 'Cliente', 'primer_apellido' => 'Playwright Sistema',
        'correo_electronico' => 'ac.sistema.playwright@test.com',
        'telefono' => '3000000001',
        'direccion' => 'Calle Playwright 2',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

// Cliente para el buscador de la solicitud manual (SCRUM-189.2/SCRUM-190.1).
$clienteManual = Cliente::firstOrCreate(
    ['numero_documento' => '900333444'],
    [
        'tipo_persona_id' => $tipoNatural->id,
        'tipo_documento_id' => $docCC->id,
        'identificacion' => '900333444',
        'nombre' => 'Cliente Playwright Manual',
        'nombres' => 'Cliente', 'primer_apellido' => 'Playwright Manual',
        'correo_electronico' => 'ac.manual.playwright@test.com',
        'telefono' => '3000000002',
        'direccion' => 'Calle Playwright 3',
        'pais' => 'Colombia', 'departamento' => 'Valle', 'ciudad' => 'Cali', 'activo' => true,
    ]
);

$numero = 'AC-PW-1';
$creditoExistente = CreditoOrdinario::where('numero_solicitud', $numero)->first();
if ($creditoExistente) {
    // Reset idempotente: una corrida previa de Playwright ya decidió este
    // crédito en un acta (pasa a rechazado/pendiente_comite/etc.) — se
    // regresa a comite_evaluacion para que la siguiente corrida también
    // tenga un crédito "de sistema" elegible.
    $creditoExistente->estado = 'comite_evaluacion';
    $creditoExistente->resultado_origen = null;
    $creditoExistente->solicitud_gestionada = false;
    $creditoExistente->fecha_gestion = null;
    $creditoExistente->save();
    echo "Reseteado {$numero} a comite_evaluacion.\n";
} else {
    $solicitud = SolicitudCredito::create([
        'cliente_id' => $clienteSistema->id,
        'usuario_registra_id' => $admin->id,
        'tipo_credito_id' => $tipoOrdinario->id,
        'monto_solicitado' => 20000000,
        'plazo_meses' => 12,
        'amortizacion_id' => $amortMensual->id,
        'destino_recurso' => 'Capital de trabajo',
        'garantia' => 'Pagaré',
        'fuente_pago' => 'Ingresos operacionales',
        'correo_notificacion' => 'ac.sistema.playwright@test.com',
        'asunto_notificacion' => 'Documentación',
        'mensaje_notificacion' => 'Adjunta los archivos.',
    ]);

    $credito = CreditoOrdinario::create([
        'numero_solicitud' => $numero,
        'cliente_id' => $admin->id,
        'solicitud_credito_id' => $solicitud->id,
        'monto' => 20000000,
        'plazo_meses' => 12,
        'estado' => 'comite_evaluacion',
        'documentos' => [],
    ]);

    echo "Creado {$numero} (credito_ordinario id={$credito->id})\n";
}

echo "Cliente manual disponible para el buscador: {$clienteManual->nombre} ({$clienteManual->numero_documento})\n";
