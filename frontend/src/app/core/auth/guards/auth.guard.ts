import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Guard funcional para proteger rutas.
 * Verifica si el usuario está autenticado mediante el Signal del AuthService.
 */
export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Si el signal isAuthenticated es verdadero, permitimos el paso
  if (authService.isAuthenticated()) {
    return true;
  }

  // De lo contrario, redirigimos al login
  router.navigate(['/login']);
  return false;
};