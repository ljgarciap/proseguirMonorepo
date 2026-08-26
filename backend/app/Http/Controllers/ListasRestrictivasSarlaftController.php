<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\CreditoOrdinario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Bandeja y detalle de Validación de Listas Restrictivas y SARLAFT
 * (SCRUM-128). Reemplaza el paso combinado analisis_sarlaft_financiero
 * de CreditoOrdinario por un estado dedicado (sarlaft_control_interno)
 * con concepto formal (favorable/desfavorable), a cargo del Oficial de
 * Cumplimiento.
 */
class ListasRestrictivasSarlaftController extends Controller
{
    use ResolvesActiveRole;

    /**
     * Bandeja: créditos pendientes/en revisión (estado sarlaft_control_interno)
     * y créditos ya diligenciados (sarlaft_concepto seteado), sin importar a
     * qué estado del BPMN haya avanzado el crédito después.
     */
    public function index(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);

        $query = CreditoOrdinario::where(function ($q) {
                $q->whereNotNull('sarlaft_concepto')
                    ->orWhere('estado', 'sarlaft_control_interno');
            })
            ->with(['cliente', 'solicitudCredito.cliente.tipoPersona', 'solicitudCredito.cliente.documentType', 'solicitudCredito.tipoCredito', 'sarlaftDiligenciadoPor']);

        if (!in_array($activeRole, ['oficial_cumplimiento', 'superadmin'])) {
            $query->whereRaw('1 = 0');
        }

        $creditos = $query->orderBy('created_at', 'desc')->get();

        $creditos->each(function (CreditoOrdinario $credito) {
            $credito->sarlaft_estado_derivado = $this->estadoDerivado($credito);
        });

