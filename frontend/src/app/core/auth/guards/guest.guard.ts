import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Guard para prevenir que usuarios autenticados accedan a rutas de invitados (como Login).
 */
export const guestGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  // Si el usuario ya está autenticado, lo mandamos al home
  if (authService.isAuthenticated()) {
    router.navigate(['/app/home']);
    return false;
  }

  // Si no está autenticado, permitimos que vea el Login
  return true;
};