# Spec: IDOR en Crédito Ordinario — falta autorización por propiedad y por rol

**Date**: 2026-09-03
**Requested by**: Luis (hallazgo adyacente detectado durante diseño del RBAC paramétrico, ver
`docs/specs/rbac-roles-permisos-parametrico.md`)
**Status**: Approved — Luis pidió tratarlo de inmediato (2026-09-03), pasa directo a implementación
por ser fix de seguridad, no feature nueva
**Project**: Proseguir Factoring
**Severidad**: Alta — exposición y manipulación de datos financieros entre clientes distintos

## Problem

Investigando `routes/api.php` completo para diseñar el RBAC paramétrico, se revisó si los grupos de
rutas sin `checkrole` a nivel de ruta (`creditos`, `informes-tecnicos`, `analisis-financiero`,
`actas-comite`, `listas-sarlaft`, `firmas`) compensan eso con autorización embebida en el
controlador. Resultado:

- **`InformeTecnicoController`, `AnalisisFinancieroController`, `ActaComiteController`,
  `ListasRestrictivasSarlaftController`**: sí tienen autorización por rol embebida y consistente
  (`autorizarVisualizacion`/`autorizarRol` en cada acción incluyendo `index`). Sin gap.
- **`FirmaElectronicaController`**: sin chequeo de rol, pero `TIPOS_FIRMABLES` está vacío a
  propósito (SCRUM-245, ningún módulo conectado todavía) — no es explotable hoy, cualquier `{tipo}`
  devuelve 404. Queda como nota para cuando se conecte el primer módulo.
- **`CreditoOrdinarioController`**: **gap real y explotable**.
  - `show($id)`: **sin ningún chequeo de rol ni de propiedad**. Cualquier usuario autenticado,
    incluido un `cliente`, puede pedir `GET /api/creditos/{id}` de un crédito que no es suyo y ver
    monto, documentos y datos del cliente dueño.
  - `transition($id)`: valida rol vs. estado (`$rolesAutorizados`), pero **nunca valida que el
    `cliente` autenticado sea el dueño del crédito** — un `cliente` puede llamar
    `POST /api/creditos/{otroId}/transition` y subir documentos o disparar transiciones sobre el
    crédito de otro cliente, porque el rol `cliente` sí está en `$rolesAutorizados` para varios
    estados y el código nunca compara `credito->cliente_id` con el usuario autenticado.
  - `index($request)`: solo filtra por `cliente_id` cuando el rol activo es `cliente` — cualquier
    otro rol (incluidos `contable` e `ingeniero`, que el frontend **no** incluye en `data.roles` de
    la pantalla `/creditos`) puede listar **todos** los créditos de **todos** los clientes vía API
    directa.
  - `store()`: sin restricción de rol — cualquier usuario autenticado puede crear un crédito.

Es un IDOR (Insecure Direct Object Reference) de libro sobre datos financieros de clientes, y una
falta de autorización por rol en el listado. Explotable hoy en producción por cualquier usuario con
sesión válida (incluido un `cliente`), sin necesitar el frontend.

## Solution summary

Endurecer `CreditoOrdinarioController` con dos capas, replicando el patrón que ya usan los otros 4
controladores hermanos (`autorizarVisualizacion`/`autorizarRol` por acción):

1. **Autorización por rol** en `index`, `show`, `store`, `transition` — mismo set de roles que ya
   declara `data.roles` de la ruta `creditos` en `app.routes.ts` (fuente de verdad hoy, ya que es
   lo único que expresa la intención real): `cliente, coordinador_comercial, oficial_cumplimiento,
   comite_credito, operativo, tesoreria, gerente, superadmin`.
2. **Autorización por propiedad** cuando el rol activo es `cliente`: en `show` y `transition`,
   comparar `credito->cliente_id === $user->id`; si no coincide, `403`. `index` ya hace el filtro
   correcto (no toca). `store` ya fuerza `cliente_id = $user->id` cuando el rol es `cliente` (no
   toca).

No se introduce el motor de permisos paramétrico para este fix — es intencionalmente un parche
puntual con las mismas herramientas que ya usa el resto del controlador (`resolveActiveRole`, un
método privado `autorizarAcceso()` análogo a los de sus hermanos), para no acoplar un fix de
seguridad urgente al desarrollo de la Fase 1 del RBAC.

## Acceptance criteria

- [ ] `GET /api/creditos/{id}` con rol `cliente` y un crédito que no es suyo → `403`.
- [ ] `GET /api/creditos/{id}` con rol `cliente` sobre su propio crédito → `200`, sin cambio de
      comportamiento.
- [ ] `POST /api/creditos/{id}/transition` con rol `cliente` sobre un crédito ajeno → `403`, sin
      ejecutar ninguna escritura (ni archivo, ni cambio de estado).
- [ ] `POST /api/creditos/{id}/transition` con rol `cliente` sobre su propio crédito → sin cambio de
      comportamiento respecto a hoy.
- [ ] `GET /api/creditos` con un rol fuera de `[cliente, coordinador_comercial,
      oficial_cumplimiento, comite_credito, operativo, tesoreria, gerente, superadmin]` (ej.
      `contable`, `ingeniero`) → `403`.
- [ ] `GET /api/creditos`, `POST /api/creditos`, `GET /api/creditos/{id}`,
      `POST /api/creditos/{id}/transition` con cualquiera de los roles permitidos y comportamiento
      hoy correcto (staff viendo todos los créditos, cliente viendo/operando solo el suyo) →
      **sin regresión** — es el criterio más importante, este es un módulo con 51 assertions de
      test de integración ya existentes (`tests/Feature/CreditoOrdinarioTest.php`) que deben seguir
      pasando.

## Edge cases and error scenarios

- Un `superadmin` opera cualquier crédito sin restricción de propiedad (ya es el comportamiento
  esperado en todo el resto del sistema).
- Un rol staff (`operativo`, `gerente`, etc.) sigue viendo/operando todos los créditos, no solo
  "los suyos" — no existe noción de propiedad para roles staff, solo para `cliente`.
- `store()` con rol no permitido → `403` antes de crear nada (hoy no rechaza a nadie).

## Out of scope

- El motor paramétrico de roles/permisos — este fix es previo e independiente (ver
  `docs/specs/rbac-roles-permisos-parametrico.md`).
- `FirmaElectronicaController` — no explotable hoy (allowlist vacío), se revisa cuando se conecte
  el primer módulo firmable.
- Cualquier otro controlador — los 4 restantes del grupo investigado ya estaban correctamente
  protegidos.

## References

- `app/Http/Controllers/CreditoOrdinarioController.php`
- Patrón de referencia (mismo proyecto): `AnalisisFinancieroController::autorizarRol()`,
  `ListasRestrictivasSarlaftController::autorizarAccion()`
- `tests/Feature/CreditoOrdinarioTest.php` — suite existente que debe seguir en verde
