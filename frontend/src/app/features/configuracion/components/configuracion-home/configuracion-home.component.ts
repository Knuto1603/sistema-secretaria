import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

interface ConfigCard {
  title: string;
  description: string;
  icon: string;
  route: string;
  color: string;
}

@Component({
  selector: 'app-configuracion-home',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './configuracion-home.component.html'
})
export class ConfiguracionHomeComponent {
  constructor(private router: Router) {}

  cards: ConfigCard[] = [
    {
      title: 'Periodos Académicos',
      description: 'Gestiona los periodos académicos, activa o desactiva según corresponda.',
      icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
      route: '/app/configuracion/periodos',
      color: 'indigo'
    },
    {
      title: 'Tipos de Solicitud',
      description: 'Configura los diferentes tipos de solicitudes disponibles para los estudiantes.',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      route: '/app/configuracion/tipos-solicitud',
      color: 'emerald'
    },
    {
      title: 'Usuarios',
      description: 'Gestiona usuarios administrativos, estudiantes y sus roles en el sistema.',
      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
      route: '/app/configuracion/usuarios',
      color: 'amber'
    },
    {
      title: 'Planes de Estudio',
      description: 'Carga y gestiona la currícula de cada escuela profesional para filtrar la programación.',
      icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
      route: '/app/configuracion/plan-estudios',
      color: 'rose'
    },
    {
      title: 'Base de Conocimientos',
      description: 'Gestiona los artículos, documentos y plantillas que usa el asistente virtual para responder a los estudiantes.',
      icon: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
      route: '/app/configuracion/knowledge-base',
      color: 'violet'
    }
  ];

  navigateTo(route: string): void {
    this.router.navigate([route]);
  }

  getColorClasses(color: string): { bg: string; border: string; icon: string; hover: string } {
    const colors: Record<string, { bg: string; border: string; icon: string; hover: string }> = {
      indigo: {
        bg: 'bg-indigo-50',
        border: 'border-indigo-200',
        icon: 'text-indigo-600 bg-indigo-100',
        hover: 'hover:border-indigo-400 hover:shadow-indigo-100'
      },
      emerald: {
        bg: 'bg-emerald-50',
        border: 'border-emerald-200',
        icon: 'text-emerald-600 bg-emerald-100',
        hover: 'hover:border-emerald-400 hover:shadow-emerald-100'
      },
      amber: {
        bg: 'bg-amber-50',
        border: 'border-amber-200',
        icon: 'text-amber-600 bg-amber-100',
        hover: 'hover:border-amber-400 hover:shadow-amber-100'
      },
      rose: {
        bg: 'bg-rose-50',
        border: 'border-rose-200',
        icon: 'text-rose-600 bg-rose-100',
        hover: 'hover:border-rose-400 hover:shadow-rose-100'
      },
      violet: {
        bg: 'bg-violet-50',
        border: 'border-violet-200',
        icon: 'text-violet-600 bg-violet-100',
        hover: 'hover:border-violet-400 hover:shadow-violet-100'
      }
    };
    return colors[color] || colors['indigo'];
  }
}
