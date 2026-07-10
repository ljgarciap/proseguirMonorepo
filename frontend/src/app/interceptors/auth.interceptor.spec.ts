import { TestBed } from '@angular/core/testing';
import { HttpClient } from '@angular/common/http';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { Router } from '@angular/router';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let httpMock: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    localStorage.clear();

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([])
      ]
    });

    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    spyOn(router, 'navigate');
  });

  afterEach(() => {
    httpMock.verify();
    localStorage.clear();
  });

  // --- Inyección de headers ---

  it('agrega Authorization y Accept cuando hay token', () => {
    localStorage.setItem('auth_token', 'mitoken123');

    http.get('/api/ping').subscribe();

    const req = httpMock.expectOne('/api/ping');
    expect(req.request.headers.get('Authorization')).toBe('Bearer mitoken123');
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush({});
  });

  it('limpia comillas del token antes de enviarlo', () => {
    localStorage.setItem('auth_token', '"token-con-comillas"');

    http.get('/api/ping').subscribe();

    const req = httpMock.expectOne('/api/ping');
    expect(req.request.headers.get('Authorization')).toBe('Bearer token-con-comillas');
    req.flush({});
  });

  it('no agrega Authorization cuando no hay token', () => {
    http.get('/api/ping').subscribe();

    const req = httpMock.expectOne('/api/ping');
    expect(req.request.headers.get('Authorization')).toBeNull();
    req.flush({});
  });

  // --- Manejo de 401 ---

  it('limpia localStorage y redirige a /login?expired=1 en 401', () => {
    localStorage.setItem('auth_token', 'tok');
    localStorage.setItem('user_data', '{"name":"X"}');

    http.get('/api/protegido').subscribe({ error: () => {} });

    const req = httpMock.expectOne('/api/protegido');
    req.flush('No autorizado', { status: 401, statusText: 'Unauthorized' });

    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(
      ['/login'],
      { queryParams: { expired: '1' } }
    );
  });

  it('no redirige en errores distintos a 401', () => {
    http.get('/api/falla').subscribe({ error: () => {} });

    const req = httpMock.expectOne('/api/falla');
    req.flush('Server Error', { status: 500, statusText: 'Internal Server Error' });

    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('re-lanza el error para que el subscriber lo maneje', (done) => {
    http.get('/api/falla').subscribe({
      error: err => {
        expect(err.status).toBe(403);
        done();
      }
    });

    httpMock.expectOne('/api/falla').flush('Forbidden', { status: 403, statusText: 'Forbidden' });
  });
});
