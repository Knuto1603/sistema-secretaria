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
  programacionesConSolicitud = signal<Set<string>>(new Set());
  solicitudesAbiertas        = signal<boolean>(true);
  showTodosCursos            = signal(false);
  programacionDetalleId      = signal<string | null>(null);

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

    this.solicitudService.getProgramacionesConSolicitudActiva().subscribe({
      next: ids => this.programacionesConSolicitud.set(new Set(ids)),
      error: () => {},
    });
  }

  tieneSolicitudActiva(id: string): boolean {
    return this.programacionesConSolicitud().has(id);
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
    this.router.navigate(['app/solicitudes/nueva/', item.id]);
  }

  solicitarInscripcionEscuela(item: Programacion): void {
    this.router.navigate(['app/solicitudes/nueva/', item.id], { queryParams: { inscripcion_escuela: '1' } });
  }

  getAulaMostrar(row: Programacion): string {
    return row.aula_nombre || row.aula || row.aula_rel?.nombre || '—';
  }
}
