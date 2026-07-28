# Diseño técnico: SCRUM-128 — Validación Listas Restrictivas y SARLAFT

**Date**: 2026-07-14
**Status**: Approved — Luis confirmó las 3 decisiones de alcance (roles reusados, reemplazo del paso combinado)
**Spec**: ticket SCRUM-128 + prototipos (5 imágenes, fiel al mockup — igual criterio que SCRUM-120 Fase 2)

## Decisiones ya confirmadas con Luis
1. Rol "Control Interno" del prototipo = rol existente **`oficial_cumplimiento`** (no se crea rol nuevo).
2. Rol "Análisis Financiero" del prototipo = rol existente **`coordinador_comercial`** (no se crea rol nuevo).
3. El paso combinado actual `analisis_sarlaft_financiero` de `CreditoOrdinario` (sube 4 documentos juntos, sin concepto formal) **se reemplaza** por dos estados secuenciales nuevos, para Ordinario y Constructor por igual.

## Estado actual (a modificar)
`CreditoOrdinarioController::transition()`:
- Estado `analisis_sarlaft_financiero`: paralelo, `oficial_cumplimiento` sube `sarlft_sintesis` + `sarlft_datacredito`, `coordinador_comercial` sube `analisis_financiero` + `presentacion_comite`. Con los 4 cargados, botón manual "aprobar" → `aprobacion_presentacion`.
- `aprobacion_presentacion` rechazado por Gerencia → vuelve a `analisis_sarlaft_financiero`.
- Frontend: `credito-ordinario.component.html` líneas 216-291 (bloque `*ngIf="estado === 'analisis_sarlaft_financiero'"`, con las dos "role-action-box" de Cumplimiento y Comercial).
- Test que cubre todo esto end-to-end: `CreditoOrdinarioTest::test_full_bpmn_transitions_and_devoluciones`.

## Estado nuevo (diagrama)
```
revision_documental
  → (aprobar, manual, ya existe) → sarlaft_control_interno   [NUEVO — bandeja dedicada]
        favorable   → pendiente_analisis_financiero          [renombre de analisis_sarlaft_financiero, sin los 2 docs SARLAFT]
        desfavorable → rechazado (+ notifica cliente y coordinador_comercial)
  pendiente_analisis_financiero
        (coordinador_comercial sube analisis_financiero + presentacion_comite, igual que hoy)
        → aprobacion_presentacion
  aprobacion_presentacion rechazado por Gerencia → pendiente_analisis_financiero (no vuelve a sarlaft_control_interno — el concepto SARLAFT ya no se re-revisa por un rechazo comercial)
```

Para Constructor: el hook automático que hoy deja `informe_tecnico_finalizado` como punto muerto (`InformeTecnicoController::registrar()`, rama coordinador) debe encadenar automáticamente a `sarlaft_control_interno` en la misma transición.

## ⚠️ Ajuste necesario a SCRUM-120 (Informe Técnico) — a implementar en este mismo trabajo
Si el crédito Constructor avanza automáticamente de `informe_tecnico_finalizado` a `sarlaft_control_interno`, el gate actual de `InformeTecnicoController::findCreditoConstructor()` (`whereIn('estado', self::ESTADOS_INFORME_TECNICO)`) ya no lo encontraría — el Informe Técnico finalizado quedaría inaccesible para consulta/descarga apenas arranca SARLAFT. Se debe ampliar el gate de **visualización únicamente** (no de edición, que ya está cerrada por estado) para que un informe con `InformeTecnico.estado === 'registrado'` siga siendo consultable/descargable sin importar en qué estado esté el `CreditoOrdinario` ahora. No relajar la escritura (`guardarBorrador`/`registrar` siguen exigiendo el estado exacto, sin cambios).

## Documento único (no se replica el Datacrédito)
El prototipo (capturas 03/04) solo pide **un** PDF obligatorio: "Síntesis Oficial de Cumplimiento". El campo `sarlft_datacredito` que existe hoy en `documentos` desaparece de este flujo — no está en el prototipo nuevo. El nuevo PDF se guarda en `documentos` (JSON ya existente en `CreditoOrdinario`) bajo la clave `sintesis_oficial_cumplimiento`, reusando el mismo mecanismo de subida por `campo_documento` que ya usa `CreditoOrdinarioController::transition()` (no hace falta un `DocumentRequest` nuevo).

