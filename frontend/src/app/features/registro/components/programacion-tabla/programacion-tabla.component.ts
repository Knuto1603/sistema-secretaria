import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgramacionService, Programacion, PaginatedResponse } from '../../services/programacion.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService } from '../../../solicitudes/services/solicitud.service';
import { DepartamentoService, Departamento } from '../../../configuracion/services/departamento.service';
import { HistorialOnboardingComponent } from '../historial-onboarding/historial-onboarding.component';
import { ProgramacionFormComponent } from '../programacion-form/programacion-form.component';
import { ProgramacionEditFormComponent } from '../programacion-edit-form/programacion-edit-form.component';
import { ProgramacionMatrizComponent } from '../programacion-matriz/programacion-matriz.component';
import { ProgramacionDetalleComponent } from '../programacion-detalle/programacion-detalle.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';

type VistaActiva = 'tabla' | 'matriz';

@Component({
  selector: 'app-programacion-tabla',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AppButtonComponent,
    AppBadgeComponent,
    AppTableComponent,
    PaginationComponent,
    HistorialOnboardingComponent,
    ProgramacionFormComponent,
    ProgramacionEditFormComponent,
    ProgramacionMatrizComponent,
    ProgramacionDetalleComponent,
  ],
  templateUrl: './programacion-tabla.component.html'
})
export class ProgramacionTablaComponent implements OnInit {
  private programacionService  = inject(ProgramacionService);
  private periodoService       = inject(PeriodoService);
  private solicitudService     = inject(SolicitudService);
  private departamentoService  = inject(DepartamentoService);
  private http                 = inject(HttpClient);
  public  authService          = inject(AuthService);
  private router               = inject(Router);

  programacion     = signal<Programacion[]>([]);
  paginationData   = signal<PaginatedResponse<Programacion> | null>(null);
  todosLosItems    = signal<Programacion[]>([]); // para la matriz

  periodos             = signal<Periodo[]>([]);
  periodoSeleccionado  = signal<string | null>(null);

  loading           = signal(false);
  loadingPeriodos   = signal(false);
  loadingMatriz     = signal(false);
  isUploading       = signal(false);
  isUploadingHtml   = signal(false);
  isExporting       = signal(false);
  searchTerm        = signal('');
  currentPage       = signal(1);
  perPage           = signal(10);

  // Vista
  vistaActiva = signal<VistaActiva>('tabla');

  // Filtros adicionales (admin)
  escuelas           = signal<Array<{ id: string; nombre: string; nombre_corto: string | null }>>([]);
  escuelaSeleccionada = signal<string>('');
  cicloSeleccionado   = signal<number | null>(null);
  ciclos = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

  departamentos       = signal<Departamento[]>([]);
  areaSeleccionada    = signal<string>('');

  // Estado modo estudiante
  cicloActual              = signal<number | null>(null);
  historialRegistrado      = signal<boolean>(false);
  showOnboarding           = signal(false);
  programacionesConSolicitud = signal<Set<string>>(new Set());

  // Modales
  showFormProgramacion  = signal(false);
  programacionAEditar   = signal<Programacion | null>(null);
  programacionAEliminar = signal<Programacion | null>(null);
  programacionDetalleId = signal<string | null>(null);
  eliminando            = signal(false);
  errorEliminar         = signal<string | null>(null);

  // Modal: Limpiar periodo (solo developer)
  showLimpiarPeriodo      = signal(false);
  limpiarPeriodoLoading   = signal(false);
  limpiarPeriodoError     = signal<string | null>(null);
  limpiarPeriodoEliminados = signal<number | null>(null);

  columnas: TableColumn[] = [
    { key: 'curso',   label: 'Curso' },
    { key: 'grupo',   label: 'GRP' },
    { key: 'seccion', label: 'SEC' },
    { key: 'aula',    label: 'Aula' },
    { key: 'docente', label: 'Docente' },
    { key: 'estado',  label: 'Cupos' },
  ];

