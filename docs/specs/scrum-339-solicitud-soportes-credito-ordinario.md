# Spec: Ajustes Solicitud de Soportes - Crédito Ordinario

**Date**: 2026-09-07
**Requested by**: Luis
**Status**: Draft
**Project**: Proseguir Factoring
**Jira**: [SCRUM-339](https://dynamo-si-team.atlassian.net/browse/SCRUM-339)

## Problem

Hoy, en Etapa 1 (`revision_documental`) de Crédito Ordinario, el botón
**"Solicitar Completar Soportes"** ejecuta `executeTransition('completar', ...)`
con un comentario fijo de audiencia ("Se solicita al cliente completar la
documentación.") y **no toca ningún documento puntual** — es una acción a
nivel de toda la solicitud, sin decir qué documento(s) exactos debe volver a
cargar el cliente ni por qué.

Esto es una limitación conocida y ya documentada: el docblock de
`AjustesDocumentalesClienteMail` (SCRUM-258, 2026-08-26) dice explícitamente:

> "la spec (RF-08, §7) pide 'cada documento que requiere ajuste y la
> observación asociada', pero Etapa 1 hoy no tiene una pantalla de revisión
> POR documento [...] itemizar por documento requeriría una pantalla nueva
> de revisión individual, fuera de alcance de este ticket."

SCRUM-339 es ese ticket de seguimiento: construir la pantalla de revisión
por documento que SCRUM-258 dejó pendiente.

## Solution summary

Reemplazar la acción directa de "Solicitar Completar Soportes" por un modal
**"Solicitud de Documentos"** (mismo patrón de diálogo ya usado en la app —
no una ruta nueva, pese a que el mockup lo dibuja con breadcrumb de página
completa) que:

- Lista los documentos de Etapa 1 ya cargados por el cliente (los mismos
  `document_request_item` que hoy alimentan `etapa1Docs`), con checkbox
  "Solicitar" y botón "Previsualizar" (reutiliza el patrón Swal + `<iframe>`
  ya usado en `operator-validation.component.ts`, no requiere componente
  nuevo de visor).
- Permite agregar tipos documentales nuevos ad-hoc ("+ Agregar documento":
  nombre + descripción/instrucción cortos) que **no** se registran en el
  catálogo compartido `document_requirements` — quedan aislados a esta
  solicitud puntual (decisión de Luis, ver Open questions resueltas abajo).
- Exige un único textarea obligatorio "Observaciones para el cliente" — no
  una observación por documento (decisión de Luis).
- Al confirmar: marca los ítems existentes seleccionados de vuelta a
  `pendiente`, crea los ítems nuevos ad-hoc, guarda las observaciones en el
  `DocumentRequest`, transiciona el crédito a `completar_solicitud` (mismo
  estado que ya existe) y dispara el correo al cliente — ahora listando
  los documentos solicitados por nombre, no un texto genérico.
- El cliente sigue viendo esto en **Mis Cargas** (`/client-upload`) y en
  **Mis Créditos** vía el mismo `DocumentRequest`/`DocumentRequestItem` que
  ya renderizan ambas pantallas — no hace falta tocarlas más que para
  mostrar el nuevo campo `observaciones` a nivel de solicitud (hoy no lo
  muestran porque no existe).

## Users and roles

| Actor | Acceso |
|---|---|
| Director de Crédito (`coordinador_comercial`/`superadmin`, mismo rol que ya opera `revision_documental`) | Abre el modal, selecciona/agrega documentos, escribe observaciones, confirma envío |
| Cliente | Recibe el correo, ve los documentos pendientes en Mis Cargas / Mis Créditos, carga cada uno |
| Sistema | Valida, publica los `DocumentRequestItem`, cambia el estado, envía el correo, registra trazabilidad (ya existente vía `historial_estados`) |

Sin cambios de permisos: la acción sigue viviendo dentro de `revision_documental`,
ya gateada por los mismos roles autorizados que hoy ven el botón.

## Acceptance criteria

- [ ] En `revision_documental`, "Solicitar Completar Soportes" abre el modal
      "Solicitud de Documentos" en vez de ejecutar la transición directo.
- [ ] El modal lista todos los `etapa1Docs` actuales (nombre, descripción,
      estado) con checkbox de selección; los ya marcados/aprobados no vienen
      pre-seleccionados.
- [ ] "Previsualizar" abre el archivo cargado sin cerrar el modal (patrón
      Swal+iframe existente), y al cerrar la previsualización las
      selecciones del modal se conservan.
- [ ] "+ Agregar documento" agrega una fila nueva marcada "(Nuevo)",
      pre-seleccionada, con "Sin archivo previo"; pedir nombre (obligatorio)
      y descripción/instrucción (opcional). Estas filas se pueden quitar
      antes de enviar.
- [ ] El nuevo tipo documental ad-hoc **no** crea una fila en
      `document_requirements` — vive solo en el `document_request_item`
      correspondiente (`document_requirement_id` null +
      `nombre_personalizado`/`descripcion_personalizada`).
- [ ] "Observaciones para el cliente" es obligatorio; sin él, no se puede
      enviar (mismo criterio que ya usa el resto de las validaciones del
      formulario en esta app).
- [ ] Enviar sin ningún documento seleccionado (ni existente ni nuevo)
      muestra error y no envía.
- [ ] Confirmar envío: 1) los `document_request_item` existentes
      seleccionados vuelven a `estado = 'pendiente'` (conservando
      `client_upload_id` del archivo previo para trazabilidad — no se borra
      hasta que el cliente cargue el reemplazo), 2) se crean los ítems
      nuevos ad-hoc en `pendiente`, 3) `document_requests.observaciones`
      guarda el texto, 4) `credito.estado` pasa a `completar_solicitud`,
      5) se registra en `historial_estados` igual que hoy.
