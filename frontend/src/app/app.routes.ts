import { Routes } from '@angular/router';
import { DashboardComponent } from './components/dashboard/dashboard.component';
import { LogsComponent } from './components/logs/logs.component';
import { SheetsComponent } from './components/sheets/sheets.component';
import { UploadComponent } from './components/upload/upload.component';
import { ContableComponent } from './components/contable/contable.component';
import { PlanillaComponent } from './components/planilla/planilla.component';

import { ClientUploadComponent } from './components/client-upload/client-upload.component';
import { OperatorValidationComponent } from './components/operator-validation/operator-validation.component';
import { LoginComponent } from './components/auth/login/login.component';
import { roleGuard } from './guards/role.guard';
import { DocumentConfigComponent } from './components/document-config/document-config.component';
import { DocumentRequestsComponent } from './components/document-requests/document-requests.component';

import { ProfileSettingsComponent } from './components/profile-settings/profile-settings.component';
import { UserManagementComponent } from './components/user-management/user-management.component';
import { MandatosComponent } from './components/mandatos/mandatos.component';
import { ConciliacionSusuerteComponent } from './components/conciliacion-susuerte/conciliacion-susuerte.component';
import { ConciliacionSusuerteHistoryComponent } from './components/conciliacion-susuerte-history/conciliacion-susuerte-history.component';
import { DestinatariosComponent } from './components/destinatarios/destinatarios.component';
import { NotificacionesComponent } from './components/notificaciones/notificaciones.component';
import { AsignacionesComponent } from './components/asignaciones/asignaciones.component';
import { RoadmapComponent } from './components/roadmap/roadmap.component';

export const routes: Routes = [
    { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    { path: 'login', component: LoginComponent },
    { 
        path: 'conciliacion-susuerte', 
        component: ConciliacionSusuerteComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin', 'operativo', 'gerente', 'contable'] } 
    },
    { 
        path: 'conciliacion-susuerte-history', 
        component: ConciliacionSusuerteHistoryComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin', 'operativo', 'gerente', 'contable'] } 
    },
    { 
        path: 'mandatos', 
        component: MandatosComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['cliente', 'superadmin', 'operativo'] } 
    },
    { 
        path: 'profile', 
        component: ProfileSettingsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente', 'operativo', 'cliente', 'contable', 'superadmin'] } 
    },
    { 
        path: 'dashboard', 
        component: DashboardComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente', 'operativo', 'contable', 'superadmin'] } 
    },
    { 
        path: 'logs', 
        component: LogsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente', 'operativo', 'contable', 'superadmin'] } 
    },
    { 
        path: 'sheets', 
        component: SheetsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo'] } 
    },
    { 
        path: 'upload', 
        component: UploadComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo'] } 
    },
    { 
        path: 'contable', 
        component: ContableComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo'] } 
    },
    { 
        path: 'planilla', 
        component: PlanillaComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo'] } 
    },
    { 
        path: 'client-upload', 
        component: ClientUploadComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['cliente'] } 
    },
    { 
        path: 'validation', 
        component: OperatorValidationComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo', 'gerente'] } 
    },
    { 
        path: 'users', 
        component: UserManagementComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin'] } 
    },
    { 
        path: 'destinatarios', 
        component: DestinatariosComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin'] } 
    },
    { 
        path: 'notificaciones', 
        component: NotificacionesComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin'] } 
    },
    { 
        path: 'asignaciones', 
        component: AsignacionesComponent, 
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
        path: 'db-cleaner', 
        loadComponent: () => import('./components/db-cleaner/db-cleaner.component').then(m => m.DbCleanerComponent), 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin'] } 
    },
    { 
        path: 'document-requests', 
        component: DocumentRequestsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo', 'superadmin'] } 
    },
    { 
        path: 'document-config', 
        component: DocumentConfigComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['operativo', 'superadmin'] } 
    },
    { 
        path: 'clientes', 
        loadComponent: () => import('./components/clientes/clientes.component').then(m => m.ClientesComponent), 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin', 'gerente', 'operativo'] } 
    },
    { 
        path: 'visitas', 
        loadComponent: () => import('./components/visitas/visitas.component').then(m => m.VisitasComponent), 
        canActivate: [roleGuard], 
        data: { roles: ['superadmin', 'gerente', 'operativo'] } 
    },
    {
        path: 'roadmap',
        component: RoadmapComponent,
        canActivate: [roleGuard],
        data: { roles: ['superadmin'] }
    },
    { path: '**', redirectTo: '' }
];
