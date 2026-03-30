import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { SolicitudService, MetricaCurso, SeccionMetrica } from '@features/solicitudes/services/solicitud.service';

@Component({
  selector: 'app-analitica',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './analitica.component.html',
})
export class AnaliticaComponent implements OnInit {
  private solicitudService = inject(SolicitudService);
  private router = inject(Router);

  cursos = signal<MetricaCurso[]>([]);
  loading = signal(true);
  error = signal<string | null>(null);

  cursosExpandidos = signal<Set<string>>(new Set());

  resumen = computed(() => {
    const todos = this.cursos();
    return {
      totalSolicitudes: todos.reduce((s, c) => s + c.total, 0),
      totalCursos: todos.length,
      totalSecciones: todos.reduce((s, c) => s + c.secciones.length, 0),
      pendientes:  todos.reduce((s, c) => s + c.por_estado.pendiente, 0),
      enRevision:  todos.reduce((s, c) => s + c.por_estado.en_revision, 0),
      aprobadas:   todos.reduce((s, c) => s + c.por_estado.aprobada, 0),
      rechazadas:  todos.reduce((s, c) => s + c.por_estado.rechazada, 0),
    };
  });

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.loading.set(true);
    this.error.set(null);
    this.solicitudService.getMetricasCupo().subscribe({
      next: (data) => { this.cursos.set(data); this.loading.set(false); },
      error: () => { this.error.set('No se pudieron cargar las métricas.'); this.loading.set(false); }
    });
  }

  toggleExpandir(cursoId: string): void {
    const set = new Set(this.cursosExpandidos());
    set.has(cursoId) ? set.delete(cursoId) : set.add(cursoId);
    this.cursosExpandidos.set(set);
  }

  estaExpandido(cursoId: string): boolean {
    return this.cursosExpandidos().has(cursoId);
  }

  verSolicitud(id: string): void {
    this.router.navigate(['/app/solicitudes/detalle', id]);
  }

  getColorEstado(estado: string): string {
    const map: Record<string, string> = {
      pendiente:   'bg-amber-100 text-amber-800 border-amber-200',
      en_revision: 'bg-indigo-100 text-indigo-800 border-indigo-200',
      aprobada:    'bg-emerald-100 text-emerald-800 border-emerald-200',
      rechazada:   'bg-red-100 text-red-800 border-red-200',
    };
    return map[estado] ?? 'bg-slate-100 text-slate-600 border-slate-200';
  }

  getLabelEstado(estado: string): string {
    const map: Record<string, string> = {
      pendiente: 'Pendiente', en_revision: 'En Revisión',
      aprobada: 'Aprobada', rechazada: 'Rechazada',
    };
    return map[estado] ?? estado;
  }

  getPct(seccion: SeccionMetrica): number {
    if (!seccion.capacidad || seccion.n_inscritos === null) return 0;
    return Math.min(100, Math.round((seccion.n_inscritos / seccion.capacidad) * 100));
  }

  getColorBarra(pct: number): string {
    if (pct >= 100) return 'bg-red-500';
    if (pct >= 80)  return 'bg-amber-500';
    return 'bg-emerald-500';
  }

  getSolicitantesPorSeccion(curso: MetricaCurso, sec: SeccionMetrica): number {
    return curso.solicitantes.filter(
      s => s.grupo_solicitado === sec.grupo &&
           (sec.seccion === null || s.seccion_solicitada === sec.seccion)
    ).length;
  }
}
