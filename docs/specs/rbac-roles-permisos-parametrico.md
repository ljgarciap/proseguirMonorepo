# Spec: Motor paramétrico de Roles y Permisos (Fase 1 — catálogo + UI de gestión)

**Date**: 2026-09-03
**Requested by**: Luis
**Status**: Approved (Luis, 2026-09-03) — diseño + Cybersecurity cerrados, lista para desglose de PM
**Project**: Proseguir Factoring

## Problem

Hoy no existe ningún mecanismo para que un superadmin cree un rol nuevo o le asigne permisos desde
la UI. La investigación de arquitectura (2026-09-03) confirmó que roles y permisos están 100%
hardcodeados en código, en ambos repos:

- `users.roles` es una columna `json` de **strings libres**, sin tabla `roles` ni `permissions` en
  BD, y sin paquete de autorización instalado (`composer.json` no tiene `spatie/laravel-permission`
  ni equivalente).
- La lista de los 10 roles válidos vive hardcodeada como whitelist de validación en
  `UserController::store()`/`update()` (`'roles.*' => 'string|in:superadmin,gerente,operativo,...'`).
- La autorización por endpoint backend se resuelve con el middleware `CheckUserRole`, invocado como
  `role:coordinador,operativo` literal en `routes/api.php`, más checks `in_array('superadmin', ...)`
  repetidos a mano en al menos 19 controladores.
- La autorización por ruta frontend se resuelve con `roleGuard` leyendo `route.data['roles']`, pero
  esos arrays están escritos a mano en **43 rutas distintas** de `app.routes.ts`.
- El modal "Nuevo Usuario" de `user-management.component.ts` tiene los 10 roles como checkboxes
  hardcodeados directamente en un string HTML (SweetAlert2) — una segunda copia manual de la misma
  lista, desincronizable de la primera.

Resultado: cualquier cambio de rol (crear uno nuevo, ajustar a qué pantallas da acceso) requiere que
un desarrollador toque como mínimo 4 archivos y haga deploy. No hay forma de que esto lo resuelva
alguien desde la UI, que es lo que el negocio necesita (superadmin operando el sistema sin depender
de un release).

## Solution summary

**Fase 1** (esta spec) entrega el **motor de datos** (tablas `roles`, `permissions`,
`role_permission`) y una **UI de administración** donde el superadmin puede: crear/editar/eliminar
roles, ver el catálogo completo de permisos (uno por cada pantalla/módulo hoy protegido) y
marcar/desmarcar cuáles aplica a cada rol. Los 10 roles actuales se migran como datos semilla, con
los permisos que hoy tienen replicados exactamente — sin cambiar el comportamiento actual de nadie.

Como parte de Fase 1, el selector de roles en Gestión de Usuarios (`UserController` +
`user-management.component.ts`) se conecta al catálogo dinámico en vez de a la lista hardcodeada,
para que un rol creado en la UI se pueda asignar a un usuario de inmediato sin deploy.

**Lo que Fase 1 explícitamente NO hace** — y es el punto más importante de esta spec — es conectar
esos permisos a la autorización real. `CheckUserRole`, los `in_array('superadmin', ...)` de los 19
controladores, `roleGuard` y las 43 rutas de `app.routes.ts` **siguen funcionando exactamente igual
que hoy**, leyendo el hardcode existente. Un permiso creado/asignado en la UI de Fase 1 no habilita
ni bloquea acceso a nada todavía — es catálogo, no enforcement. Esa conexión es **Fase 2** (ticket
aparte), que reemplaza cada uno de esos puntos hardcodeados para que lean del motor en vez del
código. Ver "Out of scope".

Referencia de estilo/patrón a seguir: `Configuracion` (tabla `configuraciones` + UI en
`/configuraciones`) es hoy el único ejemplo real de tabla paramétrica con CRUD funcionando en este
proyecto — incluye already el patrón "solo superadmin", auditoría de quién actualizó (`updated_by`)
y agrupación (`grupo`). El modelo de datos de esta spec debería inspirarse en ese mismo nivel de
simplicidad, no reinventar un framework de permisos.

## Users and roles

- **Superadmin**: único rol que puede entrar a la pantalla de gestión de roles/permisos, crear,
  editar y eliminar roles, y asignar/quitar permisos de un rol. Nadie más tiene acceso a esta
  pantalla.
