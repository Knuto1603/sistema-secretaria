import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { UsuarioService, Estudiante, EstudianteFilters, ImportResumen, ImportFila } from '@core/services/usuario.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

interface Pagination {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  from: number;
  to: number;
}

interface Escuela {
  id: string;
  nombre: string;
}


@Component({
  selector: 'app-estudiantes-lista',
  standalone: true,
  imports: [CommonModule, FormsModule, AppTableComponent, AppBadgeComponent, AppButtonComponent, PaginationComponent],
  templateUrl: './estudiantes-lista.component.html'
})
export class EstudiantesListaComponent implements OnInit {
  private usuarioService = inject(UsuarioService);

  estudiantes = signal<Estudiante[]>([]);
  loading = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  reenvioEnProgreso = signal<string | null>(null);

  // Modal detalle
  estudianteDetalle = signal<Estudiante | null>(null);

  // Import
  importando = signal(false);
  importResultado = signal<{ resumen: ImportResumen; resultados: ImportFila[] } | null>(null);

  // Filtros
  search = '';
  escuelaFilter = '';
  cuentaActivadaFilter: string = '';
  activoFilter: string = '';

  // Paginación
  pagination: Pagination = {
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    from: 0,
    to: 0
  };

  // Escuelas (hardcoded porque son fijas en FII)
  escuelas: Escuela[] = [
    { id: '0', nombre: 'Industrial' },
    { id: '1', nombre: 'Informática' },
    { id: '2', nombre: 'Mecatrónica' },
    { id: '3', nombre: 'Agroindustrial' }
  ];

  columnas: TableColumn[] = [
    { key: 'codigo_universitario', label: 'Código' },
    { key: 'name', label: 'Nombre' },
    { key: 'escuela', label: 'Escuela' },
    { key: 'anio_ingreso', label: 'Ingreso' },
    { key: 'cuenta_activada', label: 'Activación' },
    { key: 'activo', label: 'Estado' }
  ];

  ngOnInit(): void {
    this.cargarDatos();
  }

  cargarDatos(): void {
    this.loading.set(true);

    const filters: EstudianteFilters = {
      search: this.search || undefined,
      escuela_codigo: this.escuelaFilter || undefined,
      cuenta_activada: this.cuentaActivadaFilter !== '' ? this.cuentaActivadaFilter === 'true' : undefined,
      activo: this.activoFilter !== '' ? this.activoFilter === 'true' : undefined,
      per_page: this.pagination.perPage,
      page: this.pagination.currentPage
    };

    this.usuarioService.getEstudiantes(filters).subscribe({
      next: (response) => {
        this.estudiantes.set(response.items);
        const pag = response.pagination;
        this.pagination = {
          currentPage: pag.current_page,
          lastPage: pag.last_page,
          perPage: pag.per_page,
          total: pag.total,
          from: (pag.current_page - 1) * pag.per_page + 1,
          to: Math.min(pag.current_page * pag.per_page, pag.total)
        };
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar los estudiantes');
        this.loading.set(false);
      }
    });
  }

  buscar(): void {
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  limpiarFiltros(): void {
    this.search = '';
    this.escuelaFilter = '';
    this.cuentaActivadaFilter = '';
    this.activoFilter = '';
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  verDetalle(estudiante: Estudiante): void {
    this.estudianteDetalle.set(estudiante);
  }

  cerrarDetalle(): void {
    this.estudianteDetalle.set(null);
  }

  toggleActivo(estudiante: Estudiante): void {
    this.usuarioService.toggleEstudiante(estudiante.id, !estudiante.activo).subscribe({
      next: (updated) => {
        this.estudiantes.update(estudiantes =>
          estudiantes.map(e => e.id === updated.id ? updated : e)
        );
        if (this.estudianteDetalle()?.id === updated.id) {
          this.estudianteDetalle.set(updated);
        }
        this.mostrarMensaje('success', `Estudiante ${updated.activo ? 'activado' : 'desactivado'} correctamente`);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cambiar el estado');
      }
    });
  }

  reenviarOtp(estudiante: Estudiante): void {
    if (this.reenvioEnProgreso()) return;

    this.reenvioEnProgreso.set(estudiante.id);

    this.usuarioService.reenviarOtp(estudiante.id).subscribe({
      next: (result) => {
        this.mostrarMensaje('success', `OTP enviado a ${result.email}`);
        this.reenvioEnProgreso.set(null);
        // Recargar para actualizar ultimo_otp_enviado
        this.cargarDatos();
      },
      error: (err) => {
        const mensaje = err.error?.message || 'Error al enviar OTP';
        this.mostrarMensaje('error', mensaje);
        this.reenvioEnProgreso.set(null);
      }
    });
  }

  onPageChange(page: number): void {
    this.pagination.currentPage = page;
    this.cargarDatos();
  }

  onPageSizeChange(size: number): void {
    this.pagination.perPage = size;
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  descargarPlantilla(): void {
    this.usuarioService.descargarPlantillaEstudiantes();
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.importando.set(true);
    this.importResultado.set(null);

    this.usuarioService.importarEstudiantes(archivo).subscribe({
      next: (resultado) => {
        this.importResultado.set(resultado);
        this.importando.set(false);
        if (resultado.resumen.importados > 0) {
          this.cargarDatos();
        }
        input.value = ''; // reset input
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al importar el archivo');
        this.importando.set(false);
        input.value = '';
      }
    });
  }

  cerrarImportResultado(): void {
    this.importResultado.set(null);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
