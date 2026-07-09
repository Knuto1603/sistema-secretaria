import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';

import {
  GeneracionModificacionService,
  ModificacionItem,
} from '../../services/generacion-modificacion.service';
import {
  BorradorProgramacion,
  ProgramacionInteractivaService,
} from '../../../programacion-interactiva/services/programacion-interactiva.service';

interface GrupoProgramacion {
  programacion_id: string;
  curso_codigo: string;
  curso_nombre: string;
  seccion: string;
  ciclo: number | null;
  modificaciones: ModificacionItem[];
  expandido: boolean;
}

const TIPO_LABELS: Record<string, { label: string; color: string }> = {
  cerrar_curso:          { label: 'Cierre',       color: 'bg-red-100 text-red-700' },
  abrir_seccion:         { label: 'Apertura',     color: 'bg-emerald-100 text-emerald-700' },
  cambio_aula:           { label: 'Cambio Aula',  color: 'bg-blue-100 text-blue-700' },
  cambio_grupo:          { label: 'Cambio Grupo', color: 'bg-violet-100 text-violet-700' },
  cambio_aula_y_grupo:   { label: 'Aula+Grupo',   color: 'bg-sky-100 text-sky-700' },
  unificacion_secciones: { label: 'Unificación',  color: 'bg-amber-100 text-amber-700' },
};

@Component({
  selector: 'app-modificaciones-detalle',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './modificaciones-detalle.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModificacionesDetalleComponent {
  private readonly route   = inject(ActivatedRoute);
  private readonly router  = inject(Router);
  private readonly genSvc  = inject(GeneracionModificacionService);
  private readonly piSvc   = inject(ProgramacionInteractivaService);

  readonly borrador = signal<BorradorProgramacion | null>(null);
  readonly grupos   = signal<GrupoProgramacion[]>([]);
  readonly cargando = signal(false);

  readonly totalPendientes = computed(() =>
    this.grupos().reduce(
      (acc, g) => acc + g.modificaciones.filter(m => m.estado === 'pendiente').length,
      0
    )
  );

  readonly totalDocumentadas = computed(() =>
    this.grupos().reduce(
      (acc, g) => acc + g.modificaciones.filter(m => m.estado === 'documentado').length,
      0
    )
  );

  constructor() {
    const borradorId = this.route.snapshot.paramMap.get('borrador_id') ?? '';

    if (!borradorId) {
      this.volver();
      return;
    }

    this.cargando.set(true);
    forkJoin({
      borrador: this.piSvc.obtener(borradorId),
      mods:     this.genSvc.listarPorBorrador(borradorId),
    })
    .pipe(takeUntilDestroyed())
    .subscribe({
      next: ({ borrador, mods }) => {
        this.borrador.set(borrador);

        // Agrupar por programacion_id — solo secciones con cambios
        const mapaGrupos = new Map<string, GrupoProgramacion>();
        for (const m of mods) {
          const pid = m.programacion?.id;
          if (!pid) continue;

          if (!mapaGrupos.has(pid)) {
            mapaGrupos.set(pid, {
              programacion_id: pid,
              curso_codigo:    m.programacion?.curso?.codigo ?? '—',
              curso_nombre:    m.programacion?.curso?.nombre ?? '—',
              seccion:         m.programacion?.seccion ?? '—',
              ciclo:           m.programacion?.ciclo ?? null,
              modificaciones:  [],
              expandido:       false,
            });
          }
          mapaGrupos.get(pid)!.modificaciones.push(m);
        }

        // Más cambios primero, luego alfabético por código
        const sorted = [...mapaGrupos.values()].sort((a, b) => {
          const diff = b.modificaciones.length - a.modificaciones.length;
          return diff !== 0 ? diff : a.curso_codigo.localeCompare(b.curso_codigo);
        });

        this.grupos.set(sorted);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });
  }

  toggleExpandir(grupo: GrupoProgramacion): void {
    this.grupos.update(prev =>
      prev.map(g => g.programacion_id === grupo.programacion_id
        ? { ...g, expandido: !g.expandido }
        : g
      )
    );
  }

  pendientesCount(grupo: GrupoProgramacion): number {
    return grupo.modificaciones.filter(m => m.estado === 'pendiente').length;
  }

  tienePendientes(grupo: GrupoProgramacion): boolean {
    return grupo.modificaciones.some(m => m.estado === 'pendiente');
  }

  soloDocumentadas(grupo: GrupoProgramacion): boolean {
    return grupo.modificaciones.length > 0 && !this.tienePendientes(grupo);
  }

  tipoInfo(tipo: string): { label: string; color: string } {
    return TIPO_LABELS[tipo] ?? { label: tipo, color: 'bg-slate-100 text-slate-600' };
  }

  cambioResumen(mod: ModificacionItem): string {
    const ant = mod.datos_anteriores as Record<string, unknown> | null;
    const nue = mod.datos_nuevos    as Record<string, unknown> | null;
    switch (mod.tipo) {
      case 'cambio_aula':
        return `Aula: ${ant?.['aula_nombre'] ?? '—'} → ${nue?.['aula_nombre'] ?? '—'}`;
      case 'cambio_grupo':
        return `Grupo: ${ant?.['grupo_horario_nombre'] ?? '—'} → ${nue?.['grupo_horario_nombre'] ?? '—'}`;
      case 'cambio_aula_y_grupo':
        return `Aula: ${ant?.['aula_nombre'] ?? '—'} → ${nue?.['aula_nombre'] ?? '—'} · Grupo: ${ant?.['grupo_horario_nombre'] ?? '—'} → ${nue?.['grupo_horario_nombre'] ?? '—'}`;
      case 'cerrar_curso':
        return 'Sección marcada como llena';
      case 'abrir_seccion':
        return `Nueva sección — Aula: ${nue?.['aula_nombre'] ?? '—'} | Grupo: ${nue?.['grupo_horario_nombre'] ?? '—'}`;
      case 'unificacion_secciones':
        return 'Secciones unificadas';
      default:
        return '—';
    }
  }

  volver(): void {
    this.router.navigate(['/app/programacion/modificaciones']);
  }

  irAGenerar(): void {
    this.router.navigate(['/app/programacion/generar-documentos']);
  }
}
