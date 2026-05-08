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
      <!-- Sidebar -->
      <aside class="sidebar">
        <div class="sidebar-header">
          <img src="assets/logopsl.png" alt="Proseguir" class="app-logo">
        </div>

        <nav class="sidebar-nav">
          <div class="nav-section" *ngIf="authService.isAuthorized(['gerente', 'operativo'])">
            <label>Operaciones</label>
            <a routerLink="/dashboard" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a *ngIf="authService.isAuthorized(['operativo'])" routerLink="/sheets" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">database</span> Base de Datos
            </a>
            <a *ngIf="authService.isAuthorized(['operativo'])" routerLink="/upload" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">upload_file</span> Subir Operación
            </a>
            <a routerLink="/validation" routerLinkActive="active" class="nav-link">
              <div class="nav-link-content">
                <span class="material-symbols-outlined">rule</span> 
                <span>Validación</span>
                <span class="nav-badge" *ngIf="getPendingBadge() > 0">{{ getPendingBadge() }}</span>
              </div>
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['operativo'])">
            <label>Administración</label>
            <a routerLink="/contable" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">account_balance_wallet</span> Contable
            </a>
            <a routerLink="/planilla" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">agriculture</span> Planilla Fincas
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['gerente'])">
            <label>Sistema</label>
            <a routerLink="/logs" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">shield_person</span> Auditoría
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.getActiveRole() === 'superadmin'">
            <label>Configuración</label>
            <a routerLink="/users" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">group</span> Gestión Usuarios
            </a>
          </div>

          <div class="nav-section" *ngIf="authService.isAuthorized(['cliente'])">
            <label>Portal Cliente</label>
            <a routerLink="/client-upload" routerLinkActive="active" class="nav-link">
              <span class="material-symbols-outlined">folder_shared</span> Mis Cargas
            </a>
          </div>

        </nav>

        <div class="sidebar-footer">
          <div class="test-role-switcher">
            <span class="material-symbols-outlined">person_pin</span>
            <select [ngModel]="authService.getActiveRole()" (ngModelChange)="switchRole($event)">
              <option *ngFor="let r of authService.getAllRoles()" [value]="r">{{ r | titlecase }}</option>
              <option *ngIf="authService.isAuthorized(['superadmin'])" value="superadmin">Superadmin</option>
            </select>
          </div>
        </div>
      </aside>

      <!-- Main Content Area -->
      <div class="main-wrapper">
        <!-- GLOBAL PENDING NOTIFICATION BAR (HIGH VISIBILITY) -->
        <div class="pending-alert-bar" *ngIf="getPendingBadge() > 0 && !router.url.includes('/validation')">
           <div class="alert-content">
              <span class="material-symbols-outlined pulse">warning</span>
              <p>Tienes <strong>{{ getPendingBadge() }}</strong> documentos pendientes de {{ authService.getActiveRole() === 'operativo' ? 'validación' : 'aprobación' }}.</p>
           </div>
           <button class="btn-alert-action" routerLink="/validation">Gestionar Ahora</button>
        </div>

        <header class="top-bar">
          <div class="page-title">
            <h3>Sistema de Gestión de Liquidez</h3>
          </div>
          <div class="user-actions">
            <div class="user-profile-wrapper" (click)="toggleUserMenu()">
              <div class="user-profile">
                <div class="avatar">{{ authService.getActiveRole()?.charAt(0)?.toUpperCase() }}</div>
                <div class="user-info">
                  <span class="name">{{ authService.getUser()?.name || 'Usuario' }}</span>
                  <span class="status">Perfil: {{ authService.getActiveRole() | titlecase }}</span>
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
        background: #FFF5F5; border-bottom: 1px solid #FED7D7; padding: 10px 2rem; display: flex; justify-content: space-between; align-items: center; animation: slideDown 0.4s ease-out;
        .alert-content { display: flex; align-items: center; gap: 12px; p { margin: 0; font-size: 0.9rem; color: #C53030; } .material-symbols-outlined { color: #E53E3E; font-size: 20px; } }
        .btn-alert-action { background: #E53E3E; color: white; border: none; padding: 6px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; &:hover { background: #C53030; transform: translateY(-1px); } }
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
      .content-viewport { flex-grow: 1; overflow-y: auto; padding: 0; }

      .pulse { animation: alertPulse 1.5s infinite; }
      @keyframes alertPulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    `]
})
export class AppComponent implements OnInit, OnDestroy {
  showUserMenu = false;
  pendingCounts: { operativo: number, gerente: number, total: number } = { operativo: 0, gerente: 0, total: 0 };
  private pollingSub!: Subscription;

  constructor(public authService: AuthService, public router: Router, private http: HttpClient) {}

  ngOnInit() {
    this.checkPendingTasks();
    this.pollingSub = interval(30000).subscribe(() => {
      if (this.authService.isAuthenticated()) {
        this.checkPendingTasks();
      }
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

  getPendingBadge(): number {
    const role = this.authService.getActiveRole();
    if (role === 'operativo') return this.pendingCounts.operativo;
    if (role === 'gerente') return this.pendingCounts.gerente;
    if (role === 'superadmin') return this.pendingCounts.total;
    return 0;
  }

  toggleUserMenu() { this.showUserMenu = !this.showUserMenu; }

  switchRole(role: string) {
    this.authService.setActiveRole(role);
    this.showUserMenu = false;
    this.checkPendingTasks();
    if (role === 'cliente') {
      this.router.navigate(['/client-upload']);
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
