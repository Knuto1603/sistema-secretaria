import { Component, inject, OnInit, signal, computed, effect, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { ProgramacionService, Programacion, PaginatedResponse } from '../../services/programacion.service';
import { AuthService } from '@core/auth/services/auth.service';
import { DepartamentoService, Departamento } from '../../../configuracion/services/departamento.service';
import { ProgramacionFormComponent } from '../programacion-form/programacion-form.component';
import { ProgramacionEditFormComponent } from '../programacion-edit-form/programacion-edit-form.component';
import { ProgramacionMatrizComponent, CambioPendiente } from '../programacion-matriz/programacion-matriz.component';
import { ProgramacionDetalleComponent } from '../programacion-detalle/programacion-detalle.component';
import { ModificationDrawerComponent } from '../../../programacion/components/modification-drawer/modification-drawer.component';
import { CambiosPendientesPanelComponent } from '../../../programacion/components/cambios-pendientes-panel/cambios-pendientes-panel.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';
import { HttpClient } from '@angular/common/http';
import { map, forkJoin, Subject, EMPTY } from 'rxjs';
import { switchMap, catchError } from 'rxjs/operators';
import { environment } from '@env/environment';
import { ModificacionService } from '../../../programacion/services/modificacion.service';
import { ProgramacionEstadoService } from '../../../programacion/services/programacion-estado.service';
import { ProgramacionFiltrosComponent } from '../programacion-filtros/programacion-filtros.component';
import { ProgramacionEstudianteComponent } from '../programacion-estudiante/programacion-estudiante.component';
import { ConfirmEliminarModalComponent } from '../confirm-eliminar-modal/confirm-eliminar-modal.component';
import { LimpiarPeriodoModalComponent } from '../limpiar-periodo-modal/limpiar-periodo-modal.component';
import { CampusDebugPanelComponent } from '../campus-debug-panel/campus-debug-panel.component';

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
    ProgramacionFormComponent,
    ProgramacionEditFormComponent,
    ProgramacionMatrizComponent,
    ProgramacionDetalleComponent,
    ModificationDrawerComponent,
    CambiosPendientesPanelComponent,
    ProgramacionFiltrosComponent,
    ProgramacionEstudianteComponent,
    ConfirmEliminarModalComponent,
    LimpiarPeriodoModalComponent,
    CampusDebugPanelComponent,
  ],
  templateUrl: './programacion-tabla.component.html'
})
export class ProgramacionTablaComponent implements OnInit {
  private programacionService  = inject(ProgramacionService);
  private departamentoService  = inject(DepartamentoService);
  private modificacionService  = inject(ModificacionService);
  readonly estadoService       = inject(ProgramacionEstadoService);
  private http                 = inject(HttpClient);
  public  authService          = inject(AuthService);
  private router               = inject(Router);
  private route                = inject(ActivatedRoute);

  private readonly destroyRef      = inject(DestroyRef);
  private readonly loadTrigger$    = new Subject<{ page: number; size: number }>();

  readonly soloLectura = computed(() => !!this.route.snapshot.data['soloLectura']);

  programacion     = signal<Programacion[]>([]);
  paginationData   = signal<PaginatedResponse<Programacion> | null>(null);
  todosLosItems    = signal<Programacion[]>([]); // para la matriz

  loading           = signal(false);
  loadingMatriz     = signal(false);
  isUploading         = signal(false);
  isUploadingHtml     = signal(false);
  isUploadingCampus   = signal(false);
  campusDebugResult   = signal<{
    actualizados: number;
    omitidos: number;
    detalle: { codigo: string; nombre: string; seccion: any; motivo: string }[];
    no_en_campus: { id: string; codigo: string; nombre: string; seccion: any; grupo: string; escuela: string }[];
  } | null>(null);
  isExporting         = signal(false);
  searchTerm        = signal('');
  currentPage       = signal(1);
  perPage           = signal(10);

  // Vista
  vistaActiva  = signal<VistaActiva>('tabla');
  modoMatriz   = signal<'consultar' | 'modificar'>('consultar');

  // Cambios pendientes de la matriz en modo modificar
  cambiosPendientesMatriz  = signal<CambioPendiente[]>([]);
  guardandoCambiosMatriz   = signal(false);

  // Filtros adicionales (admin)
  escuelas           = signal<Array<{ id: string; nombre: string; nombre_corto: string | null }>>([]);
  escuelaSeleccionada = signal<string>('');
  cicloSeleccionado   = signal<number | null>(null);
  ciclos = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

  departamentos       = signal<Departamento[]>([]);
  areaSeleccionada    = signal<string>('');
  grupoSeleccionado   = signal<string>('');

  escuelaProgramadaSeleccionada = signal<string>('');
  tipoSeleccionado              = signal<string>(''); // '' | 'O' | 'E'

  // Grupos únicos del periodo actual (cargados desde el backend)
  grupos = signal<string[]>([]);

