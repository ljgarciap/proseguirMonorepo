import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const roleGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  
  const allowedRoles = route.data['roles'] as string[];
  
  if (!authService.isAuthenticated()) {
    router.navigate(['/login']);
    return false;
  }

  if (authService.isAuthorized(allowedRoles)) {
    return true;
  }

  // Redirect to appropriate home if not authorized for this specific route
  const role = authService.getActiveRole();
  if (role === 'cliente') {
    router.navigate(['/client-upload']);
  } else if (role === 'coordinador_comercial') {
    router.navigate(['/solicitudes-credito']);
  } else if (['oficial_cumplimiento', 'comite_credito', 'tesoreria'].includes(role || '')) {
    router.navigate(['/creditos']);
  } else {
    router.navigate(['/dashboard']);
  }
  
  return false;
};
