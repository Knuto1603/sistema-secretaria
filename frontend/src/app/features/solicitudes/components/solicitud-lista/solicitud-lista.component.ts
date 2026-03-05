import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService, Solicitud, PaginatedResponse } from '../../services/solicitud.service';
import { ProgramacionService, Programacion } from '../../../registro/services/programacion.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

@Component({
  selector: 'app-solicitud-lista',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AppTableComponent,
    AppBadgeComponent,
    AppButtonComponent,
    PaginationComponent
  ],
  templateUrl: './solicitud-lista.component.html'
})
export class SolicitudListaComponent implements OnInit {
  private solicitudService = inject(SolicitudService);
  private programacionService = inject(ProgramacionService);
  private periodoService = inject(PeriodoService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  public authService = inject(AuthService);

  // Estado reactivo
  solicitudes = signal<Solicitud[]>([]);
  loading = signal(false);
  paginationData = signal<PaginatedResponse<Solicitud> | null>(null);

  // Filtros
  searchTerm = signal('');
  estadoFiltro = signal('');
  programacionIdFiltro = signal<string | null>(null);
  currentPage = signal(1);
  perPage = signal(10);

  // Info del curso cuando se filtra por programación
  cursoFiltrado = signal<string | null>(null);

  // Lista de programaciones para el selector
  programaciones = signal<Programacion[]>([]);
  loadingProgramaciones = signal(false);

  // Estados disponibles para filtro
  estados = [
    { value: '', label: 'Todos los estados' },
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'en_revision', label: 'En Revisión' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'rechazada', label: 'Rechazada' }
  ];

  // Detectar si es admin/secretaria/decano
  esAdmin = computed(() => {
    return this.authService.hasRole('admin') ||
           this.authService.hasRole('secretaria') ||
           this.authService.hasRole('decano') ||
           this.authService.hasRole('secretario academico');
  });

  // Columnas dinámicas según rol
  columnas = computed<TableColumn[]>(() => {
    const cols: TableColumn[] = [
      { key: 'fecha', label: 'Fecha' },
      { key: 'tramite', label: 'Trámite / Curso' },
      { key: 'estado', label: 'Estado' }
    ];

    if (this.esAdmin()) {
      cols.splice(1, 0, { key: 'estudiante', label: 'Estudiante' });
    }

    return cols;
  });

  ngOnInit(): void {
    // Cargar programaciones si es admin
    if (this.esAdmin()) {
      this.cargarProgramaciones();
    }

    // Leer query params para filtrar por programación
    this.route.queryParams.subscribe(params => {
      const programacionId = params['programacion_id'];
      if (programacionId) {
        this.programacionIdFiltro.set(programacionId);
      }
      this.cargarDatos();
    });
  }

  cargarProgramaciones(): void {
    this.loadingProgramaciones.set(true);

    // Primero obtener el periodo activo
    this.periodoService.getPeriodoActivo().subscribe({
      next: (periodo: Periodo | null) => {
        if (periodo) {
          // Cargar programaciones del periodo activo (todas, luego filtrar las llenas)
          this.programacionService.getProgramacion(1, '', 500, periodo.id).subscribe({
            next: (res) => {
              // Filtrar solo los cursos llenos
              const llenos = res.data.filter(p => p.esta_lleno);
              this.programaciones.set(llenos);
              this.loadingProgramaciones.set(false);
            },
            error: () => this.loadingProgramaciones.set(false)
          });
        } else {
          this.loadingProgramaciones.set(false);
        }
      },
      error: () => this.loadingProgramaciones.set(false)
    });
  }

  cargarDatos(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);
    this.perPage.set(size);

    const request$ = this.esAdmin()
      ? this.solicitudService.getAllSolicitudes(
          page,
          size,
          this.searchTerm(),
          this.estadoFiltro(),
          this.programacionIdFiltro() || undefined
        )
      : this.solicitudService.getMisSolicitudes(page, size);

    request$.subscribe({
      next: (res) => {
        this.solicitudes.set(res.data);
        this.paginationData.set(res);

        // Si hay filtro de programación, obtener nombre del curso de la primera solicitud
        if (this.programacionIdFiltro()) {
          if (res.data.length > 0) {
            const primera = res.data[0];
            if (primera.programacion?.curso) {
              this.cursoFiltrado.set(`${primera.programacion.curso.codigo} - ${primera.programacion.curso.nombre}`);
            } else {
              this.cursoFiltrado.set(null);
            }
          } else {
            // No hay resultados, mantener cursoFiltrado en null para mostrar mensaje
            this.cursoFiltrado.set(null);
          }
        }

        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  onSearch(value: string): void {
    this.searchTerm.set(value);
    this.cargarDatos(1);
  }

  onEstadoChange(estado: string): void {
    this.estadoFiltro.set(estado);
    this.cargarDatos(1);
  }

  limpiarFiltroCurso(): void {
    this.programacionIdFiltro.set(null);
    this.cursoFiltrado.set(null);
    // Limpiar query params
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {}
    });
    this.cargarDatos(1);
  }

  onProgramacionChange(programacionId: string): void {
    if (programacionId) {
      this.cursoFiltrado.set(null);
      this.programacionIdFiltro.set(programacionId);

      // Obtener nombre del curso del selector
      const prog = this.programaciones().find(p => p.id === programacionId);
      if (prog?.curso) {
        this.cursoFiltrado.set(`${prog.curso.codigo} - ${prog.curso.nombre} (Sec: ${prog.seccion})`);
      }

      // Actualizar query params
      this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { programacion_id: programacionId }
      });
      this.cargarDatos(1);
    } else {
      this.limpiarFiltroCurso();
    }
  }

  // Helper para obtener el label del selector
  getProgramacionLabel(prog: Programacion): string {
    if (prog.curso) {
      return `${prog.curso.codigo} - ${prog.curso.nombre} (Sec: ${prog.seccion}, Grp: ${prog.grupo})`;
    }
    return `Programación ${prog.clave}`;
  }

  getColorEstado(estado: string): 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' {
    const mapping: Record<string, 'amber' | 'indigo' | 'emerald' | 'red' | 'slate'> = {
      'pendiente': 'amber',
      'en_revision': 'indigo',
      'aprobada': 'emerald',
      'rechazada': 'red'
    };
    return mapping[estado?.toLowerCase()] || 'slate';
  }

  getEstadoLabel(estado: string): string {
    const labels: Record<string, string> = {
      'pendiente': 'Pendiente',
      'en_revision': 'En Revisión',
      'aprobada': 'Aprobada',
      'rechazada': 'Rechazada'
    };
    return labels[estado?.toLowerCase()] || estado;
  }

  verDetalle(solicitud: Solicitud): void {
    this.router.navigate(['/app/solicitudes/detalle', solicitud.id]);
  }
}
