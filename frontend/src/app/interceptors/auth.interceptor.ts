import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const rawToken = localStorage.getItem('auth_token');
  const token = rawToken ? rawToken.replace(/['"]+/g, '').trim() : null;

  // `setHeaders` fusiona sobre los headers ya presentes en la request (p.ej.
  // X-Active-Role, agregado por los componentes por-llamada) — reemplazar
  // `headers` entero con un HttpHeaders nuevo los descartaba en TODA la app,
  // haciendo que el backend cayera siempre al fallback (primer rol del
  // usuario) e ignorara el rol activo seleccionado en el selector de perfil
  // para cualquier usuario con más de un rol (hallazgo Pre-QA, SCRUM-151).
  const cloned = token
    ? req.clone({
        setHeaders: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      })
    : req;

  return next(cloned).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        localStorage.clear();
        router.navigate(['/login'], {
          queryParams: { expired: '1' }
        });
      }
      return throwError(() => error);
    })
  );
};
