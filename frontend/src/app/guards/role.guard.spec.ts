import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { provideRouter } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { roleGuard } from './role.guard';

describe('roleGuard', () => {
  let authSpy: jasmine.SpyObj<AuthService>;
  let router: Router;

  const route = (permission: string): ActivatedRouteSnapshot =>
    ({ data: { permission } } as any);

  const state = {} as RouterStateSnapshot;

  const run = (permission: string) =>
    TestBed.runInInjectionContext(() => roleGuard(route(permission), state));

  beforeEach(() => {
    authSpy = jasmine.createSpyObj('AuthService', [
      'isAuthenticated',
      'hasPermission',
      'getActiveRole'
    ]);

    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: authSpy }
      ]
    });

    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
  });

  // --- Sin autenticación ---

  it('redirige a /login y retorna false cuando no está autenticado', () => {
    authSpy.isAuthenticated.and.returnValue(false);

    expect(run('upload')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  // --- Autenticado y autorizado ---

  it('retorna true cuando está autenticado y tiene el permiso', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(true);

    expect(run('upload')).toBeTrue();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  // --- Autenticado pero sin el permiso — redirecciones por rol ---

  it('redirige cliente a /client-upload', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('cliente');

    expect(run('users')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/client-upload']);
  });

  it('redirige coordinador_comercial a /solicitudes-credito', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('coordinador_comercial');

    expect(run('users')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/solicitudes-credito']);
  });

  it('redirige ingeniero a /informes-tecnicos', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('ingeniero');

    expect(run('users')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/informes-tecnicos']);
  });

  ['comite_credito', 'tesoreria'].forEach(rol => {
    it(`redirige ${rol} a /creditos`, () => {
      authSpy.isAuthenticated.and.returnValue(true);
      authSpy.hasPermission.and.returnValue(false);
      authSpy.getActiveRole.and.returnValue(rol);

      expect(run('users')).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/creditos']);
    });
  });

  // SCRUM-128: el trabajo del Oficial de Cumplimiento ya no ocurre dentro de
  // /creditos — vive en la bandeja dedicada de Listas Restrictivas y SARLAFT.
  it('redirige oficial_cumplimiento a /listas-sarlaft', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('oficial_cumplimiento');

    expect(run('users')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/listas-sarlaft']);
  });

  it('redirige cualquier otro rol a /dashboard', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('gerente');

    expect(run('users')).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
  });

  // --- Superadmin pasa siempre (AuthService.hasPermission ya maneja el bypass) ---

  it('superadmin pasa cualquier guard (hasPermission devuelve true)', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.hasPermission.and.returnValue(true); // AuthService ya maneja el bypass de superadmin

    expect(run('client-upload')).toBeTrue();
  });
});
