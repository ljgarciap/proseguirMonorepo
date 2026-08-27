<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveRole;
use App\Models\ActaComite;
use App\Models\ActaComiteSolicitud;
use App\Models\Amortizacion;
use App\Models\Cliente;
use App\Models\CreditoOrdinario;
use App\Models\SolicitudCredito;
use App\Models\TipoCredito;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SCRUM-169 — Actas del Comité de Crédito. La elegibilidad depende
 * únicamente de CreditoOrdinario.estado === 'comite_evaluacion', sin
 * distinguir tipo de crédito (decisión revisada 2026-08-04, SCRUM-183:
 * Constructor también pasa por el módulo de Análisis Financiero y llega
 * a este estado igual que Ordinario — la exclusión original asumía lo
 * contrario).
 *
 * SCRUM-178 (2026-08-04): registrar() SÍ mueve CreditoOrdinario.estado
 * ahora (ver sincronizarCreditosOrdinarios()) — esto completa la
 * integración que el diseño original de SCRUM-169 dejó explícitamente
 * pendiente ("independiente por ahora", ver
 * docs/specs/scrum-169-actas-comite-credito.md). El botón manual
 * aprobar/rechazar de CreditoOrdinarioController para 'comite_evaluacion'
 * quedó retirado — la única salida de ese estado es esta.
 *
 * SCRUM-183 (2026-08-05, decisión de Luis tras hablar con Lorena): se
 * eliminó el paso "Presentación para el Comité" + aprobación de Gerencia
 * (estado 'aprobacion_presentacion') — no aportaba valor real al flujo y
 * dejaba créditos con Análisis Financiero confirmado bloqueados
 * indefinidamente si nadie cargaba esa presentación. Ahora un crédito pasa
 * directo de 'pendiente_analisis_financiero' a 'comite_evaluacion' al
 * confirmar el Análisis Financiero (ver AnalisisFinancieroController::
 * confirmar()). La presentación se adjunta después, por solicitud, acá
 * mismo (ver subirPresentacion()) — el Coordinador Comercial la sube
 * cuando arma el Acta, no antes.
 */
class ActaComiteController extends Controller
{
    use ResolvesActiveRole;

    private const ROLES_AUTORIZADOS = ['coordinador_comercial', 'superadmin'];

    /**
     * SCRUM-198: se quitaron "Presentación de solicitudes de crédito." y
     * "Decisión de solicitudes presentadas." — son texto libre redundante
     * con funcionalidad que el Acta ya tiene estructurada (la tabla de
     * solicitudes de esta misma pestaña y el formulario de decisión de la
     * pestaña 3). Ver también RemoveOrdenDiaItemsScrum198 (mismo texto
     * literal, migración retroactiva para actas ya generadas).
     */
    private const ORDEN_DIA_DEFAULT = [
        'Verificación de quórum.',
        'Designación del presidente y secretario de la reunión.',
        'Lectura del acta anterior.',
        'Revisión Flujo de caja.',
        'Revisión de atribuciones en las operaciones (Capital de trabajo, constructor y factoring).',
    ];

    public function index(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            return response()->json([]);
        }