- [ ] El correo al cliente (`AjustesDocumentalesClienteMail`) lista el
      nombre de cada documento solicitado (existente o nuevo) y el bloque
      único de observaciones — ya no solo un párrafo genérico.
- [ ] Mis Cargas y Mis Créditos muestran los documentos solicitados
      (ya funciona hoy vía `DocumentRequestItem` — items sin
      `document_requirement_id` deben mostrar `nombre_personalizado` /
      `descripcion_personalizada` en vez de `requirement?.nombre`) y el
      bloque "Observaciones del Director de Crédito" leído de
      `document_requests.observaciones` (hoy no se muestra en ninguna de
      las 2 pantallas, campo nuevo).
- [ ] El gate existente `etapa1KeySatisfecha()`/`hasEtapa1` sigue
      funcionando igual: al resetear un ítem a `pendiente`, el crédito
      vuelve a bloquear "Aprobar Documentos" hasta que el cliente
      recargue — sin tocar ese helper.
- [ ] Cancelar el modal no persiste nada (ni ítems ni observaciones) y no
      envía correo.

## Edge cases and error scenarios

- **Documento ad-hoc sin nombre**: el botón de confirmar/agregar debe
  quedar deshabilitado o rechazar si el nombre está vacío.
- **Cliente sin correo activo**: mismo comportamiento ya existente en
  `notificarCompletarSoportes()` (log de warning, no bloquea la transición).
- **Falla al enviar el correo**: mismo criterio ya existente — no debe
  revertir la transición ya persistida (try/catch independiente, como el
  resto de `ValidacionDocumentalNotificationService`).
- **Doble envío / reintento**: igual que otras transiciones de este
  controlador, no hay columna de idempotencia dedicada — un reintento ya no
  encuentra el crédito en `revision_documental`.
- **Ítem ya en `aprobado` marcado para reenvío**: debe poder re-solicitarse
  igual que uno `pendiente`/`subido` — el Director puede pedir un
  documento ya aprobado si detecta que estaba mal, sin restricción de
  estado previo.
