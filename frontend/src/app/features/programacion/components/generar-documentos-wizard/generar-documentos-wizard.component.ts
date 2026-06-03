import { Component, inject, signal, computed, OnInit, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  GeneracionModificacionService,
  GeneracionItem,
  PreviewGrupo,
} from '../../services/generacion-modificacion.service';
import { ProgramacionEstadoService } from '../../services/programacion-estado.service';

interface AreaGroup {
  area_id: string;
  area_nombre: string;
  grupos: PreviewGrupo[];
}

const TIPO_BADGE: Record<string, string> = {
  cierre:          'bg-red-100 text-red-700',
  cierre_apertura: 'bg-orange-100 text-orange-700',
  fusion:          'bg-blue-100 text-blue-700',
  cambio_aula:     'bg-purple-100 text-purple-700',
};

@Component({
  selector: 'app-generar-documentos-wizard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './generar-documentos-wizard.component.html',
})
export class GenerarDocumentosWizardComponent implements OnInit {
  private svc    = inject(GeneracionModificacionService);
  readonly estado = inject(ProgramacionEstadoService);

  // ── Datos ─────────────────────────────────────────────────────────────────
  grupos      = signal<PreviewGrupo[]>([]);
  cargando    = signal(false);
  errorMsg    = signal('');

  // ── Selección ─────────────────────────────────────────────────────────────
  seleccionados = signal<Set<string>>(new Set());

  // ── Confirmación ──────────────────────────────────────────────────────────
  numeroOficio = signal('');
  generando    = signal(false);

  // ── Historial ─────────────────────────────────────────────────────────────
  historial    = signal<GeneracionItem[]>([]);
  cargandoHist = signal(false);
  eliminandoId = signal<string | null>(null);

  // ── Computed ──────────────────────────────────────────────────────────────

  areaGroups = computed<AreaGroup[]>(() => {
    const map = new Map<string, AreaGroup>();
    for (const g of this.grupos()) {
      if (!map.has(g.area_id)) {
        map.set(g.area_id, { area_id: g.area_id, area_nombre: g.area_nombre, grupos: [] });
      }
      map.get(g.area_id)!.grupos.push(g);
    }
    return Array.from(map.values());
  });

  totalSeleccionados = computed(() => this.seleccionados().size);

  documentosAGenerar = computed(() => {
    const sel = this.seleccionados();
    return this.grupos().filter(g => g.modificaciones.some(m => sel.has(m.id))).length;
  });

  totalModificaciones = computed(() =>
    this.grupos().reduce((acc, g) => acc + g.modificaciones.length, 0)
  );

  puedeConfirmar = computed(() =>
    this.totalSeleccionados() > 0 &&
    this.numeroOficio().trim().length > 0 &&
    !this.generando()
  );

  globalState = computed((): 'all' | 'some' | 'none' => {
    const total = this.grupos().reduce((a, g) => a + g.modificaciones.length, 0);
    const sel   = this.seleccionados().size;
    if (total === 0 || sel === 0) return 'none';
    if (sel === total)            return 'all';
    return 'some';
  });

  constructor() {
    effect(() => {
      const id = this.estado.periodoId();
      if (id) this.cargarPendientes();
    }, { allowSignalWrites: true });
  }

  ngOnInit(): void {
    this.cargarHistorial();
  }

  // ── Carga ─────────────────────────────────────────────────────────────────

  cargarPendientes(): void {
    this.cargando.set(true);
    this.errorMsg.set('');
    this.grupos.set([]);
    this.seleccionados.set(new Set());

    this.svc.preview(this.estado.periodoId()).subscribe({
      next: data => {
        this.grupos.set(data);
        const todos = data.flatMap(g => g.modificaciones.map(m => m.id));
        this.seleccionados.set(new Set(todos));
        this.cargando.set(false);
      },
      error: (err) => {
        this.errorMsg.set(err?.error?.message ?? 'Error al cargar modificaciones pendientes');
        this.cargando.set(false);
      },
    });
  }

