# Spec: Actas del Comité de Crédito (SCRUM-169)

**Date**: 2026-08-02
**Requested by**: Luis (reportado por Dynamo Si)
**Status**: Approved
**Project**: Proseguir Factoring

## Problem
Hoy no existe ningún mecanismo en el sistema para elaborar el acta del Comité de Crédito.
El único rastro es un slot de subida manual (`CreditoOrdinario.documentos['acta_comite_firmada']`)
que exige adjuntar un PDF ya firmado, elaborado por fuera del sistema, para poder avanzar el
estado `comite_evaluacion`. No hay trazabilidad del contenido de la reunión (asistentes, orden
del día, decisiones por solicitud, firmantes), ni generación de documento desde el sistema.

## Solution summary
Nuevo módulo **standalone** "Actas Comité de Crédito": una bandeja de listado + un wizard de
diligenciamiento de 8 pasos (Bandeja → Orden del día → Desarrollo → Decisión → Resumen →
Observaciones → Firmantes → Registro, según el diagrama adjunto en el ticket), sobre un nuevo
modelo `ActaComite` relacionado con `CreditoOrdinario` a través de una tabla pivote que además
admite solicitudes agregadas manualmente (créditos que no existen en el sistema).

Decisiones ya tomadas con Luis (2026-08-02):
- **Alcance de créditos**: solo **Crédito Ordinario**. Constructor y Factoring quedan fuera de
  esta versión — no tienen hoy un módulo de Análisis Financiero equivalente para calcular
  elegibilidad.
- **Formato de salida**: solo **PDF**, vía `dompdf` (mismo patrón que Análisis Financiero e
  Informe Técnico — `Pdf::loadView(...)->download(...)`). No se agrega generación de `.docx`
  real; el texto "Word" del ticket se interpreta como PDF descargable.
- **Editor de texto**: se construye un **editor WYSIWYG real con inserción de imágenes inline**
  (cumple la spec literal del ticket). No existe hoy ningún editor de este tipo en el frontend —
  es una dependencia nueva a evaluar por el Arquitecto (ver Open questions).
- **Relación con el workflow existente**: el módulo queda **independiente** por ahora. Registrar
  un acta **no** reemplaza ni satisface automáticamente `acta_comite_firmada` en
  `CreditoOrdinario`; ese slot de subida manual sigue existiendo en paralelo sin cambios. Una
  integración futura (que el registro del acta avance el estado del crédito) queda fuera de
  alcance de este ticket.

## Users and roles
| Rol | Acceso |
|---|---|
| **Coordinador Comercial** | Consulta la bandeja, genera el acta pendiente, elabora (todas las pestañas), inserta imágenes, previsualiza, descarga y registra definitivamente. Es el único rol que edita. |
| **Miembros del Comité de Crédito** | No interactúan con el sistema directamente en este flujo — participan en la reunión física/virtual; sus decisiones las transcribe el Coordinador Comercial. No requieren pantalla propia. |
| superadmin | Acceso completo, igual que en el resto de los módulos del proyecto. |

Confirmar con [Luis]: ¿algún otro rol debe tener acceso de **solo lectura** a actas ya
`Aprobada` (ej. Gerencia, Auditoría)? El ticket no lo menciona explícitamente.

## Bandeja de Actas
Lista los registros de `ActaComite` existentes. Columnas: Fecha de elaboración, Número de acta,
Estado (`Pendiente` / `Borrador` / `Aprobada`), Solicitudes incluidas (conteo), Elaborada por,
Acciones según estado (Pendiente → Elaborar; Borrador → Continuar; Aprobada → Ver y descargar).
Filtros: número, estado, fecha de elaboración.

Botón **Generar acta pendiente**: solo visible/habilitado para Coordinador Comercial. Al
presionarlo, el backend calcula en ese instante los `CreditoOrdinario` elegibles (ver regla
abajo), crea el registro `ActaComite` en estado `Pendiente` con la relación a esos créditos
(snapshot), y lo agrega al listado. Si no hay créditos elegibles, no se crea el registro y se
devuelve VAL-02.

**Regla de concurrencia (decisión de Luis, 2026-08-02)**: solo puede existir una acta en estado
`pendiente` o `borrador` a la vez. Mientras exista una, el botón "Generar acta pendiente" se
deshabilita (backend rechaza con 422 si se invoca igual, no confiar solo en el frontend).

