import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './services/auth.service';
import { environment } from '../environments/environment';
import { interval, Subscription } from 'rxjs';

@Component({
    selector: 'app-root',
    standalone: true,
    imports: [CommonModule, RouterOutlet, RouterModule, FormsModule],
    template: `
    <!-- AUTHENTICATED LAYOUT -->
    <div class="app-layout" *ngIf="authService.isAuthenticated() && authService.getActiveRole()">
      <!-- Sidebar Backdrop -->
      <div class="sidebar-backdrop" *ngIf="isSidebarOpen" (click)="toggleSidebar()"></div>

      <!-- Sidebar -->
      <aside class="sidebar" [class.open]="isSidebarOpen">
        <div class="sidebar-header">
          <img src="assets/logopsl.png" alt="Proseguir" class="app-logo">
        </div>

        <nav class="sidebar-nav">
          <div class="nav-section" *ngIf="authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin', 'coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'tesoreria'])">
            <label>Operaciones</label>
            <a *ngIf="authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin'])" routerLink="/dashboard" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a *ngIf="authService.isAuthorized(['coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin'])" routerLink="/creditos" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">payments</span> Crédito Ordinario
            </a>
            <a *ngIf="authService.isAuthorized(['coordinador_comercial', 'gerente', 'superadmin', 'operativo'])" routerLink="/solicitudes-credito" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">assignment_turned_in</span> Registro Solicitud Crédito
            </a>
            <a *ngIf="authService.isAuthorized(['operativo'])" routerLink="/sheets" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">database</span> Base de Datos
            </a>
            <a *ngIf="authService.isAuthorized(['operativo'])" routerLink="/upload" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">upload_file</span> Subir Operación
            </a>
            <a *ngIf="authService.isAuthorized(['operativo', 'gerente'])" routerLink="/validation" routerLinkActive="active" class="nav-link">
              <div class="nav-link-content">
                <span class="material-symbols-outlined">rule</span> 
                <span>Validación</span>
                <span class="nav-badge" *ngIf="getValidationBadge() > 0">{{ getValidationBadge() }}</span>
              </div>
            </a>
            <a *ngIf="authService.isAuthorized(['operativo', 'contable', 'gerente', 'superadmin'])" routerLink="/internal-docs" routerLinkActive="active" class="nav-link">
              <div class="nav-link-content">
                <span class="material-symbols-outlined">mail</span> 
                <span>Bandeja Interna</span>
                <span class="nav-badge" *ngIf="getInternalDocsBadge() > 0">{{ getInternalDocsBadge() }}</span>
              </div>
            </a>
            <a *ngIf="authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin'])" routerLink="/mandatos" routerLinkActive="active" class="nav-link">
              <div class="nav-link-content">
                <span class="material-symbols-outlined">contract</span> 
                <span>Revisión Mandatos</span>
                <span class="nav-badge" *ngIf="getMandatosBadge() > 0">{{ getMandatosBadge() }}</span>
              </div>
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin'])">
            <label>Administración</label>
            <a routerLink="/conciliacion-susuerte" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">fact_check</span> Conciliación Susuerte
            </a>
            <a *ngIf="authService.isAuthorized(['gerente', 'operativo', 'superadmin'])" routerLink="/clientes" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">group</span> Registro de Clientes
            </a>
            <a *ngIf="authService.isAuthorized(['gerente', 'operativo', 'superadmin'])" routerLink="/visitas" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">chat_bubble</span> Registro de Visita a Cliente
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin'])">
            <label>Sistema</label>
            <a routerLink="/logs" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">shield_person</span> Auditoría
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['operativo', 'superadmin'])">
            <label>Configuración Documentos</label>
            <a routerLink="/document-requests" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">checklist</span> Solicitudes Documentos
            </a>
            <a routerLink="/document-config" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">settings_applications</span> Config Requisitos
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.getActiveRole() === 'superadmin'">
            <label>Notificaciones</label>
            <a routerLink="/destinatarios" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">mail_lock</span> Destinatarios
            </a>
            <a routerLink="/notificaciones" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">notifications_active</span> Notificaciones
            </a>
            <a routerLink="/asignaciones" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">assignment_ind</span> Asignaciones
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.getActiveRole() === 'superadmin'">
            <label>Planificación</label>
            <a routerLink="/roadmap" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">route</span> Roadmap del Sistema
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.getActiveRole() === 'superadmin'">
            <label>Configuración</label>
            <a routerLink="/users" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">group</span> Gestión Usuarios
            </a>
            <a routerLink="/parameters" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">settings_applications</span> Parámetros
            </a>
            <a routerLink="/db-cleaner" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">restart_alt</span> Limpieza BD
            </a>
            <a routerLink="/configuraciones" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">key</span> Configuraciones
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['cliente'])">
            <label>Portal Cliente</label>
            <a routerLink="/client-upload" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">folder_shared</span> Mis Cargas
            </a>
            <a routerLink="/mandatos" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">description</span> Diligenciar Mandato
            </a>
            <a routerLink="/creditos" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">payments</span> Crédito Ordinario
            </a>
          </div>

        </nav>

        <div class="sidebar-footer">
          <div class="test-role-switcher">
            <span class="material-symbols-outlined">person_pin</span>
             <select [ngModel]="authService.getActiveRole()" (ngModelChange)="switchRole($event)">
               <option *ngFor="let r of authService.getAllRoles()" [value]="r">{{ r.split('_').join(' ') | titlecase }}</option>
               <option *ngIf="authService.isAuthorized(['superadmin'])" value="superadmin">Superadmin</option>
             </select>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <div class="main-wrapper">
        <!-- GLOBAL PENDING NOTIFICATION BAR -->
        <div class="pending-alert-bar" [ngClass]="{'critical': isExpiring()}" *ngIf="(getValidationBadge() > 0 || getInternalDocsBadge() > 0) && !router.url.includes('/validation') && !router.url.includes('/internal-docs')">
           <div class="alert-content">
              <span class="material-symbols-outlined pulse">{{ isExpiring() ? 'timer' : 'warning' }}</span>
              
              <!-- Message for Client Uploads -->
              <p *ngIf="getValidationBadge() > 0">
                Tienes <strong>{{ getValidationBadge() }}</strong> documentos de clientes pendientes.
              </p>

              <!-- Message for Internal Docs (Contable / Gerente) -->
              <p *ngIf="getInternalDocsBadge() > 0">
                Tienes <strong>{{ getInternalDocsBadge() }}</strong> documentos internos pendientes. 
                <span *ngIf="isExpiring()" style="color: #fee2e2; margin-left: 4px;">(¡Atención: Algunos están a punto de vencer!)</span>
              </p>
           </div>
           
           <button *ngIf="authService.isAuthorized(['operativo', 'gerente']) && (pendingCounts.operativo > 0 || pendingCounts.gerente > 0)" 
                   class="btn-alert-action" routerLink="/validation">Validar Clientes</button>
           
           <button *ngIf="(authService.getActiveRole() === 'contable' && pendingCounts.contable > 0) || (authService.getActiveRole() === 'gerente' && pendingCounts.internal_gerente > 0)"
                   class="btn-alert-action" routerLink="/internal-docs" style="margin-left: 10px;">Ver Bandeja Interna</button>
        </div>

        <header class="top-bar">
          <button class="burger-menu-btn" (click)="toggleSidebar()">
            <span class="material-symbols-outlined">menu</span>
          </button>
          <div class="page-title">
            <h3>Sistema de Gestión de Liquidez</h3>
          </div>
          <div class="user-actions">
            <div class="user-profile-wrapper" (click)="toggleUserMenu()">
              <div class="user-profile">
                <div class="avatar">{{ authService.getActiveRole()?.charAt(0)?.toUpperCase() }}</div>
                 <div class="user-info">
                   <span class="name">{{ authService.getUser()?.name || 'Usuario' }}</span>
                   <span class="status">Perfil: {{ authService.getActiveRole()?.split('_')?.join(' ') | titlecase }}</span>
                 </div>
                <span class="material-symbols-outlined expand-icon">expand_more</span>
              </div>
              
              <!-- Dropdown Menu -->
              <div class="user-dropdown" *ngIf="showUserMenu">
                <a routerLink="/profile" class="dropdown-item" (click)="showUserMenu = false">
                  <span class="material-symbols-outlined">person_settings</span> Mi Perfil
                </a>
                <div class="dropdown-divider"></div>
                <a (click)="logout()" class="dropdown-item logout">
                  <span class="material-symbols-outlined">logout</span> Cerrar Sesión
                </a>
              </div>
            </div>
          </div>
        </header>

        <main class="content-viewport">
          <router-outlet></router-outlet>
        </main>
      </div>
    </div>

    <!-- NON-AUTHENTICATED LAYOUT -->
    <div *ngIf="!authService.isAuthenticated() || !authService.getActiveRole()">
      <router-outlet></router-outlet>
    </div>
    `,
    styles: [`
      .app-layout { display: flex; height: 100vh; overflow: hidden; }
      .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid #E2E8F0; display: flex; flex-direction: column; z-index: 100; }
      .sidebar-header { padding: 2rem 1.5rem; display: flex; justify-content: center; border-bottom: 1px solid #F7FAFC; .app-logo { max-width: 180px; height: auto; } }
      .sidebar-nav { flex-grow: 1; padding: 1.5rem 1rem; overflow-y: auto; }
      .nav-section { margin-bottom: 2rem; label { display: block; font-size: 0.65rem; font-weight: 800; color: #A0AEC0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; padding-left: 0.75rem; } }
      
      .nav-link {
        display: flex; align-items: center; gap: 12px; padding: 0.75rem 1rem; color: #4A5568; text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 10px; transition: all 0.2s; margin-bottom: 4px;
        &:hover { background: #F7FAFC; color: var(--primary); }
        &.active { background: #EBF4FF; color: var(--primary); font-weight: 600; .material-symbols-outlined { color: var(--primary); font-variation-settings: 'FILL' 1; } }
        .material-symbols-outlined { font-size: 20px; color: #718096; }
      }

      .nav-link-content { display: flex; align-items: center; gap: 12px; width: 100%; position: relative; }
      .nav-badge { position: absolute; right: 0; background: var(--danger); color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 6px; min-width: 18px; text-align: center; box-shadow: 0 2px 4px rgba(229, 62, 62, 0.3); }

      /* Pending Alert Bar */
      .pending-alert-bar { 
        background: #fffbeb; /* Soft amber background */
        color: #b45309; /* Dark amber text for high contrast */
        border-bottom: 1px solid #fde68a;
        padding: 12px 2rem; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        transition: all 0.3s ease;
        &.critical { background: #dc2626; color: white; border-bottom-color: #b91c1c; }
        .alert-content { display: flex; align-items: center; gap: 12px; p { margin: 0; font-size: 0.95rem; font-weight: 500; } } 
        .btn-alert-action { background: #f59e0b; border: none; color: white; padding: 6px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2); &:hover { background: #d97706; } } 
        &.critical .btn-alert-action { background: white; color: #dc2626; box-shadow: 0 2px 4px rgba(0,0,0,0.1); &:hover { background: #fef2f2; } }
      }

      @keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }

      .sidebar-footer { padding: 1.5rem; border-top: 1px solid #F7FAFC; }
      .test-role-switcher { display: flex; align-items: center; gap: 8px; background: #F8FAFC; padding: 8px 12px; border-radius: 8px; border: 1px solid #E2E8F0; select { border: none; background: transparent; font-size: 0.75rem; font-weight: 600; color: #4A5568; outline: none; width: 100%; } .material-symbols-outlined { font-size: 18px; color: var(--secondary); } }

      .main-wrapper { flex-grow: 1; display: flex; flex-direction: column; background: #F4F7FE; overflow: hidden; }
      .top-bar { height: 70px; background: white; border-bottom: 1px solid #E2E8F0; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; h3 { margin: 0; font-size: 1.1rem; color: #1A202C; } }
      
      .user-profile-wrapper { position: relative; cursor: pointer; }
      .user-profile { display: flex; align-items: center; gap: 12px; padding: 6px 12px; border-radius: 12px; transition: background 0.2s; &:hover { background: #F8FAFC; } .expand-icon { font-size: 20px; color: var(--text-muted); } }
      .user-dropdown { position: absolute; top: calc(100% + 8px); right: 0; width: 200px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-color); padding: 8px; z-index: 1000; animation: fadeIn 0.2s ease-out; .dropdown-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--text-main); text-decoration: none; font-size: 0.9rem; font-weight: 500; border-radius: 8px; transition: all 0.2s; cursor: pointer; .material-symbols-outlined { font-size: 18px; color: var(--text-muted); } &:hover { background: #F4F7FE; color: var(--primary); .material-symbols-outlined { color: var(--primary); } } &.logout { color: var(--danger); &:hover { background: #FFF5F5; } .material-symbols-outlined { color: var(--danger); } } } .dropdown-divider { height: 1px; background: var(--border-color); margin: 6px 0; } }
      @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
      .avatar { width: 36px; height: 36px; background: var(--grad-primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
      .user-info { display: flex; flex-direction: column; .name { font-size: 0.85rem; font-weight: 700; color: #2D3748; } .status { font-size: 0.7rem; color: #48BB78; font-weight: 600; } }
      .content-viewport { flex-grow: 1; overflow-y: auto; padding: 2rem; }

      .pulse { animation: alertPulse 1.5s infinite; }
      @keyframes alertPulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

      .burger-menu-btn { display: none; background: none; border: none; cursor: pointer; color: #4A5568; outline: none; padding: 4px; display: none; align-items: center; justify-content: center; .material-symbols-outlined { font-size: 28px; } }
      .sidebar-backdrop { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); z-index: 99; animation: fadeIn 0.2s ease-out; }
      
      @media (max-width: 768px) {
        .sidebar { position: fixed; top: 0; left: -260px; height: 100vh; transition: left 0.3s ease; z-index: 100; }
        .sidebar.open { left: 0; }
        .burger-menu-btn { display: flex !important; margin-right: 12px; }
        .top-bar { padding: 0 1rem; }
        .content-viewport { padding: 1rem; }
        .pending-alert-bar { padding: 12px 1rem; flex-direction: column; gap: 8px; align-items: stretch; .alert-content { flex-direction: column; align-items: flex-start; text-align: left; } }
      }
    `]
})
export class AppComponent implements OnInit, OnDestroy {
  showUserMenu = false;
  isSidebarOpen = false;
  pendingCounts: { 
    operativo: number, 
    gerente: number, 
    contable: number, 
    internal_gerente: number, 
    internal_operativo?: number,
    expiring_contable?: number, 
    expiring_gerente?: number, 
    expiring_operativo?: number,
    pending_mandates?: number,
    total: number 
  } = { 
    operativo: 0, 
    gerente: 0, 
    contable: 0, 
    internal_gerente: 0, 
    internal_operativo: 0,
    expiring_contable: 0, 
    expiring_gerente: 0, 
    expiring_operativo: 0,
    pending_mandates: 0,
    total: 0 
  };
  private pollingSub!: Subscription;

