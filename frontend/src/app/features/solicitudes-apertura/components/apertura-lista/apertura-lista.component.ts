import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '@core/auth/services/auth.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';
import { SolicitudAperturaService, SolicitudApertura, CursoAgrupado, PaginatedResponse, TipoApertura } from '../../services/solicitud-apertura.service';
import { AperturaBuscadorModalComponent, CursoElegido } from '../apertura-buscador-modal/apertura-buscador-modal.component';
import { AperturaCursoCardComponent } from '../apertura-curso-card/apertura-curso-card.component';

@Component({
  selector: 'app-apertura-lista',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AppButtonComponent,
    AppBadgeComponent,
    PaginationComponent,
    AperturaBuscadorModalComponent,
    AperturaCursoCardComponent
  ],
  templateUrl: './apertura-lista.component.html'
})
export class AperturaListaComponent implements OnInit {
  private service = inject(SolicitudAperturaService);
  private periodoService = inject(PeriodoService);
  private router = inject(Router);
  public authService = inject(AuthService);

  esAdmin = computed(() =>
    this.authService.hasRole('admin') ||
    this.authService.hasRole('secretaria') ||
    this.authService.hasRole('decano') ||
    this.authService.hasRole('secretario academico')
  );

  mostrarBuscador = signal(false);
  loading = signal(false);

  // Estudiante
  misSolicitudes = signal<SolicitudApertura[]>([]);
  paginacion = signal<PaginatedResponse<SolicitudApertura> | null>(null);
  paginaActual = signal(1);

  // Admin
  cursosAgrupados = signal<CursoAgrupado[]>([]);
  periodos = signal<Periodo[]>([]);
  periodoFiltro = signal<string>('');
  tipoFiltro = signal<TipoApertura | ''>('');

  resumenAdmin = computed(() => {
    const cursos = this.cursosAgrupados();
    return {
      totalCursos: cursos.length,
      totalSolicitantes: cursos.reduce((sum, c) => sum + c.total_activas, 0),
      cumplenMinimo: cursos.filter(c => c.cumple_minimo).length,
      cadena: cursos.filter(c => c.es_cadena).length
    };
  });

  ngOnInit(): void {
    if (this.esAdmin()) {
      this.periodoService.getPeriodos().subscribe({
        next: (periodos) => {
          this.periodos.set(periodos);
          const activo = periodos.find(p => p.activo);
          this.periodoFiltro.set(activo?.id ?? '');
          this.cargarAgrupado();
        },
        error: () => this.cargarAgrupado()
      });
    } else {
      this.cargarMisSolicitudes(1);
    }
  }

  cargarAgrupado(): void {
    this.loading.set(true);
    this.service.getAgrupado(this.periodoFiltro() || undefined, undefined, this.tipoFiltro() || undefined).subscribe({
      next: (data) => {
        this.cursosAgrupados.set(data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  onPeriodoChange(id: string): void {
    this.periodoFiltro.set(id);
    this.cargarAgrupado();
  }

  onTipoChange(tipo: string): void {
    this.tipoFiltro.set(tipo as TipoApertura | '');
    this.cargarAgrupado();
  }

  cargarMisSolicitudes(page: number): void {
    this.loading.set(true);
    this.paginaActual.set(page);
    this.service.getMisSolicitudes(page, 10).subscribe({
      next: (res) => {
        this.misSolicitudes.set(res.data);
        this.paginacion.set(res);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  abrirBuscador(): void {
    this.mostrarBuscador.set(true);
  }

  cerrarBuscador(): void {
    this.mostrarBuscador.set(false);
  }

  onCursoElegido(elegido: CursoElegido): void {
    this.mostrarBuscador.set(false);
    this.router.navigate(['/app/solicitudes-apertura/nueva'], {
      queryParams: {
        curso_id: elegido.cursoId,
        codigo: elegido.codigo,
        nombre: elegido.nombre,
        tipo: elegido.tipo,
        ref_id: elegido.programacionReferenciaId,
        ref_label: elegido.referenciaLabel
      }
    });
  }

  anular(solicitud: SolicitudApertura): void {
    if (!confirm(`¿Anular tu solicitud de apertura de "${solicitud.curso?.nombre ?? 'este curso'}"? No se puede deshacer.`)) return;
    this.service.anular(solicitud.id).subscribe({
      next: () => this.cargarMisSolicitudes(this.paginaActual()),
      error: (err) => alert(err.error?.message || 'Error al anular la solicitud')
    });
  }

  colorEstado(estado: string): 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' {
    const mapping: Record<string, 'amber' | 'indigo' | 'emerald' | 'red' | 'slate'> = {
      pendiente: 'amber',
      en_revision: 'indigo',
      aprobada: 'emerald',
      rechazada: 'red',
      anulada: 'slate'
    };
    return mapping[estado] || 'slate';
  }

  labelEstado(estado: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente',
      en_revision: 'En Revisión',
      aprobada: 'Aprobada',
      rechazada: 'Rechazada',
      anulada: 'Anulada'
    };
    return labels[estado] || estado;
  }

  labelTipo(tipo: string): string {
    return tipo === 'cambio_grupo' ? 'Otro grupo' : 'Apertura de curso';
  }
}