### Regla de elegibilidad (confirmada)
`CreditoOrdinario` es elegible cuando `estado === 'comite_evaluacion'`.

Verificado directamente en `CreditoOrdinarioController.php` (líneas 343-378): un crédito solo
llega a `comite_evaluacion` después de que `aprobacion_presentacion` lo aprueba (línea 361-364),
lo cual a su vez exige `analisisFinanciero.estado === 'confirmado'` en la transición anterior
(línea 350, dentro de `pendiente_analisis_financiero`). Es decir, **todo crédito en
`comite_evaluacion` ya tiene análisis financiero confirmado por construcción** — no hace falta
repetir el chequeo de `analisisFinanciero`, alcanza con `estado === 'comite_evaluacion'`. Es
también el mismo estado donde hoy se exige manualmente `acta_comite_firmada` (línea 369), así que
es el punto natural donde el crédito "espera" al comité — mapea al "estado Pendiente" del texto
del ticket aunque no sea literalmente ese el nombre del enum.

No se excluyen créditos que ya aparecieron en una acta `Aprobada` anterior (decisión de Luis,
2026-08-02): si el crédito sigue en `comite_evaluacion` (p. ej. no se completó el paso manual de
`acta_comite_firmada` tras esa acta), puede volver a aparecer como elegible en la siguiente
generación.

## Modelo de datos (nuevo)

**`actas_comite`** — cabecera del acta:
- `numero` (entero, consecutivo global sin reinicio anual — `0001`, `0002`... calculado a partir
  del `numero` máximo entre las actas `aprobada`; único índice único para evitar condiciones de
  carrera en generación concurrente, aunque solo puede haber una acta `pendiente`/`borrador` a la
  vez — ver regla de concurrencia abajo)
- `estado`: `pendiente` | `borrador` | `aprobada`
- `fecha_reunion`, `lugar` (rich text), `hora_inicio`, `hora_finalizacion`
- `asistentes` (JSON: lista de nombres — al crear el acta pendiente, se prellena como sugerencia
  editable con los `asistentes` de la última acta `Aprobada`, si existe alguna)
- `orden_dia` (JSON: ítems `{id, texto, orden}` — arranca con los 7 ítems predeterminados del
  ticket, editable/agregable/eliminable, requiere aprobación explícita antes de continuar)
- `desarrollo` (JSON keyed por `orden_dia.id` → rich text con imágenes)
- `observaciones_generales` (rich text)
- `firmantes` (JSON: `{nombre, rol}`, precargado desde `asistentes`)
- `elaborada_por_id`, `registrada_por_id`, `registrada_at`

**`acta_comite_solicitudes`** — pivote, una fila por solicitud presentada:
- `acta_comite_id`
- `credito_ordinario_id` (nullable — null si es una solicitud agregada manualmente)
- `origen`: `sistema` | `manual`
- Snapshot de datos: `cliente_nombre`, `cliente_identificacion`, `tipo_solicitud`, `monto`,
  `amortizacion`, `plazo`, `tasa_interes`, `porcentaje_financiacion`, `garantias`, `fuente_pago`
- Decisión: `estado_decision` (`aprobado`/`rechazado`/`pendiente`), `monto_decision`,
  `vigencia_aprobacion`, `observaciones`

**Estructura de datos (confirmada)**: `actas_comite` usa columnas JSON para `asistentes`,
`orden_dia`, `desarrollo` y `firmantes` (mismo estilo que `documentos`/`historial_estados` de
`CreditoOrdinario`) — no hay necesidad de filtrar/reportar por ítem individual de esas listas,
solo de renderizarlas completas en el PDF, así que una tabla relacional agregaría joins sin
beneficio real. `acta_comite_solicitudes` sí es tabla relacional propia (no JSON), porque cada
fila necesita edición individual (agregar/eliminar/actualizar decisión) y eventualmente filtros
(ej. reportes de aprobados/rechazados). Guarda **snapshot desnormalizado** de los datos del
crédito al momento de inclusión (no join en vivo contra `CreditoOrdinario`/`AnalisisFinanciero`):
así el acta no cambia si el crédito se modifica después de haber sido presentado en la reunión.

