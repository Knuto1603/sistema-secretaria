import { Routes } from '@angular/router';
import { ProgramacionActivaShellComponent } from './programacion-activa-shell.component';

export const PROGRAMACION_ACTIVA_ROUTES: Routes = [
  {
    path: '',
    component: ProgramacionActivaShellComponent,
    children: [
      {
        path: '',
        pathMatch: 'full',
        data: { soloLectura: true },
        loadComponent: () =>
          import('../registro/components/programacion-tabla/programacion-tabla.component')
            .then(m => m.ProgramacionTablaComponent)
      }
    ]
  }
];
