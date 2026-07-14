# Diseño técnico: SCRUM-120 — Informe Técnico Constructor

**Date**: 2026-07-14
**Spec**: `docs/specs/scrum-120-informe-tecnico-constructor.md`
**Status**: Approved — aprobado por Luis 2026-07-14, incluyendo el cambio de esquema (FK `solicitud_credito_id`)

## ⚠️ Hallazgo que cambia el alcance
`CreditoOrdinario` **no tiene ninguna FK hacia `SolicitudCredito`** (verificado en
`database/migrations/..._create_credito_ordinarios_table.php` — solo tiene `cliente_id`).
Tampoco `DocumentRequest` tiene FK a `SolicitudCredito` (solo `cliente_id`). Hoy el vínculo
entre "la solicitud que se registró" y "el expediente BPMN que se abrió" es implícito, por
`cliente_id`, y **solo se crea automáticamente para tipo ORDINARIO**
(`SolicitudCreditoController.php:187-203` — el `if` chequea `codigo === 'ORDINARIO'`, para
Constructor hoy no pasa nada).

Esto significa que, tal como está el sistema hoy:
- Un cliente con **dos solicitudes activas simultáneas** (ej. una Ordinaria y una
  Constructor, o dos Constructor) no se puede distinguir de forma confiable solo por
  `cliente_id` — el hook de "documentación validada" podría disparar sobre el
  `CreditoOrdinario` equivocado.
- Para Constructor no existe hoy ningún `CreditoOrdinario` — hay que agregar el auto-inicio
  también para `codigo === 'CONSTRUCTOR'`.

**Propuesta (cambia el modelo de datos más de lo que la spec original preveía):**
Agregar `solicitud_credito_id` (nullable, FK) a `credito_ordinarios` Y a `document_requests`.
Se llenan al crear ambos registros desde `SolicitudCreditoController` (que ya tiene el id
de la `SolicitudCredito` recién creada disponible). Es un cambio pequeño y no rompe nada
existente (nullable, los registros históricos de Ordinario quedan con el campo en null),
pero es requisito para que SCRUM-120 funcione de forma confiable — **no es opcional**, es
la única forma de que el hook de habilitación de la bandeja sepa a qué expediente aplicar.

**Necesito tu aprobación explícita para este cambio antes de pasarlo a PM**, porque toca
dos tablas que la spec original no mencionaba.

## Componentes afectados / creados

### Backend
- **Migración nueva**: agregar `solicitud_credito_id` nullable a `credito_ordinarios` y a
  `document_requests`.
- **Migración nueva**: tabla `informes_tecnicos` (relacional, no JSON — hay fórmulas que
  dependen de columnas individuales, ver spec). FK a `credito_ordinarios`. Columnas: una
  por campo de cada sección (ventas, costos, invertido, observaciones_ingeniero,
  credito_solicitado, saldos_por_recaudar, analisis_financiacion, coberturas,
  observaciones_coordinador), `estado` (`borrador`/`registrado`), `diligenciado_por_ingeniero_id`,
  `diligenciado_por_coordinador_id`, timestamps.
- **`SolicitudCreditoController.php`** (~línea 187-203): extender el `if` para también
  auto-iniciar `CreditoOrdinario` cuando `codigo === 'CONSTRUCTOR'`, pasando
  `solicitud_credito_id`. Mismo bloque, pasar `solicitud_credito_id` también para ORDINARIO
  de una vez (consistencia).
- **`ClientUploadController.php`** (~línea 347-349): después de marcar
  `DocumentRequest.estado = 'completado'`, si `documentRequest.solicitud_credito_id` no es
  null, buscar el `CreditoOrdinario` con ese `solicitud_credito_id` y, si su
  `tipoCredito.codigo === 'CONSTRUCTOR'` y estado actual es el de espera de documentación,
  transicionar a `informe_tecnico_ingeniero` (usar el mismo patrón de `historial_estados`
  ya existente).
- **`CreditoOrdinarioController.php`**: extender `$rolesAutorizados` (mapa de roles por
  estado) con `informe_tecnico_ingeniero => ['ingeniero']` y
  `informe_tecnico_coordinador => ['coordinador_comercial']`. Nuevas acciones en
  `transition()`: `registrar_informe_ingeniero` (valida campos obligatorios incl.
  Observaciones, guarda en `informes_tecnicos`, pasa estado a `informe_tecnico_coordinador`)
  y `registrar_informe_coordinador` (idem, pasa a `informe_tecnico_finalizado`). Acción
  `guardar_borrador` en ambos estados, no cambia `estado` del `CreditoOrdinario`.
- **Nuevo `InformeTecnicoController`** (o extender el existente): endpoints CRUD del
  informe (ver/editar borrador, registrar, listar bandeja), separado del controller BPMN
  genérico para no sobrecargarlo — el genérico solo dispara transición de estado.
