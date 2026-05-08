import { HttpInterceptorFn, HttpHeaders } from '@angular/common/http';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const rawToken = localStorage.getItem('auth_token');
  const token = rawToken ? rawToken.replace(/['"]+/g, '').trim() : null;

  if (token) {
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    });
    const cloned = req.clone({ headers });
    return next(cloned);
  }

  return next(req);
};
