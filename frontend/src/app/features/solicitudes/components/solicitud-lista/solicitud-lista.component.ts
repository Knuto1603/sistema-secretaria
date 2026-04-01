import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { map } from 'rxjs';
import { environment } from '@env/environment';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService, Solicitud, PaginatedResponse } from '../../services/solicitud.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

interface Escuela { id: string; nombre: string; nombre_corto: string | null; }

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
  private http = inject(HttpClient);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  public authService = inject(AuthService);

  // Estado reactivo
  solicitudes = signal<Solicitud[]>([]);
  loading = signal(false);
  paginationData = signal<PaginatedResponse<Solicitud> | null>(null);

  // Estadísticas de demanda (solo admin)
  stats = signal<{
    por_estado: { pendiente: number; en_revision: number; aprobada: number; rechazada: number };
    total: number;
    por_tipo: { cupo_ext: number; insc_escuela: number };
    por_escuela: Array<{ escuela: string; total: number }>;
    cursos_top: Array<{ curso: string; codigo: string; total_solicitudes: number; escuela_programada?: string }>;
  } | null>(null);

  // Filtros
  searchTerm = signal('');
  estadoFiltro = signal('');
  tipoFiltro = signal('');
  escuelaIdFiltro = signal('');
  programacionIdFiltro = signal<string | null>(null);
  currentPage = signal(1);
  perPage = signal(10);
  sortOrder = signal<'asc' | 'desc'>('desc');

  // Escuelas para filtros
  escuelas = signal<Escuela[]>([]);
  escuelaProgramadaFiltro = signal('');
  exportando = signal(false);

  // Info del curso cuando se filtra por programación
  cursoFiltrado = signal<string | null>(null);

  // Valor del select de programación: solo retorna el UUID cuando la opción ya existe en la lista
  // Esto evita que ngModel se vincule antes de que los options carguen y quede en blanco
  programacionSelectValue = computed(() => {
    const id = this.programacionIdFiltro();
    if (!id) return '';
    return this.cursosConSolicitud().some(c => c.id === id) ? id : '';
  });

  // Cursos que tienen solicitudes (para el selector de filtro)
  cursosConSolicitud = signal<Array<{ id: string; clave: string; grupo: string; seccion: string | null; curso: { nombre: string; codigo: string }; escuela_programada: string | null }>>([]);
  loadingCursos = signal(false);

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
    if (this.esAdmin()) {
      this.cargarCursosConSolicitud();
      this.cargarEstadisticas();
      this.cargarEscuelas();
    }

    // Los query params son la fuente de verdad de todos los filtros
    this.route.queryParams.subscribe(params => {
      this.searchTerm.set(params['search'] ?? '');
      this.estadoFiltro.set(params['estado'] ?? '');
      this.tipoFiltro.set(params['tipo'] ?? '');
      this.escuelaIdFiltro.set(params['escuela_id'] ?? '');
      this.escuelaProgramadaFiltro.set(params['escuela_programada_id'] ?? '');
      this.sortOrder.set(params['sort'] === 'asc' ? 'asc' : 'desc');
      this.currentPage.set(Number(params['page']) || 1);
      this.perPage.set(Number(params['per_page']) || 10);
      this.programacionIdFiltro.set(params['programacion_id'] ?? null);
      this.cargarDatos();
    });
  }

  private pushQueryParams(changes: Record<string, string | number | null>): void {
    const next: Record<string, string> = { ...this.route.snapshot.queryParams };
    for (const [key, value] of Object.entries(changes)) {
      if (value === null || value === '' || value === undefined) {
        delete next[key];
      } else {
        next[key] = String(value);
      }
    }
    this.router.navigate([], { relativeTo: this.route, queryParams: next, replaceUrl: true });
  }

  cargarEscuelas(): void {
    this.http.get<{ success: boolean; data: Escuela[] }>(`${environment.apiUrl}/escuelas`).pipe(
      map(r => r.data)
    ).subscribe({ next: (data) => this.escuelas.set(data), error: () => {} });
  }

  cargarEstadisticas(): void {
    this.solicitudService.getEstadisticas().subscribe({
      next: (data) => this.stats.set(data),
      error: () => {}
    });
  }

  cargarCursosConSolicitud(): void {
    this.loadingCursos.set(true);
    this.solicitudService.getCursosConSolicitud().subscribe({
      next: (data) => {
        this.cursosConSolicitud.set(data);
        this.loadingCursos.set(false);
        // Si hay filtro activo y el nombre aún no está establecido, tomarlo de los datos cargados
        if (this.programacionIdFiltro() && !this.cursoFiltrado()) {
          const prog = data.find(p => p.id === this.programacionIdFiltro());
          if (prog) {
            this.cursoFiltrado.set(`${prog.curso.codigo} - ${prog.curso.nombre} (Sec: ${prog.seccion ?? '-'}, G: ${prog.grupo})`);
          }
        }
      },
      error: () => this.loadingCursos.set(false)
    });
  }

  cargarDatos(): void {
    this.loading.set(true);

    const request$ = this.esAdmin()
      ? this.solicitudService.getAllSolicitudes(
          this.currentPage(),
          this.perPage(),
          this.searchTerm() || undefined,
          this.estadoFiltro() || undefined,
          this.programacionIdFiltro() || undefined,
          this.tipoFiltro() || undefined,
          this.escuelaIdFiltro() || undefined,
          this.escuelaProgramadaFiltro() || undefined,
          this.sortOrder()
        )
      : this.solicitudService.getMisSolicitudes(this.currentPage(), this.perPage());

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
    this.pushQueryParams({ search: value || null, page: null });
  }

  onEstadoChange(estado: string): void {
    this.pushQueryParams({ estado: estado || null, page: null });
  }

  onTipoChange(tipo: string): void {
    this.pushQueryParams({ tipo: tipo || null, page: null });
  }

  onEscuelaChange(escuelaId: string): void {
    this.pushQueryParams({ escuela_id: escuelaId || null, page: null });
  }

  onEscuelaProgramadaChange(escuelaId: string): void {
    this.pushQueryParams({ escuela_programada_id: escuelaId || null, page: null });
  }

  onPageChange(page: number): void {
    this.pushQueryParams({ page: page === 1 ? null : page });
  }

  onPerPageChange(size: number): void {
    this.pushQueryParams({ per_page: size === 10 ? null : size, page: null });
  }

  exportar(): void {
    this.exportando.set(true);
    const params: Record<string, string> = {};
    if (this.estadoFiltro())            params['estado'] = this.estadoFiltro();
    if (this.tipoFiltro())              params['tipo'] = this.tipoFiltro();
    if (this.escuelaIdFiltro())         params['escuela_id'] = this.escuelaIdFiltro();
    if (this.escuelaProgramadaFiltro()) params['escuela_programada_id'] = this.escuelaProgramadaFiltro();
    if (this.programacionIdFiltro())    params['programacion_id'] = this.programacionIdFiltro()!;
    if (this.searchTerm())              params['search'] = this.searchTerm();

    this.solicitudService.exportarCSV(params).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `solicitudes_${new Date().toISOString().slice(0,10)}.xlsx`;
        a.click();
        URL.revokeObjectURL(url);
        this.exportando.set(false);
      },
      error: () => this.exportando.set(false)
    });
  }

  limpiarFiltroCurso(): void {
    this.cursoFiltrado.set(null);
    this.pushQueryParams({ programacion_id: null, page: null });
  }

  onProgramacionChange(programacionId: string): void {
    if (programacionId) {
      const prog = this.cursosConSolicitud().find(p => p.id === programacionId);
      if (prog) {
        this.cursoFiltrado.set(`${prog.curso.codigo} - ${prog.curso.nombre} (Sec: ${prog.seccion ?? '-'}, G: ${prog.grupo})`);
      }
      this.pushQueryParams({ programacion_id: programacionId, page: null });
    } else {
      this.limpiarFiltroCurso();
    }
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

  toggleSortOrder(): void {
    const next = this.sortOrder() === 'desc' ? 'asc' : 'desc';
    this.pushQueryParams({ sort: next, page: null });
  }

  verDetalle(solicitud: Solicitud): void {
    this.router.navigate(['/app/solicitudes/detalle', solicitud.id]);
  }

  anularSolicitud(solicitud: Solicitud): void {
    if (!confirm(`¿Anular la solicitud de "${solicitud.programacion?.curso?.nombre ?? 'este trámite'}"? No se puede deshacer.`)) return;
    this.solicitudService.anularSolicitud(solicitud.id).subscribe({
      next: () => this.pushQueryParams({ page: null }),
      error: (err) => alert(err.error?.message || 'Error al anular la solicitud')
    });
  }
}
