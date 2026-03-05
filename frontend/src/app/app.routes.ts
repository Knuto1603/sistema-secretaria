import { Routes } from '@angular/router';
import { LoginComponent } from './core/auth/components/login/login.component';
import { MainLayoutComponent } from './layouts/main-layout/main-layout.component';
import { authGuard } from './core/auth/guards/auth.guard';
import { guestGuard } from './core/auth/guards/guest.guard';

/**
 * Configuración de rutas principal.
 * Se utiliza guestGuard para el login y authGuard para las rutas privadas.
 */
export const routes: Routes = [
  // =========================================================================
  // RUTAS PÚBLICAS (Autenticación)
  // =========================================================================
  {
    path: 'login',
    component: LoginComponent,
    canActivate: [guestGuard]
  },
  {
    path: 'activar-cuenta',
    loadComponent: () => import('./core/auth/components/activar-cuenta/activar-cuenta.component')
      .then(m => m.ActivarCuentaComponent),
    canActivate: [guestGuard]
  },
  {
    path: 'recuperar-password',
    loadComponent: () => import('./core/auth/components/recuperar-password/recuperar-password.component')
      .then(m => m.RecuperarPasswordComponent),
    canActivate: [guestGuard]
  },
  {
    path: 'app', 
    component: MainLayoutComponent,
    canActivate: [authGuard], // Si no ha iniciado sesión, lo redirige al login
    children: [
       { 
         path: 'home', 
         loadComponent: () => import('./features/home/components/home/home.component').then(m => m.HomeComponent) 
       },
       { 
         path: 'programacion', 
         loadComponent: () => import('./features/registro/components/registro/registro.component').then(m => m.RegistroComponent) 
       },
       { 
          path: 'solicitudes',
         loadChildren: () => import('./features/solicitudes/components/solicitudes.routes').then(m => m.routes),
       },
       { 
         path: 'chatbot', 
         loadComponent: () => import('./features/chatbot/components/chatbot/chatbot.component').then(m => m.ChatbotComponent) 
       },
       {
         path: 'analitica',
         loadComponent: () => import('./features/analitica/components/analitica/analitica.component').then(m => m.AnaliticaComponent)
       },
       {
         path: 'configuracion',
         loadChildren: () => import('./features/configuracion/configuracion.routes').then(m => m.routes)
       },
       {
         path: 'developer',
         loadChildren: () => import('./features/developer/developer.routes').then(m => m.DEVELOPER_ROUTES)
       },
       { path: '', redirectTo: 'home', pathMatch: 'full' }
    ]
  },
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/login' }
];