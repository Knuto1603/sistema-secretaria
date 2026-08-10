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

  if (!authService.isAuthenticated()) {
    router.navigate(['/login']);
    return false;
  }

  // Si tiene una contraseña temporal pendiente de cambio, lo forzamos a Perfil
  // hasta que la actualice (excepto si ya va hacia esa misma ruta).
  if (authService.currentUser()?.must_change_password && !state.url.startsWith('/app/perfil')) {
    router.navigate(['/app/perfil'], { queryParams: { cambio_obligatorio: '1' } });
    return false;
  }

  return true;
};