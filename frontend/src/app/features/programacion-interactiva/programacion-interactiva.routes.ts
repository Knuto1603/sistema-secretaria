import { Routes } from '@angular/router';

export const PI_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./components/pi-shell/pi-shell.component').then(m => m.PiShellComponent)
  },
  {
    path: ':id',
    loadComponent: () =>
      import('./components/pi-editor/pi-editor.component').then(m => m.PiEditorComponent)
  }
];