- **Cálculo de fórmulas**: portar las fórmulas del Excel de referencia (Ventas Totales,
  Costos, Invertido, Cuotas iniciales pendientes, Saldo por recaudar contraentrega,
  Análisis de financiación, Coberturas) a un servicio `InformeTecnicoCalculoService` —
  calculado en backend al guardar (no confiar en el cálculo del frontend), casos de prueba
  contra el Excel adjunto en el ticket.
- **Descarga PDF**: nueva vista Blade + `InformeTecnicoController::descargar()` usando
  `dompdf` (ya en `composer.json`, primer uso real en el proyecto). Permitido en estado
  borrador (marca "BORRADOR" visible en el PDF) y en finalizado.
- **Rol nuevo**: agregar `'ingeniero'` como valor válido donde se valida el array de roles
  (seeder/validación, sin migración de esquema porque `users.roles` es JSON).

### Frontend (Angular)
- Nueva bandeja "Informe Técnico" (componente + ruta), listando `CreditoOrdinario` con
  `tipoCredito.codigo === 'CONSTRUCTOR'` en los 3 nuevos estados.
- Nuevo componente de formulario, siguiendo el patrón de
  `credito-ordinario.component.ts` (`activeRole` + `[disabled]` por rol) ya usado — mismo
  mecanismo de edición condicionada, sin necesidad de un patrón nuevo.
- Botón de descarga de PDF (borrador/final).

## Riesgos y mitigación
| Riesgo | Mitigación |
|---|---|
| Cambio de esquema no previsto en la spec original (FK nuevas) | Flagueado explícitamente aquí, requiere tu aprobación antes de PM. |
| Fórmulas mal portadas del Excel a backend | Casos de prueba unitarios contra el Excel de referencia adjunto en Jira, revisados antes de dar por cerrada la tarea de cálculo. |
| Reversión de documento aprobado después de habilitada la bandeja (pregunta abierta a Luis en la spec) | No se implementa manejo especial en esta fase — si ocurre, el informe queda "huérfano" en su estado actual; se resuelve manualmente. Si Luis lo considera crítico, se agrega como tarea aparte. |
| Concurrencia: Ingeniero y Coordinador Comercial no pueden estar editando a la vez porque el `estado` ya lo impide (mismo patrón que el resto de `CreditoOrdinario`) | Ninguna mitigación adicional necesaria — reusa el control de concurrencia existente basado en estado. |

## Tareas para PM (una vez aprobado el punto de alcance)
1. **Backend** — Migraciones (`solicitud_credito_id` en 2 tablas + tabla `informes_tecnicos`). Depende de: nada. ~2h.
2. **Backend** — Auto-inicio de `CreditoOrdinario` para tipo Constructor + relleno de `solicitud_credito_id`. Depende de: Tarea 1. ~2h.
3. **Backend** — Hook `DocumentRequest.completado` → transición `informe_tecnico_ingeniero`. Depende de: Tarea 1, 2. ~3h.
4. **Backend** — Rol `ingeniero` + permisos en `CheckUserRole`/mapa de roles autorizados. Depende de: nada, paralelo. ~1h.
5. **Backend** — `InformeTecnicoCalculoService` (fórmulas) + tests contra Excel de referencia. Depende de: Tarea 1. ~6h.
6. **Backend** — `InformeTecnicoController` (CRUD, transición ingeniero→coordinador→finalizado, borrador). Depende de: Tareas 1, 3, 5. ~5h.
7. **Backend** — Descarga PDF con dompdf. Depende de: Tarea 6. ~3h.
8. **Frontend** — Bandeja Informe Técnico (listado + filtros). Depende de: Tarea 6 (contrato API). ~4h.
9. **Frontend** — Formulario Ingeniero/Coordinador con secciones condicionadas por rol. Depende de: Tarea 6. ~6h.
10. **Frontend** — Botón descarga PDF. Depende de: Tarea 7, 9. ~1h.
11. **Tech Writer** — Documentar el nuevo flujo (paralelo a devs).
12. **QA** — Validar contra criterios de aceptación de la spec, incluidas fórmulas con el Excel de referencia.

Backend 1→2→3 y 1→5→6→7 son secuenciales; Tarea 4 corre en paralelo. Frontend depende del
contrato de la Tarea 6 pero puede empezar el layout de bandeja/formulario en paralelo con
mocks.

**Estimado total backend**: ~22h. **Frontend**: ~11h. Total bruto ~33h — feature grande,
candidata a dividirse en 2 sprints si Luis lo prefiere (fase 1: bandeja + rol + flujo de
estados sin fórmulas complejas; fase 2: cálculo de fórmulas + PDF).
