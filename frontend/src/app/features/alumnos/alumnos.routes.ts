import { Routes } from '@angular/router';

export const ALUMNOS_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./pages/alumnos-page/alumnos-page.component').then(m => m.AlumnosPageComponent),
  },
];
