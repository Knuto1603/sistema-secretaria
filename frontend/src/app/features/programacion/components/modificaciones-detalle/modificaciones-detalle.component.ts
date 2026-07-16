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

interface GrupoTipo {
  tipo: string;
  modificaciones: ModificacionItem[];
  expandido: boolean;
}

const TIPO_LABELS: Record<string, { label: string; color: string; borde: string }> = {
  cerrar_curso:          { label: 'Cierre',       color: 'bg-red-100 text-red-700',     borde: 'border-l-red-400' },
  abrir_seccion:         { label: 'Apertura',     color: 'bg-emerald-100 text-emerald-700', borde: 'border-l-emerald-400' },
  reabrir_seccion:       { label: 'Reapertura',   color: 'bg-teal-100 text-teal-700',   borde: 'border-l-teal-400' },
  cambio_aula:           { label: 'Cambio Aula',  color: 'bg-blue-100 text-blue-700',   borde: 'border-l-blue-400' },
  cambio_grupo:          { label: 'Cambio Grupo', color: 'bg-violet-100 text-violet-700', borde: 'border-l-violet-400' },
  cambio_aula_y_grupo:   { label: 'Aula+Grupo',   color: 'bg-sky-100 text-sky-700',     borde: 'border-l-sky-400' },
  unificacion_secciones: { label: 'Unificación',  color: 'bg-amber-100 text-amber-700', borde: 'border-l-amber-400' },
};

// Orden fijo que sigue el flujo de negocio: cierres/aperturas primero, luego cambios, luego unificaciones
const TIPO_ORDEN = [
  'cerrar_curso', 'abrir_seccion', 'reabrir_seccion',
  'cambio_aula', 'cambio_grupo', 'cambio_aula_y_grupo',
  'unificacion_secciones',
];

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
  readonly grupos   = signal<GrupoTipo[]>([]);
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

        // Agrupar por tipo de modificación — solo tipos con cambios
        const mapaGrupos = new Map<string, GrupoTipo>();
        for (const m of mods) {
          if (!mapaGrupos.has(m.tipo)) {
            mapaGrupos.set(m.tipo, { tipo: m.tipo, modificaciones: [], expandido: false });
          }
          mapaGrupos.get(m.tipo)!.modificaciones.push(m);
        }

        // Sigue el orden del flujo de negocio (cierres → aperturas → cambios → unificaciones)
        const sorted = [...mapaGrupos.values()].sort((a, b) =>
          TIPO_ORDEN.indexOf(a.tipo) - TIPO_ORDEN.indexOf(b.tipo)
        );

        this.grupos.set(sorted);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });
  }

  toggleExpandir(grupo: GrupoTipo): void {
    this.grupos.update(prev =>
      prev.map(g => g.tipo === grupo.tipo
        ? { ...g, expandido: !g.expandido }
        : g
      )
    );
  }

  pendientesCount(grupo: GrupoTipo): number {
    return grupo.modificaciones.filter(m => m.estado === 'pendiente').length;
  }

  tienePendientes(grupo: GrupoTipo): boolean {
    return grupo.modificaciones.some(m => m.estado === 'pendiente');
  }

  soloDocumentadas(grupo: GrupoTipo): boolean {
    return grupo.modificaciones.length > 0 && !this.tienePendientes(grupo);
  }

  tipoInfo(tipo: string): { label: string; color: string; borde: string } {
    return TIPO_LABELS[tipo] ?? { label: tipo, color: 'bg-slate-100 text-slate-600', borde: 'border-l-slate-300' };
  }

  seccionLabel(mod: ModificacionItem): string {
    const c = mod.programacion?.curso;
    return c ? `${c.codigo} · Sec. ${mod.programacion?.seccion ?? '—'}` : '—';
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
