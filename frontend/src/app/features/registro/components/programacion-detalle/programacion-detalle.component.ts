import { Component, inject, OnInit, signal, computed, output, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgramacionService, Programacion, InscripcionesStats, InscripcionAlumno } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

const PIE_COLORS = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'];
const CIRCUNFERENCIA = 2 * Math.PI * 40; // r=40

export interface PieSegment {
  nombre: string;
  nombre_corto: string;
  cantidad: number;
  porcentaje: number;
  color: string;
  strokeDasharray: string;
  strokeDashoffset: string;
}

@Component({
  selector: 'app-programacion-detalle',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './programacion-detalle.component.html',
})
export class ProgramacionDetalleComponent implements OnInit {
  private programacionService = inject(ProgramacionService);

  id      = input.required<string>();
  cerrado = output<void>();
  editar  = output<Programacion>();

  detalle    = signal<Programacion | null>(null);
  stats      = signal<InscripcionesStats | null>(null);
  alumnos    = signal<InscripcionAlumno[]>([]);
  totalAlumnos      = signal(0);
  paginaAlumnos     = signal(1);
  totalPaginasAlumnos = signal(1);
  loadingAlumnos    = signal(false);
  loading    = signal(true);
  error      = signal<string | null>(null);

  ngOnInit(): void {
    this.programacionService.getDetalleProgramacion(this.id()).subscribe({
      next:  d => { this.detalle.set(d); this.loading.set(false); },
      error: () => { this.error.set('Error al cargar detalles'); this.loading.set(false); },
    });

    this.programacionService.getInscripcionesStats(this.id()).subscribe({
      next:  s => this.stats.set(s),
      error: () => {},
    });

    this.cargarAlumnos(1);
  }

  cargarAlumnos(pagina: number): void {
    this.loadingAlumnos.set(true);
    this.programacionService.getInscripciones(this.id(), pagina, 50).subscribe({
      next: r => {
        this.alumnos.set(r.items);
        this.paginaAlumnos.set(r.pagination.current_page);
        this.totalPaginasAlumnos.set(r.pagination.last_page);
        this.totalAlumnos.set(r.pagination.total);
        this.loadingAlumnos.set(false);
      },
      error: () => this.loadingAlumnos.set(false),
    });
  }

  get ocupacion(): number {
    const d = this.detalle();
    if (!d || !d.capacidad) return 0;
    return Math.round((d.n_inscritos / d.capacidad) * 100);
  }

  pieSegments = computed((): PieSegment[] => {
    const s = this.stats();
    if (!s || s.total === 0) return [];

    let cumulativeArc = 0;
    return s.por_escuela.map((item, i) => {
      const arc    = (item.porcentaje / 100) * CIRCUNFERENCIA;
      const offset = -cumulativeArc;
      cumulativeArc += arc;
      return {
        nombre:           item.nombre,
        nombre_corto:     item.nombre_corto,
        cantidad:         item.cantidad,
        porcentaje:       item.porcentaje,
        color:            PIE_COLORS[i % PIE_COLORS.length],
        strokeDasharray:  `${arc.toFixed(2)} ${CIRCUNFERENCIA.toFixed(2)}`,
        strokeDashoffset: offset.toFixed(2),
      };
    });
  });

  diaNombre(dia: string): string {
    const map: Record<string, string> = {
      lunes: 'Lun', martes: 'Mar', miercoles: 'Mié',
      jueves: 'Jue', viernes: 'Vie', sabado: 'Sáb',
    };
    return map[dia] ?? dia;
  }

  aulaNombre(d: Programacion): string {
    return d.aula_rel?.pabellon?.nombre || d.aula_nombre || d.aula || 'Sin aula';
  }
}
