import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgramacionService, Programacion, PaginatedResponse } from '../../services/programacion.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AuthService } from '@core/auth/services/auth.service';
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
    PaginationComponent
  ],
  templateUrl: './programacion-tabla.component.html'
})
export class ProgramacionTablaComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private periodoService = inject(PeriodoService);
  public authService = inject(AuthService);
  private router = inject(Router);

  // Estado reactivo
  programacion = signal<Programacion[]>([]);
  paginationData = signal<PaginatedResponse<Programacion> | null>(null);

  // Periodos
  periodos = signal<Periodo[]>([]);
  periodoSeleccionado = signal<string | null>(null);

  loading = signal(false);
  loadingPeriodos = signal(false);
  isUploading = signal(false);
  searchTerm = signal('');
  currentPage = signal(1);
  perPage = signal(10);

  // Configuración de columnas para nuestra tabla genérica
  columnas: TableColumn[] = [
    { key: 'curso', label: 'Información del Curso' },
    { key: 'grupo', label: 'GRP' },
    { key: 'seccion', label: 'SEC' },
    { key: 'docente', label: 'Docente Asignado' },
    { key: 'estado', label: 'Estado de Cupos' }
  ];

  ngOnInit(): void {
    this.cargarPeriodosYProgramacion();
  }

  cargarPeriodosYProgramacion(): void {
    this.loadingPeriodos.set(true);
    this.loading.set(true);

    this.periodoService.getPeriodos().subscribe({
      next: (periodos) => {
        this.periodos.set(periodos);
        this.loadingPeriodos.set(false);

        // Seleccionar el periodo activo por defecto
        const periodoActivo = periodos.find(p => p.activo);
        if (periodoActivo) {
          this.periodoSeleccionado.set(periodoActivo.id);
        } else if (periodos.length > 0) {
          // Si no hay activo, seleccionar el primero
          this.periodoSeleccionado.set(periodos[0].id);
        }

        // Cargar programación del periodo seleccionado
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

  onPeriodoChange(periodoId: string): void {
    this.periodoSeleccionado.set(periodoId);
    this.searchTerm.set('');
    this.cargarProgramacion(1);
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.cargarProgramacion(1);
  }

  handlePageChange(page: number): void {
    this.cargarProgramacion(page);
  }

  handleSizeChange(size: number): void {
    this.cargarProgramacion(1, size);
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

  toggleLleno(item: Programacion): void {
    this.programacionService.toggleLleno(item.id).subscribe({
      next: (updated) => {
        // Actualizar el item en la lista
        this.programacion.update(items =>
          items.map(p => p.id === updated.id ? updated : p)
        );
      },
      error: (err) => console.error('Error al cambiar estado:', err)
    });
  }

  // Helper para obtener el nombre del periodo seleccionado
  getPeriodoNombre(): string {
    const periodo = this.periodos().find(p => p.id === this.periodoSeleccionado());
    return periodo?.nombre || 'Seleccionar periodo';
  }

  // Verificar si el periodo seleccionado está activo
  isPeriodoActivo = computed(() => {
    const periodo = this.periodos().find(p => p.id === this.periodoSeleccionado());
    return periodo?.activo ?? false;
  });
}
