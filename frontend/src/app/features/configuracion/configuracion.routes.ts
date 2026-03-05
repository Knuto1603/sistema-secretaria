import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    children: [
      {
        path: '',
        loadComponent: () => import('./components/configuracion-home/configuracion-home.component').then(m => m.ConfiguracionHomeComponent)
      },
      // Periodos
      {
        path: 'periodos',
        loadComponent: () => import('./components/periodos-lista/periodos-lista.component').then(m => m.PeriodosListaComponent)
      },
      {
        path: 'periodos/nuevo',
        loadComponent: () => import('./components/periodo-form/periodo-form.component').then(m => m.PeriodoFormComponent)
      },
      {
        path: 'periodos/editar/:id',
        loadComponent: () => import('./components/periodo-form/periodo-form.component').then(m => m.PeriodoFormComponent)
      },
      // Tipos de Solicitud
      {
        path: 'tipos-solicitud',
        loadComponent: () => import('./components/tipos-solicitud-lista/tipos-solicitud-lista.component').then(m => m.TiposSolicitudListaComponent)
      },
      {
        path: 'tipos-solicitud/nuevo',
        loadComponent: () => import('./components/tipo-solicitud-form/tipo-solicitud-form.component').then(m => m.TipoSolicitudFormComponent)
      },
      {
        path: 'tipos-solicitud/editar/:id',
        loadComponent: () => import('./components/tipo-solicitud-form/tipo-solicitud-form.component').then(m => m.TipoSolicitudFormComponent)
      },
      // Plan de Estudios
      {
        path: 'plan-estudios',
        loadComponent: () => import('./components/plan-estudios/plan-estudios.component').then(m => m.PlanEstudiosComponent)
      },
      // Usuarios
      {
        path: 'usuarios',
        loadComponent: () => import('./components/usuarios/usuarios-home/usuarios-home.component').then(m => m.UsuariosHomeComponent)
      },
      {
        path: 'usuarios/nuevo',
        loadComponent: () => import('./components/usuarios/administrativo-form/administrativo-form.component').then(m => m.AdministrativoFormComponent)
      },
      {
        path: 'usuarios/editar/:id',
        loadComponent: () => import('./components/usuarios/administrativo-form/administrativo-form.component').then(m => m.AdministrativoFormComponent)
      },
      // Base de Conocimientos
      {
        path: 'knowledge-base',
        loadComponent: () => import('./components/knowledge-base/kb-lista/kb-lista.component').then(m => m.KbListaComponent)
      },
      {
        path: 'knowledge-base/nuevo',
        loadComponent: () => import('./components/knowledge-base/kb-form/kb-form.component').then(m => m.KbFormComponent)
      },
      {
        path: 'knowledge-base/editar/:id',
        loadComponent: () => import('./components/knowledge-base/kb-form/kb-form.component').then(m => m.KbFormComponent)
      },
      {
        path: 'knowledge-base/documentos',
        loadComponent: () => import('./components/knowledge-base/kb-documents/kb-documents.component').then(m => m.KbDocumentsComponent)
      },
      {
        path: 'knowledge-base/analitica',
        loadComponent: () => import('./components/knowledge-base/kb-analytics/kb-analytics.component').then(m => m.KbAnalyticsComponent)
      }
    ]
  }
];