  // Modales
  showFormProgramacion  = signal(false);
  programacionAEditar   = signal<Programacion | null>(null);
  programacionAEliminar = signal<Programacion | null>(null);
  programacionDetalleId = signal<string | null>(null);
  eliminando            = signal(false);
  errorEliminar         = signal<string | null>(null);

  // Drawer de modificaciones
  programacionModif = signal<Programacion | null>(null);

  // Modal: Limpiar periodo (solo developer)
  showLimpiarPeriodo      = signal(false);
  limpiarPeriodoLoading   = signal(false);
  limpiarPeriodoError     = signal<string | null>(null);
  limpiarPeriodoEliminados = signal<number | null>(null);

  columnas: TableColumn[] = [
    { key: 'curso',             label: 'Curso' },
    { key: 'escuela_prog',      label: 'Prog. para' },
    { key: 'grupo',             label: 'GRP' },
    { key: 'seccion',           label: 'SEC' },
    { key: 'aula',              label: 'Aula' },
    { key: 'docente',           label: 'Docente' },
    { key: 'estado',            label: 'Cupos' },
  ];

  esEstudiante = computed(() => this.authService.isEstudiante());
  esDeveloper  = computed(() => this.authService.hasRole('developer'));
  esAdmin      = computed(() =>
    this.authService.hasRole('secretaria') ||
    this.authService.hasRole('admin') ||
    this.authService.hasRole('developer')
  );

