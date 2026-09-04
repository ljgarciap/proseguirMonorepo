import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const roleGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isAuthenticated()) {
    router.navigate(['/login']);
    return false;
  }

  // RBAC Fase 2 (docs/specs/rbac-fase2-enforcement.md): data['permission']
  // reemplaza data['roles'] — el gate compara contra el catálogo
  // paramétrico (vía AuthService.hasPermission) en vez de una lista de
  // roles hardcodeada acá.
  const permission = route.data['permission'] as string;

  if (authService.hasPermission(permission)) {
    return true;
  }

  // Redirect to appropriate home if not authorized for this specific route
  const role = authService.getActiveRole();
  if (role === 'cliente') {
    router.navigate(['/client-upload']);
  } else if (role === 'coordinador_comercial') {
    router.navigate(['/solicitudes-credito']);
  } else if (role === 'ingeniero') {
    router.navigate(['/informes-tecnicos']);
  } else if (role === 'oficial_cumplimiento') {
    // SCRUM-128: el trabajo del Oficial de Cumplimiento ya no ocurre
    // dentro de /creditos — vive en la bandeja dedicada.
    router.navigate(['/listas-sarlaft']);
  } else if (['comite_credito', 'tesoreria'].includes(role || '')) {
    router.navigate(['/creditos']);
  } else {
    router.navigate(['/dashboard']);
  }
  
  return false;
};