  esEstudiante = computed(() => this.authService.isEstudiante());
  esDeveloper  = computed(() => this.authService.hasRole('developer'));
  esAdmin      = computed(() =>
    this.authService.hasRole('secretaria') ||
    this.authService.hasRole('admin') ||
    this.authService.hasRole('developer')
  );

  ngOnInit(): void {
    if (this.esEstudiante()) {
      this.cargarParaMi();
    } else {
      this.cargarPeriodosYProgramacion();
      this.cargarEscuelas();
      this.cargarDepartamentos();
    }
  }

  cargarEscuelas(): void {
    this.http.get<{ success: boolean; data: Array<{ id: string; nombre: string; nombre_corto: string | null }> }>(
      `${environment.apiUrl}/escuelas`
    ).pipe(map(r => r.data)).subscribe({
      next: escuelas => this.escuelas.set(escuelas),
      error: () => {},
    });
  }

  cargarDepartamentos(): void {
    this.departamentoService.getDepartamentos().subscribe({
      next: deps => this.departamentos.set(deps),
      error: () => {},
    });
  }

  // ─── MODO ADMINISTRADOR ──────────────────────────────────────────────────

  cargarPeriodosYProgramacion(): void {
    this.loadingPeriodos.set(true);
    this.loading.set(true);

    this.periodoService.getPeriodos().subscribe({
      next: periodos => {
        this.periodos.set(periodos);
        this.loadingPeriodos.set(false);

        const activo = periodos.find(p => p.activo);
        if (activo)               this.periodoSeleccionado.set(activo.id);
        else if (periodos.length) this.periodoSeleccionado.set(periodos[0].id);

        this.cargarProgramacion();
      },
      error: () => {
        this.loadingPeriodos.set(false);
        this.loading.set(false);
      },
    });
  }

  cargarProgramacion(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);
    this.perPage.set(size);

    const periodoId = this.periodoSeleccionado() || undefined;
    const escuelaId = this.escuelaSeleccionada() || undefined;
    const ciclo     = this.cicloSeleccionado() || undefined;
    const areaId    = this.areaSeleccionada() || undefined;