        $actas = ActaComite::withCount('solicitudes')
            ->with('elaboradaPor')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($actas);
    }

    /**
     * Genera el registro "Pendiente": toma en este instante todos los
     * CreditoOrdinario en estado comite_evaluacion (ya implica análisis
     * financiero confirmado, por construcción del workflow — ver spec) y
     * los relaciona como solicitudes de origen "sistema" con snapshot
     * desnormalizado. Solo puede existir una acta pendiente/borrador a la
     * vez (VAL-04 análogo si ya existe una).
     */
    public function generar(Request $request)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        $existeSinRegistrar = ActaComite::whereIn('estado', ['pendiente', 'borrador'])->exists();
        if ($existeSinRegistrar) {
            return response()->json([
                'message' => 'Ya existe un acta pendiente o en borrador. Regístrela o continúe su elaboración antes de generar una nueva.',
            ], 422);
        }

        $creditosElegibles = CreditoOrdinario::where('estado', 'comite_evaluacion')
            ->with(['solicitudCredito.cliente', 'solicitudCredito.tipoCredito', 'solicitudCredito.amortizacion'])
            ->get();

        if ($creditosElegibles->isEmpty()) {
            return response()->json([
                'message' => 'No hay créditos con Análisis Financiero confirmado listos para el Comité todavía.',
            ], 422);
        }

        $acta = DB::transaction(function () use ($creditosElegibles, $user) {
            $ultimaAprobada = ActaComite::where('estado', 'aprobada')->orderByDesc('numero')->first();

            $acta = ActaComite::create([
                'numero' => (ActaComite::max('numero') ?? 0) + 1,
                'estado' => 'pendiente',
                'asistentes' => $ultimaAprobada->asistentes ?? [],
                'orden_dia' => $this->ordenDiaInicial($ultimaAprobada),
                'desarrollo' => [],
                'firmantes' => [],
                'elaborada_por_id' => $user->id,
            ]);

            foreach ($creditosElegibles as $credito) {
                $this->crearSolicitudDesdeCredito($acta, $credito);
            }

            return $acta;
        });

        return response()->json($acta->load('solicitudes'), 201);
    }

    private function crearSolicitudDesdeCredito(ActaComite $acta, CreditoOrdinario $credito, string $origen = 'sistema'): ActaComiteSolicitud
    {
        $solicitud = $credito->solicitudCredito;
        $cliente = $solicitud?->cliente;

        return ActaComiteSolicitud::create([
            'acta_comite_id' => $acta->id,
            'credito_ordinario_id' => $credito->id,
            'origen' => $origen,
            'cliente_nombre' => $cliente?->nombre,
            'cliente_identificacion' => $cliente?->numero_documento,
            'tipo_solicitud' => $solicitud?->tipoCredito?->nombre,
            'monto' => $credito->monto,
            'amortizacion' => $solicitud?->amortizacion?->nombre,
            'plazo_meses' => $credito->plazo_meses,
            'garantias' => $solicitud?->garantia,
            'fuente_pago' => $solicitud?->fuente_pago,
        ]);
    }

    /**
     * SCRUM-189 (2026-08-12, punto 1): el acta pendiente/borrador queda
     * abierta potencialmente varios días mientras el Coordinador la
     * elabora — cualquier CreditoOrdinario que llegue a comite_evaluacion
     * en ese lapso quedaba huérfano, porque generar() solo toma el
     * snapshot en el instante de creación y la regla de concurrencia
     * (una sola acta pendiente/borrador a la vez) impide abrir una
     * segunda para "recogerlo". Decisión de Luis (2026-08-12): sincronizar
     * automáticamente en cada guardado, sin acción manual del Coordinador.
     * Se llama desde show() (por si el usuario solo reabre la acta sin
     * guardar nada) y desde actualizar() (autoguardado). No aplica a actas
     * `aprobada` (rechazarSiAprobada ya las excluye de edición).
     */
    private function sincronizarSolicitudesElegibles(ActaComite $acta): void
    {
        if ($acta->estado === 'aprobada') {
            return;
        }

        $idsYaIncluidos = $acta->solicitudes()->whereNotNull('credito_ordinario_id')->pluck('credito_ordinario_id');
        // SCRUM-279: créditos que el Coordinador eliminó explícitamente de
        // ESTA acta (ver eliminarSolicitud()) — siguen en comite_evaluacion
        // (elegibles para una futura acta) pero no se auto-reincorporan acá.
        $idsExcluidos = $acta->creditos_excluidos ?? [];

        $creditosNuevos = CreditoOrdinario::where('estado', 'comite_evaluacion')
            ->whereNotIn('id', $idsYaIncluidos)
            ->whereNotIn('id', $idsExcluidos)
            ->with(['solicitudCredito.cliente', 'solicitudCredito.tipoCredito', 'solicitudCredito.amortizacion'])
            ->get();

        foreach ($creditosNuevos as $credito) {
            $this->crearSolicitudDesdeCredito($acta, $credito);
        }
    }

    public function show(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            return $this->forbidden('ver');
        }

        $this->sincronizarSolicitudesElegibles($acta);

        return response()->json($acta->load('solicitudes', 'elaboradaPor', 'registradaPor'));
    }

    /**
     * Guardado genérico de campos de la cabecera del acta — usado por las
     * pestañas Orden del día (info. de reunión), Desarrollo, Observaciones
     * generales y Firmantes. Autoguardado parcial: solo persiste los campos
     * presentes en el request (mismo criterio que Análisis Financiero /
     * Informe Técnico).
     */
    public function actualizar(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        $datos = $request->only([
            'fecha_reunion', 'lugar', 'hora_inicio', 'hora_finalizacion',
            'asistentes', 'orden_dia', 'desarrollo', 'observaciones_generales', 'firmantes',
        ]);

        $acta->fill($datos);
        $acta->estado = $acta->estado === 'pendiente' ? 'borrador' : $acta->estado;
        $acta->save();

        $this->sincronizarSolicitudesElegibles($acta);

        return response()->json($acta->fresh()->load('solicitudes'));
    }

    /**
     * Aprobar el orden del día (VAL: requiere al menos un ítem). Botón
     * explícito de unanimidad — no se infiere automáticamente al guardar.
     */
    public function aprobarOrdenDia(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        if (empty($acta->orden_dia)) {
            return response()->json(['message' => 'Complete el orden del día antes de aprobarlo.'], 422);
        }

        $acta->orden_dia_aprobado = true;
        $acta->estado = $acta->estado === 'pendiente' ? 'borrador' : $acta->estado;
        $acta->save();

        return response()->json($acta->fresh());
    }

    /**
     * SCRUM-198: agregar a mano un crédito ya existente en el sistema
     * (elegible para comité) — ya no se permite tipear datos desde cero,
     * el frontend solo expone el buscador de buscarCreditosElegibles().
     * `credito_ordinario_id` es el único camino real; los campos libres
     * quedan como fallback defensivo del backend, no expuestos en la UI.
     */
    public function agregarSolicitud(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        $validado = $request->validate([
            'credito_ordinario_id' => 'nullable|exists:credito_ordinarios,id',
            'cliente_nombre' => 'required_without:credito_ordinario_id|string|max:255',
            'cliente_identificacion' => 'nullable|string|max:255',
            'tipo_solicitud' => 'required_without:credito_ordinario_id|string|max:255',
            'monto' => 'required_without:credito_ordinario_id|numeric|min:0',
        ]);

        if (!empty($validado['credito_ordinario_id'])) {
            $credito = CreditoOrdinario::where('estado', 'comite_evaluacion')
                ->with(['solicitudCredito.cliente', 'solicitudCredito.tipoCredito', 'solicitudCredito.amortizacion'])
                ->findOrFail($validado['credito_ordinario_id']);

            $yaIncluido = $acta->solicitudes()->where('credito_ordinario_id', $credito->id)->exists();
            if ($yaIncluido) {
                return response()->json(['message' => 'Ese crédito ya está incluido en esta Acta.'], 422);
            }

            $solicitud = $this->crearSolicitudDesdeCredito($acta, $credito, origen: 'manual_existente');

            // SCRUM-279: si se había excluido antes (ver eliminarSolicitud()),
            // re-agregarlo a mano revierte la exclusión — si no, quedaría un
            // ID excluido "huérfano" sin ningún efecto real pero confuso de
            // leer más adelante.
            $excluidos = $acta->creditos_excluidos ?? [];
            if (in_array($credito->id, $excluidos, true)) {
                $acta->creditos_excluidos = array_values(array_diff($excluidos, [$credito->id]));
            }
        } else {
            $solicitud = ActaComiteSolicitud::create($validado + [
                'acta_comite_id' => $acta->id,
                'origen' => 'manual',
            ]);
        }

        $acta->estado = $acta->estado === 'pendiente' ? 'borrador' : $acta->estado;
        $acta->save();

        return response()->json($solicitud, 201);
    }

    /**
     * SCRUM-198: buscador de créditos existentes para el alta manual de la
     * pestaña Desarrollo — mismo criterio de elegibilidad que
     * sincronizarSolicitudesElegibles() (estado comite_evaluacion), excluye
     * los créditos ya vinculados a ESTA acta. Search-as-you-type por
     * nombre/identificación del cliente, mínimo 3 caracteres.
     */
    public function buscarCreditosElegibles(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $idsYaIncluidos = $acta->solicitudes()->whereNotNull('credito_ordinario_id')->pluck('credito_ordinario_id');

        $creditos = CreditoOrdinario::where('estado', 'comite_evaluacion')
            ->whereNotIn('id', $idsYaIncluidos)
            ->whereHas('solicitudCredito.cliente', function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('numero_documento', 'like', "%{$q}%");
            })
            ->with(['solicitudCredito.cliente', 'solicitudCredito.tipoCredito'])
            ->limit(20)
            ->get();

        return response()->json($creditos->map(fn (CreditoOrdinario $c) => [
            'id' => $c->id,
            'cliente_nombre' => $c->solicitudCredito?->cliente?->nombre,
            'cliente_identificacion' => $c->solicitudCredito?->cliente?->numero_documento,
            'tipo_solicitud' => $c->solicitudCredito?->tipoCredito?->nombre,
            'monto' => $c->monto,
        ]));
    }

    /**
     * Actualiza el detalle/decisión de una solicitud (pestaña Decisión y
     * detalle, también editable para completar datos de las manuales).
     * VAL-06 (estado sin seleccionar) y VAL-07 (formato inválido) se
     * validan acá.
     */
    public function actualizarSolicitud(Request $request, ActaComite $acta, ActaComiteSolicitud $solicitud)
    {
        if ($solicitud->acta_comite_id !== $acta->id) {
            abort(404);
        }

        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        $validado = $request->validate([
            // 'nullable' es obligatorio en todos estos campos aunque el negocio
            // los trate como requeridos: el middleware global de Laravel
            // (ConvertEmptyStringsToNull) convierte "" a null ANTES de la
            // validación, y esta ruta reenvía el objeto completo en cada
            // guardado (no solo el campo tocado) — un valor vacío legítimo
            // (ej. un Cliente con nombre incompleto) se rechazaría con
            // "must be a string" en cualquier edición futura de esa solicitud
            // si no se admite null acá. La ausencia real (VAL-05/06) ya se
            // valida en agregarSolicitud() al crear y en camposFaltantes()
            // al registrar.
            'cliente_nombre' => 'sometimes|nullable|string|max:255',
            'cliente_identificacion' => 'sometimes|nullable|string|max:255',
            'tipo_solicitud' => 'sometimes|nullable|string|max:255',
            'monto' => 'sometimes|nullable|numeric|min:0',
            'amortizacion' => 'sometimes|nullable|string|max:255',
            'plazo_meses' => 'sometimes|nullable|integer|min:0',
            'tasa_interes' => 'sometimes|nullable|numeric|min:0',
            'porcentaje_financiacion' => 'sometimes|nullable|numeric|min:0|max:100',
            'garantias' => 'sometimes|nullable|string',
            'fuente_pago' => 'sometimes|nullable|string',
            'estado_decision' => 'sometimes|nullable|in:aprobado,rechazado,pendiente',
            'monto_decision' => 'sometimes|nullable|numeric|min:0',
            'vigencia_aprobacion' => 'sometimes|nullable|string|max:255',
            'observaciones' => 'sometimes|nullable|string',
        ]);

        $solicitud->fill($validado);
        $solicitud->save();

        return response()->json($solicitud->fresh());
    }

    /**
     * Elimina una solicitud de "Presentación de solicitudes" (SCRUM-279).
     * Si está vinculada a un CreditoOrdinario real que sigue en
     * comite_evaluacion (origen 'sistema' o 'manual_existente'), borrar la
     * fila no alcanza: sincronizarSolicitudesElegibles() la vuelve a traer
     * en la próxima carga porque el crédito real sigue siendo elegible — se
     * registra en `creditos_excluidos` para que el auto-sync la ignore.
     * Decisión explícita de Luis (2026-08-27): el crédito sigue disponible
     * para una futura acta, no cambia de estado, solo se excluye de ESTA
     * acta puntual (se puede volver a agregar a mano desde el buscador si
     * fue un error).
     */
    public function eliminarSolicitud(Request $request, ActaComite $acta, ActaComiteSolicitud $solicitud)
    {
        if ($solicitud->acta_comite_id !== $acta->id) {
            abort(404);
        }

        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        if ($solicitud->credito_ordinario_id) {
            $excluidos = $acta->creditos_excluidos ?? [];
            if (!in_array($solicitud->credito_ordinario_id, $excluidos, true)) {
                $excluidos[] = $solicitud->credito_ordinario_id;
                $acta->creditos_excluidos = $excluidos;
                $acta->save();
            }
        }

        $solicitud->delete();

        return response()->json(['message' => 'Solicitud eliminada.']);
    }

    /**
     * Sube una imagen para insertar inline en un campo rich text
     * (ngx-quill). VAL-08 si el formato/tamaño no cumple. Se guarda en el
     * disco 'public' bajo actas-comite/{acta}/ y se devuelve la URL
     * resuelta con el APP_URL actual — queda embebida tal cual en el HTML
     * guardado, pero el accessor del modelo la vuelve a resolver en cada
     * lectura futura (tolera URLs "horneadas" con un APP_URL viejo, mismo
     * mecanismo que CreditoOrdinario::resolveStorageUrl).
     */
    public function subirImagen(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'imagen.required' => 'El archivo seleccionado no cumple el formato o tamaño permitido.',
            'imagen.image' => 'El archivo seleccionado no cumple el formato o tamaño permitido.',
            'imagen.mimes' => 'El archivo seleccionado no cumple el formato o tamaño permitido.',
            'imagen.max' => 'El archivo seleccionado no cumple el formato o tamaño permitido.',
        ]);

        $path = $request->file('imagen')->store("actas-comite/{$acta->id}", 'public');

        return response()->json(['url' => CreditoOrdinario::resolveStorageUrl($path)]);
    }

    /**
     * SCRUM-183: la Presentación para el Comité de Crédito ya no bloquea la
     * transición a comite_evaluacion (ver AnalisisFinancieroController::
     * confirmar() y CreditoOrdinarioController::transition()) — se adjunta
     * acá, directo sobre la solicitud dentro del Acta, una por solicitud
     * (cada crédito puede traer la suya propia).
     */
    public function subirPresentacion(Request $request, ActaComite $acta, ActaComiteSolicitud $solicitud)
    {
        if ($solicitud->acta_comite_id !== $acta->id) {
            abort(404);
        }

        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $this->rechazarSiAprobada($acta);

        $request->validate([
            'archivo' => 'required|file|mimes:pdf|max:20480',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo PDF.',
            'archivo.mimes' => 'El archivo debe ser un PDF.',
            'archivo.max' => 'El archivo no debe superar 20 MB.',
        ]);

        $path = $request->file('archivo')->storeAs(
            "actas-comite/{$acta->id}/presentaciones",
            $request->file('archivo')->getClientOriginalName(),
            'public'
        );

        $solicitud->presentacion_comite = $path;
        $solicitud->save();

        return response()->json($solicitud->fresh());
    }

    public function previsualizar(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            return $this->forbidden('ver');
        }

        return $this->generarPdf($acta)->stream("acta-comite-{$acta->numero}.pdf");
    }

    public function descargar(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            return $this->forbidden('ver');
        }

        return $this->generarPdf($acta)->download("acta-comite-{$acta->numero}.pdf");
    }

    /**
     * Registro definitivo (VAL-09/10/11): valida que la información
     * obligatoria de todas las pestañas esté completa, bloquea el acta
     * (estado aprobada) y ya no admite edición.
     */
    public function registrar(Request $request, ActaComite $acta)
    {
        $activeRole = $this->resolveActiveRole($request);
        $this->autorizarRol($activeRole);
        $user = Auth::user();

        if ($acta->estado === 'aprobada') {
            return response()->json(['message' => 'El acta ya fue registrada.'], 422);
        }

        $faltantes = $this->camposFaltantes($acta);
        if (!empty($faltantes)) {
            return response()->json([
                'message' => 'Complete la información requerida antes de registrar el acta.',
                'campos_faltantes' => $faltantes,
            ], 422);
        }

        DB::transaction(function () use ($acta, $user) {
            $acta->estado = 'aprobada';
            $acta->registrada_por_id = $user->id;
            $acta->registrada_at = now();
            $acta->save();

            $this->sincronizarCreditosOrdinarios($acta);
            $this->materializarSolicitudesManuales($acta, $user->id);
        });

        return response()->json([
            'message' => 'El acta fue registrada correctamente y quedó disponible para consulta y descarga.',
            'acta' => $acta->fresh()->load('solicitudes'),
        ]);
    }

    /**
     * SCRUM-178 — registrar el acta es lo que efectivamente mueve
     * CreditoOrdinario.estado según la decisión tomada por solicitud
     * (integración que SCRUM-169 dejó deliberadamente pendiente el
     * 2026-08-02, ver docs/specs/scrum-169-actas-comite-credito.md). Las
     * solicitudes agregadas manualmente (sin credito_ordinario_id, créditos
     * que no existen en el sistema) se ignoran, igual que en el resto del
     * módulo.
     *
     * Se copia el PDF del acta a documentos_raw['acta_comite_firmada'] para
     * no perder la continuidad documental ahora que ese slot ya no bloquea
     * el avance del estado (ver diseño §2, caso borde "acta_comite_firmada").
     */
    private function sincronizarCreditosOrdinarios(ActaComite $acta): void
    {
        $mapaDecision = [
            'aprobado'  => ['estado' => 'aprobada_garantias', 'origen' => 'comite_aprobado'],
            'rechazado' => ['estado' => 'rechazado', 'origen' => 'comite_rechazado'],
            'pendiente' => ['estado' => 'pendiente_comite', 'origen' => 'comite_pendiente'],
        ];

        $solicitudesConCredito = $acta->solicitudes()->whereNotNull('credito_ordinario_id')->get();
        if ($solicitudesConCredito->isEmpty()) {
            return;
        }

        $pdfPath = "actas-comite/{$acta->id}/acta-comite-{$acta->numero}-firmada.pdf";
        Storage::disk('public')->put($pdfPath, $this->generarPdf($acta)->output());

        foreach ($solicitudesConCredito as $solicitud) {
            $mapeo = $mapaDecision[$solicitud->estado_decision] ?? null;
            $credito = CreditoOrdinario::find($solicitud->credito_ordinario_id);

            // Defensivo: si el crédito ya no está en comite_evaluacion (por
            // ejemplo, otra acta ya lo movió), no lo tocamos de nuevo.
            if (!$mapeo || !$credito || $credito->estado !== 'comite_evaluacion') {
                continue;
            }

            $estadoAnterior = $credito->estado;

            // Caso borde de re-decisión: si ya estaba gestionado, una nueva
            // decisión implica una nueva gestión pendiente.
            $credito->solicitud_gestionada = false;
            $credito->fecha_gestion = null;
            $credito->resultado_origen = $mapeo['origen'];
            $credito->estado = $mapeo['estado'];

            $documentos = $credito->documentos_raw ?? [];
            $documentos['acta_comite_firmada'] = $pdfPath;
            $credito->documentos = $documentos;

            $historial = $credito->historial_estados ?? [];
            $historial[] = [
                'fecha' => now()->toIso8601String(),
                'usuario' => 'Acta de Comité N.° ' . $acta->numero,
                'rol' => 'comite_credito',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $mapeo['estado'],
                'comentario' => 'Decisión registrada en el Acta de Comité N.° ' . $acta->numero . ': ' . $solicitud->estado_decision . '.',
            ];
            $credito->historial_estados = $historial;

            $credito->save();
        }
    }

    /**
     * SCRUM-190 (2026-08-12, punto 1, decisión de Luis): las solicitudes
     * `manual` (créditos que no existían en el sistema, agregados a mano
     * dentro del Acta) con decisión tomada se materializan acá como un
     * CreditoOrdinario real — antes quedaban invisibles para siempre en
     * Gestión de Créditos (sincronizarCreditosOrdinarios() las ignora a
     * propósito, ver docblock arriba, porque no tienen credito_ordinario_id).
     *
     * A propósito NO se crea un Cliente nuevo desde el texto libre de la
     * solicitud: camposFaltantesParaMaterializar() ya bloqueó el registro
     * del acta si la identificación no matchea un Cliente existente — evita
     * clientes fantasma con datos incompletos (sin correo, etc.).
     *
     * `CreditoOrdinario.cliente_id` (columna legacy, referencia `users` no
     * `clientes` — ver CreditoOrdinario::cliente()) se deja en el usuario de
     * portal del cliente SI existe uno con el mismo numero_documento; si no
     * existe (el cliente nunca inició sesión, típico de un crédito que nace
     * directo en el Acta), queda null — GestionCreditoController::
     * crearSolicitudDocumentos() ya bloquea explícitamente el caso
     * "requiere documentos" sin cuenta de portal en vez de fallar por FK.
     */
    private function materializarSolicitudesManuales(ActaComite $acta, int $userId): void
    {
        $mapaDecision = [
            'aprobado'  => ['estado' => 'aprobada_garantias', 'origen' => 'comite_aprobado'],
            'rechazado' => ['estado' => 'rechazado', 'origen' => 'comite_rechazado'],
            'pendiente' => ['estado' => 'pendiente_comite', 'origen' => 'comite_pendiente'],
        ];

        $solicitudesManuales = $acta->solicitudes()
            ->where('origen', 'manual')
            ->whereNull('credito_ordinario_id')
            ->get();

        if ($solicitudesManuales->isEmpty()) {
            return;
        }

        // Mismo PDF que sincronizarCreditosOrdinarios() — si el acta también
        // tenía solicitudes de sistema, ya se generó y no se repite el trabajo.
        $pdfPath = "actas-comite/{$acta->id}/acta-comite-{$acta->numero}-firmada.pdf";
        if (!Storage::disk('public')->exists($pdfPath)) {
            Storage::disk('public')->put($pdfPath, $this->generarPdf($acta)->output());
        }

        foreach ($solicitudesManuales as $solicitud) {
            $mapeo = $mapaDecision[$solicitud->estado_decision] ?? null;
            $cliente = Cliente::where('numero_documento', $solicitud->cliente_identificacion)->first();
            $tipoCredito = TipoCredito::where('nombre', $solicitud->tipo_solicitud)->first();
            $amortizacion = Amortizacion::where('nombre', $solicitud->amortizacion)->first();

            // Defensivo: camposFaltantesParaMaterializar() ya bloqueó
            // registrar() si alguno de estos no resuelve.
            if (!$mapeo || !$cliente || !$tipoCredito || !$amortizacion) {
                continue;
            }

            $usuarioPortal = User::where('numero_documento', $solicitud->cliente_identificacion)->first();

            $solicitudCredito = SolicitudCredito::create([
                'cliente_id' => $cliente->id,
                'usuario_registra_id' => $userId,
                'tipo_credito_id' => $tipoCredito->id,
                'monto_solicitado' => $solicitud->monto,
                'plazo_meses' => $solicitud->plazo_meses,
                'amortizacion_id' => $amortizacion->id,
                'destino_recurso' => "Aprobado por el Comité de Crédito (Acta N.° {$acta->numero}, solicitud adicionada manualmente).",
                'garantia' => $solicitud->garantias,
                'fuente_pago' => $solicitud->fuente_pago,
                'correo_notificacion' => $cliente->correo_electronico,
                'asunto_notificacion' => 'Registro de crédito aprobado por Comité',
                'mensaje_notificacion' => "Su crédito fue evaluado por el Comité de Crédito y registrado en el Acta N.° {$acta->numero}.",
            ]);

            $credito = CreditoOrdinario::iniciar(
                clienteId: $usuarioPortal?->id,
                monto: (float) $solicitud->monto,
                plazoMeses: (int) $solicitud->plazo_meses,
                usuario: 'Acta de Comité N.° ' . $acta->numero,
                rol: 'comite_credito',
                comentario: 'Crédito creado a partir de una solicitud manual del Acta de Comité N.° ' . $acta->numero . ': ' . $solicitud->estado_decision . '.',
                solicitudCreditoId: $solicitudCredito->id,
                estadoInicial: $mapeo['estado'],
                documentosIniciales: ['acta_comite_firmada' => $pdfPath],
            );

            $credito->resultado_origen = $mapeo['origen'];
            $credito->save();

            $solicitud->credito_ordinario_id = $credito->id;
            $solicitud->save();
        }
    }

    private function ordenDiaInicial(?ActaComite $ultimaAprobada): array
    {
        return array_map(function (int $i, string $texto) use ($ultimaAprobada) {
            $item = ['id' => $i + 1, 'texto' => $texto, 'orden' => $i + 1];

            // Ítem #3 "Lectura del acta anterior" se prellena con un link a
            // la última acta Aprobada, si existe (decisión de Luis, 2026-08-02).
            if ($i === 2 && $ultimaAprobada) {
                $item['texto'] .= " Referencia: Acta N.° {$ultimaAprobada->numero} del "
                    . optional($ultimaAprobada->fecha_reunion)->format('d/m/Y') . '.';
            }

            return $item;
        }, array_keys(self::ORDEN_DIA_DEFAULT), self::ORDEN_DIA_DEFAULT);
    }

    /**
     * SCRUM-189 (2026-08-12, punto 6/7): DomPDF trae `enable_remote => false`
     * por defecto (config/dompdf.php del paquete, nunca sobreescrito en el
     * proyecto) — las imágenes insertadas en el editor rich text quedan
     * embebidas como URL http(s) absoluta (ver ActaComite::resolveImagenesEnHtml
     * / CreditoOrdinario::resolveStorageUrl) y DomPDF no puede traerlas por
     * red, así que no se veían ni en Previsualizar ni en el PDF final. En vez
     * de habilitar fetch remoto (superficie extra, depende de red), se
     * resuelve el mismo archivo a su ruta LOCAL en el disco 'public' — mismo
     * archivo, sin salir a red — solo para el HTML que se le pasa a DomPDF.
     * No se toca el accessor del modelo: ese sigue devolviendo URL absoluta
     * para el uso normal en Angular/ngx-quill.
     */
    private function generarPdf(ActaComite $acta)
    {
        $acta->load('solicitudes');
        $totales = $this->calcularTotales($acta);

        $ordenDiaPdf = collect($acta->orden_dia ?? [])->map(function ($item) {
            $item['texto'] = $this->resolverImagenesParaPdf($item['texto'] ?? '');
            return $item;
        })->all();

        $desarrolloPdf = collect($acta->desarrollo ?? [])
            ->map(fn ($html) => $this->resolverImagenesParaPdf($html))
            ->all();

        return Pdf::loadView('actas-comite.pdf', [
            'acta' => $acta,
            'totales' => $totales,
            'lugarPdf' => $this->resolverImagenesParaPdf($acta->lugar),
            'ordenDiaPdf' => $ordenDiaPdf,
            'desarrolloPdf' => $desarrolloPdf,
            'observacionesPdf' => $this->resolverImagenesParaPdf($acta->observaciones_generales),
        ]);
    }

    private function resolverImagenesParaPdf(?string $html): ?string
    {
        if (!$html) {
            return $html;
        }

        return preg_replace_callback('/(<img[^>]+src=")([^"]+)(")/i', function ($m) {
            $marcador = '/storage/';
            $pos = strpos($m[2], $marcador);
            if ($pos === false) {
                // No es una imagen de nuestro disco 'public' (URL externa u
                // otra cosa) — se deja tal cual, DomPDF hará lo que pueda.
                return $m[0];
            }

            $rutaRelativa = rawurldecode(substr($m[2], $pos + strlen($marcador)));
            $rutaLocal = Storage::disk('public')->path($rutaRelativa);

            return $m[1] . $rutaLocal . $m[3];
        }, $html);
    }

    private function calcularTotales(ActaComite $acta): array
    {
        $solicitudes = $acta->solicitudes;

        return [
            'aprobado' => $solicitudes->where('estado_decision', 'aprobado')->sum('monto_decision'),
            'rechazado' => $solicitudes->where('estado_decision', 'rechazado')->sum('monto_decision'),
            'pendiente' => $solicitudes->where('estado_decision', 'pendiente')->sum('monto_decision'),
        ];
    }

    private function camposFaltantes(ActaComite $acta): array
    {
        $faltantes = [];

        if (!$acta->fecha_reunion) $faltantes[] = 'Fecha de la reunión';
        if (!$acta->lugar) $faltantes[] = 'Lugar';
        if (!$acta->hora_inicio) $faltantes[] = 'Hora de inicio';
        if (empty($acta->asistentes)) $faltantes[] = 'Asistentes (mínimo 1)';
        if (!$acta->orden_dia_aprobado) $faltantes[] = 'Aprobación del orden del día';
        if (!$acta->hora_finalizacion) $faltantes[] = 'Hora de finalización';
        if (empty($acta->firmantes)) $faltantes[] = 'Firmantes (mínimo 1)';

        foreach ($acta->solicitudes as $solicitud) {
            if (!$solicitud->estado_decision) {
                $faltantes[] = "Decisión pendiente para {$solicitud->cliente_nombre}";
                continue;
            }

            if ($solicitud->origen === 'manual') {
                $faltantes = array_merge($faltantes, $this->camposFaltantesParaMaterializar($solicitud));
            }
        }

        return $faltantes;
    }

    /**
     * SCRUM-190 (2026-08-12): las solicitudes manuales con decisión se
     * materializan como CreditoOrdinario real al registrar (ver
     * materializarSolicitudesManuales) — eso exige datos enlazables a
     * catálogos/registros reales, no el texto libre que el formulario
     * admitía hasta ahora. Bloquea el registro del acta con el mismo
     * mecanismo VAL-09 de siempre en vez de fallar a mitad de la
     * materialización.
     */
    private function camposFaltantesParaMaterializar(ActaComiteSolicitud $solicitud): array
    {
        $faltantes = [];
        $prefijo = "Solicitud manual de {$solicitud->cliente_nombre}";

        $clienteExiste = $solicitud->cliente_identificacion
            && Cliente::where('numero_documento', $solicitud->cliente_identificacion)->exists();
        if (!$clienteExiste) {
            $faltantes[] = "{$prefijo}: identificación sin vincular a un cliente existente (use el buscador de cliente)";
        }

        if (!$solicitud->tipo_solicitud || !TipoCredito::where('nombre', $solicitud->tipo_solicitud)->exists()) {
            $faltantes[] = "{$prefijo}: tipo de solicitud no reconocido (selecciónelo del listado)";
        }

        if (!$solicitud->amortizacion || !Amortizacion::where('nombre', $solicitud->amortizacion)->exists()) {
            $faltantes[] = "{$prefijo}: amortización no reconocida (selecciónela del listado)";
        }

        if (!$solicitud->plazo_meses) {
            $faltantes[] = "{$prefijo}: plazo (meses)";
        }

        if (!$solicitud->fuente_pago) {
            $faltantes[] = "{$prefijo}: fuente de pago";
        }

        return $faltantes;
    }

    private function autorizarRol(string $activeRole): void
    {
        if (!in_array($activeRole, self::ROLES_AUTORIZADOS)) {
            abort($this->forbidden('editar'));
        }
    }

    private function forbidden(string $accion)
    {
        return response()->json(['message' => "No tienes autorización para {$accion} el acta de comité."], 403);
    }

    private function rechazarSiAprobada(ActaComite $acta): void
    {
        if ($acta->estado === 'aprobada') {
            abort(response()->json(['message' => 'El acta ya fue registrada y no puede modificarse.'], 422));
        }
    }
}
