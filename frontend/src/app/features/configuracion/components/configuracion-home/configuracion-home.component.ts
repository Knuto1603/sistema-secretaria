import { Component, inject } from '@angular/core';
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
  private router = inject(Router);

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
      title: 'Grupos Horario',
      description: 'Configura las plantillas horarias G1–G14 con los días y horas de cada grupo.',
      icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      route: '/app/configuracion/horarios',
      color: 'indigo'
    },
    {
      title: 'Pabellones y Aulas',
      description: 'Administra los pabellones e infraestructura de aulas disponibles para la programación.',
      icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
      route: '/app/configuracion/aulas',
      color: 'slate'
    },
    {
      title: 'Departamentos',
      description: 'Gestiona los departamentos académicos y sus prefijos de código para auto-asignar cursos.',
      icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
      route: '/app/configuracion/departamentos',
      color: 'orange'
    },
    {
      title: 'Cursos por Departamento',
      description: 'Asigna o reasigna manualmente cada curso a su departamento académico.',
      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
      route: '/app/configuracion/cursos-departamento',
      color: 'teal'
    },
    {
      title: 'Información Institucional',
      description: 'Configura los datos institucionales que aparecen en los oficios generados automáticamente.',
      icon: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
      route: '/app/configuracion/institucional',
      color: 'cyan'
    },
    {
      title: 'Plantillas Word',
      description: 'Descarga, edita en Word y sube las plantillas usadas para generar los oficios de programación académica.',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      route: '/app/configuracion/plantillas',
      color: 'orange'
    },
    {
      title: 'Base de Conocimientos',
      description: 'Gestiona los artículos, documentos y plantillas que usa el asistente virtual para responder a los estudiantes.',
      icon: 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
      route: '/app/configuracion/knowledge-base',
      color: 'indigo'
    },
    {
      title: 'Plantillas de Modificación',
      description: 'Sube y gestiona las 4 plantillas DOCX usadas para generar los oficios de modificaciones de programación.',
      icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
      route: '/app/configuracion/plantillas-modificacion',
      color: 'purple'
    }
  ];

  navigateTo(route: string): void {
    this.router.navigate([route]);
  }

  getColorClasses(color: string): { bg: string; border: string; icon: string; hover: string } {
    const iconColors: Record<string, string> = {
      indigo:  'text-indigo-600 bg-indigo-50',
      emerald: 'text-emerald-600 bg-emerald-50',
      amber:   'text-amber-600 bg-amber-50',
      rose:    'text-rose-600 bg-rose-50',
      slate:   'text-slate-600 bg-slate-100',
      orange:  'text-slate-600 bg-slate-100',
      teal:    'text-slate-600 bg-slate-100',
      cyan:    'text-slate-600 bg-slate-100',
      purple:  'text-slate-600 bg-slate-100',
    };
    return {
      bg:     'bg-white',
      border: 'border-slate-200',
      icon:   iconColors[color] || 'text-slate-600 bg-slate-100',
      hover:  'hover:border-slate-300',
    };
  }
}
