import { Component, OnInit, inject, signal, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { SolicitudAperturaService, CursoBusqueda, TipoApertura } from '../../services/solicitud-apertura.service';

export interface CursoElegido {
  cursoId: string;
  codigo: string;
  nombre: string;
  tipo: TipoApertura;
  programacionReferenciaId: string | null;
  referenciaLabel: string | null;
}

@Component({
  selector: 'app-apertura-buscador-modal',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './apertura-buscador-modal.component.html'
})
export class AperturaBuscadorModalComponent implements OnInit {
  private service = inject(SolicitudAperturaService);

  cerrado = output<void>();
  elegido = output<CursoElegido>();

  cursos = signal<CursoBusqueda[]>([]);
  loading = signal(false);
  search = signal('');
  page = signal(1);
  lastPage = signal(1);

  cursoExpandidoId = signal<string | null>(null);
  seccionElegidaId = signal<string | null>(null);

  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  ngOnInit(): void {
    this.cargar();
  }

  cargar(page: number = 1): void {
    this.loading.set(true);
    this.page.set(page);
    this.service.buscarCurso(this.search(), page, 10).subscribe({
      next: res => {
        this.cursos.set(res.data);
        this.lastPage.set(res.last_page);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  onSearchInput(value: string): void {
    this.search.set(value);
    if (this.searchTimer) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.cargar(1), 300);
  }

  toggleExpandir(curso: CursoBusqueda): void {
    if (curso.ya_tiene_solicitud_activa) return;

    if (!curso.programado_este_periodo) {
      this.confirmar(curso, 'nueva_apertura', null, null);
      return;
    }

    this.seccionElegidaId.set(null);
    this.cursoExpandidoId.set(this.cursoExpandidoId() === curso.id ? null : curso.id);
  }

  confirmarCambioGrupo(curso: CursoBusqueda): void {
    const seccion = curso.secciones.find(s => s.id === this.seccionElegidaId());
    if (!seccion) return;
    const label = `Sección ${seccion.seccion ?? '-'} / Grupo ${seccion.grupo ?? '-'}`;
    this.confirmar(curso, 'cambio_grupo', seccion.id, label);
  }

  private confirmar(curso: CursoBusqueda, tipo: TipoApertura, programacionReferenciaId: string | null, referenciaLabel: string | null): void {
    this.elegido.emit({
      cursoId: curso.id,
      codigo: curso.codigo,
      nombre: curso.nombre,
      tipo,
      programacionReferenciaId,
      referenciaLabel
    });
  }
}
