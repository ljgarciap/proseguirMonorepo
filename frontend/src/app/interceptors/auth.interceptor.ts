import { HttpInterceptorFn, HttpHeaders, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const rawToken = localStorage.getItem('auth_token');
  const token = rawToken ? rawToken.replace(/['"]+/g, '').trim() : null;

  const cloned = token
    ? req.clone({
        headers: new HttpHeaders({
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        })
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