  constructor(public authService: AuthService, public router: Router, private http: HttpClient) {}

  toggleSidebar() {
    this.isSidebarOpen = !this.isSidebarOpen;
  }

  ngOnInit() {
    this.checkPendingTasks();
    this.pollingSub = interval(30000).subscribe(() => {
      if (this.authService.isAuthenticated()) {
        this.checkPendingTasks();
      }
    });

    this.router.events.subscribe(() => {
      this.isSidebarOpen = false;
    });
  }

  ngOnDestroy() {
    if (this.pollingSub) this.pollingSub.unsubscribe();
  }

  checkPendingTasks() {
    if (!this.authService.isAuthenticated()) return;
    this.http.get<any>(`${environment.apiUrl}/uploads/pending-count`).subscribe({
      next: (res) => {
        this.pendingCounts = res;
      },
      error: () => {}
    });
  }

  getValidationBadge(): number {
    const role = this.authService.getActiveRole();
    if (role === 'operativo') return this.pendingCounts.operativo;
    if (role === 'gerente') return this.pendingCounts.gerente;
    if (role === 'superadmin') return this.pendingCounts.operativo + this.pendingCounts.gerente;
    return 0;
  }

  getInternalDocsBadge(): number {
    const role = this.authService.getActiveRole();
    if (role === 'contable') return this.pendingCounts.contable;
    if (role === 'gerente') return this.pendingCounts.internal_gerente;
    if (role === 'operativo') return this.pendingCounts.internal_operativo || 0;
    if (role === 'superadmin') return this.pendingCounts.contable + this.pendingCounts.internal_gerente + (this.pendingCounts.internal_operativo || 0);
    return 0;
  }

