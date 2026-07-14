import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

interface DevCard {
  title: string;
  description: string;
  icon: string;
  route: string;
  color: string;
}

@Component({
  selector: 'app-developer-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './developer-home.component.html',
})
export class DeveloperHomeComponent {
  constructor(private router: Router) {}

  cards: DevCard[] = [
    {
      title: 'Health Panel',
      description: 'Estado del servidor: base de datos, disco, versiones y entorno.',
      icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      route: '/app/developer/health',
      color: 'emerald',
    },
    {
      title: 'Activity Log',
      description: 'Registro de toda la actividad de usuarios en el sistema.',
      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
      route: '/app/developer/activity',
      color: 'indigo',
    },
    {
      title: 'Email Viewer',
      description: 'Visualiza los correos OTP enviados a los estudiantes.',
      icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
      route: '/app/developer/emails',
      color: 'indigo',
    },
    {
      title: 'System Settings',
      description: 'Configuraciones clave-valor editables del sistema.',
      icon: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
      route: '/app/developer/settings',
      color: 'amber',
    },
    {
      title: 'Maintenance',
      description: 'Limpiar caché, vaciar logs y herramientas de mantenimiento.',
      icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
      route: '/app/developer/maintenance',
      color: 'orange',
    },
    {
      title: 'API Explorer',
      description: 'Visualiza todas las rutas API registradas en el sistema.',
      icon: 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
      route: '/app/developer/api-explorer',
      color: 'indigo',
    },
    {
      title: 'Impersonación',
      description: 'Inicia sesión como cualquier usuario para depuración.',
      icon: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
      route: '/app/developer/impersonation',
      color: 'rose',
    },
    {
      title: 'PDF Debug',
      description: 'Inspecciona el texto crudo que extrae smalot/pdfparser de un PDF.',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      route: '/app/developer/pdf-debug',
      color: 'amber',
    },
    {
      title: 'Base de Datos',
      description: 'Descarga un backup completo de la BD o restaura desde un archivo .sql.',
      icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
      route: '/app/developer/database',
      color: 'rose',
    },
    {
      title: 'Error Logs',
      description: 'Visualiza y filtra los errores de Laravel sin necesidad de SSH ni tail.',
      icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
      route: '/app/developer/error-logs',
      color: 'rose',
    },
  ];

  navigateTo(route: string): void {
    this.router.navigate([route]);
  }

  getColorClasses(color: string): { bg: string; border: string; icon: string; hover: string } {
    const map: Record<string, { bg: string; border: string; icon: string; hover: string }> = {
      emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', icon: 'text-emerald-600 bg-emerald-100', hover: 'hover:border-emerald-400 hover:shadow-emerald-100' },
      indigo:  { bg: 'bg-indigo-50',  border: 'border-indigo-200',  icon: 'text-indigo-600 bg-indigo-100',   hover: 'hover:border-indigo-400 hover:shadow-indigo-100' },
      amber:   { bg: 'bg-amber-50',   border: 'border-amber-200',   icon: 'text-amber-600 bg-amber-100',     hover: 'hover:border-amber-400 hover:shadow-amber-100' },
      orange:  { bg: 'bg-orange-50',  border: 'border-orange-200',  icon: 'text-orange-600 bg-orange-100',   hover: 'hover:border-orange-400 hover:shadow-orange-100' },
      rose:    { bg: 'bg-rose-50',    border: 'border-rose-200',    icon: 'text-rose-600 bg-rose-100',       hover: 'hover:border-rose-400 hover:shadow-rose-100' },
    };
    return map[color] ?? map['emerald'];
  }
}
