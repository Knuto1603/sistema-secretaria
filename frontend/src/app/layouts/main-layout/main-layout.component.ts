import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterOutlet, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../core/auth/services/auth.service';

/**
 * Interface para los items del menú lateral
 */
interface MenuItem {
  title: string;
  icon: string;
  route: string;
  roles: string[];
}

@Component({
  selector: 'app-main-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './main-layout.component.html',
  styleUrl: './main-layout.component.css'
})
export class MainLayoutComponent {
  authService = inject(AuthService);
  private router = inject(Router);

  // Acceso al signal del usuario
  user = this.authService.currentUser;
  
  // Estado de la sidebar: abierta en desktop, cerrada en mobile por defecto
  isSidebarOpen = signal(typeof window !== 'undefined' ? window.innerWidth >= 768 : true);

  // Configuración del Menú por Roles
  menuItems: MenuItem[] = [
    { 
      title: 'Inicio', 
      icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 
      route: '/app/home', 
      roles: ['admin', 'decano', 'secretaria', 'secretario academico', 'estudiante'] 
    },
    { 
      title: 'Programación Académica', 
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 
      route: '/app/programacion', 
      roles: ['admin', 'secretaria', 'secretario academico','estudiante'] 
    },
    {
      title: 'Solicitudes',
      icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
      route: '/app/solicitudes/list',
      roles: ['admin', 'secretaria', 'decano', 'secretario academico', 'estudiante']
    },
    { 
      title: 'Chatbot IA', 
      icon: 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z', 
      route: '/app/chatbot', 
      roles: ['admin', 'secretaria', 'secretario academico', 'estudiante']
    },
    {
      title: 'Analíticas',
      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
      route: '/app/analitica',
      roles: ['admin', 'decano', 'secretario academico']
    },
    {
      title: 'Configuración',
      icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
      route: '/app/configuracion',
      roles: ['admin', 'decano', 'secretaria', 'secretario academico']
    },
    {
      title: 'Panel Developer',
      icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
      route: '/app/developer',
      roles: []   // Solo developer (se filtra en filteredMenu)
    }
  ];


  get filteredMenu() {
    const isDev = this.authService.isDeveloper();

    return this.menuItems.filter(item => {
      // El Panel Developer solo se muestra al developer
      if (item.route === '/app/developer') return isDev;
      // El developer tiene acceso a todos los demás items
      if (isDev) return true;
      return item.roles.some(role => this.authService.hasRole(role));
    });
  }

  toggleSidebar() {
    this.isSidebarOpen.update(v => !v);
  }

  handleNavClick() {
    if (typeof window !== 'undefined' && window.innerWidth < 768) {
      this.isSidebarOpen.set(false);
    }
  }

  logout() {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login'])
    });
  }

  stopImpersonation(): void {
    // Restauramos la sesión original del developer localmente.
    // El token de impersonación queda en BD con nombre 'impersonation'
    // y puede ser revocado desde el panel developer si se desea.
    this.authService.stopImpersonation();
    this.router.navigate(['/app/developer/impersonation']);
  }
}