- **Guard de archivo duplicado** (`DuplicateDocumentGuard`, ya existe): debe
  seguir aplicando también quen el cliente re-sube un ítem reseteado a
  `pendiente` por esta acción.

## Out of scope

- El flujo análogo de Constructor (`validacion_documental_constructor` /
  `completar_solicitud_constructor`) — el ticket dice "Crédito Ordinario"
  explícitamente. Si Luis quiere extenderlo a Constructor, es un ticket
  aparte (misma mecánica, mismo componente reusable).
- Observación individual por documento en el correo/pantalla (mockup de
  correo con una fila de texto distinto por documento) — decisión de Luis:
  una sola observación para toda la solicitud.
- Plantillas descargables para los documentos ad-hoc (el catálogo sí las
  soporta vía `document_requirements.tiene_plantilla`, pero un documento
  ad-hoc no tiene fila en ese catálogo).
- Cualquier cambio al mecanismo de aprobación/rechazo de un documento ya
  cargado (`validateUpload`/`approveUpload`) — no se toca.

## Open questions

Ya resueltas por Luis (2026-09-07):
- ~~¿Observación por documento o una sola para toda la solicitud?~~ → una
  sola, obligatoria.
- ~~¿El documento ad-hoc entra al catálogo global o queda aislado?~~ →
  aislado a esta solicitud (sin fila en `document_requirements`).

Pendientes:
- [Arquitecto] ¿Modal (SweetAlert2/diálogo Angular) o pantalla enrutada
  nueva? El mockup dibuja breadcrumb de página completa; recomiendo modal
  por consistencia con el resto del flujo de Crédito Ordinario (todas las
  demás confirmaciones de este módulo son diálogos, no rutas), pero es una
  decisión de diseño técnico, no de negocio — dejo la recomendación, no la
  bloqueo.

## References

- Related existing code:
  - `frontend/src/app/components/credito-ordinario/credito-ordinario.component.{ts,html}` (`etapa1Docs`, botón "Solicitar Completar Soportes", `executeTransition`)
  - `backend/app/Http/Controllers/CreditoOrdinarioController.php` (`transition()`, `etapa1DocumentKeys()`, `etapa1KeySatisfecha()`)
  - `backend/app/Services/ValidacionDocumentalNotificationService.php` / `backend/app/Mail/AjustesDocumentalesClienteMail.php` (SCRUM-258, el gap que este ticket cierra)
  - `backend/app/Http/Controllers/GestionCreditoController.php::crearSolicitudDocumentos()` (mecanismo DocumentRequest/Item ya usado para `pre_comite`/`garantias`, mismo patrón a reusar acá)
  - `backend/app/Http/Controllers/ClientUploadController.php` (Mis Cargas — ya genérico sobre cualquier `DocumentRequest`)
  - `frontend/src/app/components/client-upload/client-upload.component.ts` (Mis Cargas, portal cliente)
  - `frontend/src/app/components/operator-validation/operator-validation.component.ts` (patrón de previsualización Swal+iframe a reutilizar)
  - `backend/database/migrations/2026_06_02_100000_create_document_requirements_tables.php` (schema base — requiere migración nueva para `document_requests.observaciones` y `document_request_items.document_requirement_id` nullable + `nombre_personalizado`/`descripcion_personalizada`)
- Prototype: 7 adjuntos en SCRUM-339 (acceso, grid de solicitud, previsualización, confirmación, correo, Mis Cargas, Mis Créditos)

## Quality checklist

- [x] Cada criterio de aceptación es verificable por QA
- [x] Roles explícitos (sin cambios de permisos)
- [x] Casos de error documentados (>3)
- [x] Fuera de alcance evita scope creep (Constructor, observación por doc, plantillas ad-hoc)
- [x] Sin preguntas abiertas bloqueantes (la única pendiente es de diseño técnico, no bloquea el arranque)
