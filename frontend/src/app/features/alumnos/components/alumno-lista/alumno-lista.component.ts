import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import {
  UsuarioService,
  Estudiante,
  EstudianteFilters,
} from '@core/services/usuario.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';
import { AlumnoDetalleComponent } from '../alumno-detalle/alumno-detalle.component';

interface Escuela {
  id: string;
  codigo: string;
  nombre: string;
  nombre_corto: string | null;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

@Component({
  selector: 'app-alumno-lista',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AppTableComponent,
    AppBadgeComponent,
    AppButtonComponent,
    PaginationComponent,
    AlumnoDetalleComponent,
  ],
  templateUrl: './alumno-lista.component.html',
})
export class AlumnoListaComponent implements OnInit {
  private service = inject(UsuarioService);
  private http     = inject(HttpClient);

  alumnos       = signal<Estudiante[]>([]);
  pagination    = signal<PaginationMeta | null>(null);
  loading       = signal(false);
  escuelas      = signal<Escuela[]>([]);

  // Filtros
  search              = signal('');
  escuelaSeleccionada = signal('');   // stores escuela.codigo
  cuentaActivada      = signal('');   // '' | 'true' | 'false'
  activoFiltro        = signal('');   // '' | 'true' | 'false'
  currentPage         = signal(1);
  perPage             = signal(20);

  // Detalle
  alumnoDetalleId = signal<string | null>(null);

  columnas: TableColumn[] = [
    { key: 'codigo',  label: 'Código' },
    { key: 'nombre',  label: 'Alumno' },
    { key: 'escuela', label: 'Escuela' },
    { key: 'anio',    label: 'Ingreso' },
    { key: 'cuenta',  label: 'Cuenta' },
    { key: 'estado',  label: 'Estado' },
  ];

  ngOnInit(): void {
    this.cargarEscuelas();
    this.cargar();
  }

  cargarEscuelas(): void {
    this.http
      .get<{ success: boolean; data: Escuela[] }>(`${environment.apiUrl}/escuelas`)
      .pipe(map(r => r.data))
      .subscribe({ next: e => this.escuelas.set(e), error: () => {} });
  }

  cargar(page: number = this.currentPage()): void {
    this.loading.set(true);
    this.currentPage.set(page);

    const filters: EstudianteFilters = {
      page,
      per_page: this.perPage(),
    };
    if (this.search())              filters.search = this.search();
    if (this.escuelaSeleccionada()) filters.escuela_codigo = this.escuelaSeleccionada();
    if (this.cuentaActivada())      filters.cuenta_activada = this.cuentaActivada() === 'true';
    if (this.activoFiltro())        filters.activo = this.activoFiltro() === 'true';

    this.service.getEstudiantes(filters).subscribe({
      next: res => {
        this.alumnos.set(res.items);
        this.pagination.set(res.pagination);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  limpiarFiltros(): void {
    this.search.set('');
    this.escuelaSeleccionada.set('');
    this.cuentaActivada.set('');
    this.activoFiltro.set('');
    this.cargar(1);
  }

  onToggle(alumno: Estudiante): void {
    this.service.toggleEstudiante(alumno.id, !alumno.activo).subscribe({
      next: updated => {
        this.alumnos.update(list => list.map(a => a.id === updated.id ? updated : a));
      },
      error: () => {},
    });
  }

  hayFiltros(): boolean {
    return !!(this.search() || this.escuelaSeleccionada() || this.cuentaActivada() || this.activoFiltro());
  }

  get paginationFrom(): number {
    const p = this.pagination();
    if (!p || p.total === 0) return 0;
    return (p.current_page - 1) * p.per_page + 1;
  }

  get paginationTo(): number {
    const p = this.pagination();
    if (!p) return 0;
    return Math.min(p.current_page * p.per_page, p.total);
  }
}