- **Resto de roles** (gerente, operativo, cliente, contable, coordinador_comercial,
  oficial_cumplimiento, comite_credito, tesoreria, ingeniero): sin cambio de comportamiento en
  Fase 1 — siguen operando exactamente como hoy, porque el enforcement no cambia.
- Confirmado con Luis (2026-09-03): permisos por **módulo/pantalla completa** (no por acción
  dentro de un módulo — ver/editar/aprobar queda fuera de alcance) y **estrictamente por rol**, sin
  excepciones/overrides a nivel de usuario individual.

## Acceptance criteria

- [ ] Superadmin puede crear un rol nuevo (nombre + descripción) desde la UI sin tocar código ni
      redeploy.
- [ ] La UI muestra el catálogo completo de permisos (uno por cada pantalla/módulo hoy protegido
      por `role:` en backend o `data.roles` en frontend) y permite marcar/desmarcar cuáles aplican
      a un rol dado.
- [ ] Los 10 roles actuales existen en la tabla `roles` desde el primer deploy de esta feature, con
      los permisos que hoy tienen replicados exactamente (mismo criterio 1:1 que hoy tienen en
      `role:` middleware / `data.roles`).
- [ ] Superadmin puede editar los permisos de un rol existente (agregar/quitar) desde la UI.
- [ ] Superadmin puede eliminar un rol — **bloqueado si tiene usuarios asignados actualmente**, con
      mensaje explícito de cuántos usuarios lo tienen y que deben reasignarse primero.
- [ ] El rol `superadmin` no puede eliminarse, ni perder el permiso de administrar roles/permisos
      (evita que alguien se bloquee a sí mismo el acceso a esta misma pantalla).
- [ ] El selector de roles en "Nuevo Usuario"/"Editar Usuario" (Gestión de Usuarios) lee del
      catálogo dinámico — un rol creado hoy en la UI de roles aparece ahí sin deploy, reemplazando
      los checkboxes hardcodeados actuales.
- [ ] La UI de gestión de roles/permisos muestra un aviso visible (banner o texto permanente) de que
      los permisos asignados aquí **todavía no controlan el acceso real** a pantallas/endpoints —
      eso es Fase 2 — para que nadie asuma que ya está en efecto.
- [ ] Todo cambio de rol/permiso (crear, editar, eliminar, asignar/quitar permiso) queda auditado
      (quién, qué, cuándo), reutilizando el mecanismo de log de actividad ya existente en el
      proyecto (`ActivityLogController`, SCRUM-246).
- [ ] Solo `superadmin` puede acceder a la pantalla de gestión de roles/permisos (protegido con el
      enforcement actual, no con el motor nuevo — ver "Out of scope").

## Edge cases and error scenarios

- Intentar eliminar un rol con usuarios asignados → bloqueado, mensaje explícito.
- Intentar quitarle a `superadmin` su propio permiso de administrar roles/permisos → bloqueado.
- Crear un rol con nombre duplicado (comparación case-insensitive, sin distinguir por espacios/guiones)
  → rechazado con mensaje claro.
- Migración/seed de los 10 roles actuales con typo o permiso de más/menos respecto al hardcode
  vigente → rompe acceso real de usuarios en Fase 2 sin que nadie lo note en Fase 1 (porque Fase 1
  no hace enforcement). Este es el riesgo más serio de la spec — ver checklist de verificación abajo.
- Guardar cambios de permisos y falla la red/servidor → error explícito, no perder el estado del
  formulario ni dejar el rol a medio guardar (operación atómica).
- Dos superadmins editando el mismo rol al mismo tiempo → último en guardar gana; no hay lock
  optimista en Fase 1 (aceptable dado el volumen de uso esperado — un solo superadmin real hoy,
  según CLAUDE.md del workspace).
- Un rol se queda sin ningún permiso asignado (todos desmarcados) → válido, no es error; simplemente
  ese rol no da acceso a nada hasta que se le asigne algo.

## Out of scope

- **Enforcement real de los permisos** — reemplazar `CheckUserRole`, los `in_array('superadmin', ...)`
  de los 19 controladores backend, `roleGuard` y las 43 rutas de `app.routes.ts` para que lean del
  motor nuevo en vez de hardcode. Es **Fase 2**, ticket aparte, y es donde vive el riesgo real de
  romper accesos existentes en producción.
- Menú/sidebar dinámico según permisos reales (mostrar/ocultar ítems de navegación) — depende de que
  Fase 2 exista primero.