## Modelo de datos
**Migración nueva** — columnas directas en `credito_ordinarios` (no tabla aparte, a diferencia de Informe Técnico: acá es solo concepto + observaciones + 1 PDF, no un formulario multi-sección):
- `sarlaft_concepto` (string nullable: `favorable` | `desfavorable`)
- `sarlaft_observaciones` (text nullable)
- `sarlaft_diligenciado_por_id` (FK nullable a `users`, `onDelete('set null')`)
- `sarlaft_diligenciado_at` (timestamp nullable)

## Backend

### Nuevo `ListasRestrictivasSarlaftController`
- `index()` — bandeja. Filtra `CreditoOrdinario` donde `sarlaft_concepto IS NOT NULL OR estado = 'sarlaft_control_interno'` (cubre Pendiente/En revisión — por `estado` — y Favorable/Desfavorable — por `sarlaft_concepto`, sin importar a qué estado haya avanzado después). Solo rol `oficial_cumplimiento`/`superadmin` ve todo; otros roles, lista vacía (mismo patrón que `InformeTecnicoController::index()`). Columnas: fecha solicitud (`created_at`), no. crédito, tipo crédito, tipo documento/identificación/tipo persona/nombre/tipo empresa (del cliente vía `solicitudCredito.cliente`), estado derivado (Pendiente/En revisión/Favorable/Desfavorable), acciones.
- `show($creditoId)` — detalle solo lectura: datos completos de cliente (natural o jurídica según `tipoPersona`, incluye representante legal si jurídica) + datos del crédito solicitado, replicando exactamente los campos de los prototipos 03/04. Gate de visualización: `oficial_cumplimiento`/`superadmin` siempre; otros roles no (no hay necesidad de que Coordinador Comercial vea este detalle en este ticket).
- `guardarBorrador(Request, $creditoId)` — rol `oficial_cumplimiento`/`superadmin`, solo si `estado === 'sarlaft_control_interno'`. Guarda `sarlaft_concepto`/`sarlaft_observaciones`/PDF sin transicionar.
- `finalizar(Request, $creditoId)` — mismo gate. Exige los 3 (concepto + observaciones + PDF) — 422 con el mensaje del prototipo si falta alguno (ver tabla "Validaciones del sistema" del ticket, reusar esos mensajes literalmente). Si favorable → transiciona a `pendiente_analisis_financiero`. Si desfavorable → transiciona a `rechazado` + dispara los 2 correos (cliente y coordinador_comercial). Registra en `historial_estados` (mismo patrón ya usado en todo el proyecto) usuario/rol/fecha/comentario, y setea `sarlaft_diligenciado_por_id`/`_at`.

### `CreditoOrdinarioController::transition()` — cirugía mínima
- Renombrar el `case` del switch de `analisis_sarlaft_financiero` a `pendiente_analisis_financiero`, quitando la condición de los 2 documentos SARLAFT (solo exige `analisis_financiero` + `presentacion_comite`).
- `$rolesAutorizados`: quitar la entrada `analisis_sarlaft_financiero`, agregar `pendiente_analisis_financiero => ['coordinador_comercial']`.
- Rama `revision_documental` (acción `aprobar`): el `$estadoNuevo` pasa a ser `sarlaft_control_interno` en vez de `analisis_sarlaft_financiero`.
- Rama `rechazar` de `aprobacion_presentacion`: el retorno pasa a ser `pendiente_analisis_financiero` en vez de `analisis_sarlaft_financiero`.

### Hook Constructor (`InformeTecnicoController::registrar()`, rama coordinador)
Después de fijar `estado = 'informe_tecnico_finalizado'` en el mismo `save()`, encadenar automáticamente otra entrada de `historial_estados` y `estado = 'sarlaft_control_interno'` — una sola llamada HTTP, dos saltos de estado documentados en el historial.

