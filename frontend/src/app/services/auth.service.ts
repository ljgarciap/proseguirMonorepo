import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

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
  
  public activeRole$: Observable<string | null> = this.activeRoleSubject.asObservable();
  public allRoles$: Observable<string[]> = this.allRolesSubject.asObservable();
  public user$: Observable<any> = this.userSubject.asObservable();

  constructor() {}

  login(token: string, user: any, roles: string[]): void {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('user_data', JSON.stringify(user));
    localStorage.setItem('all_roles', JSON.stringify(roles));
    
    this.userSubject.next(user);
    this.allRolesSubject.next(roles);

    // If only one role, set it as active immediately
    if (roles && roles.length === 1) {
      this.setActiveRole(roles[0]);
    }
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

  isAuthenticated(): boolean {
    return !!localStorage.getItem('auth_token');
  }

  logout(): void {
    localStorage.clear();
    this.activeRoleSubject.next(null);
    this.allRolesSubject.next([]);
    this.userSubject.next(null);
  }

  isAuthorized(allowedRoles: string[]): boolean {
    const currentRole = this.getActiveRole();
    if (!currentRole) return false;
    if (currentRole === 'superadmin') return true;
    return allowedRoles.includes(currentRole);
  }
}