- Permisos granulares por acción dentro de un mismo módulo (ver vs. editar vs. aprobar vs. eliminar)
  — descartado explícitamente por decisión de Luis (2026-09-03), esta spec es solo por
  pantalla/módulo completo.
- Permisos o excepciones a nivel de usuario individual (override sobre su rol) — descartado
  explícitamente por decisión de Luis (2026-09-03), todo pasa estrictamente por rol.
- Reporte de auditoría "quién tiene acceso a qué en este momento" como vista separada.
- Cualquier cambio a `resolveActiveRole()` / al selector de "rol activo" que ya usa el usuario en
  sesión (multi-rol) — sigue funcionando igual, no se toca en Fase 1 ni Fase 2 salvo que el
  Arquitecto identifique una dependencia directa.

## Open questions

- [Arquitecto] Modelo de datos final: tablas `roles` / `permissions` / `role_permission`, y cómo se
  relaciona `users.roles` — ¿se migra ya en Fase 1 a una tabla pivote `user_role`, o se mantiene la
  columna `json` actual pero validada contra el catálogo dinámico en vez del `in:` hardcodeado?
- [Arquitecto] Dónde vive la UI: ¿pantalla nueva `/roles`, o una pestaña dentro de `/configuraciones`
  (ya es "solo superadmin" y ya tiene el patrón de tabla paramétrica)?
- [Arquitecto] Construir el catálogo completo de `permissions` (inventario 1:1 de las 43 rutas
  frontend + los grupos de rutas backend protegidas por `role:`) es trabajo de análisis no trivial —
  la investigación de esta spec fue exploratoria (grep puntual), no un inventario exhaustivo
  verificado uno a uno. Confirmar si este inventario se arma como parte del diseño de Fase 1 o si
  amerita una tarea de análisis separada antes de implementar.
- [Luis] ¿Aceptás que la UI de Fase 1 quede visible para el superadmin real con el aviso de "esto
  no aplica todavía" (criterio de aceptación de arriba), o preferís no exponerla hasta que Fase 2
  esté lista, para no dar la impresión de una herramienta a medias?
- [Luis] ¿Se crea un ticket Jira para esto (confirmé por búsqueda que no existe ninguno hoy que
  cubra esta feature)? Si sí, ¿como épica con Fase 1/Fase 2 como sub-tareas, o dos tickets
  independientes?
- [Cybersecurity] Fase 1 no cambia el enforcement real, por lo que el riesgo inmediato de "romper
  accesos" es bajo — pero el modelo de datos que se defina acá es la base de todo el sistema de
  autorización futuro (Fase 2 lo va a consumir tal cual). Confirmar si Cybersecurity revisa el
  modelo de datos ya en Fase 1, o si espera a Fase 2 (que es donde cambia la superficie de
  seguridad real).

## Diseño técnico (Arquitecto, 2026-09-03)

Inventario completo revisado antes de diseñar: `routes/api.php` (394 líneas, todos los grupos) y
las 43 rutas de `app.routes.ts` con sus `data.roles`. Con esa base:

### Modelo de datos (corregido durante implementación, 2026-09-04 — ver nota abajo)

```
roles
  id, nombre (display), slug (unique, snake_case — igual string que usa hoy users.roles para los
  10 roles semilla), descripcion, es_sistema (bool), created_at, updated_at

permissions
  id, clave (unique, ej. 'gestion-creditos:formalizacion-garantias'), nombre, descripcion,
  modulo (agrupador para la UI, ej. "Gestión de Créditos"), created_at, updated_at

role_permission
  role_id, permission_id   (PK compuesta)
```

**Nota de corrección (Backend Dev, 2026-09-04):** el diseño original de esta sección proponía migrar
`users.roles` (columna `json`) a una tabla pivote `user_role`, con un accessor Eloquent
(`getRolesAttribute()`) para que el resto del código siguiera funcionando igual. Al implementar se
encontró que esa premisa era falsa: **12 sitios en 9 archivos** (`GarantiasFormalizacionService`,
`InformeTecnicoNotificationService`, `ValidacionDocumentalNotificationService`,
`GestionCreditoController`, `DocumentEnvioController`, `InternalDocumentController`,
`VisitaController`, `DocumentRequestController`, `CreditoOrdinarioController`) hacen
`User::whereJsonContains('roles', ...)` — **consultas SQL directas sobre la columna física**, no
lecturas de `$user->roles` en un modelo ya cargado. Un accessor de Eloquent no intercepta eso: la
migración tal como estaba diseñada habría roto 12 flujos de notificación en producción sin que
ningún test unitario lo detectara a tiempo (son queries, no accesos a atributo).

