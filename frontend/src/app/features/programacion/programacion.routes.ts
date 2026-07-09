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
        redirectTo: 'borradores'
      },
      {
        path: 'borradores',
        loadChildren: () =>
          import('../programacion-interactiva/programacion-interactiva.routes').then(m => m.PI_ROUTES)
      },
      {
        path: 'modificaciones',
        children: [
          {
            path: '',
            loadComponent: () =>
              import('./components/modificaciones-historial/modificaciones-historial.component')
                .then(m => m.ModificacionesHistorialComponent)
          },
          {
            path: ':borrador_id',
            loadComponent: () =>
              import('./components/modificaciones-detalle/modificaciones-detalle.component')
                .then(m => m.ModificacionesDetalleComponent)
          }
        ]
      },
      {
        path: 'generar-documentos',
        loadComponent: () =>
          import('./components/generar-documentos-wizard/generar-documentos-wizard.component')
            .then(m => m.GenerarDocumentosWizardComponent)
      }
    ]
  }
];
