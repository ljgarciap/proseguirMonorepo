import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './services/auth.service';
import { environment } from '../environments/environment';
import { interval, Subscription } from 'rxjs';

interface NavItem {
  label: string;
  route: string;
  icon: string;
  roles: string[];
  badge?: () => number;
}

interface NavSection {
  title: string;
  roles: string[];
  items: NavItem[];
}

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
          <!-- SCRUM-160: bloques e ítems ordenados alfabéticamente, colapsables -->
          <div class="nav-section" *ngFor="let section of visibleSections()">
            <label (click)="toggleSection(section.title)">
              <span>{{ section.title }}</span>
              <span class="material-symbols-outlined nav-section-toggle" [class.collapsed]="isSectionCollapsed(section.title)">expand_more</span>
            </label>
            <ng-container *ngIf="!isSectionCollapsed(section.title)">
              <a *ngFor="let item of visibleItems(section)" [routerLink]="item.route" routerLinkActive="active" class="nav-link">
                <div class="nav-link-content">
                  <span class="material-symbols-outlined">{{ item.icon }}</span>
                  <span>{{ item.label }}</span>
                  <span class="nav-badge" *ngIf="item.badge && item.badge()! > 0">{{ item.badge!() }}</span>
                </div>
              </a>
            </ng-container>
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
      .nav-section {
        margin-bottom: 2rem;
        label { display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; font-size: 0.65rem; font-weight: 800; color: #A0AEC0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; padding-left: 0.75rem; padding-right: 0.75rem; }
        .nav-section-toggle { font-size: 16px; transition: transform 0.2s ease; transform: rotate(180deg); &.collapsed { transform: rotate(0deg); } }
      }

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

  // SCRUM-160: fuente de datos del menú lateral. El orden de declaración acá NO importa —
  // navSections se construye ya ordenado alfabéticamente (bloques por title, ítems por label)
  // en buildSortedNavSections(). Los arrays `roles` reproducen exactamente los *ngIf que había
  // antes por sección/ítem (ver commits previos) para no alterar la superficie de permisos.
  private readonly rawNavSections: NavSection[] = [
    {
      title: 'Operaciones',
      roles: ['gerente', 'operativo', 'contable', 'superadmin'],
      items: [
        { label: 'Dashboard', route: '/dashboard', icon: 'dashboard', roles: ['gerente', 'operativo', 'contable', 'superadmin'] },
        { label: 'Base de Datos', route: '/sheets', icon: 'database', roles: ['operativo'] },
        { label: 'Subir Operación', route: '/upload', icon: 'upload_file', roles: ['operativo'] },
        { label: 'Validación', route: '/validation', icon: 'rule', roles: ['operativo', 'gerente'], badge: () => this.getValidationBadge() },
        { label: 'Bandeja Interna', route: '/internal-docs', icon: 'mail', roles: ['operativo', 'contable', 'gerente', 'superadmin'], badge: () => this.getInternalDocsBadge() },
        { label: 'Revisión Mandatos', route: '/mandatos', icon: 'contract', roles: ['gerente', 'operativo', 'contable', 'superadmin'], badge: () => this.getMandatosBadge() },
      ],
    },
    {
      // SCRUM-153: sección propia de Crédito, ya alfabetizada internamente — se preserva.
      title: 'Crédito',
      roles: ['coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin', 'ingeniero'],
      items: [
        { label: 'Actas Comité de Crédito', route: '/actas-comite', icon: 'history_edu', roles: ['coordinador_comercial', 'superadmin'] },
        { label: 'Análisis Financiero', route: '/analisis-financiero', icon: 'finance', roles: ['coordinador_comercial', 'superadmin'] },
        { label: 'Crédito Ordinario', route: '/creditos', icon: 'payments', roles: ['coordinador_comercial', 'oficial_cumplimiento', 'comite_credito', 'operativo', 'tesoreria', 'gerente', 'superadmin'] },
        { label: 'Gestión de Créditos', route: '/gestion-creditos', icon: 'task_alt', roles: ['coordinador_comercial', 'superadmin'] },
        { label: 'Informe Técnico', route: '/informes-tecnicos', icon: 'engineering', roles: ['ingeniero', 'coordinador_comercial', 'superadmin'] },
        { label: 'Listas Restrictivas y SARLAFT', route: '/listas-sarlaft', icon: 'gavel', roles: ['oficial_cumplimiento', 'superadmin'] },
        { label: 'Registro Solicitud Crédito', route: '/solicitudes-credito', icon: 'assignment_turned_in', roles: ['coordinador_comercial', 'gerente', 'superadmin', 'operativo'] },
      ],
    },
    {
      title: 'Administración',
      roles: ['gerente', 'operativo', 'contable', 'superadmin', 'coordinador_comercial'],
      items: [
        { label: 'Conciliación Susuerte', route: '/conciliacion-susuerte', icon: 'fact_check', roles: ['gerente', 'operativo', 'contable', 'superadmin'] },
        { label: 'Registro de Clientes', route: '/clientes', icon: 'group', roles: ['gerente', 'operativo', 'superadmin', 'coordinador_comercial'] },
        { label: 'Registro de Visita a Cliente', route: '/visitas', icon: 'chat_bubble', roles: ['gerente', 'operativo', 'superadmin'] },
      ],
    },
    {
      title: 'Sistema',
      roles: ['gerente', 'operativo', 'contable', 'superadmin'],
      items: [
        // Auditoría no tenía *ngIf propio: hereda el permiso del bloque.
        { label: 'Auditoría', route: '/logs', icon: 'shield_person', roles: ['gerente', 'operativo', 'contable', 'superadmin'] },
      ],
    },
    {
      title: 'Configuración Documentos',
      roles: ['operativo', 'superadmin'],
      items: [
        // Ítems sin *ngIf propio: heredan el permiso del bloque.
        { label: 'Solicitudes Documentos', route: '/document-requests', icon: 'checklist', roles: ['operativo', 'superadmin'] },
        { label: 'Config Requisitos', route: '/document-config', icon: 'settings_applications', roles: ['operativo', 'superadmin'] },
      ],
    },
    {
      // El bloque original usaba getActiveRole() === 'superadmin'. isAuthorized(['superadmin'])
      // es equivalente: true solo cuando el rol activo es superadmin (ver AuthService.isAuthorized).
      title: 'Notificaciones',
      roles: ['superadmin'],
      items: [
        { label: 'Destinatarios', route: '/destinatarios', icon: 'mail_lock', roles: ['superadmin'] },
        { label: 'Notificaciones', route: '/notificaciones', icon: 'notifications_active', roles: ['superadmin'] },
        { label: 'Asignaciones', route: '/asignaciones', icon: 'assignment_ind', roles: ['superadmin'] },
      ],
    },
    {
      title: 'Planificación',
      roles: ['superadmin'],
      items: [
        { label: 'Roadmap del Sistema', route: '/roadmap', icon: 'route', roles: ['superadmin'] },
      ],
    },
    {
      title: 'Configuración',
      roles: ['superadmin'],
      items: [
        { label: 'Gestión Usuarios', route: '/users', icon: 'group', roles: ['superadmin'] },
        { label: 'Parámetros', route: '/parameters', icon: 'settings_applications', roles: ['superadmin'] },
        { label: 'Limpieza BD', route: '/db-cleaner', icon: 'restart_alt', roles: ['superadmin'] },
        { label: 'Configuraciones', route: '/configuraciones', icon: 'key', roles: ['superadmin'] },
        { label: 'Áreas de Aprobación', route: '/document-areas', icon: 'account_tree', roles: ['superadmin'] },
      ],
    },
    {
      title: 'Portal Cliente',
      roles: ['cliente'],
      items: [
        { label: 'Mis Cargas', route: '/client-upload', icon: 'folder_shared', roles: ['cliente'] },
        { label: 'Diligenciar Mandato', route: '/mandatos', icon: 'description', roles: ['cliente'] },
        { label: 'Mis Créditos', route: '/creditos', icon: 'payments', roles: ['cliente'] },
      ],
    },
  ];

  navSections: NavSection[] = this.buildSortedNavSections();

  // SCRUM-160: estado en memoria de bloques colapsados — no persiste entre sesiones a propósito
  // (siempre arranca expandido, igual que isSidebarOpen no persiste).
  private collapsedSections = new Set<string>();

  constructor(public authService: AuthService, public router: Router, private http: HttpClient) {}

  private buildSortedNavSections(): NavSection[] {
    return [...this.rawNavSections]
      .map(section => ({
        ...section,
        items: [...section.items].sort((a, b) => a.label.localeCompare(b.label, 'es')),
      }))
      .sort((a, b) => a.title.localeCompare(b.title, 'es'));
  }

  visibleSections(): NavSection[] {
    return this.navSections.filter(section => this.authService.isAuthorized(section.roles));
  }

  visibleItems(section: NavSection): NavItem[] {
    return section.items.filter(item => this.authService.isAuthorized(item.roles));
  }

  isSectionCollapsed(title: string): boolean {
    return this.collapsedSections.has(title);
  }

  toggleSection(title: string): void {
    if (this.collapsedSections.has(title)) {
      this.collapsedSections.delete(title);
    } else {
      this.collapsedSections.add(title);
    }
  }

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
    if (!this.authService.isAuthorized(['gerente', 'operativo', 'contable', 'superadmin'])) return;
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
    } else if (role === 'ingeniero') {
      this.router.navigate(['/informes-tecnicos']);
    } else if (role === 'oficial_cumplimiento') {
      // SCRUM-128: el trabajo del Oficial de Cumplimiento ya no ocurre
      // dentro de /creditos — vive en la bandeja dedicada.
      this.router.navigate(['/listas-sarlaft']);
    } else if (['comite_credito', 'tesoreria'].includes(role)) {
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
