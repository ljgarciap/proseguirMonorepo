import { TestBed } from '@angular/core/testing';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({});
    service = TestBed.inject(AuthService);
  });

  afterEach(() => localStorage.clear());

  // --- login() ---

  describe('login()', () => {
    it('persiste token, usuario y roles en localStorage', () => {
      service.login('tok123', { name: 'Ana' }, ['operativo']);

      expect(localStorage.getItem('auth_token')).toBe('tok123');
      expect(JSON.parse(localStorage.getItem('user_data')!)).toEqual({ name: 'Ana' });
      expect(JSON.parse(localStorage.getItem('all_roles')!)).toEqual(['operativo']);
    });

    it('auto-asigna rol activo cuando el usuario tiene exactamente un rol', () => {
      service.login('tok', {}, ['gerente']);
      expect(service.getActiveRole()).toBe('gerente');
      expect(localStorage.getItem('active_role')).toBe('gerente');
    });

    it('no auto-asigna rol activo cuando el usuario tiene múltiples roles', () => {
      service.login('tok', {}, ['gerente', 'operativo']);
      expect(service.getActiveRole()).toBeNull();
    });

    it('emite el usuario actualizado a través de user$', () => {
      service.login('tok', { name: 'Luis' }, ['superadmin']);
      let emitted: any;
      service.user$.subscribe(u => emitted = u);
      expect(emitted).toEqual({ name: 'Luis' });
    });

    it('emite los roles actualizados a través de allRoles$', () => {
      service.login('tok', {}, ['superadmin', 'gerente']);
      let emitted: string[] = [];
      service.allRoles$.subscribe(r => emitted = r);
      expect(emitted).toEqual(['superadmin', 'gerente']);
    });
  });

  // --- setActiveRole() / getActiveRole() ---

  describe('setActiveRole() / getActiveRole()', () => {
    it('persiste el rol en localStorage y en el subject', () => {
      service.setActiveRole('contable');
      expect(service.getActiveRole()).toBe('contable');
      expect(localStorage.getItem('active_role')).toBe('contable');
    });

    it('emite el cambio de rol a través de activeRole$', () => {
      const emitted: (string | null)[] = [];
      service.activeRole$.subscribe(r => emitted.push(r));
      service.setActiveRole('operativo');
      expect(emitted).toContain('operativo');
    });
  });

  // --- getAllRoles() / getUser() ---

  describe('getAllRoles() / getUser()', () => {
    it('retorna el array de roles actual', () => {
      service.login('tok', {}, ['gerente', 'contable']);
      expect(service.getAllRoles()).toEqual(['gerente', 'contable']);
    });

    it('retorna el objeto usuario actual', () => {
      service.login('tok', { id: 42, name: 'Pedro' }, ['operativo']);
      expect(service.getUser()).toEqual({ id: 42, name: 'Pedro' });
    });
  });

  // --- isAuthenticated() ---

  describe('isAuthenticated()', () => {
    it('retorna false cuando no hay token', () => {
      expect(service.isAuthenticated()).toBeFalse();
    });

    it('retorna true cuando hay token en localStorage', () => {
      localStorage.setItem('auth_token', 'cualquier-token');
      expect(service.isAuthenticated()).toBeTrue();
    });
  });

  // --- isAuthorized() ---

  describe('isAuthorized()', () => {
    it('retorna false cuando no hay rol activo', () => {
      expect(service.isAuthorized(['operativo'])).toBeFalse();
    });

    it('superadmin tiene acceso a cualquier ruta', () => {
      service.setActiveRole('superadmin');
      expect(service.isAuthorized(['cliente'])).toBeTrue();
      expect(service.isAuthorized(['operativo', 'gerente'])).toBeTrue();
    });

    it('retorna true cuando el rol activo está en allowedRoles', () => {
      service.setActiveRole('gerente');
      expect(service.isAuthorized(['operativo', 'gerente'])).toBeTrue();
    });

    it('retorna false cuando el rol activo no está en allowedRoles', () => {
      service.setActiveRole('contable');
      expect(service.isAuthorized(['operativo', 'gerente'])).toBeFalse();
    });
  });

  // --- logout() ---

  describe('logout()', () => {
    it('limpia localStorage y resetea todos los subjects', () => {
      service.login('tok', { name: 'X' }, ['operativo']);
      service.setActiveRole('operativo');

      service.logout();

      expect(localStorage.getItem('auth_token')).toBeNull();
      expect(service.isAuthenticated()).toBeFalse();
      expect(service.getActiveRole()).toBeNull();
      expect(service.getAllRoles()).toEqual([]);
      expect(service.getUser()).toBeNull();
    });
  });
});
