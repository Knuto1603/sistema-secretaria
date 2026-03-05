import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RolService, Rol } from '@core/services/rol.service';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-roles-lista',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './roles-lista.component.html'
})
export class RolesListaComponent implements OnInit {
  private rolService = inject(RolService);

  roles = signal<Rol[]>([]);
  loading = signal(false);
  error = signal<string | null>(null);
  expandedRol = signal<number | null>(null);

  ngOnInit(): void {
    this.cargarRoles();
  }

  cargarRoles(): void {
    this.loading.set(true);
    this.error.set(null);

    this.rolService.getAll().subscribe({
      next: (roles) => {
        this.roles.set(roles);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Error al cargar los roles');
        this.loading.set(false);
      }
    });
  }

  toggleExpand(rolId: number): void {
    if (this.expandedRol() === rolId) {
      this.expandedRol.set(null);
    } else {
      this.expandedRol.set(rolId);
    }
  }

  getRolColor(rolName: string): string {
    const colors: Record<string, string> = {
      'admin': 'indigo',
      'developer': 'purple',
      'secretaria': 'emerald',
      'decano': 'amber',
      'secretario academico': 'cyan',
      'estudiante': 'slate'
    };
    return colors[rolName.toLowerCase()] || 'slate';
  }

  getRolIcon(rolName: string): string {
    const icons: Record<string, string> = {
      'admin': 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
      'developer': 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4',
      'secretaria': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      'decano': 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
      'secretario academico': 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
      'estudiante': 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'
    };
    return icons[rolName.toLowerCase()] || 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z';
  }

  getRolDescription(rolName: string): string {
    const descriptions: Record<string, string> = {
      'admin': 'Acceso completo a la gestión del sistema',
      'developer': 'Acceso total al sistema',
      'secretaria': 'Gestión de trámites y documentos',
      'decano': 'Autoridad máxima de la facultad',
      'secretario academico': 'Gestión académica institucional',
      'estudiante': 'Acceso a servicios estudiantiles'
    };
    return descriptions[rolName.toLowerCase()] || 'Rol del sistema';
  }
}
