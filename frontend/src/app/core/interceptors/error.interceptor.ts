import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { ErrorModalService } from '../services/error-modal.service';
import { AuthService } from '../auth/services/auth.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const errorModal = inject(ErrorModalService);
  const router = inject(Router);
  const authService = inject(AuthService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      switch (error.status) {
        case 403:
          errorModal.show({
            message: 'Usted no tiene permisos para realizar esta acción.',
            goBack: true,
          });
          break;

        case 401:
          // Token inválido o expirado → limpiar sesión y redirigir al login
          authService.clearLocalSession();
          router.navigate(['/login']);
          break;

        case 0:
          // Sin conexión al servidor
          errorModal.show({
            message: 'No se pudo conectar con el servidor. Verifique su conexión.',
            goBack: false,
          });
          break;

        case 500:
          errorModal.show({
            message: 'Ocurrió un error interno en el servidor. Intente nuevamente.',
            goBack: false,
          });
          break;
      }

      // Propagar el error para que los componentes también puedan manejarlo
      return throwError(() => error);
    })
  );
};
