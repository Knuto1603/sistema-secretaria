import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./components/apertura-lista/apertura-lista.component').then(m => m.AperturaListaComponent)
  },
  {
    path: 'nueva',
    loadComponent: () => import('./components/apertura-form/apertura-form.component').then(m => m.AperturaFormComponent)
  }
];
