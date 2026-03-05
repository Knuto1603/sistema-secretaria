import { Routes } from '@angular/router';

export const routes: Routes = [
    {
        path:'',
        loadComponent: () => import('./solicitudes/solicitudes.component').then(m => m.SolicitudesComponent)
    },
    {
        path:'list',
        loadComponent: () => import('./solicitud-lista/solicitud-lista.component').then(m => m.SolicitudListaComponent)
    },
    {
        path:'nueva/:id',
        loadComponent: () => import('./solicitud-form/solicitud-form.component').then(m => m.SolicitudFormComponent)
    },
    {
        path:'detalle/:id',
        loadComponent: () => import('./solicitud-detalle/solicitud-detalle.component').then(m => m.SolicitudDetalleComponent)
    }
];