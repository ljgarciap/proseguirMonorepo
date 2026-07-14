# Spec: Registro de Informe Técnico — Crédito Constructor (SCRUM-120)

**Date**: 2026-07-14
**Requested by**: Luis (reportado por Dynamo Si)
**Status**: Approved
**Project**: Proseguir Factoring

## Problem
Para solicitudes de Crédito Ordinario tipo **Constructor**, hoy no existe un mecanismo
para elaborar el Informe Técnico del proyecto de forma controlada y secuencial entre dos
roles (Ingeniero → Coordinador Comercial). El informe se maneja hoy fuera del sistema
(Excel externo, "INFORME TÉCNICO - ENTRE VERDE M+D.xlsx"), sin trazabilidad, sin control
de acceso por sección, y sin bandeja de trabajo.

## Solution summary
Extender el motor de estados existente de `CreditoOrdinario` (mismo patrón que ya usa
`estado` + `historial_estados` + mapa de roles autorizados en `CreditoOrdinarioController`)
con dos nuevos estados secuenciales: uno donde el rol **Ingeniero** diligencia las
secciones técnicas (Ventas Totales Proyecto, Costos, Invertido, Observaciones del
Ingeniero) y otro donde el rol **Coordinador Comercial** (rol ya existente) ve esa
información en solo lectura y completa el resto (Crédito Solicitado, Saldos por recaudar,
Análisis de financiación, Coberturas, Observaciones/concepto). El nuevo rol **Ingeniero**
se agrega al array de roles de `users`. El informe se puede guardar como borrador,
registrar, y descargar como **PDF** (vía `dompdf`, ya instalado sin uso).

Decisiones ya tomadas con Luis:
- El flujo vive **dentro** de `CreditoOrdinario` (no tabla/módulo aislado) — reusa
  `estado`/`historial_estados`/`transition()`.
- Se crea el rol **Ingeniero** (nuevo valor en el array `users.roles`), asignado por Luis
  a los usuarios que correspondan.
- Descarga en **PDF** con `dompdf` + vista Blade nueva.

## Trigger de habilitación ("documentación validada")
Luis no tenía certeza de que ya existiera el estado — se confirmó en código que **sí existe
el evento**, solo falta conectarlo al flujo de `CreditoOrdinario`:
- `SolicitudCredito` tiene `document_preset_id` → define los `DocumentRequirement`
  requeridos vía `DocumentPreset`.
- Cada documento subido crea un `DocumentRequestItem` con `estado` (pendiente/aprobado/...).
- En `ClientUploadController.php:347-349`: cuando **todos** los `DocumentRequestItem` de
  un `DocumentRequest` quedan `estado = 'aprobado'`, el `DocumentRequest` pasa a
  `estado = 'completado'`.
- **Propuesta**: enganchar ese evento (`DocumentRequest` → `completado`) para, si la
  `SolicitudCredito`/`CreditoOrdinario` asociada es tipo `CONSTRUCTOR`, disparar la
  transición del `CreditoOrdinario` al nuevo estado `informe_tecnico_ingeniero`
  (bandeja habilitada para rol Ingeniero). — **[Architect] validar el punto exacto de
  enganche**: `ClientUploadController::347` actualiza `DocumentRequest`, pero no tiene
  visibilidad directa de `CreditoOrdinario`; hay que resolver la relación
  `SolicitudCredito` ↔ `CreditoOrdinario` para ubicar el hook correcto sin acoplar
  controladores que no se conocen entre sí hoy.

## Users and roles
| Rol | Acceso |
|---|---|
| **Ingeniero** (nuevo) | Ve la bandeja solo cuando el `CreditoOrdinario` está en estado `informe_tecnico_ingeniero`. Edita: Ventas Totales Proyecto, Costos, Invertido, Observaciones del Ingeniero. Solo lectura del resto. |
| **Coordinador Comercial** (ya existe) | Ve la bandeja solo en estado `informe_tecnico_coordinador`. Ve en solo lectura las secciones del Ingeniero. Edita: Crédito Solicitado, Saldos por recaudar, Análisis de financiación, Coberturas, Observaciones/concepto Coordinador Comercial. |
| superadmin | Acceso completo, igual que en el resto de `CreditoOrdinario` (patrón `activeRole === 'superadmin'` ya usado en frontend). |

## Bandeja de Informe Técnico
Lista solicitudes con `tipo_credito.codigo = 'CONSTRUCTOR'` y `CreditoOrdinario.estado`
en (`informe_tecnico_ingeniero`, `informe_tecnico_coordinador`, `informe_tecnico_finalizado`).
Columnas: No. de crédito, Proyecto, Ubicación/ciudad, Solicitante, Tipo de crédito, Estado
del informe técnico, Rol actual, Acciones (Iniciar/Continuar/Abrir/Ver/Descargar según
estado y permisos).

## Modelo de datos (nuevo)
Nueva tabla para los campos del informe (no existe ningún campo de estos hoy):
`informes_tecnicos` con FK a `credito_ordinarios`, columnas por sección (ventas, costos,
invertido, observaciones_ingeniero, crédito solicitado, saldos por recaudar, análisis de
financiación, coberturas, observaciones_coordinador), estado (`borrador`/`registrado`),
timestamps de quién/cuándo diligenció cada bloque.
— **[Architect]** definir si va como tabla relacional con columnas explícitas (recomendado,
dado que hay fórmulas que dependen de campos individuales) o como JSON similar a
`documentos`/`historial_estados` de `CreditoOrdinario`.

