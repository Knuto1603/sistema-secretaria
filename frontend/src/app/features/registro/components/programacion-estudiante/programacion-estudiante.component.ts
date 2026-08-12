import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgramacionService, Programacion, PaginatedResponse } from '../../services/programacion.service';
import { PeriodoService } from '@core/services/periodo.service';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService } from '../../../solicitudes/services/solicitud.service';
import { HistorialOnboardingComponent } from '../historial-onboarding/historial-onboarding.component';
import { ProgramacionDetalleComponent } from '../programacion-detalle/programacion-detalle.component';
import { TodosCursosModalComponent } from '../todos-cursos-modal/todos-cursos-modal.component';
import { SeccionAlternativaModalComponent } from '../seccion-alternativa-modal/seccion-alternativa-modal.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

@Component({
  selector: 'app-programacion-estudiante',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AppButtonComponent,
    AppBadgeComponent,
    AppTableComponent,
    PaginationComponent,
    HistorialOnboardingComponent,
    ProgramacionDetalleComponent,
    TodosCursosModalComponent,
    SeccionAlternativaModalComponent,
  ],
  templateUrl: './programacion-estudiante.component.html'
})
export class ProgramacionEstudianteComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private periodoService      = inject(PeriodoService);
  private solicitudService    = inject(SolicitudService);
  public  authService         = inject(AuthService);
  private router              = inject(Router);

  programacion   = signal<Programacion[]>([]);
  paginationData = signal<PaginatedResponse<Programacion> | null>(null);
  loading        = signal(false);
  searchTerm     = signal('');
  currentPage    = signal(1);
  perPage        = signal(10);

  cicloActual                = signal<number | null>(null);
  historialRegistrado        = signal<boolean>(false);
  showOnboarding             = signal(false);
  cursosConSolicitud         = signal<Set<string>>(new Set());
  solicitudesAbiertas        = signal<boolean>(true);
  showTodosCursos            = signal(false);
  programacionDetalleId      = signal<string | null>(null);
  seccionLlenaAviso          = signal<Programacion | null>(null);

  isPeriodoActivo = signal(true);

  columnas: TableColumn[] = [
    { key: 'curso',   label: 'Curso' },
    { key: 'grupo',   label: 'GRP' },
    { key: 'seccion', label: 'SEC' },
    { key: 'aula',    label: 'Aula' },
    { key: 'docente', label: 'Docente' },
    { key: 'estado',  label: 'Cupos' },
  ];

  ngOnInit(): void {
    this.cargar();
    this.periodoService.getPeriodoActivo().subscribe({
      next: p => {
        this.solicitudesAbiertas.set(p?.solicitudes_abiertas ?? true);
        this.isPeriodoActivo.set(p?.activo ?? true);
      },
      error: () => {},
    });
  }

  cargar(page: number = this.currentPage(), size: number = this.perPage()): void {
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

    this.solicitudService.getCursosConSolicitudActiva().subscribe({
      next: ids => this.cursosConSolicitud.set(new Set(ids)),
      error: () => {},
    });
  }

  tieneSolicitudActiva(item: Programacion): boolean {
    return !!item.curso?.id && this.cursosConSolicitud().has(item.curso.id);
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.cargar(1);
  }

  handlePageChange(page: number): void { this.cargar(page); }
  handleSizeChange(size: number): void { this.cargar(1, size); }

  abrirOnboarding(): void { this.showOnboarding.set(true); }

  onHistorialGuardado(): void {
    this.showOnboarding.set(false);
    this.authService.patchCurrentUser({ ultima_actualizacion_historial: new Date().toISOString() });
    this.cargar(1);
  }

  solicitarCupo(item: Programacion): void {
    if (item.seccion_hermana_disponible) {
      this.seccionLlenaAviso.set(item);
      return;
    }
    this.irASolicitud(item);
  }

  cerrarAvisoSeccionLlena(): void {
    this.seccionLlenaAviso.set(null);
  }

  verSeccionDisponible(): void {
    const hermana = this.seccionLlenaAviso()?.seccion_hermana_disponible;
    this.seccionLlenaAviso.set(null);
    if (hermana) this.programacionDetalleId.set(hermana.id);
  }

  confirmarPresentarSolicitud(): void {
    const item = this.seccionLlenaAviso();
    this.seccionLlenaAviso.set(null);
    if (item) this.irASolicitud(item);
  }

  private irASolicitud(item: Programacion): void {
    this.router.navigate(['app/solicitudes/nueva/', item.id]);
  }

  solicitarInscripcionEscuela(item: Programacion): void {
    this.router.navigate(['app/solicitudes/nueva/', item.id], { queryParams: { inscripcion_escuela: '1' } });
  }

  getAulaMostrar(row: Programacion): string {
    return row.aula_nombre || row.aula || row.aula_rel?.nombre || '—';
  }
}