### Mail
Nuevo Mailable `SarlaftDesfavorableClienteMail` y `SarlaftDesfavorableCoordinadorMail` (o uno solo parametrizado por destinatario), siguiendo el patrón de `SolicitudCreditoMail`. Contenido mínimo: no. de crédito, motivo (observaciones), fecha. No hay plantilla de diseño aprobada en el ticket — usar un mail simple de texto, sin necesidad de diseño gráfico.

## Frontend

### Nuevo módulo "Listas Restrictivas y SARLAFT"
- Ruta `/listas-sarlaft` (bandeja) y `/listas-sarlaft/:creditoId` (detalle), protegidas con `roleGuard` para `oficial_cumplimiento`/`superadmin`.
- Bandeja: 4 tiles (Pendientes/En revisión/Favorables/Desfavorables — contados sobre el resultado de `index()`), filtros (texto libre no./cliente/documento, tipo de crédito, tipo de persona, estado), tabla con columnas del prototipo 02, botón Validar/Continuar/👁 según estado.
- Detalle: bloque "Información del cliente (solo lectura)" con las variantes natural/jurídica del prototipo 03/04, bloque "Crédito solicitado" solo lectura, panel derecho "Resultado de la validación" (radio Favorable/Desfavorable, textarea observaciones, input de archivo PDF único, botones Guardar Borrador / Finalizar Validación con confirmación SweetAlert2 — mismo patrón que Informe Técnico).
- Sidebar: agregar el ítem "Listas Restrictivas y SARLAFT" (y de paso "Análisis Financiero" si no está, aunque esa pantalla ya existe embebida en `credito-ordinario`).

### `credito-ordinario.component.html`/`.ts` — cambios mínimos
- Cambiar el `*ngIf` del bloque "Stage 3" de `estado === 'analisis_sarlaft_financiero'` a `estado === 'pendiente_analisis_financiero'`.
- Eliminar el bloque completo "Oficial de Cumplimiento Area" (líneas ~221-249) — ese trabajo ahora vive en el módulo nuevo.
- La condición del botón de transición automática (línea ~282) pasa a exigir solo `analisis_financiero && presentacion_comite` (sin los 2 campos SARLAFT).

## Tests a actualizar (no solo agregar)
- `CreditoOrdinarioTest::test_full_bpmn_transitions_and_devoluciones`: el tramo que sube los 4 documentos y verifica `analisis_sarlaft_financiero` → `aprobacion_presentacion` debe reescribirse — ya no sube `sarlft_sintesis`/`sarlft_datacredito` ahí (eso ahora es responsabilidad del nuevo módulo, probado aparte), sube directo `analisis_financiero`/`presentacion_comite` en estado `pendiente_analisis_financiero`. El tramo de rechazo de Gerencia debe esperar `pendiente_analisis_financiero` como estado de retorno, no `analisis_sarlaft_financiero`.
- Nuevo `ListasRestrictivasSarlaftTest`: flujo completo (bandeja filtra por rol, guardar borrador, finalizar favorable → `pendiente_analisis_financiero`, finalizar desfavorable → `rechazado` + 2 mails, validaciones 422 de los 3 campos obligatorios, 403 fuera de rol).
- Nuevo caso en `InformeTecnicoTest`: registrar como Coordinador Comercial encadena automáticamente a `sarlaft_control_interno`, y el informe sigue siendo consultable/descargable después (verifica el ajuste del gate de visualización).

## Riesgos
| Riesgo | Mitigación |
|---|---|
| Cirugía sobre BPMN de Ordinario ya probado | Cambio mínimo y localizado (renombrar 1 estado, quitar 1 condición, mover 1 destino de rechazo) — no se toca el resto de la máquina de estados. Test existente reescrito, no borrado. |
| Créditos que hoy ya estén en `analisis_sarlaft_financiero` en `test`/prod al momento del deploy | Mismo criterio que SCRUM-118: producción tiene 0 clientes activos en este flujo (verificar en `test` antes de mergear con una consulta rápida); si hubiera alguno, agregar un paso manual de migración de datos documentado, no automático. |
| Pérdida de la capacidad de subir "Reporte Datacrédito" (`sarlft_datacredito`) | Decisión explícita: el prototipo nuevo no lo contempla. Si Luis lo necesita, es un ticket aparte — no se improvisa un campo que no está en el mockup. |
