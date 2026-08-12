import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { SolicitudService, MetricaCurso, SeccionMetrica } from '@features/solicitudes/services/solicitud.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';

@Component({
  selector: 'app-analitica',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './analitica.component.html',
})
export class AnaliticaComponent implements OnInit {
  private solicitudService = inject(SolicitudService);
  private periodoService = inject(PeriodoService);
  private router = inject(Router);

  tipoActivo = signal<'CUPO_EXT' | 'INSC_ESCUELA'>('CUPO_EXT');

  periodos = signal<Periodo[]>([]);
  periodoSeleccionado = signal<string | null>(null);

  cursos = signal<MetricaCurso[]>([]);
  loading = signal(true);
  exportando = signal(false);
  error = signal<string | null>(null);

  cursosExpandidos = signal<Set<string>>(new Set());
  busqueda = signal('');

  cursosFiltrados = computed(() => {
    const q = this.busqueda().trim().toLowerCase();
    if (!q) return this.cursos();
    return this.cursos().filter(c =>
      c.nombre.toLowerCase().includes(q) || c.codigo.toLowerCase().includes(q)
    );
  });

  resumen = computed(() => {
    const lista = this.cursosFiltrados();
    return {
      totalSolicitudes: lista.reduce((s, c) => s + c.total, 0),
      totalCursos: lista.length,
      totalSecciones: lista.reduce((s, c) => s + c.secciones.length, 0),
      pendientes:  lista.reduce((s, c) => s + c.por_estado.pendiente, 0),
      enRevision:  lista.reduce((s, c) => s + c.por_estado.en_revision, 0),
      aprobadas:   lista.reduce((s, c) => s + c.por_estado.aprobada, 0),
      rechazadas:  lista.reduce((s, c) => s + c.por_estado.rechazada, 0),
    };
  });

  ngOnInit(): void {
    this.periodoService.getPeriodos().subscribe({
      next: (periodos) => {
        this.periodos.set(periodos);
        const activo = periodos.find(p => p.activo);
        this.periodoSeleccionado.set(activo?.id ?? periodos[0]?.id ?? null);
        this.cargar();
      },
      error: () => this.cargar()
    });
  }

  cargar(): void {
    this.loading.set(true);
    this.error.set(null);
    this.cursosExpandidos.set(new Set());
    const periodoId = this.periodoSeleccionado() ?? undefined;
    this.solicitudService.getMetricasCupo(this.tipoActivo(), periodoId).subscribe({
      next: (data) => { this.cursos.set(data); this.loading.set(false); },
      error: () => { this.error.set('No se pudieron cargar las métricas.'); this.loading.set(false); }
    });
  }

  cambiarTipo(tipo: 'CUPO_EXT' | 'INSC_ESCUELA'): void {
    if (this.tipoActivo() === tipo) return;
    this.tipoActivo.set(tipo);
    this.busqueda.set('');
    this.cargar();
  }

  cambiarPeriodo(periodoId: string): void {
    if (this.periodoSeleccionado() === periodoId) return;
    this.periodoSeleccionado.set(periodoId);
    this.busqueda.set('');
    this.cargar();
  }

  esInscEscuela(): boolean {
    return this.tipoActivo() === 'INSC_ESCUELA';
  }

  exportar(): void {
    this.exportando.set(true);
    const periodoId = this.periodoSeleccionado() ?? undefined;
    this.solicitudService.exportarMetricas(periodoId).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `metricas_solicitudes_${new Date().toISOString().slice(0, 10)}.xlsx`;
        a.click();
        URL.revokeObjectURL(url);
        this.exportando.set(false);
      },
      error: () => this.exportando.set(false)
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
      apelado:     'bg-violet-100 text-violet-800 border-violet-200',
    };
    return map[estado] ?? 'bg-slate-100 text-slate-600 border-slate-200';
  }

  getLabelEstado(estado: string): string {
    const map: Record<string, string> = {
      pendiente: 'Pendiente', en_revision: 'En Revisión',
      aprobada: 'Aprobada', rechazada: 'Rechazada', apelado: 'Apelado',
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
    return curso.solicitantes.filter(s => s.programacion_id === sec.id).length;
  }
}