  cargarHistorial(): void {
    this.cargandoHist.set(true);
    this.svc.historialGeneraciones().subscribe({
      next: data => { this.historial.set(data); this.cargandoHist.set(false); },
      error: ()  => this.cargandoHist.set(false),
    });
  }

  // ── Selección ─────────────────────────────────────────────────────────────

  toggleGlobal(): void {
    if (this.globalState() === 'all') {
      this.seleccionados.set(new Set());
    } else {
      const todos = this.grupos().flatMap(g => g.modificaciones.map(m => m.id));
      this.seleccionados.set(new Set(todos));
    }
  }

  grupoState(grupo: PreviewGrupo): 'all' | 'some' | 'none' {
    const ids   = grupo.modificaciones.map(m => m.id);
    const sel   = this.seleccionados();
    const count = ids.filter(id => sel.has(id)).length;
    if (count === 0)        return 'none';
    if (count === ids.length) return 'all';
    return 'some';
  }

  areaState(area: AreaGroup): 'all' | 'some' | 'none' {
    const ids   = area.grupos.flatMap(g => g.modificaciones.map(m => m.id));
    const sel   = this.seleccionados();
    const count = ids.filter(id => sel.has(id)).length;
    if (count === 0)        return 'none';
    if (count === ids.length) return 'all';
    return 'some';
  }

  toggleMod(id: string): void {
    this.seleccionados.update(set => {
      const next = new Set(set);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  }

  toggleGrupo(grupo: PreviewGrupo): void {
    const ids   = grupo.modificaciones.map(m => m.id);
    const state = this.grupoState(grupo);
    this.seleccionados.update(set => {
      const next = new Set(set);
      if (state === 'all') ids.forEach(id => next.delete(id));
      else                 ids.forEach(id => next.add(id));
      return next;
    });
  }

  toggleArea(area: AreaGroup): void {
    const ids   = area.grupos.flatMap(g => g.modificaciones.map(m => m.id));
    const state = this.areaState(area);
    this.seleccionados.update(set => {
      const next = new Set(set);
      if (state === 'all') ids.forEach(id => next.delete(id));
      else                 ids.forEach(id => next.add(id));
      return next;
    });
  }

  // ── Generación ────────────────────────────────────────────────────────────

  confirmarGeneracion(): void {
    if (!this.puedeConfirmar()) return;
    this.generando.set(true);
    this.errorMsg.set('');

    const ids = Array.from(this.seleccionados());

    this.svc.generar(this.estado.periodoId(), this.numeroOficio(), ids).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = `modificaciones_${this.numeroOficio()}.zip`;
        a.click();
        URL.revokeObjectURL(url);
        this.generando.set(false);
        this.numeroOficio.set('');
        this.cargarPendientes();
        this.cargarHistorial();
      },
      error: (err) => {
        this.errorMsg.set(err?.error?.message ?? 'Error al generar documentos');
        this.generando.set(false);
      },
    });
  }

  descargarZip(id: string, numeroOficio: string): void {
    this.svc.descargarZip(id).subscribe(blob => {
      const url = URL.createObjectURL(blob);
      const a   = document.createElement('a');
      a.href     = url;
      a.download = `modificaciones_${numeroOficio}.zip`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  eliminarGeneracion(id: string): void {
    if (!confirm('¿Eliminar esta generación y sus archivos?')) return;
    this.eliminandoId.set(id);
    this.svc.eliminarGeneracion(id).subscribe({
      next: () => {
        this.historial.update(h => h.filter(g => g.id !== id));
        this.eliminandoId.set(null);
      },
      error: () => this.eliminandoId.set(null),
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  tipoBadgeClass(tipo: string): string {
    return TIPO_BADGE[tipo] ?? 'bg-slate-100 text-slate-600';
  }

  trackByAreaId(_: number, area: AreaGroup): string { return area.area_id; }
  trackByGrupo(_: number, g: PreviewGrupo): string  { return `${g.area_id}_${g.tipo_documento}`; }
  trackById(_: number, item: { id: string }): string { return item.id; }
}