  constructor() {
    // Pipeline con switchMap: cancela el request anterior antes de lanzar el nuevo
    this.loadTrigger$.pipe(
      switchMap(({ page, size }) => {
        this.loading.set(true);
        this.currentPage.set(page);
        this.perPage.set(size);

        const periodoId           = this.estadoService.periodoId() || undefined;
        const escuelaId           = this.escuelaSeleccionada() || undefined;
        const ciclo               = this.cicloSeleccionado() || undefined;
        const areaId              = this.areaSeleccionada() || undefined;
        const grupo               = this.grupoSeleccionado() || undefined;
        const escuelaProgramadaId = this.escuelaProgramadaSeleccionada() || undefined;
        const tipo                = this.tipoSeleccionado() || undefined;

        return this.programacionService
          .getProgramacion(page, this.searchTerm(), size, periodoId, escuelaId, ciclo, areaId, grupo, escuelaProgramadaId, tipo)
          .pipe(catchError(() => { this.loading.set(false); return EMPTY; }));
      }),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe(res => {
      this.programacion.set(res.data);
      this.paginationData.set(res);
      this.loading.set(false);
    });

    // Recarga completa al cambiar de período
    effect(() => {
      const periodoId = this.estadoService.periodoId();
      if (periodoId && !this.esEstudiante()) {
        this.searchTerm.set('');
        this.grupoSeleccionado.set('');
        this.todosLosItems.set([]);
        this.cargarGrupos(periodoId);
        this.cargarProgramacion(1);
      }
    });

    // Refresco silencioso al guardar una modificación desde otro módulo
    effect(() => {
      const refresh = this.estadoService.ultimaModificacion();
      if (refresh > 0 && !this.esEstudiante()) {
        this.todosLosItems.set([]);
        this.cargarProgramacion(this.currentPage());
      }
    });
  }

  ngOnInit(): void {
    if (!this.esEstudiante()) {
      this.cargarEscuelas();
      this.cargarDepartamentos();
    }
  }

  cargarGrupos(periodoId: string): void {
    this.http.get<{ success: boolean; data: string[] }>(
      `${environment.apiUrl}/programacion/grupos`,
      { params: { periodo_id: periodoId } }
    ).pipe(map(r => r.data)).subscribe({
      next: grupos => this.grupos.set(grupos),
      error: () => {},
    });
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

  cargarProgramacion(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loadTrigger$.next({ page, size });
  }

  cargarMatriz(): void {
    const periodoId = this.estadoService.periodoId() || undefined;
    if (!periodoId) return;

    this.loadingMatriz.set(true);
    const escuelaId           = this.escuelaSeleccionada() || undefined;
    const ciclo               = this.cicloSeleccionado() || undefined;
    const areaId              = this.areaSeleccionada() || undefined;
    const grupo               = this.grupoSeleccionado() || undefined;
    const escuelaProgramadaId = this.escuelaProgramadaSeleccionada() || undefined;
    const tipo                = this.tipoSeleccionado() || undefined;
    this.programacionService.getProgramacion(1, this.searchTerm(), 1000, periodoId, escuelaId, ciclo, areaId, grupo, escuelaProgramadaId, tipo).subscribe({
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

  // ─── EDITAR / ELIMINAR ───────────────────────────────────────────────────

  abrirModificacion(item: Programacion): void {
    this.programacionModif.set(item);
  }

  irAHistorialModificaciones(): void {
    this.router.navigate(['/app/programacion/modificaciones']);
  }

  irAGenerarDocumentos(): void {
    this.router.navigate(['/app/programacion/generar-documentos']);
  }

  onModificacionGuardada(): void {
    this.cargarProgramacion(this.currentPage());
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  toggleModoMatriz(): void {
    if (this.modoMatriz() === 'consultar') {
      this.modoMatriz.set('modificar');
    } else {
      this.modoMatriz.set('consultar');
      this.cambiosPendientesMatriz.set([]);
    }
  }

  onCambiosPendientesMatriz(cambios: CambioPendiente[]): void {
    this.cambiosPendientesMatriz.set(cambios);
  }

  confirmarCambiosMatriz(motivo: string): void {
    const cambios = this.cambiosPendientesMatriz();
    if (!cambios.length) return;

    this.guardandoCambiosMatriz.set(true);

    const llamadas = cambios.map(c =>
      this.modificacionService.cambiarAulaYGrupo(c.programacionId, {
        aula_id: c.nuevaAulaId ?? '',
        grupo_horario_id: c.nuevoGrupoId ?? '',
        motivo,
      })
    );

    forkJoin(llamadas).subscribe({
      next: () => {
        this.guardandoCambiosMatriz.set(false);
        this.cambiosPendientesMatriz.set([]);
        this.modoMatriz.set('consultar');
        this.cargarMatriz();
      },
      error: () => {
        this.guardandoCambiosMatriz.set(false);
      },
    });
  }

  descartarCambiosMatriz(): void {
    this.cambiosPendientesMatriz.set([]);
  }

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
    const periodoId = this.estadoService.periodoId() || undefined;
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
      const periodoId = this.estadoService.periodoId() || undefined;
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

  onCampusFileSelected(event: any): void {
    const file: File = event.target.files[0];
    if (file) {
      this.isUploadingCampus.set(true);
      const periodoId = this.estadoService.periodoId() || undefined;
      this.programacionService.importarExcelCampus(file, periodoId).subscribe({
        next: (res: any) => {
          this.isUploadingCampus.set(false);
          (event.target as HTMLInputElement).value = '';
          this.cargarProgramacion(1);
          this.todosLosItems.set([]);
          if (res?.data) {
            this.campusDebugResult.set(res.data);
          }
        },
        error: () => {
          this.isUploadingCampus.set(false);
          (event.target as HTMLInputElement).value = '';
        },
      });
    }
  }

  // ─── EXPORT ──────────────────────────────────────────────────────────────

  exportarConHorario = signal(false);

  exportarExcel(): void {
    this.programacionService.exportarExcel(
      this.estadoService.periodoId() || undefined,
      this.searchTerm() || undefined,
      this.escuelaSeleccionada() || undefined,
      this.cicloSeleccionado() || undefined,
      this.areaSeleccionada() || undefined,
      this.exportarConHorario()
    );
  }

  hayFiltrosActivos = computed(() =>
    !!(this.searchTerm() || this.escuelaSeleccionada() || this.cicloSeleccionado() || this.areaSeleccionada() || this.grupoSeleccionado() || this.escuelaProgramadaSeleccionada() || this.tipoSeleccionado())
  );

  // ─── EVENTOS COMUNES ─────────────────────────────────────────────────────

  onEscuelaChange(escuelaId: string): void {
    this.escuelaSeleccionada.set(escuelaId);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onEscuelaProgramadaChange(escuelaId: string): void {
    this.escuelaProgramadaSeleccionada.set(escuelaId);
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
    this.grupoSeleccionado.set('');
    this.escuelaProgramadaSeleccionada.set('');
    this.tipoSeleccionado.set('');
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onTipoChange(tipo: string): void {
    this.tipoSeleccionado.set(tipo);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onAreaChange(areaId: string): void {
    this.areaSeleccionada.set(areaId);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onGrupoChange(grupo: string): void {
    this.grupoSeleccionado.set(grupo);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.todosLosItems.set([]);
    this.cargarProgramacion(1);
    if (this.vistaActiva() === 'matriz') this.cargarMatriz();
  }

  handlePageChange(page: number): void {
    this.cargarProgramacion(page);
  }

  handleSizeChange(size: number): void {
    this.cargarProgramacion(1, size);
  }

  triggerImport(fileInput: HTMLInputElement): void { fileInput.click(); }

  verSolicitudesCurso(item: Programacion): void {
    this.router.navigate(['/app/solicitudes/list'], {
      queryParams: { curso_id: item.curso?.id, grupo: item.grupo },
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

  isPeriodoActivo = computed(() => this.estadoService.periodo()?.activo ?? false);

  getAulaMostrar(row: Programacion): string {
    return row.aula_nombre || row.aula || row.aula_rel?.nombre || '—';
  }

  abrirLimpiarPeriodo(): void {
    this.limpiarPeriodoError.set(null);
    this.limpiarPeriodoEliminados.set(null);
    this.showLimpiarPeriodo.set(true);
  }

  confirmarLimpiarPeriodo(): void {
    const periodoId = this.estadoService.periodoId();
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