**Corrección**: `users.roles` **no se toca, sigue siendo la fuente de verdad tal cual está hoy** —
no hay tabla `user_role`, no hay accessor nuevo, cero cambios en `User.php`. "Cuántos usuarios
tiene asignado este rol" (necesario para bloquear el `DELETE` de un rol con usuarios) se resuelve
con el mismo patrón que ya usa el resto del código:
`User::whereJsonContains('roles', $role->slug)->count()`. Esto reduce el blast radius de Fase 1
todavía más de lo que ya estimó Cybersecurity — ni siquiera se toca el modelo `User`.

`UserController::store()`/`update()` cambia la validación `roles.* => in:superadmin,...` (lista
hardcodeada) por `roles.* => exists:roles,slug` — el catálogo de `roles` provee el mismo conjunto
de strings válidos que antes vivían hardcodeados, y el usuario se sigue guardando exactamente igual
(`'roles' => $request->roles` sobre la columna `json`, sin cambios). Esto es lo que hace que un rol
creado en la UI nueva se pueda asignar de inmediato a un usuario. El modal de
`user-management.component.ts` pasa de checkboxes hardcodeados a iterar `GET /api/roles`.

### Ubicación de la UI

Pantalla nueva `/roles` (no una pestaña de `/configuraciones`). Razón: `/configuraciones` es
key-value plano; esto es una matriz rol×permiso con su propio CRUD de dos entidades — el mismo
criterio que ya separó `/users` de `/configuraciones` pese a que ambas son "solo superadmin".

### Endpoints backend (nuevos, todos bajo `checkrole:superadmin`)

```
GET    /api/roles                    -- catálogo de roles
POST   /api/roles                    -- crear rol
PUT    /api/roles/{id}                -- editar (nombre, descripción, permisos asignados)
DELETE /api/roles/{id}                -- eliminar (rechaza si tiene usuarios asignados o es_sistema+permiso de gestión de roles)
GET    /api/permissions               -- catálogo completo de permisos, agrupado por módulo
```

### Metodología para construir el catálogo de `permissions` (tarea de implementación, no de esta spec)

El inventario que hice para este diseño confirma que **no hay una correspondencia 1:1 limpia**
entre "rutas backend protegidas por `checkrole`" y "rutas frontend con `data.roles`" — hay grupos
backend sin ruta frontend equivalente (`sectores`, `destinatarios`, `notificaciones`,
`asignaciones`) y módulos frontend con varias sub-pantallas de permisos distintos dentro del mismo
módulo (`gestion-creditos` tiene 5 roles a nivel de módulo pero cada acción interna —
`formalizacion-garantias`, `registro-cyf`, `desembolso-ingreso`, etc. — es su propia ruta/pantalla
con su propio array de roles; esto sigue siendo granularidad "por pantalla", no "por acción dentro
de la misma pantalla", porque cada una es una vista/paso distinto del flujo, no una acción sobre la
misma vista). El desarrollador que implemente Fase 1 arma el catálogo final tomando **las 43 rutas
de `app.routes.ts` como base** (una `permission` por ruta) y agregando las entradas backend-only que
no tienen pantalla propia. Esta spec no fija el catálogo línea por línea — sería trabajo desechable
si el inventario cambia antes de implementar.

### Hallazgo de seguridad adyacente (fuera de alcance de este ticket, para Cybersecurity)

Los grupos backend `creditos`, `informes-tecnicos`, `analisis-financiero`, `actas-comite`,
`listas-sarlaft` y `firmas` **no tienen `checkrole` a nivel de ruta** — solo `auth:api`. Hoy su
única restricción por rol es el guard de Angular (client-side) y, en algunos casos, lógica
embebida en el controlador (ej. `GestionCreditoController` sí valida acciones puntuales
internamente, pero `CreditoOrdinarioController::index/show/store` no se verificó que lo haga). En
teoría, cualquier usuario autenticado con cualquier rol (incluido `cliente`) podría llamar esos
endpoints directo por API sin pasar por el frontend. Esto es independiente de este RBAC — existe
hoy, con o sin este ticket — pero es información relevante para el threat model. Recomiendo
reportarlo a Luis para decidir si se abre un ticket de seguridad aparte.

### Preguntas abiertas de la spec — resueltas por el Arquitecto

