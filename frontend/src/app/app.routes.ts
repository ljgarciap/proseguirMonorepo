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

export const routes: Routes = [
    { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    { path: 'login', component: LoginComponent },
    { 
        path: 'profile', 
        component: ProfileSettingsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente', 'operativo', 'cliente'] } 
    },
    { 
        path: 'dashboard', 
        component: DashboardComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente', 'operativo'] } 
    },
    { 
        path: 'logs', 
        component: LogsComponent, 
        canActivate: [roleGuard], 
        data: { roles: ['gerente'] } 
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
    { path: '**', redirectTo: '' }
];
