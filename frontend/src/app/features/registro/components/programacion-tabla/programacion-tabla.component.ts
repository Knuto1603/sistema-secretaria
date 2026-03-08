import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgramacionService, Programacion, PaginatedResponse } from '../../services/programacion.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService } from '../../../solicitudes/services/solicitud.service';
import { HistorialOnboardingComponent } from '../historial-onboarding/historial-onboarding.component';
import { ProgramacionFormComponent } from '../programacion-form/programacion-form.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

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
  ],
  templateUrl: './programacion-tabla.component.html'
})
export class ProgramacionTablaComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private periodoService = inject(PeriodoService);
  private solicitudService = inject(SolicitudService);
  public authService = inject(AuthService);
  private router = inject(Router);

  programacion = signal<Programacion[]>([]);
  paginationData = signal<PaginatedResponse<Programacion> | null>(null);

  periodos = signal<Periodo[]>([]);
  periodoSeleccionado = signal<string | null>(null);

  loading = signal(false);
  loadingPeriodos = signal(false);
  isUploading = signal(false);
  isUploadingHtml = signal(false);
  searchTerm = signal('');
  currentPage = signal(1);
  perPage = signal(10);

  // Estado modo estudiante
  cicloActual = signal<number | null>(null);
  historialRegistrado = signal<boolean>(false);
  showOnboarding = signal(false);
  programacionesConSolicitud = signal<Set<string>>(new Set());

  // Modal nueva programación
  showFormProgramacion = signal(false);

  columnas: TableColumn[] = [
    { key: 'curso', label: 'Información del Curso' },
    { key: 'grupo', label: 'GRP' },
    { key: 'seccion', label: 'SEC' },
    { key: 'docente', label: 'Docente Asignado' },
    { key: 'estado', label: 'Estado de Cupos' }
  ];

  esEstudiante = computed(() => this.authService.isEstudiante());

  ngOnInit(): void {
    if (this.esEstudiante()) {
      this.cargarParaMi();
    } else {
      this.cargarPeriodosYProgramacion();
    }
  }

  // ─── MODO ADMINISTRADOR ──────────────────────────────────────────────────

  cargarPeriodosYProgramacion(): void {
    this.loadingPeriodos.set(true);
    this.loading.set(true);

    this.periodoService.getPeriodos().subscribe({
      next: (periodos) => {
        this.periodos.set(periodos);
        this.loadingPeriodos.set(false);

        const periodoActivo = periodos.find(p => p.activo);
        if (periodoActivo) {
          this.periodoSeleccionado.set(periodoActivo.id);
        } else if (periodos.length > 0) {
          this.periodoSeleccionado.set(periodos[0].id);
        }

        this.cargarProgramacion();
      },
      error: () => {
        this.loadingPeriodos.set(false);
        this.loading.set(false);
      }
    });
  }

  cargarProgramacion(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);
    this.perPage.set(size);

    const periodoId = this.periodoSeleccionado() || undefined;

    this.programacionService.getProgramacion(page, this.searchTerm(), size, periodoId).subscribe({
      next: (res) => {
        this.programacion.set(res.data);
        this.paginationData.set(res);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  // ─── MODO ESTUDIANTE ─────────────────────────────────────────────────────

  cargarParaMi(page: number = this.currentPage(), size: number = this.perPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);
    this.perPage.set(size);

    this.programacionService.getParaMi(page, this.searchTerm(), size).subscribe({
      next: (res) => {
        this.cicloActual.set(res.cicloActual);
        this.historialRegistrado.set(res.historialRegistrado);
        this.programacion.set(res.paginatedData.data);
        this.paginationData.set(res.paginatedData);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });

    // Cargar solicitudes activas en paralelo
    this.solicitudService.getProgramacionesConSolicitudActiva().subscribe({
      next: (ids) => this.programacionesConSolicitud.set(new Set(ids)),
      error: () => {}
    });
  }

  tieneSolicitudActiva(programacionId: string): boolean {
    return this.programacionesConSolicitud().has(programacionId);
  }

  onHtmlFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    this.isUploadingHtml.set(true);
    const periodoId = this.periodoSeleccionado() || undefined;
    this.programacionService.importarHtml(file, periodoId).subscribe({
      next: (res) => {
        this.isUploadingHtml.set(false);
        (event.target as HTMLInputElement).value = '';
        this.cargarProgramacion(1);
      },
      error: () => {
        this.isUploadingHtml.set(false);
        (event.target as HTMLInputElement).value = '';
      }
    });
  }

  abrirOnboarding(): void {
    this.showOnboarding.set(true);
  }

  onHistorialGuardado(): void {
    this.showOnboarding.set(false);
    this.authService.patchCurrentUser({ ultima_actualizacion_historial: new Date().toISOString() });
    this.cargarParaMi(1);
  }

  onOnboardingCerrado(): void {
    this.showOnboarding.set(false);
  }

  // ─── EVENTOS COMUNES ─────────────────────────────────────────────────────

  onPeriodoChange(periodoId: string): void {
    this.periodoSeleccionado.set(periodoId);
    this.searchTerm.set('');
    this.cargarProgramacion(1);
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    if (this.esEstudiante()) {
      this.cargarParaMi(1);
    } else {
      this.cargarProgramacion(1);
    }
  }

  handlePageChange(page: number): void {
    if (this.esEstudiante()) {
      this.cargarParaMi(page);
    } else {
      this.cargarProgramacion(page);
    }
  }

  handleSizeChange(size: number): void {
    if (this.esEstudiante()) {
      this.cargarParaMi(1, size);
    } else {
      this.cargarProgramacion(1, size);
    }
  }

  triggerImport(fileInput: HTMLInputElement): void {
    fileInput.click();
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
        },
        error: () => this.isUploading.set(false)
      });
    }
  }

  solicitarCupo(item: Programacion): void {
    this.router.navigate(['app/solicitudes/nueva/', item.id]);
  }

  verSolicitudesCurso(item: Programacion): void {
    this.router.navigate(['/app/solicitudes/list'], {
      queryParams: { programacion_id: item.id }
    });
  }

  descargarPlantilla(): void {
    this.programacionService.descargarPlantilla();
  }

  onProgramacionGuardada(): void {
    this.showFormProgramacion.set(false);
    this.cargarProgramacion(1);
  }

  toggleLleno(item: Programacion): void {
    this.programacionService.toggleLleno(item.id).subscribe({
      next: (updated) => {
        this.programacion.update(items =>
          items.map(p => p.id === updated.id ? updated : p)
        );
      },
      error: (err) => console.error('Error al cambiar estado:', err)
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
}
