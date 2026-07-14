import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { provideRouter } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { roleGuard } from './role.guard';

describe('roleGuard', () => {
  let authSpy: jasmine.SpyObj<AuthService>;
  let router: Router;

  const route = (roles: string[]): ActivatedRouteSnapshot =>
    ({ data: { roles } } as any);

  const state = {} as RouterStateSnapshot;

  const run = (roles: string[]) =>
    TestBed.runInInjectionContext(() => roleGuard(route(roles), state));

  beforeEach(() => {
    authSpy = jasmine.createSpyObj('AuthService', [
      'isAuthenticated',
      'isAuthorized',
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

    expect(run(['operativo'])).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  // --- Autenticado y autorizado ---

  it('retorna true cuando está autenticado y autorizado', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(true);

    expect(run(['operativo'])).toBeTrue();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  // --- Autenticado pero no autorizado — redirecciones por rol ---

  it('redirige cliente a /client-upload', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('cliente');

    expect(run(['superadmin'])).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/client-upload']);
  });

  it('redirige coordinador_comercial a /solicitudes-credito', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('coordinador_comercial');

    expect(run(['superadmin'])).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/solicitudes-credito']);
  });

  it('redirige ingeniero a /informes-tecnicos', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('ingeniero');

    expect(run(['superadmin'])).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/informes-tecnicos']);
  });

  ['oficial_cumplimiento', 'comite_credito', 'tesoreria'].forEach(rol => {
    it(`redirige ${rol} a /creditos`, () => {
      authSpy.isAuthenticated.and.returnValue(true);
      authSpy.isAuthorized.and.returnValue(false);
      authSpy.getActiveRole.and.returnValue(rol);

      expect(run(['superadmin'])).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/creditos']);
    });
  });

  it('redirige cualquier otro rol a /dashboard', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(false);
    authSpy.getActiveRole.and.returnValue('gerente');

    expect(run(['superadmin'])).toBeFalse();
    expect(router.navigate).toHaveBeenCalledWith(['/dashboard']);
  });

  // --- Superadmin pasa siempre ---

  it('superadmin pasa cualquier guard (isAuthorized devuelve true)', () => {
    authSpy.isAuthenticated.and.returnValue(true);
    authSpy.isAuthorized.and.returnValue(true); // AuthService ya maneja esto

    expect(run(['cliente'])).toBeTrue();
  });
});
