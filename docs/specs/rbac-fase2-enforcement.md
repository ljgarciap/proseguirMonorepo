# Spec: RBAC — Fase 2 (enforcement real, permisos por acción)

**Date**: 2026-09-04
**Requested by**: Luis
**Status**: Approved (Luis, 2026-09-04) — alcance ajustado por el Arquitecto, ver "Corrección de alcance"
**Project**: Proseguir Factoring
**Ticket**: SCRUM-326 (hija de la épica SCRUM-324)

## Problem

Fase 1 (SCRUM-325, ya en producción) entregó el catálogo de roles/permisos y la UI de gestión, pero
es inerte — `CheckUserRole`, los `in_array(...)` de 19 controladores y `data.roles` de 43 rutas
frontend siguen leyendo el hardcode de siempre. Fase 2 conecta ese catálogo a la autorización real.

## Corrección de alcance (Arquitecto, 2026-09-04)

Al planear el reemplazo se encontraron dos hechos que la spec original (y el ticket SCRUM-326) no
tenían en cuenta:

1. **El hardcode no es uniformemente "por pantalla"**. El catálogo de Fase 1 asume 1 permiso = 1
   pantalla, pero gran parte de `routes/api.php` tiene **roles distintos por acción dentro del
   mismo prefijo** — ej. `mandatos`: listar es `[cliente,gerente,operativo,superadmin]`, crear es
   `[cliente]`, aprobar es `[operativo,superadmin]`. Mismo patrón en `uploads`, `parameters`,
   `document-presets`, `document-requests`, `solicitudes-credito`. Confirmado con Luis (2026-09-04):
   se expande el catálogo a granularidad por acción para estos casos (revierte la decisión "solo
   por pantalla" de Fase 1, que ya no alcanza).

2. **6 controladores autorizan por estado de un flujo BPMN, no por rol estático** —
   `CreditoOrdinarioController::transition()` (mapa `$rolesAutorizados` por `estado`),
   `InformeTecnicoController::autorizarRolParaEstado()`, `AnalisisFinancieroController::autorizarRol()`,
   `ActaComiteController::autorizarRol()`, `ListasRestrictivasSarlaftController::autorizarAccion()`,
   `GestionCreditoController::ROLES_POR_CLAVE`. Esto no es RBAC — es una regla de negocio ("solo el
   Gerente puede aprobar la Etapa 3, y solo si el crédito está en ese estado exacto") que ni
   siquiera con permisos por acción se puede representar en una matriz de checkboxes sin explotar
   combinatoriamente (rol × acción × estado). Forzarlo ahí sería peor que dejarlo como está.

   **Decisión técnica (dentro del alcance ya aprobado, no escala a Luis)**: estos 6 controladores
   quedan **fuera de Fase 2**, permanentemente — siguen exactamente como están hoy. Fase 2 cubre
   únicamente autorización estática (rol → puede/no puede), no reglas de workflow.

### Alcance final de Fase 2

**Dentro de alcance:**
- Frontend: las 43 rutas de `app.routes.ts` (`data.roles` → permiso del catálogo). Esto SÍ es
  limpiamente 1:1 con el catálogo de Fase 1 (cada ruta Angular ya era "pantalla completa").
- Backend: los grupos de `routes/api.php` con `checkrole:` — tanto los de nivel de grupo (mismo rol
  para todo el prefijo: `internal-docs`, `document-envios`, `configuraciones`, `db-cleaner`,
  `activity-logs`, `clientes`, `visitas`, `destinatarios`, `notificaciones`, `asignaciones`,
  `sectores`, `logs`, `dashboard`, `history`, `settlement`, `conciliacion-susuerte`, `contable`,
  `planilla`, `datos-factor`, `document-areas`, `document-requirements`, `gestion-creditos` en su
  entrada de módulo) como los de nivel de acción (`mandatos`, `uploads`, `parameters`,
  `document-presets`, `document-requests`, `solicitudes-credito`) — estos últimos necesitan
  permisos nuevos con clave `modulo:accion` (ej. `mandatos:crear`).

**Fuera de alcance (permanente, no es deuda de Fase 2):**
- Los 6 controladores de workflow BPMN listados arriba — ninguna de sus reglas por estado se toca.
- `GestionCreditoController::ROLES_POR_CLAVE` específicamente, aunque el grupo `gestion-creditos`
  sí tiene su gate de entrada al módulo en Fase 2 (nivel ruta), la restricción fina por sub-acción
  dentro del módulo queda igual que hoy.
- Menú/sidebar dinámico (`app.component.ts`) — sigue con su array hardcodeado; un ítem de menú que
  apunte a una ruta sin permiso simplemente redirige al hacer click, mismo comportamiento que hoy.

## Solution summary

1. **Catálogo**: extender `RolesPermissionsSeeder` con permisos de acción (`clave` tipo
   `modulo:accion`, ej. `mandatos:ver`, `mandatos:crear`, `mandatos:aprobar`) para los 6 módulos con
   diferencias por acción, asignados a los roles que hoy tienen cada acción en el hardcode. El resto
   de los ~42 permisos de Fase 1 (uno por pantalla) se mantienen para el gate de nivel de
   pantalla/grupo.
2. **Backend**: middleware nuevo `checkpermission:<clave>` — resuelve los roles del usuario
   (`$user->roles`, sin cambios, sigue siendo `users.roles` json), busca esos roles en el catálogo,
   verifica si alguno tiene la `clave` pedida. Reemplaza cada `checkrole:role1,role2,...` por
   `checkpermission:<clave-correspondiente>`, ruta por ruta — mapeo 1:1 verificado contra el
   hardcode actual antes de cada swap (mismo criterio de fidelidad que el seeder de Fase 1).
3. **Frontend**: `/api/me` se extiende para devolver `permissions: string[]` (unión de permisos de
   todos los roles del usuario). `roleGuard` pasa a comparar `route.data['permission']` contra esa
   lista (en vez de `route.data['roles']` contra los roles crudos del usuario). `AuthService`
   cachea `permissions` junto al resto de la sesión.
4. **Rollout verificable, no big-bang**: el swap se hace módulo por módulo, no en un commit único —
   cada módulo migrado se verifica contra los 10 roles reales (mismo criterio que la migración de
   Fase 1: "¿alguien pierde o gana acceso que no debería?") antes de pasar al siguiente. Empieza por
   el gate de pantalla frontend (bajo riesgo, mecánico) y los grupos backend de nivel de grupo (bajo
   riesgo, 1 rol set por prefijo); los 6 módulos con permisos por acción van después, por ser más
   propensos a error de mapeo.

## Acceptance criteria

- [ ] Las 43 rutas de `app.routes.ts` usan `data.permission` (no `data.roles`) y `roleGuard` las
      resuelve contra `/api/me`'s `permissions[]`.
- [ ] Cada grupo backend de nivel de grupo usa `checkpermission:<clave>` en vez de `checkrole:...`.
- [ ] Los 6 módulos con acciones distintas (`mandatos`, `uploads`, `parameters`,
      `document-presets`, `document-requests`, `solicitudes-credito`) tienen un permiso de acción
      por cada combinación rol-acción que hoy existe en el hardcode, sin perder ni ganar ninguna.
- [ ] Los 6 controladores de workflow BPMN (listados arriba) no se modifican — ni una línea.
- [ ] Para cada uno de los 10 roles reales: acceso idéntico antes/después del swap (checklist
      manual + tests), módulo por módulo.
- [ ] `/roles` deja de mostrar el banner de "esto no aplica todavía" — ahora sí controla acceso
      real — y se agrega (en un ticket de UI aparte si hace falta) el enlace en el menú para
      exponerla a superadmin.
- [ ] `CheckUserRole` (el middleware viejo) se puede eliminar del todo una vez el último grupo migra
      — no queda código hardcodeado de autorización de pantalla/grupo en ningún lado (los 6
      controladores de workflow BPMN son la única excepción, fuera de alcance por diseño).

## Edge cases and error scenarios

- Un rol pierde sin querer un permiso durante el swap → usuario real ve 403 en una pantalla que
  antes veía. Mitigado por la verificación módulo por módulo antes de avanzar al siguiente.
- Un permiso de acción nuevo (`modulo:accion`) queda sin asignar a ningún rol por error de mapeo →
  esa acción queda inaccesible para todos, incluido superadmin si el mapeo lo excluye por error.
  Mitigado por incluir siempre `superadmin` explícitamente en cada permiso de acción nuevo (no
  asumir que "ya lo tiene" de otro lado).
- `/api/me` crece con `permissions[]` — verificar que no rompe nada que dependa de la forma actual
  de esa respuesta (frontend ya la consume en varios lugares).
- Durante el rollout, un módulo ya migrado y otro sin migrar conviven — ambos mecanismos
  (`checkrole` viejo y `checkpermission` nuevo) coexisten sin conflicto porque actúan sobre rutas
  distintas.

## Out of scope

- Los 6 controladores de workflow BPMN — permanente, no es deuda, ver "Corrección de alcance".
- Menú/sidebar dinámico — permanece hardcodeado.
- Permisos a nivel de usuario individual (override) — sigue descartado (decisión de Fase 1, no
  cambia acá).

## References

- `docs/specs/rbac-roles-permisos-parametrico.md` (Fase 1)
- SCRUM-324 (épica), SCRUM-325 (Fase 1, en revisión), SCRUM-326 (esta spec)
