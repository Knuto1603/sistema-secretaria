import { HttpInterceptorFn } from '@angular/common/http';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  // Obtenemos el token del localStorage
  const token = localStorage.getItem('access_token');

  // Si el token existe, clonamos la petición y añadimos el header Authorization
  if (token) {
    const cloned = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`
      }
    });
    return next(cloned);
  }

  // Si no hay token, enviamos la petición original
  return next(req);
};