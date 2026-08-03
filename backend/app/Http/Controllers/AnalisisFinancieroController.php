<?php

namespace App\Http\Controllers;

use App\Exports\AnalisisFinancieroExport;
use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\AnalisisFinanciero;
use App\Models\CreditoOrdinario;
use App\Services\AnalisisFinancieroCalculoService;
use App\Services\ConfiguracionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * SCRUM-155 — Análisis Financiero. A diferencia de Informe Técnico, es un
 * único actor (Coordinador Comercial) de principio a fin — no hay
 * ROL_POR_ESTADO ni secciones con visibilidad cruzada entre roles.
 */
class AnalisisFinancieroController extends Controller
{
    use ResolvesActiveRole;

    private const ROLES_AUTORIZADOS = ['coordinador_comercial', 'superadmin'];

    public function __construct(private AnalisisFinancieroCalculoService $calculo)
    {
    }

    /**
     * Bandeja: créditos con concepto SARLAFT favorable listos para Análisis
     * Financiero (RN-01), o cuyo análisis ya fue confirmado (para poder
     * volver a consultarlo aunque el crédito ya haya avanzado más adelante
     * en el BPMN — mismo criterio que SCRUM-154 aplicó a Informe Técnico).
     */
    public function index(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);

        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            return response()->json([]);
        }

        $confirmado = function ($q) {
            $q->whereHas('analisisFinanciero', function ($aq) {
                $aq->where('estado', 'confirmado');
            });
        };

        $creditos = CreditoOrdinario::where('sarlaft_concepto', 'favorable')
            ->where(function ($q) use ($confirmado) {
                $q->where('estado', 'pendiente_analisis_financiero')->orWhere($confirmado);
            })
            ->with(['solicitudCredito.cliente', 'solicitudCredito.tipoCredito', 'analisisFinanciero'])
            ->orderBy('created_at', 'desc')
            ->get();

        $creditos->each(function (CreditoOrdinario $credito) {
            $cliente = $credito->solicitudCredito?->cliente;
            $credito->proyecto = $credito->solicitudCredito?->proyecto;
            $credito->ciudad = $cliente?->ciudad;
            $credito->tipo_credito = $credito->solicitudCredito?->tipoCredito?->nombre;
        });

        return response()->json($creditos);
    }

    /**
     * Detalle: crea el registro en blanco (borrador) la primera vez que se
     * abre, si todavía no existe.
     */
    public function show(Request $request, $creditoId)
    {
        $credito = $this->findCreditoVisible($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarVisualizacion($activeRole, $credito);

        $analisis = $credito->analisisFinanciero ?? AnalisisFinanciero::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        return response()->json([
            'credito' => $credito,
            'analisis' => $analisis,
            'calculado' => $this->calcularTodo($analisis),
        ]);
    }

    /**
     * Guardar como borrador — no cambia el estado del expediente. Autoguarda
     * solo las pestañas presentes en el request (permite autoguardado
     * parcial, igual que Informe Técnico) pero siempre recalcula el
     * resumen/validación contable completos, porque dependen de todas las
     * secciones ya persistidas.
     */
    public function guardarBorrador(Request $request, $creditoId)
    {
        $credito = $this->findCredito($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        if ($credito->analisisFinanciero?->estado === 'confirmado') {
            return response()->json(['message' => 'El análisis financiero ya fue confirmado.'], 422);
        }

        if ($request->has('cantidad_anios')) {
            $cantidadAnios = (int) $request->input('cantidad_anios');
            if ($cantidadAnios < 2 || $cantidadAnios > 3) {
                return response()->json([
                    'message' => 'La cantidad de años del análisis debe ser 2 o 3.',
                ], 422);
            }
        }

        $analisis = $credito->analisisFinanciero ?? AnalisisFinanciero::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        $datos = $request->only([
            'anio_inicial', 'cantidad_anios', 'activo', 'pasivo', 'patrimonio',
            'utilidad_neta', 'ori', 'cartera', 'observaciones', 'soporte_complementario',
        ]);

        $analisis->fill($datos);
        $analisis->save();

        return response()->json([
            'analisis' => $analisis->fresh(),
            'calculado' => $this->calcularTodo($analisis->fresh()),
        ]);
    }

    /**
     * Confirmar: valida campos obligatorios y la ecuación contable (con
     * tolerancia configurable), marca el análisis como confirmado y
     * encadena el expediente hacia Aprobación de Presentación — solo si
     * "Presentación Comité" (upload manual, fuera de alcance de este
     * ticket) también ya existe, igual que el gate ya vigente hoy.
     */
    public function confirmar(Request $request, $creditoId)
    {
        $credito = $this->findCredito($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $analisis = $credito->analisisFinanciero ?? AnalisisFinanciero::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);
        if ($analisis->estado === 'confirmado') {
            return response()->json(['message' => 'El análisis financiero ya fue confirmado.'], 422);
        }

        $datos = $request->only([
            'anio_inicial', 'cantidad_anios', 'activo', 'pasivo', 'patrimonio',
            'utilidad_neta', 'ori', 'cartera', 'observaciones', 'soporte_complementario',
        ]);
        $analisis->fill($datos);

        if (!$analisis->anio_inicial || !$analisis->cantidad_anios) {
            return response()->json([
                'message' => 'Debe configurar el año inicial y la cantidad de años antes de confirmar.',
            ], 422);
        }

        $calculado = $this->calcularTodo($analisis);
        $resumen = $calculado['resumen'];

        if (($calculado['utilidad_neta']['valores']['ingresos_ordinarios'][$resumen['anio_resumen']] ?? 0) <= 0
            || ($calculado['activo']['total_activo'][$resumen['anio_resumen']] ?? 0) <= 0) {
            return response()->json([
                'message' => 'Debe diligenciar al menos Activo e Ingresos Ordinarios antes de confirmar el análisis financiero.',
            ], 422);
        }

        $toleranciaMM = (float) ConfiguracionService::get('ANALISIS_FINANCIERO_TOLERANCIA_DIFERENCIA_MM', 5);
        if (abs($resumen['diferencia_contable']) > $toleranciaMM) {
            return response()->json([
                'message' => "La ecuación contable no cuadra: Total Activo difiere de Pasivo + Patrimonio por {$resumen['diferencia_contable']} (tolerancia configurada: {$toleranciaMM}).",
                'diferencia_contable' => $resumen['diferencia_contable'],
            ], 422);
        }

        $analisis->estado = 'confirmado';
        $analisis->diligenciado_por_id = $user->id;
        $analisis->diligenciado_por_at = now();
        $analisis->save();

        $tienePresentacionComite = !empty($credito->documentos['presentacion_comite'] ?? null);
        if ($tienePresentacionComite) {
            $this->transicionarCredito($credito, 'aprobacion_presentacion', $user->name, $activeRole,
                'Análisis financiero confirmado y presentación cargada. Pasa a aprobación de presentación por Gerencia.');
        } else {
            $this->transicionarCredito($credito, $credito->estado, $user->name, $activeRole,
                'Análisis financiero confirmado. Falta cargar la presentación para el Comité de Crédito antes de continuar.');
        }

        return response()->json([
            'analisis' => $analisis->fresh(),
            'calculado' => $this->calcularTodo($analisis->fresh()),
        ]);
    }

    /**
     * SCRUM-175 — adjuntar un soporte libre en la pestaña Resumen (no está
     * atado a un preset de documentación, a diferencia de los documentos de
     * SolicitudCredito/CreditoOrdinario). Se acumulan en
     * analisis.archivos_adjuntos, mismo gate de edición que guardarBorrador.
     */
    public function subirAdjunto(Request $request, $creditoId)
    {
        $credito = $this->findCredito($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        if ($credito->analisisFinanciero?->estado === 'confirmado') {
            return response()->json(['message' => 'El análisis financiero ya fue confirmado.'], 422);
        }

        $request->validate([
            'archivo' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $analisis = $credito->analisisFinanciero ?? AnalisisFinanciero::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        $file = $request->file('archivo');
        $nombreOriginal = $file->getClientOriginalName();
        $nombreAlmacenado = uniqid() . '_' . $nombreOriginal;
        $ruta = $file->storeAs('analisis_financiero_adjuntos/' . $credito->id, $nombreAlmacenado, 'public');

        $adjuntos = $analisis->archivos_adjuntos ?? [];
        $adjuntos[] = [
            'nombre' => $nombreOriginal,
            'ruta' => $ruta,
            'tamano' => $file->getSize(),
            'subido_por' => Auth::user()->name,
            'subido_en' => now()->toDateTimeString(),
        ];
        $analisis->archivos_adjuntos = $adjuntos;
        $analisis->save();

        return response()->json(['analisis' => $analisis->fresh()]);
    }

    public function eliminarAdjunto(Request $request, $creditoId, $index)
    {
        $credito = $this->findCredito($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        if ($credito->analisisFinanciero?->estado === 'confirmado') {
            return response()->json(['message' => 'El análisis financiero ya fue confirmado.'], 422);
        }

        $analisis = $credito->analisisFinanciero;
        $adjuntos = $analisis?->archivos_adjuntos ?? [];

        if (!isset($adjuntos[$index])) {
            abort(404, 'Adjunto no encontrado.');
        }

        Storage::disk('public')->delete($adjuntos[$index]['ruta']);
        unset($adjuntos[$index]);
        $analisis->archivos_adjuntos = array_values($adjuntos);
        $analisis->save();

        return response()->json(['analisis' => $analisis->fresh()]);
    }

    public function descargar(Request $request, $creditoId)
    {
        $credito = $this->findCreditoVisible($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarVisualizacion($activeRole, $credito);

        $analisis = $credito->analisisFinanciero;
        if (!$analisis) {
            abort(404, 'El análisis financiero aún no tiene datos para descargar.');
        }

        $calculado = $this->calcularTodo($analisis);
        $formato = $request->query('formato', 'pdf');
        $filename = 'analisis-financiero-' . $credito->numero_solicitud;

        if ($formato === 'excel') {
            return Excel::download(new AnalisisFinancieroExport($credito, $analisis, $calculado), $filename . '.xlsx');
        }

        $pdf = Pdf::loadView('analisis-financiero.pdf', [
            'credito' => $credito,
            'analisis' => $analisis,
            'solicitudCredito' => $credito->solicitudCredito,
            'cliente' => $credito->solicitudCredito?->cliente,
            'calculado' => $calculado,
            'labelsActivo' => AnalisisFinancieroExport::LABELS_ACTIVO,
            'labelsPasivo' => AnalisisFinancieroExport::LABELS_PASIVO,
            'labelsPatrimonio' => AnalisisFinancieroExport::LABELS_PATRIMONIO,
            'labelsCartera' => AnalisisFinancieroExport::LABELS_CARTERA,
        ]);

        return $pdf->download($filename . '.pdf');
    }

    /**
     * Recalcula todas las pestañas a partir de lo persistido en $analisis
     * (nunca se confía en totales del frontend). Patrimonio depende de los
     * totales ya calculados de Activo y Pasivo del mismo recálculo.
     */
    private function calcularTodo(AnalisisFinanciero $analisis): array
    {
        $anios = $this->anios($analisis);

        $activo = $this->calculo->calcularActivo($analisis->activo ?? [], $anios);
        $pasivo = $this->calculo->calcularPasivo($analisis->pasivo ?? [], $anios);
        $patrimonio = $this->calculo->calcularPatrimonio($analisis->patrimonio ?? [], $anios, $activo['total_activo'], $pasivo['total_pasivo']);
        $utilidadNeta = $this->calculo->calcularUtilidadNeta($analisis->utilidad_neta ?? [], $anios);
        $ori = $this->calculo->calcularOri($analisis->ori ?? [], $anios);
        $cartera = $this->calculo->calcularCartera($analisis->cartera ?? [], $anios);
        $resumen = $this->calculo->calcularResumen($anios, $activo, $pasivo, $patrimonio, $utilidadNeta, $cartera);

        return compact('anios', 'activo', 'pasivo', 'patrimonio', 'utilidadNeta', 'ori', 'cartera', 'resumen')
            + ['utilidad_neta' => $utilidadNeta]; // alias para el front (snake_case consistente con el resto)
    }

    private function anios(AnalisisFinanciero $analisis): array
    {
        $inicial = $analisis->anio_inicial ?? (int) date('Y') - 1;
        $cantidad = $analisis->cantidad_anios ?? 2;
        return range($inicial, $inicial + $cantidad - 1);
    }

    private function findCredito($creditoId): CreditoOrdinario
    {
        return CreditoOrdinario::where('sarlaft_concepto', 'favorable')
            ->where('estado', 'pendiente_analisis_financiero')
            ->with(['solicitudCredito.cliente.tipoPersona', 'solicitudCredito.tipoCredito', 'solicitudCredito.amortizacion', 'analisisFinanciero'])
            ->findOrFail($creditoId);
    }

    /**
     * Gate ampliado solo para VISUALIZACIÓN (show/descargar): un análisis ya
     * confirmado sigue siendo consultable/descargable sin importar a qué
     * estado haya avanzado el CreditoOrdinario después.
     */
    private function findCreditoVisible($creditoId): CreditoOrdinario
    {
        return CreditoOrdinario::where('sarlaft_concepto', 'favorable')
            ->where(function ($q) {
                $q->where('estado', 'pendiente_analisis_financiero')
                    ->orWhereHas('analisisFinanciero', function ($aq) {
                        $aq->where('estado', 'confirmado');
                    });
            })
            ->with(['solicitudCredito.cliente.tipoPersona', 'solicitudCredito.tipoCredito', 'solicitudCredito.amortizacion', 'analisisFinanciero'])
            ->findOrFail($creditoId);
    }

    private function autorizarRol(string $activeRole): void
    {
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            abort(response()->json([
                'message' => 'No tienes autorización para editar el análisis financiero.',
                'rol_activo' => $activeRole,
            ], 403));
        }
    }

    private function autorizarVisualizacion(string $activeRole, CreditoOrdinario $credito): void
    {
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            abort(response()->json([
                'message' => 'No tienes autorización para ver el análisis financiero.',
                'rol_activo' => $activeRole,
            ], 403));
        }
    }

    private function transicionarCredito(CreditoOrdinario $credito, string $estadoNuevo, string $usuario, string $rol, string $comentario): void
    {
        $historial = $credito->historial_estados ?? [];
        $historial[] = [
            'fecha' => now()->toIso8601String(),
            'usuario' => $usuario,
            'rol' => $rol,
            'estado_anterior' => $credito->estado,
            'estado_nuevo' => $estadoNuevo,
            'comentario' => $comentario,
        ];

        $credito->estado = $estadoNuevo;
        $credito->historial_estados = $historial;
        $credito->save();
    }
}
