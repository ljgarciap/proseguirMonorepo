<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md). Catálogo puro: nada acá
 * controla acceso real todavía (Fase 2). Los 10 roles semilla y sus
 * permisos replican EXACTAMENTE lo que hoy vive hardcodeado en
 * app.routes.ts (data.roles) — verificado línea por línea contra el
 * archivo real, no de memoria, el 2026-09-04.
 *
 * Idempotente y seguro en cada deploy (mismo criterio que ConfiguracionSeeder):
 * - roles/permissions: firstOrCreate por slug/clave — nunca sobreescribe
 *   nombre/descripción de un rol ya existente (un superadmin puede haberlo
 *   editado desde /roles).
 * - role_permission: solo se siembra la primera vez que el ROL se crea
 *   (wasRecentlyCreated) — si el rol ya existía, no se re-sincronizan sus
 *   permisos en cada deploy, para no revertir una asignación que un
 *   superadmin haya cambiado manualmente desde la UI.
 */
class RolesPermissionsSeeder extends Seeder
{
    /**
     * Catálogo de permisos: una entrada por pantalla protegida hoy en
     * app.routes.ts. Pares lista+detalle con el mismo set de roles se
     * colapsan en un solo permiso (ej. 'creditos' cubre /creditos y
     * /creditos/:creditoId); donde el detalle tiene roles distintos al
     * listado, quedan separados (ej. listas-sarlaft).
     *
     * @var array<int, array{clave: string, nombre: string, modulo: string, roles: string[]}>
     */
    private const PERMISOS = [
        // Conciliación
        ['clave' => 'conciliacion-susuerte', 'nombre' => 'Conciliación Susuerte', 'modulo' => 'Conciliación', 'roles' => ['superadmin', 'operativo', 'gerente', 'contable']],
        ['clave' => 'conciliacion-susuerte-history', 'nombre' => 'Historial de Conciliación', 'modulo' => 'Conciliación', 'roles' => ['superadmin', 'operativo', 'gerente', 'contable']],

        // General
        ['clave' => 'mandatos', 'nombre' => 'Mandatos', 'modulo' => 'General', 'roles' => ['cliente', 'superadmin', 'operativo', 'gerente', 'contable']],
        ['clave' => 'profile', 'nombre' => 'Mi Perfil', 'modulo' => 'General', 'roles' => ['gerente', 'operativo', 'cliente', 'contable', 'superadmin']],
        ['clave' => 'dashboard', 'nombre' => 'Dashboard', 'modulo' => 'General', 'roles' => ['gerente', 'operativo', 'contable', 'superadmin']],

        // Administración
        ['clave' => 'logs', 'nombre' => 'Logs (Pipeline OCR)', 'modulo' => 'Administración', 'roles' => ['gerente', 'operativo', 'contable', 'superadmin']],
        ['clave' => 'actividad-usuarios', 'nombre' => 'Actividad de Usuarios', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'users', 'nombre' => 'Gestión de Usuarios', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'destinatarios', 'nombre' => 'Destinatarios', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'notificaciones', 'nombre' => 'Notificaciones', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'asignaciones', 'nombre' => 'Asignaciones', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'parameters', 'nombre' => 'Parámetros Genéricos', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'db-cleaner', 'nombre' => 'Limpieza de Base de Datos', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'configuraciones', 'nombre' => 'Configuraciones del Sistema', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'document-areas', 'nombre' => 'Áreas de Documentos', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        ['clave' => 'roadmap', 'nombre' => 'Roadmap', 'modulo' => 'Administración', 'roles' => ['superadmin']],
        // Pantalla nueva de esta feature (docs/specs/rbac-roles-permisos-parametrico.md) —
        // no vive en app.routes.ts todavía (Fase 1 la mantiene oculta de la
        // navegación). Existe como permiso desde ya porque RoleController
        // necesita algo concreto que proteger cuando 'superadmin' intenta
        // quitarse a sí mismo el acceso a esta misma pantalla (criterio de
        // aceptación de la spec).
        ['clave' => 'roles', 'nombre' => 'Gestión de Roles y Permisos', 'modulo' => 'Administración', 'roles' => ['superadmin']],

        // OCR / Cargas
        ['clave' => 'sheets', 'nombre' => 'Hojas de Cálculo', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo']],
        ['clave' => 'upload', 'nombre' => 'Carga OCR', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo']],
        ['clave' => 'contable', 'nombre' => 'Contable', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo']],
        ['clave' => 'planilla', 'nombre' => 'Planilla', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo']],
        ['clave' => 'client-upload', 'nombre' => 'Mis Cargas (Cliente)', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente']],
        ['clave' => 'validation', 'nombre' => 'Validación de Cargas', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo', 'gerente']],

        // Crédito Ordinario
        ['clave' => 'creditos', 'nombre' => 'Crédito Ordinario / Mis Créditos', 'modulo' => 'Crédito Ordinario', 'roles' => ['cliente', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin']],
        ['clave' => 'solicitudes-credito', 'nombre' => 'Solicitudes de Crédito', 'modulo' => 'Crédito Ordinario', 'roles' => ['superadmin', 'gerente', 'coordinador_comercial', 'operativo']],
        ['clave' => 'informes-tecnicos', 'nombre' => 'Informes Técnicos', 'modulo' => 'Crédito Ordinario', 'roles' => ['ingeniero', 'coordinador_comercial', 'superadmin']],
        ['clave' => 'actas-comite', 'nombre' => 'Actas de Comité', 'modulo' => 'Crédito Ordinario', 'roles' => ['coordinador_comercial', 'superadmin']],
        ['clave' => 'analisis-financiero', 'nombre' => 'Análisis Financiero', 'modulo' => 'Crédito Ordinario', 'roles' => ['coordinador_comercial', 'superadmin']],
        ['clave' => 'listas-sarlaft', 'nombre' => 'Listas Restrictivas y SARLAFT (Bandeja)', 'modulo' => 'Crédito Ordinario', 'roles' => ['oficial_cumplimiento', 'superadmin']],
        ['clave' => 'listas-sarlaft:detalle', 'nombre' => 'Listas Restrictivas y SARLAFT (Detalle)', 'modulo' => 'Crédito Ordinario', 'roles' => ['oficial_cumplimiento', 'coordinador_comercial', 'superadmin']],

        // Gestión de Créditos
        ['clave' => 'gestion-creditos', 'nombre' => 'Gestión de Créditos (Bandeja)', 'modulo' => 'Gestión de Créditos', 'roles' => ['coordinador_comercial', 'gerente', 'operativo', 'tesoreria', 'superadmin']],
        ['clave' => 'gestion-creditos:detalle', 'nombre' => 'Gestión de Créditos (Detalle)', 'modulo' => 'Gestión de Créditos', 'roles' => ['coordinador_comercial', 'superadmin']],
        ['clave' => 'gestion-creditos:formalizacion-garantias', 'nombre' => 'Formalización de Garantías', 'modulo' => 'Gestión de Créditos', 'roles' => ['operativo', 'superadmin']],
        ['clave' => 'gestion-creditos:registro-cyf', 'nombre' => 'Registro de Crédito en CYF', 'modulo' => 'Gestión de Créditos', 'roles' => ['coordinador_comercial', 'superadmin']],
        ['clave' => 'gestion-creditos:aprobacion-registro-cyf', 'nombre' => 'Aprobación de Registro CYF', 'modulo' => 'Gestión de Créditos', 'roles' => ['gerente', 'superadmin']],
        ['clave' => 'gestion-creditos:desembolso-ingreso', 'nombre' => 'Registro de Desembolso', 'modulo' => 'Gestión de Créditos', 'roles' => ['operativo', 'superadmin']],
        ['clave' => 'gestion-creditos:desembolso-aprobacion', 'nombre' => 'Aprobación de Desembolso', 'modulo' => 'Gestión de Créditos', 'roles' => ['gerente', 'superadmin']],
        ['clave' => 'gestion-creditos:transferencia-bancaria', 'nombre' => 'Transferencia Bancaria', 'modulo' => 'Gestión de Créditos', 'roles' => ['tesoreria', 'superadmin']],

        // Documentos
        ['clave' => 'internal-docs', 'nombre' => 'Documentos Internos (Bandeja Interna)', 'modulo' => 'Documentos', 'roles' => ['operativo', 'contable', 'gerente', 'superadmin']],
        ['clave' => 'document-requests', 'nombre' => 'Solicitudes de Documentos a Clientes', 'modulo' => 'Documentos', 'roles' => ['operativo', 'superadmin']],
        // SCRUM-327: Coordinador Comercial también necesita esta pantalla —
        // agregado 2026-09-04, ver migración de grant para instalaciones
        // donde el catálogo ya estaba sembrado antes de este cambio.
        ['clave' => 'document-config', 'nombre' => 'Configuración de Documentos', 'modulo' => 'Documentos', 'roles' => ['operativo', 'coordinador_comercial', 'superadmin']],

        // Clientes
        ['clave' => 'clientes', 'nombre' => 'Clientes', 'modulo' => 'Clientes', 'roles' => ['superadmin', 'gerente', 'operativo', 'coordinador_comercial']],
        ['clave' => 'visitas', 'nombre' => 'Visitas a Clientes', 'modulo' => 'Clientes', 'roles' => ['superadmin', 'gerente', 'operativo']],
    ];

    /**
     * RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md, 2026-09-04): estos
     * 6 módulos tienen roles DISTINTOS por acción dentro del mismo prefijo
     * de ruta backend — el catálogo "una pantalla = un permiso" de arriba
     * no alcanza para ellos. Cada entrada acá replica 1:1 el
     * `checkrole:` real de esa acción en routes/api.php (verificado línea
     * por línea, no de memoria, el 2026-09-04). Acciones con el mismo set
     * de roles se colapsan en un solo permiso (ej. mandatos:gestionar
     * cubre status+editar+eliminar, los 3 con [operativo,superadmin]).
     *
     * @var array<int, array{clave: string, nombre: string, modulo: string, roles: string[]}>
     */
    private const PERMISOS_ACCION = [
        ['clave' => 'uploads:subir', 'nombre' => 'Subir Documento (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente', 'operativo']],
        ['clave' => 'uploads:descargar', 'nombre' => 'Descargar Documento (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente', 'operativo', 'gerente', 'coordinador_comercial', 'superadmin']],
        ['clave' => 'uploads:validar', 'nombre' => 'Validar Documento (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['operativo']],
        ['clave' => 'uploads:aprobar', 'nombre' => 'Aprobar Documento (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['gerente']],
        ['clave' => 'uploads:eliminar', 'nombre' => 'Eliminar Documento (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente', 'superadmin']],
        ['clave' => 'uploads:pending-count', 'nombre' => 'Ver Contador de Pendientes (OCR)', 'modulo' => 'OCR y Cargas', 'roles' => ['gerente', 'operativo', 'contable', 'superadmin']],

        ['clave' => 'mandatos:crear', 'nombre' => 'Crear Mandato', 'modulo' => 'General', 'roles' => ['cliente']],
        ['clave' => 'mandatos:ver', 'nombre' => 'Ver Mandatos', 'modulo' => 'General', 'roles' => ['cliente', 'gerente', 'operativo', 'superadmin']],
        ['clave' => 'mandatos:exportar', 'nombre' => 'Exportar Mandato', 'modulo' => 'General', 'roles' => ['cliente', 'gerente', 'operativo', 'contable', 'superadmin']],
        ['clave' => 'mandatos:gestionar', 'nombre' => 'Gestionar Mandato (estado/editar/eliminar)', 'modulo' => 'General', 'roles' => ['operativo', 'superadmin']],

        ['clave' => 'parameters:gestionar', 'nombre' => 'Gestionar Parámetros Genéricos', 'modulo' => 'Administración', 'roles' => ['superadmin']],

        ['clave' => 'document-presets:ver', 'nombre' => 'Ver Presets de Documentos', 'modulo' => 'Documentos', 'roles' => ['superadmin', 'operativo', 'coordinador_comercial']],
        ['clave' => 'document-presets:gestionar', 'nombre' => 'Gestionar Presets de Documentos', 'modulo' => 'Documentos', 'roles' => ['superadmin', 'operativo']],

        ['clave' => 'document-requests:gestionar', 'nombre' => 'Gestionar Solicitudes de Documentos a Clientes', 'modulo' => 'Documentos', 'roles' => ['superadmin', 'operativo']],

        ['clave' => 'solicitudes-credito:editar', 'nombre' => 'Editar Condiciones Financieras de Solicitud de Crédito', 'modulo' => 'Crédito Ordinario', 'roles' => ['coordinador_comercial']],

        ['clave' => 'ubicaciones', 'nombre' => 'Consultar Departamentos/Ciudades', 'modulo' => 'Clientes', 'roles' => ['superadmin', 'gerente', 'operativo', 'coordinador_comercial']],
        ['clave' => 'settlement:reconcile', 'nombre' => 'Conciliar Settlement', 'modulo' => 'Conciliación', 'roles' => ['operativo', 'superadmin']],

        // Menú lateral (app.component.ts) — encontrado 2026-09-04 al conectar
        // el gap: /mandatos y /creditos aparecen 2 veces en el menú (una vez
        // para staff, otra para cliente en "Portal Cliente"), cada una con su
        // propia audiencia — más angosta que el permiso de pantalla completo
        // (`mandatos`/`creditos`, que cubre ambas audiencias juntas para el
        // gate de la ruta). Reusar el permiso de pantalla acá le mostraría a
        // un cliente el ítem "Revisión Mandatos" duplicado (pensado para
        // staff), que hoy no ve.
        ['clave' => 'menu:mandatos-staff', 'nombre' => 'Menú: Revisión Mandatos (staff)', 'modulo' => 'General', 'roles' => ['gerente', 'operativo', 'contable', 'superadmin']],
        ['clave' => 'menu:mandatos-cliente', 'nombre' => 'Menú: Diligenciar Mandato (cliente)', 'modulo' => 'General', 'roles' => ['cliente']],
        ['clave' => 'menu:creditos-staff', 'nombre' => 'Menú: Crédito Ordinario (staff)', 'modulo' => 'Crédito Ordinario', 'roles' => ['coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin']],
        ['clave' => 'menu:creditos-cliente', 'nombre' => 'Menú: Mis Créditos (cliente)', 'modulo' => 'Crédito Ordinario', 'roles' => ['cliente']],

        ['clave' => 'dashboard:stats', 'nombre' => 'Ver Estadísticas del Dashboard', 'modulo' => 'General', 'roles' => ['gerente', 'operativo', 'superadmin']],
        ['clave' => 'dashboard:cartera-factoring', 'nombre' => 'Ver Cartera Factoring', 'modulo' => 'General', 'roles' => ['operativo', 'superadmin']],
        ['clave' => 'dashboard:cartera-factoring-export', 'nombre' => 'Exportar Cartera Factoring', 'modulo' => 'General', 'roles' => ['superadmin']],

        ['clave' => 'logs:gestionar', 'nombre' => 'Gestionar Logs (Pipeline OCR y Sistema)', 'modulo' => 'Administración', 'roles' => ['gerente', 'operativo', 'superadmin']],

        ['clave' => 'sectores', 'nombre' => 'Consultar Sectores', 'modulo' => 'Administración', 'roles' => ['superadmin']],

        ['clave' => 'contable:importar', 'nombre' => 'Importar Archivo Contable', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente', 'superadmin']],
        ['clave' => 'contable:limpiar', 'nombre' => 'Limpiar Datos Contables', 'modulo' => 'OCR y Cargas', 'roles' => ['superadmin']],
        ['clave' => 'contable:ver', 'nombre' => 'Ver Datos Contables', 'modulo' => 'OCR y Cargas', 'roles' => ['gerente', 'operativo', 'superadmin']],

        ['clave' => 'planilla:cargar', 'nombre' => 'Cargar Planilla', 'modulo' => 'OCR y Cargas', 'roles' => ['cliente', 'superadmin']],
        ['clave' => 'planilla:ver', 'nombre' => 'Ver Planilla', 'modulo' => 'OCR y Cargas', 'roles' => ['gerente', 'operativo', 'superadmin']],

        ['clave' => 'datos-factor:ver', 'nombre' => 'Ver Datos Factor', 'modulo' => 'General', 'roles' => ['cliente', 'operativo', 'gerente', 'superadmin']],
        ['clave' => 'datos-factor:editar', 'nombre' => 'Editar Datos Factor', 'modulo' => 'General', 'roles' => ['operativo', 'gerente', 'superadmin']],

        ['clave' => 'internal-docs:gestionar', 'nombre' => 'Gestionar Documentos Internos y Ruta de Aprobación', 'modulo' => 'Documentos', 'roles' => ['operativo', 'contable', 'gerente', 'superadmin', 'coordinador_comercial']],
        ['clave' => 'document-areas:ver', 'nombre' => 'Ver Áreas de Documentos', 'modulo' => 'Documentos', 'roles' => ['operativo', 'contable', 'gerente', 'superadmin', 'coordinador_comercial']],
        // SCRUM-327: mismo grant que 'document-config' arriba — sin este
        // permiso de acción, Coordinador Comercial vería la pantalla pero
        // cada operación de la pantalla (listar/crear/editar/eliminar
        // requisitos) le devolvería 403.
        ['clave' => 'document-requirements:gestionar', 'nombre' => 'Gestionar Requisitos de Documentos', 'modulo' => 'Documentos', 'roles' => ['superadmin', 'operativo', 'coordinador_comercial']],
    ];

    /**
     * Los 10 roles que hoy viven hardcodeados (whitelist de
     * UserController, checkboxes de user-management.component.ts). El
     * slug es el string legacy exacto — nunca editable desde la UI (ver
     * revisión de Cybersecurity en la spec).
     *
     * @var array<int, array{slug: string, nombre: string, descripcion: string}>
     */
    private const ROLES = [
        ['slug' => 'superadmin', 'nombre' => 'Super Administrador', 'descripcion' => 'Acceso completo al sistema.'],
        ['slug' => 'gerente', 'nombre' => 'Gerencia', 'descripcion' => 'Dirección administrativa y financiera.'],
        ['slug' => 'operativo', 'nombre' => 'Operativo', 'descripcion' => 'Operación diaria: cargas, validación, desembolsos.'],
        ['slug' => 'cliente', 'nombre' => 'Cliente', 'descripcion' => 'Portal de clientes de factoring.'],
        ['slug' => 'contable', 'nombre' => 'Contable', 'descripcion' => 'Bandeja interna y conciliación contable.'],
        // SCRUM-331: renombrado de "Coordinador Comercial" a "Director de
        // Crédito" (cambio de cargo interno en Proseguir) — el slug NO
        // cambia (nunca editable desde la UI, ver docblock arriba), solo
        // el nombre visible. Ver migración de rename para instalaciones
        // donde el rol ya estaba sembrado antes de este cambio.
        ['slug' => 'coordinador_comercial', 'nombre' => 'Director de Crédito', 'descripcion' => 'Gestión comercial de solicitudes de crédito.'],
        ['slug' => 'oficial_cumplimiento', 'nombre' => 'Oficial de Cumplimiento', 'descripcion' => 'Listas restrictivas y SARLAFT.'],
        ['slug' => 'comite_credito', 'nombre' => 'Comité de Crédito', 'descripcion' => 'Evaluación de créditos en comité.'],
        ['slug' => 'tesoreria', 'nombre' => 'Tesorería', 'descripcion' => 'Transferencias y desembolsos bancarios.'],
        ['slug' => 'ingeniero', 'nombre' => 'Ingeniero', 'descripcion' => 'Informes técnicos de crédito constructor.'],
    ];

    public function run(): void
    {
        $catalogo = array_merge(self::PERMISOS, self::PERMISOS_ACCION);

        // Roles primero (los permisos de acción necesitan poder resolver
        // el rol por slug al asignarse más abajo).
        foreach (self::ROLES as $datos) {
            Role::firstOrCreate(
                ['slug' => $datos['slug']],
                [
                    'nombre' => $datos['nombre'],
                    'descripcion' => $datos['descripcion'],
                    'es_sistema' => true,
                ]
            );
        }

        $rolesPorSlug = Role::whereIn('slug', array_column(self::ROLES, 'slug'))
            ->get()->keyBy('slug');

        foreach ($catalogo as $datos) {
            $permiso = Permission::firstOrCreate(
                ['clave' => $datos['clave']],
                [
                    'nombre' => $datos['nombre'],
                    'modulo' => $datos['modulo'],
                    'descripcion' => $datos['nombre'],
                ]
            );

            // Un permiso RECIÉN CREADO se asigna a sus roles designados sin
            // importar si esos roles ya existían — si no, cada permiso que
            // se agregue en una expansión futura del catálogo (como los de
            // Fase 2, PERMISOS_ACCION) quedaría huérfano en cualquier
            // ambiente donde el seeder ya corrió antes (los 10 roles
            // semilla siempre existen ahí, wasRecentlyCreated ya es false).
            // Un permiso que YA EXISTÍA no se re-sincroniza — un
            // superadmin puede haberlo reconfigurado desde /roles (mismo
            // criterio que ConfiguracionSeeder con 'valor').
            if (!$permiso->wasRecentlyCreated) {
                continue;
            }

            foreach ($datos['roles'] as $slugRol) {
                $rol = $rolesPorSlug->get($slugRol);
                if ($rol) {
                    $rol->permissions()->syncWithoutDetaching([$permiso->id]);
                }
            }
        }
    }
}
