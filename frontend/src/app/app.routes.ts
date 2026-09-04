import { Routes } from '@angular/router';
import { roleGuard } from './guards/role.guard';

export const routes: Routes = [
    { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    {
        path: 'login',
        loadComponent: () => import('./components/auth/login/login.component').then(m => m.LoginComponent)
    },
    {
        path: 'conciliacion-susuerte',
        loadComponent: () => import('./components/conciliacion-susuerte/conciliacion-susuerte.component').then(m => m.ConciliacionSusuerteComponent),
        canActivate: [roleGuard],
        data: { permission: 'conciliacion-susuerte' }
    },
    {
        path: 'conciliacion-susuerte-history',
        loadComponent: () => import('./components/conciliacion-susuerte-history/conciliacion-susuerte-history.component').then(m => m.ConciliacionSusuerteHistoryComponent),
        canActivate: [roleGuard],
        data: { permission: 'conciliacion-susuerte-history' }
    },
    {
        path: 'mandatos',
        loadComponent: () => import('./components/mandatos/mandatos.component').then(m => m.MandatosComponent),
        canActivate: [roleGuard],
        data: { permission: 'mandatos' }
    },
    {
        path: 'profile',
        loadComponent: () => import('./components/profile-settings/profile-settings.component').then(m => m.ProfileSettingsComponent),
        canActivate: [roleGuard],
        data: { permission: 'profile' }
    },
    {
        path: 'dashboard',
        loadComponent: () => import('./components/dashboard/dashboard.component').then(m => m.DashboardComponent),
        canActivate: [roleGuard],
        data: { permission: 'dashboard' }
    },
    {
        path: 'logs',
        loadComponent: () => import('./components/logs/logs.component').then(m => m.LogsComponent),
        canActivate: [roleGuard],
        data: { permission: 'logs' }
    },
    {
        // SCRUM-246 — distinta de /logs (esa es el pipeline de OCR).
        path: 'actividad-usuarios',
        loadComponent: () => import('./components/activity-logs/activity-logs.component').then(m => m.ActivityLogsComponent),
        canActivate: [roleGuard],
        data: { permission: 'actividad-usuarios' }
    },
    {
        path: 'sheets',
        loadComponent: () => import('./components/sheets/sheets.component').then(m => m.SheetsComponent),
        canActivate: [roleGuard],
        data: { permission: 'sheets' }
    },
    {
        path: 'upload',
        loadComponent: () => import('./components/upload/upload.component').then(m => m.UploadComponent),
        canActivate: [roleGuard],
        data: { permission: 'upload' }
    },
    {
        path: 'contable',
        loadComponent: () => import('./components/contable/contable.component').then(m => m.ContableComponent),
        canActivate: [roleGuard],
        data: { permission: 'contable' }
    },
    {
        path: 'planilla',
        loadComponent: () => import('./components/planilla/planilla.component').then(m => m.PlanillaComponent),
        canActivate: [roleGuard],
        data: { permission: 'planilla' }
    },
    {
        path: 'client-upload',
        loadComponent: () => import('./components/client-upload/client-upload.component').then(m => m.ClientUploadComponent),
        canActivate: [roleGuard],
        data: { permission: 'client-upload' }
    },
    {
        path: 'validation',
        loadComponent: () => import('./components/operator-validation/operator-validation.component').then(m => m.OperatorValidationComponent),
        canActivate: [roleGuard],
        data: { permission: 'validation' }
    },
    {
        path: 'users',
        loadComponent: () => import('./components/user-management/user-management.component').then(m => m.UserManagementComponent),
        canActivate: [roleGuard],
        data: { permission: 'users' }
    },
    {
        path: 'destinatarios',
        loadComponent: () => import('./components/destinatarios/destinatarios.component').then(m => m.DestinatariosComponent),
        canActivate: [roleGuard],
        data: { permission: 'destinatarios' }
    },
    {
        path: 'notificaciones',
        loadComponent: () => import('./components/notificaciones/notificaciones.component').then(m => m.NotificacionesComponent),
        canActivate: [roleGuard],
        data: { permission: 'notificaciones' }
    },
    {
        path: 'asignaciones',
        loadComponent: () => import('./components/asignaciones/asignaciones.component').then(m => m.AsignacionesComponent),
        canActivate: [roleGuard],
        data: { permission: 'asignaciones' }
    },
    {
        path: 'parameters',
        loadComponent: () => import('./components/parameters/parameters.component').then(m => m.ParametersComponent),
        canActivate: [roleGuard],
        data: { permission: 'parameters' }
    },
    {
        path: 'internal-docs',
        loadComponent: () => import('./components/internal-docs/internal-docs.component').then(m => m.InternalDocsComponent),
        canActivate: [roleGuard],
        data: { permission: 'internal-docs' }
    },
    {
        path: 'creditos',
        loadComponent: () => import('./components/credito-ordinario/credito-ordinario.component').then(m => m.CreditoOrdinarioComponent),
        canActivate: [roleGuard],
        data: { permission: 'creditos' }
    },
    {
        // SCRUM-176: id de crédito explícito en la URL — fuente de verdad de
        // qué crédito muestra la trazabilidad, en vez del fallback anterior
        // al primero de la lista (ver credito-ordinario.component.ts).
        path: 'creditos/:creditoId',
        loadComponent: () => import('./components/credito-ordinario/credito-ordinario.component').then(m => m.CreditoOrdinarioComponent),
        canActivate: [roleGuard],
        data: { permission: 'creditos' }
    },
    {
        path: 'solicitudes-credito',
        loadComponent: () => import('./components/solicitudes-credito/solicitudes-credito.component').then(m => m.SolicitudesCreditoComponent),
        canActivate: [roleGuard],
        data: { permission: 'solicitudes-credito' }
    },
    {
        path: 'informes-tecnicos',
        loadComponent: () => import('./components/informe-tecnico/informe-tecnico-bandeja.component').then(m => m.InformeTecnicoBandejaComponent),
        canActivate: [roleGuard],
        data: { permission: 'informes-tecnicos' }
    },
    {
        path: 'informes-tecnicos/:creditoId',
        loadComponent: () => import('./components/informe-tecnico/informe-tecnico-detalle.component').then(m => m.InformeTecnicoDetalleComponent),
        canActivate: [roleGuard],
        data: { permission: 'informes-tecnicos' }
    },
    {
        path: 'actas-comite',
        loadComponent: () => import('./components/actas-comite/actas-comite-bandeja.component').then(m => m.ActasComiteBandejaComponent),
        canActivate: [roleGuard],
        data: { permission: 'actas-comite' }
    },
    {
        path: 'actas-comite/:actaId',
        loadComponent: () => import('./components/actas-comite/actas-comite-detalle.component').then(m => m.ActasComiteDetalleComponent),
        canActivate: [roleGuard],
        data: { permission: 'actas-comite' }
    },
    {
        path: 'analisis-financiero',
        loadComponent: () => import('./components/analisis-financiero/analisis-financiero-bandeja.component').then(m => m.AnalisisFinancieroBandejaComponent),
        canActivate: [roleGuard],
        data: { permission: 'analisis-financiero' }
    },
    {
        path: 'analisis-financiero/:creditoId',
        loadComponent: () => import('./components/analisis-financiero/analisis-financiero-detalle.component').then(m => m.AnalisisFinancieroDetalleComponent),
        canActivate: [roleGuard],
        data: { permission: 'analisis-financiero' }
    },
    {
        path: 'listas-sarlaft',
        loadComponent: () => import('./components/listas-sarlaft/listas-sarlaft-bandeja.component').then(m => m.ListasSarlaftBandejaComponent),
        canActivate: [roleGuard],
        data: { permission: 'listas-sarlaft' }
    },
    {
        // SCRUM-184: Coordinador Comercial puede ver el detalle SARLAFT
        // (link "Ver" desde Crédito Ordinario), igual que ya puede ver el
        // Informe Técnico — la edición sigue restringida a Oficial de
        // Cumplimiento vía `puedeEditar` dentro del propio componente, no
        // por este guard.
        path: 'listas-sarlaft/:creditoId',
        loadComponent: () => import('./components/listas-sarlaft/listas-sarlaft-detalle.component').then(m => m.ListasSarlaftDetalleComponent),
        canActivate: [roleGuard],
        data: { permission: 'listas-sarlaft:detalle' }
    },
    {
        // SCRUM-211/215/219: Gerente y Operativo entran al mismo módulo —
        // la restricción de qué tarjeta/pantalla ve cada uno vive en el
        // backend (GestionCreditoController::ROLES_POR_CLAVE), no acá.
        path: 'gestion-creditos',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-bandeja.component').then(m => m.GestionCreditosBandejaComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos' }
    },
    {
        path: 'gestion-creditos/:creditoId',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-detalle.component').then(m => m.GestionCreditosDetalleComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:detalle' }
    },
    {
        // SCRUM-205, rol Operativo desde SCRUM-237 (antes Coordinador Comercial)
        path: 'gestion-creditos/:creditoId/formalizacion-garantias',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-formalizacion-garantias.component').then(m => m.GestionCreditosFormalizacionGarantiasComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:formalizacion-garantias' }
    },
    {
        // SCRUM-193
        path: 'gestion-creditos/:creditoId/registro-cyf',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-registro-cyf.component').then(m => m.GestionCreditosRegistroCyfComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:registro-cyf' }
    },
    {
        // SCRUM-211
        path: 'gestion-creditos/:creditoId/aprobacion-registro-cyf',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-aprobacion-registro-cyf.component').then(m => m.GestionCreditosAprobacionRegistroCyfComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:aprobacion-registro-cyf' }
    },
    {
        // SCRUM-215
        path: 'gestion-creditos/:creditoId/desembolso-ingreso',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-desembolso-ingreso.component').then(m => m.GestionCreditosDesembolsoIngresoComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:desembolso-ingreso' }
    },
    {
        // SCRUM-219
        path: 'gestion-creditos/:creditoId/desembolso-aprobacion',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-desembolso-aprobacion.component').then(m => m.GestionCreditosDesembolsoAprobacionComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:desembolso-aprobacion' }
    },
    {
        // SCRUM-224
        path: 'gestion-creditos/:creditoId/transferencia-bancaria',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-transferencia-bancaria.component').then(m => m.GestionCreditosTransferenciaBancariaComponent),
        canActivate: [roleGuard],
        data: { permission: 'gestion-creditos:transferencia-bancaria' }
    },
    {
        path: 'db-cleaner',
        loadComponent: () => import('./components/db-cleaner/db-cleaner.component').then(m => m.DbCleanerComponent),
        canActivate: [roleGuard],
        data: { permission: 'db-cleaner' }
    },
    {
        path: 'configuraciones',
        loadComponent: () => import('./components/configuraciones/configuraciones.component').then(m => m.ConfiguracionesComponent),
        canActivate: [roleGuard],
        data: { permission: 'configuraciones' }
    },
    {
        path: 'document-areas',
        loadComponent: () => import('./components/document-areas/document-areas.component').then(m => m.DocumentAreasComponent),
        canActivate: [roleGuard],
        data: { permission: 'document-areas' }
    },
    {
        path: 'document-requests',
        loadComponent: () => import('./components/document-requests/document-requests.component').then(m => m.DocumentRequestsComponent),
        canActivate: [roleGuard],
        data: { permission: 'document-requests' }
    },
    {
        path: 'document-config',
        loadComponent: () => import('./components/document-config/document-config.component').then(m => m.DocumentConfigComponent),
        canActivate: [roleGuard],
        data: { permission: 'document-config' }
    },
    {
        path: 'clientes',
        loadComponent: () => import('./components/clientes/clientes.component').then(m => m.ClientesComponent),
        canActivate: [roleGuard],
        data: { permission: 'clientes' }
    },
    {
        path: 'visitas',
        loadComponent: () => import('./components/visitas/visitas.component').then(m => m.VisitasComponent),
        canActivate: [roleGuard],
        data: { permission: 'visitas' }
    },
    {
        path: 'roadmap',
        loadComponent: () => import('./components/roadmap/roadmap.component').then(m => m.RoadmapComponent),
        canActivate: [roleGuard],
        data: { permission: 'roadmap' }
    },
    {
        // Motor paramétrico de Roles y Permisos — Fase 1/2 (ver
        // docs/specs/rbac-roles-permisos-parametrico.md y
        // docs/specs/rbac-fase2-enforcement.md). Sigue sin enlace en
        // ningún menú/nav hasta que el rollout completo de Fase 2 termine
        // (decisión de Luis) — alcanzable tipeando la URL directamente.
        path: 'roles',
        loadComponent: () => import('./components/roles-management/roles-management.component').then(m => m.RolesManagementComponent),
        canActivate: [roleGuard],
        data: { permission: 'roles' }
    },
    { path: '**', redirectTo: '' }
];