### Imágenes inline (confirmado)
Editor: **`ngx-quill`** (Quill 2.x) — compatible con Angular 17.3 ya usado en el proyecto, es la
opción más liviana con handler de imagen personalizable para subir a un endpoint propio en vez de
embeber base64 (evita inflar el HTML guardado en BD). Se integra en los ~8 campos rich text del
acta (lugar, asistentes si aplica, ítems de orden del día, desarrollo, observaciones generales).

Endpoint nuevo: `POST /api/actas-comite/{acta}/imagenes` — recibe `multipart/form-data`, valida
formato (jpg/png/webp) y tamaño máximo (a definir por Backend Dev, mismo criterio que otros
uploads del proyecto), guarda en disco `public` bajo `actas-comite/{acta}/`, devuelve URL a
insertar en el HTML (VAL-08 si no cumple validación).

### Lectura del acta anterior
El ítem predeterminado "Lectura del acta anterior" (orden del día #3) se prellena con un link de
solo lectura a la última acta `Aprobada` (si existe), para que el Coordinador la cite sin tener
que buscarla en la bandeja. Si no existe ninguna acta previa, el campo queda vacío/editable sin
sugerencia.

## Acceptance criteria
- [ ] La bandeja lista todas las actas existentes con Fecha, Número, Estado, Solicitudes
      incluidas, Elaborada por, y acción según estado.
- [ ] "Generar acta pendiente" crea un registro solo si existe al menos un `CreditoOrdinario`
      elegible en ese instante; si no hay ninguno, muestra VAL-02 y no crea registro.
- [ ] El registro creado incluye exactamente los créditos elegibles en el instante de
      generación (snapshot), visibles en la pestaña Desarrollo → Presentación de solicitudes.
- [ ] Coordinador Comercial puede completar Fecha, Lugar, Hora y al menos 1 Asistente antes de
      continuar (VAL-01 si falta alguno).
- [ ] El orden del día arranca con los 7 ítems predeterminados en el orden definido por el
      ticket, es editable, admite agregar/eliminar ítems, y requiere click en "Aprobar" antes de
      pasar a Desarrollo.
- [ ] Desarrollo replica los ítems aprobados del orden del día con espacio editable con imágenes
      debajo de cada uno.
- [ ] Se pueden agregar solicitudes manuales (cliente por buscador/campo libre, tipo, monto) y
      eliminarlas; las solicitudes del sistema no son eliminables desde esta pantalla.
- [ ] Decisión y detalle de solicitudes permite diligenciar todos los campos obligatorios
      listados en el ticket (Cliente, Solicitud, Monto, Amortización, Plazo, Tasa, % financiación,
      Garantías, Fuente de pago, Estado, Vigencia) y bloquea el avance si falta alguno (VAL-05/06/07).
- [ ] Resumen de aprobaciones consolida por estado (`APROBADO`/`RECHAZADO`/`PENDIENTE`) y totales;
      editar el monto de decisión ahí se refleja en la previsualización y en el PDF final.
- [ ] Observaciones generales y Hora de finalización son obligatorias antes de continuar a
      Firmantes (VAL-01 análogo).
- [ ] Firmantes precarga los asistentes, permite editar rol, eliminar y agregar firmantes.
- [ ] Previsualización muestra el acta completa (con imágenes) antes del registro definitivo.
- [ ] Descargar genera el PDF con toda la información e imágenes registradas (VAL-12 si falla).
- [ ] "Registrar acta" muestra advertencia de irreversibilidad (VAL-10); al confirmar, el acta
      pasa a `Aprobada`, queda bloqueada (solo lectura/consulta/descarga) y VAL-11 confirma éxito.
- [ ] Un acta `Aprobada` no admite edición desde ningún endpoint (verificar en backend, no solo
      ocultar botones en frontend).
- [ ] Solo Coordinador Comercial (y superadmin) puede generar, elaborar y registrar actas.

## Edge cases and error scenarios
- **Sin créditos elegibles** al generar acta pendiente → VAL-02, no crea registro.
- **Falla el guardado** del registro pendiente (ej. error de BD) → VAL-04.
- **Solicitud manual incompleta** al intentar avanzar → VAL-05.
- **Solicitud sin estado de decisión seleccionado** → VAL-06.
- **Formato inválido** en campos monetarios/porcentuales → VAL-07.
- **Imagen que excede tamaño o formato no permitido** → VAL-08, no se inserta.
- **Intento de registrar con campos obligatorios pendientes** (cualquier pestaña) → VAL-09,
  bloquea el registro y señala qué falta.
- **Falla la generación del PDF** (previsualización o descarga final) → VAL-12.
- **Créditos elegibles cambian entre "Generar acta pendiente" y la elaboración** (ej. un nuevo
  crédito llega a `comite_evaluacion` después de generado el acta) → el acta ya generada NO se
  actualiza automáticamente; solo se pueden sumar vía "agregar solicitud manual". A confirmar con
  [Luis] si este comportamiento es el esperado.
- **Doble click / doble submit en "Registrar acta"** → debe ser idempotente (no crear dos
  transiciones a `Aprobada` ni duplicar el PDF).
- **Acta `Aprobada`, intento de editar vía API directamente** (no solo desde UI) → debe
  rechazarse en backend (403/422), no solo ocultarse en frontend.

## Out of scope
- Generación de Word (`.docx`) real — solo PDF.
- Firma electrónica o captura de firma manuscrita — "Firmantes" es solo registro de
  nombre + rol en texto, no un mecanismo de firma digital.
- Constructor y Factoring como tipos de crédito elegibles.
- Integración con el workflow de `CreditoOrdinario` (el registro del acta no avanza
  automáticamente el estado del crédito ni reemplaza `acta_comite_firmada`).
- Notificaciones/recordatorios automáticos de reunión de comité.
- Edición de un acta ya `Aprobada`.

## Open questions
Ninguna pendiente — todas resueltas el 2026-08-02 (ver "Decisiones cerradas" abajo).

## Decisiones cerradas (2026-08-02)
| # | Pregunta | Decisión |
|---|---|---|
| 1 | ¿Actas Pendiente/Borrador simultáneas? | No, solo una a la vez — botón deshabilitado + rechazo backend si se fuerza. |
| 2 | ¿Excluir créditos ya en acta Aprobada? | No se excluyen — pueden reaparecer si siguen en `comite_evaluacion`. |
| 3 | ¿Para qué se usa "recuperar última acta aprobada"? | Las tres cosas: numeración consecutiva, prellenar asistentes sugeridos, y referencia/link para el ítem "Lectura del acta anterior". |
| 4 | ¿Formato del número de acta? | Consecutivo global (`0001`, `0002`...), sin reinicio anual. |
| 5 | ¿Rol adicional de solo lectura? | No por ahora — solo Coordinador Comercial y superadmin. |
| 6 | Estado de elegibilidad de `CreditoOrdinario` | Confirmado en código: `estado === 'comite_evaluacion'` (implica análisis financiero ya confirmado por construcción del workflow). |
| 7 | Librería de editor WYSIWYG | `ngx-quill` (Quill 2.x), compatible con Angular 17.3 ya en uso. |
| 8 | Estructura de datos | `actas_comite` con columnas JSON (asistentes/orden_dia/desarrollo/firmantes); `acta_comite_solicitudes` como tabla relacional con snapshot desnormalizado. |

## References
- Jira SCRUM-169 — texto completo de la spec funcional (secciones 1–6, incluye tabla de
  validaciones VAL-01 a VAL-12, no duplicada aquí — es la fuente de verdad para mensajes exactos).
- Diagrama de flujo adjunto en el ticket (`flujo acta.png`): 8 pasos — Bandeja, Orden del día,
  Desarrollo, Decisión, Resumen, Observaciones, Firmantes, Registro.
- Código existente relevante:
  - `backend/app/Http/Controllers/AnalisisFinancieroController.php` (patrón de generación PDF
    con `dompdf`, y gate `estado === 'confirmado'`)
  - `backend/app/Models/AnalisisFinanciero.php` + su migración (modelo de referencia para
    `estado` borrador/confirmado)
  - `backend/app/Http/Controllers/CreditoOrdinarioController.php` (~línea 350: patrón de
    elegibilidad combinando `estado` + `analisisFinanciero.estado`; ~líneas 369-375: gate manual
    de `acta_comite_firmada` en `comite_evaluacion`)
  - `frontend/src/app/components/analisis-financiero/analisis-financiero-bandeja.component.ts`
    y `analisis-financiero-detalle.component.ts` (precedente estructural de bandeja + wizard
    multi-pestaña a imitar para Actas)
