<?php

namespace App\Http\Controllers;

use App\Exports\InformeTecnicoExport;
use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\CreditoOrdinario;
use App\Models\InformeTecnico;
use App\Services\InformeTecnicoCalculoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class InformeTecnicoController extends Controller
{
    use ResolvesActiveRole;

    public function __construct(private InformeTecnicoCalculoService $calculo)
    {
    }

    /**
     * Estados del expediente en los que aplica el flujo de Informe Técnico,
     * y qué rol puede actuar en cada uno (SCRUM-120, Fase 1).
     */
    private const ESTADOS_INFORME_TECNICO = [
        'informe_tecnico_ingeniero',
        'informe_tecnico_coordinador',
        'informe_tecnico_finalizado',
    ];

    private const ROL_POR_ESTADO = [
        'informe_tecnico_ingeniero' => 'ingeniero',
        'informe_tecnico_coordinador' => 'coordinador_comercial',
    ];

    /**
     * Bandeja de Informe Técnico: solo créditos tipo Constructor en alguno
     * de los estados del flujo, filtrados por el rol activo.
     */
    public function index(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);

        $query = CreditoOrdinario::whereHas('solicitudCredito.tipoCredito', function ($q) {
                $q->where('codigo', 'CONSTRUCTOR');
            })
            ->whereIn('estado', self::ESTADOS_INFORME_TECNICO)
            ->with(['cliente', 'solicitudCredito.cliente', 'informeTecnico']);

        if ($activeRole === 'ingeniero') {
            $query->where('estado', 'informe_tecnico_ingeniero');
        } elseif ($activeRole === 'coordinador_comercial') {
            $query->whereIn('estado', ['informe_tecnico_coordinador', 'informe_tecnico_finalizado']);
        } elseif ($activeRole !== 'superadmin') {
            $query->whereRaw('1 = 0');
        }

        $creditos = $query->orderBy('created_at', 'desc')->get();

        // Bandeja: proyecto/ciudad/dirección se toman de SolicitudCredito.proyecto
        // y Cliente.ciudad/direccion (no de CreditoOrdinario.cliente, que apunta al
        // User del cliente y no tiene estos campos).
        $creditos->each(function (CreditoOrdinario $credito) {
            $cliente = $credito->solicitudCredito?->cliente;
            $credito->proyecto = $credito->solicitudCredito?->proyecto;
            $credito->ciudad = $cliente?->ciudad;
            $credito->direccion = $cliente?->direccion;
        });

        return response()->json($creditos);
    }

    /**
     * Detalle del informe técnico de un crédito. Crea el registro en blanco
     * (borrador) la primera vez que se abre, si todavía no existe.
     */
    public function show(Request $request, $creditoId)
    {
        $credito = $this->findCreditoConstructorVisible($creditoId);
        $activeRole = $this->resolveActiveRole($request);

        $this->autorizarVisualizacion($activeRole, $credito);

        $informe = $credito->informeTecnico ?? InformeTecnico::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        return response()->json([
            'credito' => $credito,
            'informe' => $informe,
        ]);
    }

    /**
     * Guardar como borrador — no cambia el estado del expediente.
     */
    public function guardarBorrador(Request $request, $creditoId)
    {
        $credito = $this->findCreditoConstructor($creditoId);
        $activeRole = $this->resolveActiveRole($request);

        $this->autorizarRolParaEstado($activeRole, $credito->estado);

        $informe = $credito->informeTecnico ?? InformeTecnico::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        $informe->update($this->datosSeccionSegunEstado($request, $credito->estado, $informe));

        return response()->json($informe->fresh());
    }

    /**
     * Registrar la sección del rol activo: valida campos obligatorios,
     * bloquea esa sección y transiciona el expediente al siguiente rol.
     */
    public function registrar(Request $request, $creditoId)
    {
        $credito = $this->findCreditoConstructor($creditoId);
        $activeRole = $this->resolveActiveRole($request);
        $user = Auth::user();

        $this->autorizarRolParaEstado($activeRole, $credito->estado);

        $informe = $credito->informeTecnico ?? InformeTecnico::create([
            'credito_ordinario_id' => $credito->id,
            'estado' => 'borrador',
        ]);

        $datos = $this->datosSeccionSegunEstado($request, $credito->estado, $informe);

        if ($credito->estado === 'informe_tecnico_ingeniero') {
            $observaciones = $request->input('observaciones_ingeniero', $informe->observaciones_ingeniero);
            if (empty($observaciones)) {
                return response()->json([
                    'message' => 'Las Observaciones del Ingeniero son obligatorias antes de registrar el informe técnico.'
                ], 422);
            }

            $totalVentas = $datos['ventas_totales_proyecto']['total_ventas']
                ?? $informe->ventas_totales_proyecto['total_ventas']
                ?? 0;
            if ($totalVentas <= 0) {
                return response()->json([
                    'message' => 'Debe diligenciar al menos un valor en Ventas Totales Proyecto antes de registrar el informe técnico.'
                ], 422);
            }

            $datos['diligenciado_por_ingeniero_id'] = $user->id;
            $datos['diligenciado_por_ingeniero_at'] = now();
            $informe->update($datos);

            $this->transicionarCredito($credito, 'informe_tecnico_coordinador', $user->name, $activeRole,
                'Ingeniero registró el informe técnico. Pasa a Coordinador Comercial.');

            return response()->json($informe->fresh());
        }

        if ($credito->estado === 'informe_tecnico_coordinador') {
            $datos['diligenciado_por_coordinador_id'] = $user->id;
            $datos['diligenciado_por_coordinador_at'] = now();
            $datos['estado'] = 'registrado';
            $informe->update($datos);

            $this->transicionarCredito($credito, 'informe_tecnico_finalizado', $user->name, $activeRole,
                'Coordinador Comercial registró el informe técnico. Informe finalizado.');

            // SCRUM-128: el expediente Constructor no se detiene en
            // informe_tecnico_finalizado — encadena automáticamente a la
            // validación de Listas Restrictivas y SARLAFT en la misma
            // llamada, con su propia entrada de historial.
            $this->transicionarCredito($credito, 'sarlaft_control_interno', $user->name, $activeRole,
                'Informe técnico finalizado. Pasa automáticamente a validación de Listas Restrictivas y SARLAFT.');

            return response()->json($informe->fresh());
        }

        return response()->json(['message' => 'El informe técnico ya fue finalizado.'], 422);
    }

    /**
     * Documento consolidado (PDF o Excel) del informe técnico — habilitado
     * apenas exista algún dato guardado, no solo cuando está finalizado
     * (así lo pide el prototipo). Reusa el mismo gate de visualización que
     * el detalle (show()) para que el Coordinador no pueda descargar antes
     * de que le corresponda ver el informe.
     */
    public function descargar(Request $request, $creditoId)
    {
        $credito = $this->findCreditoConstructorVisible($creditoId);
        $activeRole = $this->resolveActiveRole($request);

        $this->autorizarVisualizacion($activeRole, $credito);

        $informe = $credito->informeTecnico;
        if (!$informe) {
            abort(404, 'El informe técnico aún no tiene datos para descargar.');
        }

        $formato = $request->query('formato', 'pdf');
        $filename = 'informe-tecnico-' . $credito->numero_solicitud;

        if ($formato === 'excel') {
            return Excel::download(new InformeTecnicoExport($credito, $informe), $filename . '.xlsx');
        }

        $pdf = Pdf::loadView('informe-tecnico.pdf', [
            'credito' => $credito,
            'informe' => $informe,
            'solicitudCredito' => $credito->solicitudCredito,
            'cliente' => $credito->solicitudCredito?->cliente,
            'v' => $informe->ventas_totales_proyecto ?? [],
            'c' => $informe->costos ?? [],
            'i' => $informe->invertido ?? [],
            'cs' => $informe->credito_solicitado ?? [],
            's' => $informe->saldos_por_recaudar_contraentrega ?? [],
            'af' => $informe->analisis_financiacion ?? [],
            'cob' => $informe->coberturas ?? [],
        ]);

        return $pdf->download($filename . '.pdf');
    }

    private function findCreditoConstructor($creditoId): CreditoOrdinario
    {
        return CreditoOrdinario::whereHas('solicitudCredito.tipoCredito', function ($q) {
                $q->where('codigo', 'CONSTRUCTOR');
            })
            ->whereIn('estado', self::ESTADOS_INFORME_TECNICO)
            ->with(['cliente', 'solicitudCredito.cliente', 'informeTecnico'])
            ->findOrFail($creditoId);
    }

    /**
     * Gate ampliado solo para VISUALIZACIÓN (show/descargar, SCRUM-128): el
     * expediente Constructor ya no se detiene en informe_tecnico_finalizado
     * — encadena automáticamente a sarlaft_control_interno (y de ahí sigue
     * avanzando por el resto del BPMN). El informe técnico ya registrado
     * debe seguir siendo consultable/descargable sin importar en qué estado
     * esté el CreditoOrdinario ahora. La EDICIÓN (guardarBorrador/registrar)
     * sigue usando findCreditoConstructor() sin relajar nada.
     */
    private function findCreditoConstructorVisible($creditoId): CreditoOrdinario
    {
        return CreditoOrdinario::whereHas('solicitudCredito.tipoCredito', function ($q) {
                $q->where('codigo', 'CONSTRUCTOR');
            })
            ->where(function ($q) {
                $q->whereIn('estado', self::ESTADOS_INFORME_TECNICO)
                    ->orWhereHas('informeTecnico', function ($iq) {
                        $iq->where('estado', 'registrado');
                    });
            })
            ->with(['cliente', 'solicitudCredito.cliente', 'informeTecnico'])
            ->findOrFail($creditoId);
    }

    /**
     * Gatilla la visibilidad del detalle (no solo la edición): el Coordinador
     * Comercial no debe poder ver el informe mientras todavía está en manos
     * del Ingeniero (evita ver un borrador ajeno antes de que le corresponda).
     * El Ingeniero sí puede ver en cualquiera de los 3 estados, ya que su
     * fase siempre ocurre primero — no hay riesgo de ver "antes de tiempo".
     *
     * SCRUM-128: además de los 2 estados originales del Coordinador, un
     * informe ya registrado (InformeTecnico.estado === 'registrado') sigue
     * siendo visible sin importar a qué estado haya avanzado el
     * CreditoOrdinario después (sarlaft_control_interno, etc.) — mismo
     * criterio que findCreditoConstructorVisible().
     */
    private function autorizarVisualizacion(string $activeRole, CreditoOrdinario $credito): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'ingeniero') {
            return;
        }

        $estado = $credito->estado;
        $informeRegistrado = $credito->informeTecnico && $credito->informeTecnico->estado === 'registrado';

        if ($activeRole === 'coordinador_comercial'
            && (in_array($estado, ['informe_tecnico_coordinador', 'informe_tecnico_finalizado']) || $informeRegistrado)) {
            return;
        }

        abort(response()->json([
            'message' => 'No tienes autorización para ver el informe técnico en esta etapa.',
            'rol_activo' => $activeRole,
        ], 403));
    }

    private function autorizarRolParaEstado(string $activeRole, string $estado): void
    {
        if ($activeRole === 'superadmin') {
            return;
        }

        $rolRequerido = self::ROL_POR_ESTADO[$estado] ?? null;

        if ($rolRequerido === null || $activeRole !== $rolRequerido) {
            abort(response()->json([
                'message' => 'No tienes autorización para actuar sobre el informe técnico en esta etapa.',
                'rol_activo' => $activeRole,
                'rol_requerido' => $rolRequerido,
            ], 403));
        }
    }

    /**
     * Solo recalcula/persiste las secciones presentes en el request (permite
     * autoguardado parcial). Los cruces entre secciones (ej. % costos e
     * invertido necesitan Total Ventas) usan lo ya persistido en $informe
     * cuando la sección de origen no viene en este request puntual.
     *
     * El backend SIEMPRE calcula los totales — nunca se confía en lo que
     * mande el frontend (SCRUM-120 Fase 2, InformeTecnicoCalculoService).
     */
    private function datosSeccionSegunEstado(Request $request, string $estado, InformeTecnico $informe): array
    {
        if ($estado === 'informe_tecnico_ingeniero') {
            $datos = [];
            $totalVentas = $informe->ventas_totales_proyecto['total_ventas'] ?? 0;

            if ($request->has('ventas_totales_proyecto')) {
                $datos['ventas_totales_proyecto'] = $this->calculo->calcularVentasTotalesProyecto(
                    $request->input('ventas_totales_proyecto', [])
                );
                $totalVentas = $datos['ventas_totales_proyecto']['total_ventas'];
            }

            if ($request->has('costos')) {
                $datos['costos'] = $this->calculo->calcularCostos($request->input('costos', []), $totalVentas);
            }

            if ($request->has('invertido')) {
                $datos['invertido'] = $this->calculo->calcularInvertido($request->input('invertido', []), $totalVentas);
            }

            if ($request->has('observaciones_ingeniero')) {
                $datos['observaciones_ingeniero'] = $request->input('observaciones_ingeniero');
            }

            return $datos;
        }

        if ($estado === 'informe_tecnico_coordinador') {
            $datos = [];

            // Contexto ya diligenciado por el Ingeniero (persistido antes de que
            // el expediente llegara a esta etapa).
            $totalVentas = $informe->ventas_totales_proyecto['total_ventas'] ?? 0;
            $costosIngeniero = $informe->costos ?? [];
            $invertidoIngeniero = $informe->invertido ?? [];
            $cuotasInicialesYaPagadas = $invertidoIngeniero['cuotas_iniciales_ya_pagadas'] ?? 0;

            $creditoSolicitado = $informe->credito_solicitado ?? [];
            $saldosPorRecaudar = $informe->saldos_por_recaudar_contraentrega ?? [];

            if ($request->has('credito_solicitado')) {
                $creditoSolicitado = $this->calculo->calcularCreditoSolicitado(
                    $request->input('credito_solicitado', []),
                    $totalVentas,
                    $cuotasInicialesYaPagadas
                );
                $datos['credito_solicitado'] = $creditoSolicitado;
            }

            $aptosVendidos = $creditoSolicitado['aptos_vendidos'] ?? 0;

            if ($request->has('saldos_por_recaudar_contraentrega')) {
                $saldosPorRecaudar = $this->calculo->calcularSaldosPorRecaudarContraentrega(
                    $request->input('saldos_por_recaudar_contraentrega', []),
                    $totalVentas,
                    $aptosVendidos,
                    $creditoSolicitado
                );
                $datos['saldos_por_recaudar_contraentrega'] = $saldosPorRecaudar;
            }

            // Análisis de Financiación y Coberturas son 100% derivados (sin
            // input directo del Coordinador) — se recalculan si cambió
            // cualquiera de las dos secciones de las que dependen.
            if ($request->has('credito_solicitado') || $request->has('saldos_por_recaudar_contraentrega')) {
                $analisisFinanciacion = $this->calculo->calcularAnalisisFinanciacion(
                    $costosIngeniero,
                    $invertidoIngeniero,
                    $creditoSolicitado,
                    $saldosPorRecaudar
                );
                $datos['analisis_financiacion'] = $analisisFinanciacion;
                $ventasTotalesProyecto = $informe->ventas_totales_proyecto ?? [];
                $datos['coberturas'] = $this->calculo->calcularCoberturas($creditoSolicitado, $analisisFinanciacion, $saldosPorRecaudar, $ventasTotalesProyecto);
            }

            if ($request->has('observaciones_coordinador')) {
                $datos['observaciones_coordinador'] = $request->input('observaciones_coordinador');
            }

            return $datos;
        }

        return [];
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
