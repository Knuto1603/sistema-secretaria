import { Component, computed, inject, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { forkJoin, interval, of, Subscription, switchMap, takeWhile, tap } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { UsuarioService, Estudiante, EstudianteFilters, EstudianteInscripcion, ImportResumen, ImportFila, CursoPendienteResumen } from '@core/services/usuario.service';
import { ProgresoService } from '@core/services/progreso.service';
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
export class EstudiantesListaComponent implements OnInit, OnDestroy {
  private usuarioService = inject(UsuarioService);
  private progresoService = inject(ProgresoService);
  private pollSubExcel?: Subscription;
  private pollSubReporte?: Subscription;

  estudiantes = signal<Estudiante[]>([]);
  loading = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  reenvioEnProgreso            = signal<string | null>(null);
  resetActivacionEnProgreso    = signal<string | null>(null);
  toggleEgresanteEnProgreso    = signal<string | null>(null);
  inhabilitarResetearEnProgreso = signal<string | null>(null);

  // Modal detalle
  estudianteDetalle = signal<Estudiante | null>(null);
  loadingDetalle = signal(false);
  tabDetalle = signal<'perfil' | 'progreso' | 'inscripciones'>('perfil');
  inscripciones = signal<EstudianteInscripcion[]>([]);

  inscripcionesPorPeriodo = computed(() => {
    const map = new Map<string, { nombre: string; items: EstudianteInscripcion[] }>();
    for (const insc of this.inscripciones()) {
      const key  = insc.periodo?.id ?? '__sin_periodo__';
      const nombre = insc.periodo?.nombre ?? 'Sin periodo';
      if (!map.has(key)) map.set(key, { nombre, items: [] });
      map.get(key)!.items.push(insc);
    }
    return Array.from(map.values()).reverse(); // más reciente primero
  });

  // Import Excel
  importando = signal(false);
  procesandoExcelEnServidor = signal(false);
  importResultado = signal<{ resumen: ImportResumen; resultados: ImportFila[] } | null>(null);

  // Crear estudiante
  mostrarFormCrear = signal(false);
  formNombre = '';
  formCodigo = '';
  creando = signal(false);

  // Import HTML (SIGA)
  importandoHtml = signal(false);
  importHtmlMensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Import Reporte Matrícula (password = DNI)
  importandoReporte = signal(false);
  procesandoReporteEnServidor = signal(false);

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

  // Escuelas (hardcoded porque son fijas en FII). id = escuelas.codigo en backend:
  // 0=Industrial, 1=Informática, 2=Agroindustrial, 3=Mecatrónica.
  escuelas: Escuela[] = [
    { id: '0', nombre: 'Industrial' },
    { id: '1', nombre: 'Informática' },
    { id: '2', nombre: 'Agroindustrial' },
    { id: '3', nombre: 'Mecatrónica' }
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

  abrirFormCrear(): void {
    this.formNombre = '';
    this.formCodigo = '';
    this.mostrarFormCrear.set(true);
  }

  cancelarFormCrear(): void {
    this.mostrarFormCrear.set(false);
  }

  crearEstudiante(): void {
    if (!this.formNombre.trim() || this.formCodigo.length !== 10 || this.creando()) return;

    this.creando.set(true);
    this.usuarioService.crearEstudiante({
      name: this.formNombre.trim(),
      codigo_universitario: this.formCodigo.trim(),
    }).subscribe({
      next: () => {
        this.creando.set(false);
        this.mostrarFormCrear.set(false);
        this.mostrarMensaje('success', 'Estudiante creado correctamente.');
        this.cargarDatos();
      },
      error: (err) => {
        this.creando.set(false);
        this.mostrarMensaje('error', err.error?.message || 'Error al crear el estudiante.');
      },
    });
  }

  verDetalle(estudiante: Estudiante): void {
    this.estudianteDetalle.set(estudiante);
    this.loadingDetalle.set(true);
    this.tabDetalle.set('perfil');
    this.inscripciones.set([]);

    forkJoin({
      detalle:       this.usuarioService.getEstudianteById(estudiante.id).pipe(catchError(() => of(null))),
      inscripciones: this.usuarioService.getEstudianteInscripciones(estudiante.id).pipe(catchError(() => of([] as EstudianteInscripcion[]))),
    }).subscribe({
      next: ({ detalle, inscripciones }) => {
        if (detalle) this.estudianteDetalle.set(detalle);
        this.inscripciones.set(inscripciones);
        this.loadingDetalle.set(false);
      },
    });
  }

  cerrarDetalle(): void {
    this.estudianteDetalle.set(null);
  }

  toggleEgresante(estudiante: Estudiante): void {
    this.toggleEgresanteEnProgreso.set(estudiante.id);
    this.progresoService.toggleEgresante(estudiante.id).subscribe({
      next: (res) => {
        const actualizado = { ...estudiante, egresante: res.egresante };
        this.estudianteDetalle.set(actualizado);
        this.estudiantes.update(lista =>
          lista.map(e => e.id === estudiante.id ? actualizado : e)
        );
        this.toggleEgresanteEnProgreso.set(null);
      },
      error: () => this.toggleEgresanteEnProgreso.set(null),
    });
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

  resetActivacion(estudiante: Estudiante): void {
    if (this.resetActivacionEnProgreso()) return;

    if (!confirm(`¿Resetear la activación de ${estudiante.name}? El alumno deberá solicitar un nuevo OTP y establecer su contraseña nuevamente.`)) return;

    this.resetActivacionEnProgreso.set(estudiante.id);

    this.usuarioService.resetActivacion(estudiante.id).subscribe({
      next: (updated) => {
        this.estudiantes.update(lista =>
          lista.map(e => e.id === updated.id ? updated : e)
        );
        if (this.estudianteDetalle()?.id === updated.id) {
          this.estudianteDetalle.set(updated);
        }
        this.mostrarMensaje('success', `Cuenta de ${updated.name} reseteada correctamente`);
        this.resetActivacionEnProgreso.set(null);
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al resetear la activación');
        this.resetActivacionEnProgreso.set(null);
      }
    });
  }

  inhabilitarYResetear(estudiante: Estudiante): void {
    if (this.inhabilitarResetearEnProgreso()) return;

    if (!confirm(
      `¿Inhabilitar la cuenta de ${estudiante.name}?\n\n` +
      `La cuenta quedará desactivada y se borrará la contraseña. ` +
      `El alumno deberá ser reactivado por un administrador y luego solicitar un nuevo OTP para ingresar.`
    )) return;

    this.inhabilitarResetearEnProgreso.set(estudiante.id);

    this.usuarioService.inhabilitarYResetear(estudiante.id).subscribe({
      next: (updated) => {
        this.estudiantes.update(lista =>
          lista.map(e => e.id === updated.id ? updated : e)
        );
        if (this.estudianteDetalle()?.id === updated.id) {
          this.estudianteDetalle.set(updated);
        }
        this.mostrarMensaje('success', `Cuenta de ${updated.name} inhabilitada y credenciales reseteadas.`);
        this.inhabilitarResetearEnProgreso.set(null);
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al inhabilitar la cuenta');
        this.inhabilitarResetearEnProgreso.set(null);
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
    this.pollSubExcel?.unsubscribe();

    this.usuarioService.importarEstudiantes(archivo).subscribe({
      next: ({ job_id }) => this.pollImportJob(job_id, 'excel'),
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al importar el archivo');
        this.importando.set(false);
      }
    });
    input.value = ''; // reset input
  }

  cerrarImportResultado(): void {
    this.importResultado.set(null);
  }

  onArchivoReporteSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.importandoReporte.set(true);
    this.importResultado.set(null);
    this.pollSubReporte?.unsubscribe();

    this.usuarioService.importarReporteMatricula(archivo).subscribe({
      next: ({ job_id }) => this.pollImportJob(job_id, 'reporte'),
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al importar el reporte de matrícula');
        this.importandoReporte.set(false);
      }
    });
    input.value = '';
  }

  /**
   * Hace polling del estado de un import job (carga por Excel/reporte de matrícula
   * corre en background porque con ~1500 alumnos supera el timeout del request síncrono).
   */
  private pollImportJob(jobId: string, origen: 'excel' | 'reporte'): void {
    const enServidor = origen === 'excel' ? this.procesandoExcelEnServidor : this.procesandoReporteEnServidor;
    enServidor.set(true);

    const finalizar = () => {
      enServidor.set(false);
      origen === 'excel' ? this.importando.set(false) : this.importandoReporte.set(false);
    };

    const sub = interval(3000).pipe(
      switchMap(() => this.usuarioService.getImportJobStatus(jobId)),
      tap(status => {
        if (status.estado === 'completado') {
          this.importResultado.set(status.resultado as { resumen: ImportResumen; resultados: ImportFila[] });
          finalizar();
          const resumen = (status.resultado as { resumen: ImportResumen } | null)?.resumen;
          if (resumen && resumen.importados > 0) {
            this.cargarDatos();
          }
        } else if (status.estado === 'fallido') {
          this.mostrarMensaje('error', status.error_mensaje || 'Error en el procesamiento de la importación');
          finalizar();
        }
      }),
      takeWhile(status => status.estado === 'pendiente' || status.estado === 'procesando'),
    ).subscribe();

    if (origen === 'excel') this.pollSubExcel = sub; else this.pollSubReporte = sub;
  }

  onArchivoHtmlSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.importandoHtml.set(true);
    this.importHtmlMensaje.set(null);

    this.usuarioService.importarEstudiantesHtml(archivo).subscribe({
      next: (res) => {
        this.importandoHtml.set(false);
        input.value = '';
        this.importHtmlMensaje.set({
          tipo: 'success',
          texto: `SIGA importado: ${res.importados} estudiantes procesados, ${res.errores} errores.`
        });
        if (res.importados > 0) this.cargarDatos();
        setTimeout(() => this.importHtmlMensaje.set(null), 6000);
      },
      error: (err) => {
        this.importandoHtml.set(false);
        input.value = '';
        this.importHtmlMensaje.set({
          tipo: 'error',
          texto: err.error?.message || 'Error al importar el archivo HTML.'
        });
        setTimeout(() => this.importHtmlMensaje.set(null), 6000);
      }
    });
  }

  sumCreditos(cursos: CursoPendienteResumen[]): number {
    return cursos.reduce((acc, c) => acc + c.creditos, 0);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }

  ngOnDestroy(): void {
    this.pollSubExcel?.unsubscribe();
    this.pollSubReporte?.unsubscribe();
  }
}