        return response()->json($creditos);
    }

    /**
     * Detalle solo lectura: datos completos del cliente (natural o jurídica)
     * y del crédito solicitado. Oficial de Cumplimiento/superadmin pueden
     * editar; Coordinador Comercial puede consultarlo (SCRUM-184) pero
     * queda en solo lectura por autorizarAccion().
     */
    public function show(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarVisualizacion($activeRole);

        $credito = CreditoOrdinario::where(function ($q) {
                $q->whereNotNull('sarlaft_concepto')
                    ->orWhere('estado', 'sarlaft_control_interno');
            })
            ->with([
                'cliente',
                'solicitudCredito.cliente.tipoPersona',
                'solicitudCredito.cliente.documentType',
                'solicitudCredito.cliente.repDocumentType',
                'solicitudCredito.tipoCredito',
                'solicitudCredito.amortizacion',
                'sarlaftDiligenciadoPor',
            ])->findOrFail($creditoId);

        return response()->json([
            'credito' => $credito,
            'sarlaft_estado_derivado' => $this->estadoDerivado($credito),
        ]);
    }

    /**
     * Guarda concepto/observaciones/PDF sin transicionar el expediente.
     */
    public function guardarBorrador(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $credito = CreditoOrdinario::findOrFail($creditoId);

        $this->autorizarAccion($activeRole, $credito);

        $errorArchivo = $this->guardarCamposYArchivo($request, $credito);
        if ($errorArchivo) {
            return $errorArchivo;
        }

        return response()->json($credito->fresh());
    }

    /**
     * Exige los 3 campos (concepto + observaciones + PDF) y transiciona el
     * expediente según el concepto emitido.
     */
    public function finalizar(Request $request, $creditoId)
    {
        $activeRole = $this->resolveActiveRole($request);
        $user = Auth::user();
        $credito = CreditoOrdinario::findOrFail($creditoId);

        $this->autorizarAccion($activeRole, $credito);

        $errorArchivo = $this->guardarCamposYArchivo($request, $credito);
        if ($errorArchivo) {
            return $errorArchivo;
        }

        $credito->refresh();

        $concepto = $credito->sarlaft_concepto;
        $observaciones = $credito->sarlaft_observaciones;
        $documentos = $credito->documentos ?? [];
        $pdf = $documentos['sintesis_oficial_cumplimiento'] ?? null;

        if (empty($concepto)) {
            return response()->json(['message' => 'Seleccione el resultado de la validación.'], 422);
        }

        if (empty($observaciones)) {
            return response()->json(['message' => 'Ingrese las observaciones que sustentan el concepto.'], 422);
        }

        if (empty($pdf)) {
            return response()->json(['message' => 'Adjunte el documento Síntesis Oficial de Cumplimiento en formato PDF.'], 422);
        }

        $estadoAnterior = $credito->estado;
        $estadoNuevo = $concepto === 'favorable' ? 'pendiente_analisis_financiero' : 'rechazado';

        if ($concepto === 'desfavorable') {
            $credito->resultado_origen = 'sarlaft';
        }

        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $user->name,
            'rol' => $activeRole,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'comentario' => $concepto === 'favorable'
                ? 'Concepto de Listas Restrictivas y SARLAFT: favorable. Pasa a análisis financiero.'
                : 'Concepto de Listas Restrictivas y SARLAFT: desfavorable. Crédito rechazado. ' . $observaciones,
        ];

        $credito->estado = $estadoNuevo;
        $credito->historial_estados = $historial;
        $credito->sarlaft_diligenciado_por_id = $user->id;
        $credito->sarlaft_diligenciado_at = now();
        $credito->save();

        // SCRUM-267: notifica al Coordinador Comercial responsable en los 2
        // caminos (antes solo el desfavorable disparaba algo).
        (new \App\Services\SarlaftValidacionNotificationService())->notificar($credito, $concepto);

        return response()->json($credito->fresh());
    }

    /**
     * Persiste concepto/observaciones (si vienen en el request) y el PDF
     * (si se adjuntó). Devuelve una respuesta 422 si el archivo adjunto no
     * es un PDF válido, o null si todo salió bien.
     */
    private function guardarCamposYArchivo(Request $request, CreditoOrdinario $credito)
    {
        // mimes:pdf valida el contenido real del archivo (no la extensión que
        // manda el cliente, fácilmente falseable) — mismo criterio ya usado en
        // CreditoOrdinarioController::transition() para el resto de los PDFs.
        $validator = Validator::make($request->all(), [
            'sarlaft_concepto' => 'nullable|string|in:favorable,desfavorable',
            'sarlaft_observaciones' => 'nullable|string',
            'archivo' => 'nullable|file|mimes:pdf|max:102400',
        ], [
            'archivo.mimes' => 'El archivo debe estar en formato PDF.',
            'archivo.max' => 'El archivo debe estar en formato PDF.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if ($request->has('sarlaft_concepto')) {
            $credito->sarlaft_concepto = $request->input('sarlaft_concepto') ?: null;
        }

        if ($request->has('sarlaft_observaciones')) {
            $credito->sarlaft_observaciones = $request->input('sarlaft_observaciones');
        }

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $fileName = $file->getClientOriginalName();
            $path = $file->storeAs('credito_documentos/' . $credito->id, $fileName, 'public');

            // documentos_raw: se reescribe el JSON completo más abajo, no
            // solo el campo que cambia (ver nota en CreditoOrdinarioController).
            $documentos = $credito->documentos_raw ?? [];
            // Ruta relativa; el modelo resuelve la URL al leer (SCRUM-148).
            $documentos['sintesis_oficial_cumplimiento'] = $path;
            $credito->documentos = $documentos;
        }

        $credito->save();

        return null;
    }

    /**
     * Estado derivado para la bandeja/tiles: Pendiente (nada diligenciado
     * todavía), En revisión (borrador guardado, no finalizado), Favorable o
     * Desfavorable (concepto emitido).
     */
    private function estadoDerivado(CreditoOrdinario $credito): string
    {
        if ($credito->sarlaft_concepto === 'favorable') {
            return 'Favorable';
        }

        if ($credito->sarlaft_concepto === 'desfavorable') {
            return 'Desfavorable';
        }

        $tieneBorrador = !empty($credito->sarlaft_observaciones)
            || !empty(($credito->documentos ?? [])['sintesis_oficial_cumplimiento'] ?? null);

        return $tieneBorrador ? 'En revisión' : 'Pendiente';
    }

    private function autorizarVisualizacion(string $activeRole): void
    {
        // SCRUM-184: Coordinador Comercial puede consultar el detalle
        // (solo lectura — autorizarAccion() abajo sigue exigiendo
        // Oficial de Cumplimiento para guardar/finalizar).
        if (in_array($activeRole, ['superadmin', 'oficial_cumplimiento', 'coordinador_comercial'], true)) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para ver esta validación.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    private function autorizarAccion(string $activeRole, CreditoOrdinario $credito): void
    {
        if ($activeRole === 'superadmin') {
            return;
        }

        if ($activeRole !== 'oficial_cumplimiento' || $credito->estado !== 'sarlaft_control_interno') {
            abort(response()->json([
                'message' => 'No tienes autorización para actuar sobre esta validación en su estado actual.',
                'rol_activo' => $activeRole,
            ], 403));
        }
    }
}