  getMandatosBadge(): number {
    const role = this.authService.getActiveRole();
    if (role === 'operativo' || role === 'superadmin') {
      return this.pendingCounts.pending_mandates || 0;
    }
    return 0;
  }

  isExpiring(): boolean {
    const role = this.authService.getActiveRole();
    if (role === 'contable') return (this.pendingCounts.expiring_contable || 0) > 0;
    if (role === 'gerente') return (this.pendingCounts.expiring_gerente || 0) > 0;
    if (role === 'operativo') return (this.pendingCounts.expiring_operativo || 0) > 0;
    if (role === 'superadmin') return ((this.pendingCounts.expiring_contable || 0) + (this.pendingCounts.expiring_gerente || 0) + (this.pendingCounts.expiring_operativo || 0)) > 0;
    return false;
  }

  toggleUserMenu() { this.showUserMenu = !this.showUserMenu; }

  switchRole(role: string) {
    this.authService.setActiveRole(role);
    this.showUserMenu = false;
    this.checkPendingTasks();
    if (role === 'cliente') {
      this.router.navigate(['/client-upload']);
    } else if (role === 'coordinador_comercial') {
      this.router.navigate(['/solicitudes-credito']);
    } else if (['oficial_cumplimiento', 'comite_credito', 'tesoreria'].includes(role)) {
      this.router.navigate(['/creditos']);
    } else {
      this.router.navigate(['/dashboard']);
    }
  }

  logout() {
    this.authService.logout();
    this.showUserMenu = false;
    this.router.navigate(['/login']);
  }
}