- Modelo de datos final → resuelto arriba.
- Dónde vive la UI → `/roles`, resuelto arriba.
- Quién arma el inventario exhaustivo → tarea de implementación con la metodología de arriba, no
  bloquea el diseño.

### Decisiones de Luis (2026-09-03)

- **Exposición de la UI**: `/roles` **no se expone** a superadmin real en Fase 1. La ruta/pantalla
  se implementa y se prueba, pero queda oculta (sin enlace de navegación, y con el propio guard de
  rol devuelto vacío o detrás de un flag) hasta que Fase 2 esté lista y el banner de aviso ya no
  aplique — evita exponer una herramienta que aparenta controlar acceso real sin hacerlo. Esto es
  un criterio de aceptación nuevo: **la pantalla `/roles` no debe ser alcanzable desde la
  navegación normal en el deploy de Fase 1**, aunque el código exista y esté testeado.
- **Hallazgo de seguridad adyacente**: se trata ya, como ítem separado — ver
  `docs/specs/checkrole-faltante-rutas-criticas.md`. No bloquea esta spec ni depende de ella.
- **Cybersecurity**: sigue revisando el modelo de datos de esta spec antes de pasar a PM (ver
  sección siguiente).

## Revisión de Cybersecurity (2026-09-03)

Threat model sobre el modelo de datos de Fase 1 (no sobre enforcement — no cambia en esta fase).

- **Slug de `superadmin` protegido**: el `slug` de los roles semilla es la identidad que el código
  legacy sigue comparando literal (`in_array('superadmin', ...)`, `resolveActiveRole()`) mientras
  Fase 2 no exista. Renombrar o eliminar el rol `superadmin`, o el slug de cualquiera de los otros
  9 roles semilla, rompe autorización real en producción sin que Fase 1 lo detecte (Fase 1 no
  hace enforcement, así que el error se manifiesta como "usuario legítimo perdió acceso", no como
  un error de esta feature). **Criterio de aceptación agregado**: el `slug` de los 10 roles
  `es_sistema` no es editable desde la UI (solo nombre/descripción/permisos lo son); crear un rol
  nuevo sí permite definir su propio slug libremente, porque no hay código legacy que lo lea.
- **Mass-assignment en `PUT /api/roles/{id}`**: el endpoint que asigna permisos a un rol debe
  validar que cada `permission_id` recibido exista en la tabla `permissions` (`exists:permissions,id`)
  — no aceptar arrays libres. Bajo riesgo real hoy (Fase 1 es inerte), pero es higiene necesaria
  antes de Fase 2, cuando esos IDs sí van a decidir acceso real.
- **Auto-bloqueo de superadmin**: ya cubierto en criterios de aceptación (no se puede eliminar el
  rol `superadmin` ni quitarle su propio permiso de gestión de roles) — se mantiene, es la
  protección correcta contra que alguien se deje a sí mismo sin acceso a la pantalla de gestión.
- **Auditoría**: ya cubierta en criterios de aceptación (reutilizar `ActivityLogController`) —
  necesaria en Fase 1 pese a ser inerte, porque el historial de "quién creó qué rol/permiso" es
  insumo para el threat model de Fase 2, no algo que se pueda reconstruir después.
- **Blast radius de Fase 1**: bajo — sin enforcement conectado, un error en el modelo de datos o en
  la UI no puede otorgar ni quitar acceso real a nadie. El riesgo real de este proyecto vive
  íntegramente en Fase 2 (ahí sí amerita una revisión de Cybersecurity nueva, específica sobre el
  reemplazo de cada punto hardcodeado, antes de mergear).

**Aprobado para pasar a PM** con los 2 criterios de aceptación agregados arriba (slug protegido,
`exists:permissions,id` en la validación).

## References

- Investigación de arquitectura previa (misma sesión, 2026-09-03): `UserController.php`,
  `app/Http/Middleware/CheckUserRole.php`, `app/Http/Controllers/Concerns/ResolvesActiveRole.php`,
  `frontend/src/app/guards/role.guard.ts`, `frontend/src/app/app.routes.ts`,
  `frontend/src/app/components/user-management/user-management.component.ts`.
- Patrón de referencia para tabla paramétrica + UI CRUD: `app/Models/Configuracion.php` +
  `database/migrations/2026_06_23_000001_create_configuraciones_table.php` + vista `/configuraciones`.
- Regla global de tablas paramétricas para valores de negocio: `../../../CLAUDE.md` (raíz
  workspace), sección "Nunca".
