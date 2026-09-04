# Manual — Superadmin

## Gestión de Roles y Permisos (Fase 1)

> **Estado: catálogo, sin efecto real todavía.** Todo lo que hagas en esta sección —crear un rol,
> marcar o desmarcar permisos— **no cambia a qué pantallas puede entrar nadie**. El acceso real
> sigue funcionando exactamente igual que siempre (por los roles que le asignás a cada usuario en
> **Gestión de Usuarios**). Esto es la base de un sistema que sí va a controlar acceso real más
> adelante (Fase 2) — ver `docs/specs/rbac-roles-permisos-parametrico.md` para el detalle técnico.

### Qué podés hacer hoy

La pantalla vive en `/roles` (no tiene enlace en el menú todavía — entrá escribiendo la URL
directamente, con sesión de superadmin activa).

- **Crear un rol nuevo**: nombre, un slug único (identificador técnico, ej. `auditor_externo`) y
  una descripción opcional. Un rol recién creado **se puede asignar de inmediato** a un usuario
  desde Gestión de Usuarios — no hace falta ningún deploy.
- **Editar un rol**: cambiar nombre, descripción y qué permisos tiene marcados. Los 10 roles que ya
  existían (Superadmin, Gerencia, Operativo, Cliente, Contable, Coordinador Comercial, Oficial de
  Cumplimiento, Comité de Crédito, Tesorería, Ingeniero) tienen el slug bloqueado — el resto del
  sistema todavía depende de ese texto exacto internamente.
- **Eliminar un rol**: solo roles creados por vos (no los 10 originales), y solo si ningún usuario
  lo tiene asignado actualmente.
- El rol **Superadmin** no se puede eliminar, ni se le puede quitar el permiso "Gestión de Roles y
  Permisos" — te dejaría sin forma de volver a entrar a esta pantalla.

### Qué NO hace todavía

- Marcar/desmarcar un permiso no habilita ni bloquea nada en la app real.
- No hay permisos por acción dentro de una pantalla (ej. "ver" vs "aprobar") — cada permiso es una
  pantalla completa.
- No hay excepciones por usuario individual — todo pasa por el rol.

## API (referencia técnica)

Todos los endpoints requieren sesión de `superadmin`.

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/api/roles` | Catálogo de roles, con sus permisos y cuántos usuarios tiene cada uno |
| POST | `/api/roles` | Crea un rol (`nombre`, `slug`, `descripcion`, `permission_ids[]`) |
| PUT | `/api/roles/{id}` | Edita un rol (mismo payload; `slug` rechazado si el rol es del sistema) |
| DELETE | `/api/roles/{id}` | Elimina un rol (rechaza roles del sistema o con usuarios asignados) |
| GET | `/api/permissions` | Catálogo de permisos, agrupado por módulo |

Todo cambio (crear/editar/eliminar rol) queda auditado en **Actividad de Usuarios**
(`/actividad-usuarios`), con las acciones `rol_creado`, `rol_actualizado`, `rol_eliminado`.
