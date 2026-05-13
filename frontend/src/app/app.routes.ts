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

import { ProfileSettingsComponent } from './components/profile-settings/profile-settings.component';
import { UserManagementComponent } from './components/user-management/user-management.component';
import { MandatosComponent } from './components/mandatos/mandatos.component';
import { ConciliacionSusuerteComponent } from './components/conciliacion-susuerte/conciliacion-susuerte.component';

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
    { path: '**', redirectTo: '' }
];
