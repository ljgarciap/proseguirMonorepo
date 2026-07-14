<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\CreditoOrdinario;
use App\Models\InformeTecnico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InformeTecnicoController extends Controller
{
    use ResolvesActiveRole;

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
            ->with(['cliente', 'solicitudCredito', 'informeTecnico']);

        if ($activeRole === 'ingeniero') {
            $query->where('estado', 'informe_tecnico_ingeniero');
        } elseif ($activeRole === 'coordinador_comercial') {
            $query->whereIn('estado', ['informe_tecnico_coordinador', 'informe_tecnico_finalizado']);
        } elseif ($activeRole !== 'superadmin') {
            $query->whereRaw('1 = 0');
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Detalle del informe técnico de un crédito. Crea el registro en blanco
     * (borrador) la primera vez que se abre, si todavía no existe.
     */
    public function show(Request $request, $creditoId)
    {
        $credito = $this->findCreditoConstructor($creditoId);
        $activeRole = $this->resolveActiveRole($request);

        $this->autorizarVisualizacion($activeRole, $credito->estado);

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

        $informe->update($this->datosSeccionSegunEstado($request, $credito->estado));

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

        $datos = $this->datosSeccionSegunEstado($request, $credito->estado);

        if ($credito->estado === 'informe_tecnico_ingeniero') {
            $observaciones = $request->input('observaciones_ingeniero', $informe->observaciones_ingeniero);
            if (empty($observaciones)) {
                return response()->json([
                    'message' => 'Las Observaciones del Ingeniero son obligatorias antes de registrar el informe técnico.'
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

            return response()->json($informe->fresh());
        }

        return response()->json(['message' => 'El informe técnico ya fue finalizado.'], 422);
    }

    private function findCreditoConstructor($creditoId): CreditoOrdinario
    {
        return CreditoOrdinario::whereHas('solicitudCredito.tipoCredito', function ($q) {
                $q->where('codigo', 'CONSTRUCTOR');
            })
            ->whereIn('estado', self::ESTADOS_INFORME_TECNICO)
            ->with(['cliente', 'solicitudCredito', 'informeTecnico'])
            ->findOrFail($creditoId);
    }

    /**
     * Gatilla la visibilidad del detalle (no solo la edición): el Coordinador
     * Comercial no debe poder ver el informe mientras todavía está en manos
     * del Ingeniero (evita ver un borrador ajeno antes de que le corresponda).
     * El Ingeniero sí puede ver en cualquiera de los 3 estados, ya que su
     * fase siempre ocurre primero — no hay riesgo de ver "antes de tiempo".
     */
    private function autorizarVisualizacion(string $activeRole, string $estado): void
    {
        if ($activeRole === 'superadmin' || $activeRole === 'ingeniero') {
            return;
        }

        if ($activeRole === 'coordinador_comercial' && in_array($estado, ['informe_tecnico_coordinador', 'informe_tecnico_finalizado'])) {
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

    private function datosSeccionSegunEstado(Request $request, string $estado): array
    {
        if ($estado === 'informe_tecnico_ingeniero') {
            return $request->only([
                'ventas_totales_proyecto',
                'costos',
                'invertido',
                'observaciones_ingeniero',
            ]);
        }

        if ($estado === 'informe_tecnico_coordinador') {
            return $request->only([
                'credito_solicitado',
                'saldos_por_recaudar_contraentrega',
                'analisis_financiacion',
                'coberturas',
                'observaciones_coordinador',
            ]);
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