## Fórmulas y validaciones
Ver detalle completo en la descripción original del ticket SCRUM-120 (Ventas Totales,
Costos, Invertido, Cuotas iniciales pendientes, Saldo por recaudar contraentrega, Análisis
de financiación, Coberturas) — mapeadas contra
`INFORME TÉCNICO - ENTRE VERDE M+D.xlsx` (adjunto en Jira). El Architect debe portar cada
fórmula a validaciones de backend (no confiar solo en cálculo de frontend) antes de permitir
"registrar" el informe.

## Acceptance criteria
- [ ] Rol Ingeniero creado y asignable a usuarios vía el mecanismo actual de roles.
- [ ] Una `SolicitudCredito` tipo Constructor con documentación 100% aprobada dispara
      automáticamente la aparición de su `CreditoOrdinario` en la bandeja de Informe
      Técnico, estado inicial `informe_tecnico_ingeniero`.
- [ ] El Ingeniero solo puede editar sus 4 secciones; el resto queda no visible/no editable.
- [ ] Al registrar su parte, el Ingeniero no puede seguir editando (solo lectura) y el
      `CreditoOrdinario` transiciona a `informe_tecnico_coordinador`.
- [ ] El Coordinador Comercial ve en solo lectura lo que registró el Ingeniero y edita
      únicamente sus secciones.
- [ ] Guardar como borrador persiste sin cambiar de estado ni bloquear edición.
- [ ] Registrar (final) bloquea edición de esa sección y anota en `historial_estados` quién
      y cuándo.
- [ ] Todas las fórmulas del prototipo (ventas, costos, invertido, coberturas, etc.) se
      calculan igual que en el Excel de referencia — casos de prueba con los valores del
      Excel adjunto.
- [ ] Descarga de informe (borrador y final) genera un PDF con el consolidado de ambas
      secciones.
- [ ] Un usuario sin el rol correspondiente al estado actual no puede editar ni ver los
      campos restringidos (verificar con `CheckUserRole` + `X-Active-Role`).

## Edge cases and error scenarios
- Documentación se revierte (un documento pasa de `aprobado` a otro estado) después de
  haber entrado a la bandeja de Informe Técnico — ¿debe sacarse de la bandeja o continuar?
  **[Luis]** — no cubierto aún, definir con negocio.
- Ingeniero intenta registrar sin diligenciar un campo obligatorio (Observaciones es
  obligatorio antes de registrar según el ticket) — debe bloquear con mensaje claro.
- Coordinador Comercial intenta actuar antes de que el Ingeniero registre su parte — debe
  rechazarse (estado no lo permite).
- Descarga de informe en estado `borrador` antes de que el Coordinador complete su parte —
  ¿se permite descargar parcial? El ticket dice "según estado", asumir sí pero marcado como
  "Borrador" en el PDF — **[Architect]** confirmar en diseño.
- Falla la generación del PDF (dompdf) — debe mostrar error claro, no dejar la solicitud en
  estado inconsistente.

## Out of scope
- Migración del histórico de informes técnicos ya elaborados fuera del sistema (Excel).
- Notificaciones automáticas (email/Telegram) al Ingeniero o Coordinador cuando la bandeja
  se habilita — no mencionado en el ticket, no se incluye salvo pedido explícito.
- Edición del `DocumentPreset`/requisitos documentales para tipo Constructor — se asume que
  ya existe o se gestiona por fuera de este ticket.

## Open questions
- [Luis] ¿Qué pasa si un documento aprobado se revierte después de habilitada la bandeja?
- [Architect] Punto exacto de enganche entre `DocumentRequest.completado` y la transición
  de `CreditoOrdinario` (relación `SolicitudCredito` ↔ `CreditoOrdinario` a confirmar).
- [Architect] Tabla relacional vs JSON para `informes_tecnicos`.
- [Architect] ¿Se permite descarga en estado borrador (antes de que el Coordinador
  termine)?

## References
- `backend/app/Models/CreditoOrdinario.php`, `backend/app/Http/Controllers/CreditoOrdinarioController.php` (mapa `$rolesAutorizados`, método `transition()`)
- `backend/app/Models/{SolicitudCredito,DocumentPreset,DocumentRequirement,DocumentRequest,DocumentRequestItem}.php`
- `backend/app/Http/Controllers/{SolicitudCreditoController,DocumentRequestController,ClientUploadController}.php` (línea 347-349: trigger `DocumentRequest` → `completado`)
- `backend/app/Http/Middleware/CheckUserRole.php`, `database/seeders/UserSeeder.php` (roles existentes)
- `frontend/src/app/components/credito-ordinario/credito-ordinario.component.ts` (patrón `activeRole` + edición condicionada por rol)
- `backend/app/Exports/{MandatoExport,HistoryExport,ConciliationExport}.php` (patrón de export existente, no usado para PDF pero referencia de dónde vive el código de descarga)
- Jira: SCRUM-120 (incluye prototipos, hoja de fórmulas y Excel de referencia)
