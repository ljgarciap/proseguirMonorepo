import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { setRoleLabelsCache } from '../shared/role-label.util';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private safeParse(key: string, defaultValue: any): any {
    const data = localStorage.getItem(key);
    if (!data || data === 'undefined' || data === 'null') {
      return defaultValue;
    }
    try {
      return JSON.parse(data);
    } catch (e) {
      return defaultValue;
    }
  }

  private activeRoleSubject = new BehaviorSubject<string | null>(localStorage.getItem('active_role'));
  private allRolesSubject = new BehaviorSubject<string[]>(this.safeParse('all_roles', []));
  private userSubject = new BehaviorSubject<any>(this.safeParse('user_data', null));
  // RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md): unión de permisos
  // de todos los roles del usuario, devuelta por /api/login y /api/me —
  // roleGuard la usa en vez de comparar contra data.roles hardcodeado.
  private permissionsSubject = new BehaviorSubject<string[]>(this.safeParse('permissions', []));
  // SCRUM-346 (seguimiento): mapa slug → nombre de TODO el catálogo de
  // roles, sembrado desde login()/me() (AuthController::etiquetasDeRoles())
  // — única fuente de verdad para mostrar el nombre de un rol, ver
  // shared/role-label.util.ts. Se siembra la caché del util también en el
  // constructor (no solo en login()) para que getRoleLabel() funcione
  // inmediatamente tras un refresh de página, antes de cualquier llamada
  // nueva al backend.
  private roleLabelsSubject = new BehaviorSubject<Record<string, string>>(this.safeParse('role_labels', {}));

  public activeRole$: Observable<string | null> = this.activeRoleSubject.asObservable();
  public allRoles$: Observable<string[]> = this.allRolesSubject.asObservable();
  public user$: Observable<any> = this.userSubject.asObservable();
  public permissions$: Observable<string[]> = this.permissionsSubject.asObservable();
  public roleLabels$: Observable<Record<string, string>> = this.roleLabelsSubject.asObservable();

  constructor() {
    setRoleLabelsCache(this.roleLabelsSubject.value);
  }

  login(token: string, user: any, roles: string[], permissions: string[] = [], roleLabels: Record<string, string> = {}): void {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user_data', JSON.stringify(user));
    localStorage.setItem('all_roles', JSON.stringify(roles));
    localStorage.setItem('permissions', JSON.stringify(permissions));
    localStorage.setItem('role_labels', JSON.stringify(roleLabels));

    this.userSubject.next(user);
    this.allRolesSubject.next(roles);
    this.permissionsSubject.next(permissions);
    this.setRoleLabels(roleLabels);

    // If only one role, set it as active immediately
    if (roles && roles.length === 1) {
      this.setActiveRole(roles[0]);
    }
  }

  /**
   * SCRUM-346 (seguimiento): actualiza la caché de nombres de rol —
   * llamado desde login() y, si en el futuro se agrega, desde cualquier
   * refresco vía /api/me. Separado de login() para eso.
   */
  setRoleLabels(roleLabels: Record<string, string>): void {
    localStorage.setItem('role_labels', JSON.stringify(roleLabels));
    this.roleLabelsSubject.next(roleLabels);
    setRoleLabelsCache(roleLabels);
  }

  getRoleLabels(): Record<string, string> {
    return this.roleLabelsSubject.value;
  }

  getPermissions(): string[] {
    return this.permissionsSubject.value;
  }

  /**
   * RBAC Fase 2: true si el usuario tiene la clave de permiso pedida.
   * superadmin siempre pasa (mismo bypass que CheckPermission en backend
   * y que isAuthorized() ya tenía para roles) — no depende de que el
   * catálogo le haya asignado esa clave explícitamente.
   */
  hasPermission(permission: string): boolean {
    if (this.getActiveRole() === 'superadmin') return true;
    return this.getPermissions().includes(permission);
  }

  setActiveRole(role: string): void {
    localStorage.setItem('active_role', role);
    this.activeRoleSubject.next(role);
  }

  getActiveRole(): string | null {
    return this.activeRoleSubject.value;
  }

  getAllRoles(): string[] {
    return this.allRolesSubject.value;
  }

  getUser(): any {
    return this.userSubject.value;
  }

  /**
   * SCRUM-161: actualiza los datos del usuario en memoria/localStorage tras
   * un cambio de perfil (nombre, duración de sesión), sin requerir un
   * nuevo login.
   */
  setUser(user: any): void {
    localStorage.setItem('user_data', JSON.stringify(user));
    this.userSubject.next(user);
  }

  isAuthenticated(): boolean {
    return !!localStorage.getItem('auth_token');
  }

  logout(): void {
    localStorage.clear();
    this.activeRoleSubject.next(null);
    this.allRolesSubject.next([]);
    this.userSubject.next(null);
    this.permissionsSubject.next([]);
    // roleLabelsSubject NO se limpia a propósito: son nombres de rol del
    // catálogo general (no datos de la sesión ni del usuario), y dejarlos
    // en memoria evita que la próxima pantalla de login (selector de
    // perfil, si el usuario tiene varios roles) muestre el fallback
    // genérico mientras espera la respuesta del próximo login().
  }

  isAuthorized(allowedRoles: string[]): boolean {
    const currentRole = this.getActiveRole();
    if (!currentRole) return false;
    if (currentRole === 'superadmin') return true;
    return allowedRoles.includes(currentRole);
  }
}
