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
        data: { roles: ['superadmin', 'operativo', 'gerente', 'contable'] }
    },
    {
        path: 'conciliacion-susuerte-history',
        loadComponent: () => import('./components/conciliacion-susuerte-history/conciliacion-susuerte-history.component').then(m => m.ConciliacionSusuerteHistoryComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin', 'operativo', 'gerente', 'contable'] }
    },
    {
        path: 'mandatos',
        loadComponent: () => import('./components/mandatos/mandatos.component').then(m => m.MandatosComponent),
        canActivate: [roleGuard],
        data: { roles: ['cliente', 'superadmin', 'operativo', 'gerente', 'contable'] }
    },
    {
        path: 'profile',
        loadComponent: () => import('./components/profile-settings/profile-settings.component').then(m => m.ProfileSettingsComponent),
        canActivate: [roleGuard],
        data: { roles: ['gerente', 'operativo', 'cliente', 'contable', 'superadmin'] }
    },
    {
        path: 'dashboard',
        loadComponent: () => import('./components/dashboard/dashboard.component').then(m => m.DashboardComponent),
        canActivate: [roleGuard],
        data: { roles: ['gerente', 'operativo', 'contable', 'superadmin'] }
    },
    {
        path: 'logs',
        loadComponent: () => import('./components/logs/logs.component').then(m => m.LogsComponent),
        canActivate: [roleGuard],
        data: { roles: ['gerente', 'operativo', 'contable', 'superadmin'] }
    },
    {
        path: 'sheets',
        loadComponent: () => import('./components/sheets/sheets.component').then(m => m.SheetsComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo'] }
    },
    {
        path: 'upload',
        loadComponent: () => import('./components/upload/upload.component').then(m => m.UploadComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo'] }
    },
    {
        path: 'contable',
        loadComponent: () => import('./components/contable/contable.component').then(m => m.ContableComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo'] }
    },
    {
        path: 'planilla',
        loadComponent: () => import('./components/planilla/planilla.component').then(m => m.PlanillaComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo'] }
    },
    {
        path: 'client-upload',
        loadComponent: () => import('./components/client-upload/client-upload.component').then(m => m.ClientUploadComponent),
        canActivate: [roleGuard],
        data: { roles: ['cliente'] }
    },
    {
        path: 'validation',
        loadComponent: () => import('./components/operator-validation/operator-validation.component').then(m => m.OperatorValidationComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo', 'gerente'] }
    },
    {
        path: 'users',
        loadComponent: () => import('./components/user-management/user-management.component').then(m => m.UserManagementComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'destinatarios',
        loadComponent: () => import('./components/destinatarios/destinatarios.component').then(m => m.DestinatariosComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'notificaciones',
        loadComponent: () => import('./components/notificaciones/notificaciones.component').then(m => m.NotificacionesComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'asignaciones',
        loadComponent: () => import('./components/asignaciones/asignaciones.component').then(m => m.AsignacionesComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'parameters',
        loadComponent: () => import('./components/parameters/parameters.component').then(m => m.ParametersComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'internal-docs',
        loadComponent: () => import('./components/internal-docs/internal-docs.component').then(m => m.InternalDocsComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo', 'contable', 'gerente', 'superadmin'] }
    },
    {
        path: 'creditos',
        loadComponent: () => import('./components/credito-ordinario/credito-ordinario.component').then(m => m.CreditoOrdinarioComponent),
        canActivate: [roleGuard],
        data: { roles: ['cliente', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin'] }
    },
    {
        // SCRUM-176: id de crédito explícito en la URL — fuente de verdad de
        // qué crédito muestra la trazabilidad, en vez del fallback anterior
        // al primero de la lista (ver credito-ordinario.component.ts).
        path: 'creditos/:creditoId',
        loadComponent: () => import('./components/credito-ordinario/credito-ordinario.component').then(m => m.CreditoOrdinarioComponent),
        canActivate: [roleGuard],
        data: { roles: ['cliente', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin'] }
    },
    {
        path: 'solicitudes-credito',
        loadComponent: () => import('./components/solicitudes-credito/solicitudes-credito.component').then(m => m.SolicitudesCreditoComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin', 'gerente', 'coordinador_comercial', 'operativo'] }
    },
    {
        path: 'informes-tecnicos',
        loadComponent: () => import('./components/informe-tecnico/informe-tecnico-bandeja.component').then(m => m.InformeTecnicoBandejaComponent),
        canActivate: [roleGuard],
        data: { roles: ['ingeniero', 'coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'informes-tecnicos/:creditoId',
        loadComponent: () => import('./components/informe-tecnico/informe-tecnico-detalle.component').then(m => m.InformeTecnicoDetalleComponent),
        canActivate: [roleGuard],
        data: { roles: ['ingeniero', 'coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'actas-comite',
        loadComponent: () => import('./components/actas-comite/actas-comite-bandeja.component').then(m => m.ActasComiteBandejaComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'actas-comite/:actaId',
        loadComponent: () => import('./components/actas-comite/actas-comite-detalle.component').then(m => m.ActasComiteDetalleComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'analisis-financiero',
        loadComponent: () => import('./components/analisis-financiero/analisis-financiero-bandeja.component').then(m => m.AnalisisFinancieroBandejaComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'analisis-financiero/:creditoId',
        loadComponent: () => import('./components/analisis-financiero/analisis-financiero-detalle.component').then(m => m.AnalisisFinancieroDetalleComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        path: 'listas-sarlaft',
        loadComponent: () => import('./components/listas-sarlaft/listas-sarlaft-bandeja.component').then(m => m.ListasSarlaftBandejaComponent),
        canActivate: [roleGuard],
        data: { roles: ['oficial_cumplimiento', 'superadmin'] }
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
        data: { roles: ['oficial_cumplimiento', 'coordinador_comercial', 'superadmin'] }
    },
    {
        // SCRUM-211/215/219: Gerente y Operativo entran al mismo módulo —
        // la restricción de qué tarjeta/pantalla ve cada uno vive en el
        // backend (GestionCreditoController::ROLES_POR_CLAVE), no acá.
        path: 'gestion-creditos',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-bandeja.component').then(m => m.GestionCreditosBandejaComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'gerente', 'operativo', 'superadmin'] }
    },
    {
        path: 'gestion-creditos/:creditoId',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-detalle.component').then(m => m.GestionCreditosDetalleComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        // SCRUM-205
        path: 'gestion-creditos/:creditoId/formalizacion-garantias',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-formalizacion-garantias.component').then(m => m.GestionCreditosFormalizacionGarantiasComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        // SCRUM-193
        path: 'gestion-creditos/:creditoId/registro-cyf',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-registro-cyf.component').then(m => m.GestionCreditosRegistroCyfComponent),
        canActivate: [roleGuard],
        data: { roles: ['coordinador_comercial', 'superadmin'] }
    },
    {
        // SCRUM-211
        path: 'gestion-creditos/:creditoId/aprobacion-registro-cyf',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-aprobacion-registro-cyf.component').then(m => m.GestionCreditosAprobacionRegistroCyfComponent),
        canActivate: [roleGuard],
        data: { roles: ['gerente', 'superadmin'] }
    },
    {
        // SCRUM-215
        path: 'gestion-creditos/:creditoId/desembolso-ingreso',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-desembolso-ingreso.component').then(m => m.GestionCreditosDesembolsoIngresoComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo', 'superadmin'] }
    },
    {
        // SCRUM-219
        path: 'gestion-creditos/:creditoId/desembolso-aprobacion',
        loadComponent: () => import('./components/gestion-creditos/gestion-creditos-desembolso-aprobacion.component').then(m => m.GestionCreditosDesembolsoAprobacionComponent),
        canActivate: [roleGuard],
        data: { roles: ['gerente', 'superadmin'] }
    },
    {
        path: 'db-cleaner',
        loadComponent: () => import('./components/db-cleaner/db-cleaner.component').then(m => m.DbCleanerComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'configuraciones',
        loadComponent: () => import('./components/configuraciones/configuraciones.component').then(m => m.ConfiguracionesComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'document-areas',
        loadComponent: () => import('./components/document-areas/document-areas.component').then(m => m.DocumentAreasComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    {
        path: 'document-requests',
        loadComponent: () => import('./components/document-requests/document-requests.component').then(m => m.DocumentRequestsComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo', 'superadmin'] }
    },
    {
        path: 'document-config',
        loadComponent: () => import('./components/document-config/document-config.component').then(m => m.DocumentConfigComponent),
        canActivate: [roleGuard],
        data: { roles: ['operativo', 'superadmin'] }
    },
    {
        path: 'clientes',
        loadComponent: () => import('./components/clientes/clientes.component').then(m => m.ClientesComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin', 'gerente', 'operativo', 'coordinador_comercial'] }
    },
    {
        path: 'visitas',
        loadComponent: () => import('./components/visitas/visitas.component').then(m => m.VisitasComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin', 'gerente', 'operativo'] }
    },
    {
        path: 'roadmap',
        loadComponent: () => import('./components/roadmap/roadmap.component').then(m => m.RoadmapComponent),
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    { path: '**', redirectTo: '' }
];