    this.programacionService.getProgramacion(page, this.searchTerm(), size, periodoId, escuelaId, ciclo, areaId).subscribe({
      next: res => {
        this.programacion.set(res.data);
        this.paginationData.set(res);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  cargarMatriz(): void {
    const periodoId = this.periodoSeleccionado() || undefined;
    if (!periodoId) return;

    this.loadingMatriz.set(true);
    const escuelaId = this.escuelaSeleccionada() || undefined;
    const ciclo     = this.cicloSeleccionado() || undefined;
    const areaId    = this.areaSeleccionada() || undefined;
    this.programacionService.getProgramacion(1, this.searchTerm(), 1000, periodoId, escuelaId, ciclo, areaId).subscribe({
      next: res => {
        this.todosLosItems.set(res.data);
        this.loadingMatriz.set(false);
      },
      error: () => this.loadingMatriz.set(false),
    });
  }

  cambiarVista(vista: VistaActiva): void {
    this.vistaActiva.set(vista);
    if (vista === 'matriz' && this.todosLosItems().length === 0) {
      this.cargarMatriz();
    }
  }

  // ─── MODO ESTUDIANTE ─────────────────────────────────────────────────────

  cargarParaMi(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);
    this.perPage.set(size);

    this.programacionService.getParaMi(page, this.searchTerm(), size).subscribe({
      next: res => {
        this.cicloActual.set(res.cicloActual);
        this.historialRegistrado.set(res.historialRegistrado);
        this.programacion.set(res.paginatedData.data);
        this.paginationData.set(res.paginatedData);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });

    this.solicitudService.getProgramacionesConSolicitudActiva().subscribe({
      next: ids => this.programacionesConSolicitud.set(new Set(ids)),
      error: () => {},
    });
  }

  tieneSolicitudActiva(programacionId: string): boolean {
    return this.programacionesConSolicitud().has(programacionId);
  }

  // ─── EDITAR / ELIMINAR ───────────────────────────────────────────────────

  abrirDetalle(item: Programacion): void {
    this.programacionDetalleId.set(item.id);
  }

  onDetalleEditar(prog: Programacion): void {
    this.programacionDetalleId.set(null);
    this.programacionAEditar.set(prog);
  }

  abrirEditar(item: Programacion): void {
    this.programacionAEditar.set(item);
  }

  onEdicionGuardada(updated: Programacion): void {
    this.programacionAEditar.set(null);
    this.programacion.update(items => items.map(p => p.id === updated.id ? updated : p));
    if (this.vistaActiva() === 'matriz') {
      this.todosLosItems.update(items => items.map(p => p.id === updated.id ? updated : p));
    }
  }

  abrirEliminar(item: Programacion): void {
    this.programacionAEliminar.set(item);
    this.errorEliminar.set(null);
  }

  confirmarEliminar(): void {
    const prog = this.programacionAEliminar();
    if (!prog || this.eliminando()) return;

    this.eliminando.set(true);
    this.programacionService.eliminarProgramacion(prog.id).subscribe({
      next: () => {
        this.eliminando.set(false);
        this.programacionAEliminar.set(null);
        this.cargarProgramacion(1);
        if (this.vistaActiva() === 'matriz') this.cargarMatriz();
      },
      error: err => {
        this.errorEliminar.set(err.error?.message || 'Error al eliminar');
        this.eliminando.set(false);
      },
    });
  }

  // ─── IMPORTACIÓN ─────────────────────────────────────────────────────────

  onHtmlFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    this.isUploadingHtml.set(true);
    const periodoId = this.periodoSeleccionado() || undefined;
    this.programacionService.importarHtml(file, periodoId).subscribe({
      next: () => {
        this.isUploadingHtml.set(false);
        (event.target as HTMLInputElement).value = '';
        this.cargarProgramacion(1);
        this.todosLosItems.set([]);
      },
      error: () => {
        this.isUploadingHtml.set(false);
        (event.target as HTMLInputElement).value = '';
      },
    });
  }

  onFileSelected(event: any): void {
    const file: File = event.target.files[0];
    if (file) {
      this.isUploading.set(true);
      const periodoId = this.periodoSeleccionado() || undefined;
      this.programacionService.importarExcel(file, periodoId).subscribe({
        next: () => {
          this.isUploading.set(false);
          this.cargarProgramacion(1);
          this.todosLosItems.set([]);
        },
        error: () => this.isUploading.set(false),
      });
    }
  }

  // ─── EXPORT ──────────────────────────────────────────────────────────────

  exportarConHorario = signal(false);

  exportarExcel(): void {
    this.programacionService.exportarExcel(
      this.periodoSeleccionado() || undefined,
      this.searchTerm() || undefined,
      this.escuelaSeleccionada() || undefined,
      this.cicloSeleccionado() || undefined,
      this.areaSeleccionada() || undefined,
      this.exportarConHorario()
    );
  }

  hayFiltrosActivos = computed(() =>
    !!(this.searchTerm() || this.escuelaSeleccionada() || this.cicloSeleccionado() || this.areaSeleccionada())
  );

  // ─── ONBOARDING ──────────────────────────────────────────────────────────

  abrirOnboarding(): void   { this.showOnboarding.set(true); }

  onHistorialGuardado(): void {
    this.showOnboarding.set(false);
    this.authService.patchCurrentUser({ ultima_actualizacion_historial: new Date().toISOString() });
    this.cargarParaMi(1);
  }

  onOnboardingCerrado(): void { this.showOnboarding.set(false); }

  // ─── EVENTOS COMUNES ─────────────────────────────────────────────────────

  onPeriodoChange(periodoId: string): void {
    this.periodoSeleccionado.set(periodoId);
    this.searchTerm.set('');
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onEscuelaChange(escuelaId: string): void {
    this.escuelaSeleccionada.set(escuelaId);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onCicloChange(ciclo: string): void {
    this.cicloSeleccionado.set(ciclo ? parseInt(ciclo) : null);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  limpiarFiltros(): void {
    this.searchTerm.set('');
    this.escuelaSeleccionada.set('');
    this.cicloSeleccionado.set(null);
    this.areaSeleccionada.set('');
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
  }

  onAreaChange(areaId: string): void {
    this.areaSeleccionada.set(areaId);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.todosLosItems.set([]);
    if (this.esEstudiante()) {
      this.cargarParaMi(1);
    } else {
      this.cargarProgramacion(1);
      if (this.vistaActiva() === 'matriz') this.cargarMatriz();
    }
  }

  handlePageChange(page: number): void {
    if (this.esEstudiante()) this.cargarParaMi(page);
    else                     this.cargarProgramacion(page);
  }

  handleSizeChange(size: number): void {
    if (this.esEstudiante()) this.cargarParaMi(1, size);
    else                     this.cargarProgramacion(1, size);
  }

  triggerImport(fileInput: HTMLInputElement): void { fileInput.click(); }

  solicitarCupo(item: Programacion): void {
    this.router.navigate(['app/solicitudes/nueva/', item.id]);
  }

  verSolicitudesCurso(item: Programacion): void {
    this.router.navigate(['/app/solicitudes/list'], {
      queryParams: { programacion_id: item.id },
    });
  }

  descargarPlantilla(): void { this.programacionService.descargarPlantilla(); }

  onProgramacionGuardada(): void {
    this.showFormProgramacion.set(false);
    this.cargarProgramacion(1);
    this.todosLosItems.set([]);
  }

  toggleLleno(item: Programacion): void {
    this.programacionService.toggleLleno(item.id).subscribe({
      next: updated => {
        this.programacion.update(items => items.map(p => p.id === updated.id ? updated : p));
        if (this.vistaActiva() === 'matriz') {
          this.todosLosItems.update(items => items.map(p => p.id === updated.id ? updated : p));
        }
      },
      error: err => console.error('Error al cambiar estado:', err),
    });
  }

  getPeriodoNombre(): string {
    const periodo = this.periodos().find(p => p.id === this.periodoSeleccionado());
    return periodo?.nombre || 'Seleccionar periodo';
  }

  isPeriodoActivo = computed(() => {
    if (this.esEstudiante()) return true;
    const periodo = this.periodos().find(p => p.id === this.periodoSeleccionado());
    return periodo?.activo ?? false;
  });

  getAulaMostrar(row: Programacion): string {
    return row.aula_nombre || row.aula || row.aula_rel?.nombre || '—';
  }

  abrirLimpiarPeriodo(): void {
    this.limpiarPeriodoError.set(null);
    this.limpiarPeriodoEliminados.set(null);
    this.showLimpiarPeriodo.set(true);
  }

  confirmarLimpiarPeriodo(): void {
    const periodoId = this.periodoSeleccionado();
    if (!periodoId || this.limpiarPeriodoLoading()) return;

    this.limpiarPeriodoLoading.set(true);
    this.limpiarPeriodoError.set(null);

    this.programacionService.eliminarPorPeriodo(periodoId).subscribe({
      next: res => {
        this.limpiarPeriodoEliminados.set(res.eliminados);
        this.limpiarPeriodoLoading.set(false);
        this.showLimpiarPeriodo.set(false);
        this.cargarProgramacion(1);
        this.todosLosItems.set([]);
      },
      error: err => {
        this.limpiarPeriodoError.set(err.error?.message || 'Error al limpiar la programación del periodo');
        this.limpiarPeriodoLoading.set(false);
      },
    });
  }
}
