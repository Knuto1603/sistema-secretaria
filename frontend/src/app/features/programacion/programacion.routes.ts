import { Routes } from '@angular/router';
import { ProgramacionShellComponent } from './programacion-shell.component';

export const PROGRAMACION_ROUTES: Routes = [
  {
    path: '',
    component: ProgramacionShellComponent,
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('../registro/components/registro/registro.component').then(m => m.RegistroComponent)
      },
      {
        path: 'borradores',
        loadChildren: () =>
          import('../programacion-interactiva/programacion-interactiva.routes').then(m => m.PI_ROUTES)
      }
    ]
  }
];
