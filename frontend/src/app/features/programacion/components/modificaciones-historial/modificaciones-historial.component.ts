import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { distinctUntilChanged, filter } from 'rxjs/operators';

import {
  GeneracionModificacionService,
  ModificacionItem,
} from '../../services/generacion-modificacion.service';
import { ProgramacionEstadoService } from '../../services/programacion-estado.service';
import {
  Programacion,
  ProgramacionService,
} from '../../../registro/services/programacion.service';

interface ProgramacionConMods {
  prog: Programacion;
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
  selector: 'app-modificaciones-historial',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './modificaciones-historial.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModificacionesHistorialComponent {
  protected readonly estadoSvc     = inject(ProgramacionEstadoService);
  private  readonly genSvc         = inject(GeneracionModificacionService);
  private  readonly programacionSvc = inject(ProgramacionService);
  private  readonly router          = inject(Router);

  readonly items    = signal<ProgramacionConMods[]>([]);
  readonly cargando = signal(false);
  readonly busqueda = signal('');

  readonly itemsFiltrados = computed(() => {
    const q = this.busqueda().toLowerCase().trim();
    if (!q) return this.items();
    return this.items().filter(({ prog }) =>
      prog.curso?.codigo?.toLowerCase().includes(q) ||
      prog.curso?.nombre?.toLowerCase().includes(q) ||
      (prog.escuela_programada?.nombre_corto ?? prog.escuela_programada?.nombre ?? '')
        .toLowerCase().includes(q)
    );
  });

  readonly totalConMods    = computed(() => this.items().filter(i => i.modificaciones.length > 0).length);
  readonly totalPendientes = computed(() =>
    this.items().reduce(
      (acc, i) => acc + i.modificaciones.filter(m => m.estado === 'pendiente').length,
      0
    )
  );

  constructor() {
    this.estadoSvc.cargarPeriodos();

    toObservable(this.estadoSvc.periodoId)
      .pipe(
        filter(id => !!id),
        distinctUntilChanged(),
        takeUntilDestroyed(),
      )
      .subscribe(id => this.cargar(id));
  }

  cargar(periodoId: string): void {
    this.cargando.set(true);
    forkJoin({
      progs: this.programacionSvc.getProgramacion(1, '', 500, periodoId),
      mods:  this.genSvc.listarTodasDelPeriodo(periodoId),
    }).subscribe({
      next: ({ progs, mods }) => {
        const modsPorProg = new Map<string, ModificacionItem[]>();
        for (const m of mods) {
          const pid = m.programacion?.id;
          if (pid) {
            if (!modsPorProg.has(pid)) modsPorProg.set(pid, []);
            modsPorProg.get(pid)!.push(m);
          }
        }

        const combined: ProgramacionConMods[] = progs.data.map(p => ({
          prog: p,
          modificaciones: modsPorProg.get(p.id) ?? [],
          expandido: false,
        }));

        // Con modificaciones primero (más cambios arriba), sin cambios al final
        combined.sort((a, b) => b.modificaciones.length - a.modificaciones.length);

        this.items.set(combined);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });
  }

  onPeriodoChange(id: string): void {
    this.estadoSvc.seleccionarPeriodo(id);
  }

  toggleExpandir(item: ProgramacionConMods): void {
    this.items.update(prev =>
      prev.map(i => i.prog.id === item.prog.id ? { ...i, expandido: !i.expandido } : i)
    );
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

  tienePendientes(item: ProgramacionConMods): boolean {
    return item.modificaciones.some(m => m.estado === 'pendiente');
  }

  soloDocumentadas(item: ProgramacionConMods): boolean {
    return item.modificaciones.length > 0 && !this.tienePendientes(item);
  }

  irAGenerar(): void {
    this.router.navigate(['/app/programacion/generar-documentos']);
  }
}
