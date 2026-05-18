import { Component, input, computed, output, signal, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Programacion } from '../../services/programacion.service';

export interface CambioPendiente {
  programacionId: string;
  cursoNombre: string;
  cursoCode: string;
  seccion: string;
  anteriorAulaId: string | null;
  anteriorAulaNombre: string;
  anteriorGrupoId: string | null;
  anteriorGrupoNombre: string;
  nuevaAulaId: string | null;
  nuevaAulaNombre: string;
  nuevoGrupoId: string | null;
  nuevoGrupoNombre: string;
}

interface AulaColumna { id: string | null; nombre: string; }
interface GrupoFila   { id: string | null; nombre: string; }

@Component({
  selector: 'app-programacion-matriz',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './programacion-matriz.component.html',
})
export class ProgramacionMatrizComponent {
  items   = input.required<Programacion[]>();
  loading = input<boolean>(false);
  modo    = input<'consultar' | 'modificar'>('consultar');

  verDetalle           = output<string>();
  cambiosPendientesOut = output<CambioPendiente[]>();

  // ─── Estado local para modo modificar ───────────────────────────────────────
  private _itemsLocal    = signal<Programacion[]>([]);
  readonly cambiosPendientes = signal<CambioPendiente[]>([]);
  draggingId             = signal<string | null>(null);

  constructor() {
    effect(() => { this._itemsLocal.set([...this.items()]); });
  }

  // ─── MODO CONSULTAR: computed actuales ───────────────────────────────────────

  grupos = computed(() => {
    const gs = [...new Set(this.items().map(p => p.grupo).filter(Boolean))];
    return gs.sort((a, b) => {
      const na = parseInt(a?.replace(/\D/g, '') ?? '0');
      const nb = parseInt(b?.replace(/\D/g, '') ?? '0');
      return na - nb;
    });
  });

  aulas = computed(() => {
    const as = [...new Set(this.items().map(p => this.aulaNombre(p)).filter(Boolean))];
    return as.sort();
  });

  matriz = computed(() => {
    const map = new Map<string, Map<string, Programacion[]>>();
    for (const item of this.items()) {
      const grupo = item.grupo;
      const aula  = this.aulaNombre(item);
      if (!grupo || !aula) continue;
      if (!map.has(grupo)) map.set(grupo, new Map());
      const aulaMap = map.get(grupo)!;
      if (!aulaMap.has(aula)) aulaMap.set(aula, []);
      aulaMap.get(aula)!.push(item);
    }
    return map;
  });

  getCeldas(grupo: string, aula: string): Programacion[] {
    return this.matriz().get(grupo)?.get(aula) ?? [];
  }

  hasCambioPendiente(progId: string): boolean {
    return this.cambiosPendientes().some(c => c.programacionId === progId);
  }

  // ─── MODO MODIFICAR: computed ID-based ──────────────────────────────────────

  aulaColumnasMod = computed((): AulaColumna[] => {
    const map = new Map<string | null, string>();
    for (const p of this._itemsLocal()) {
      if (!map.has(p.aula_id)) map.set(p.aula_id, this.aulaNombre(p) || 'Sin aula');
    }
    return [...map.entries()]
      .map(([id, nombre]) => ({ id, nombre }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  gruposFilasMod = computed((): GrupoFila[] => {
    const map = new Map<string | null, string>();
    for (const p of this._itemsLocal()) {
      if (!map.has(p.grupo_horario_id)) {
        map.set(p.grupo_horario_id, p.grupo_horario?.nombre ?? p.grupo ?? 'Sin horario');
      }
    }
    return [...map.entries()]
      .map(([id, nombre]) => ({ id, nombre }))
      .sort((a, b) => {
        const na = parseInt(a.nombre.replace(/\D/g, '') || '0');
        const nb = parseInt(b.nombre.replace(/\D/g, '') || '0');
        return na - nb;
      });
  });

  getCeldaMod(aulaId: string | null, grupoId: string | null): Programacion[] {
    return this._itemsLocal().filter(p =>
      p.aula_id === aulaId && p.grupo_horario_id === grupoId
    );
  }

  // ─── DnD modo modificar ─────────────────────────────────────────────────────

  onDragStartMod(event: DragEvent, id: string): void {
    this.draggingId.set(id);
    event.dataTransfer?.setData('text/plain', id);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
  }

  onDragEndMod(): void { this.draggingId.set(null); }

  onDragOverMod(event: DragEvent, el: HTMLElement): void {
    event.preventDefault();
    el.classList.add('ring-2', 'ring-indigo-400', 'bg-indigo-50/60');
  }

  onDragLeaveMod(event: DragEvent, el: HTMLElement): void {
    if (!el.contains(event.relatedTarget as Node))
      el.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50/60');
  }

  onDropMod(event: DragEvent, nuevaAulaId: string | null, nuevaAulaNombre: string,
            nuevoGrupoId: string | null, nuevoGrupoNombre: string, el: HTMLElement): void {
    event.preventDefault();
    el.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50/60');

    const id = event.dataTransfer?.getData('text/plain') ?? this.draggingId();
    if (!id) return;

    const prog = this._itemsLocal().find(p => p.id === id);
    if (!prog) return;
    if (prog.aula_id === nuevaAulaId && prog.grupo_horario_id === nuevoGrupoId) return;

    const anteriorAulaId     = prog.aula_id;
    const anteriorAulaNombre = this.aulaNombre(prog) || 'Sin aula';
    const anteriorGrupoId    = prog.grupo_horario_id;
    const anteriorGrupoNombre = prog.grupo_horario?.nombre ?? prog.grupo ?? 'Sin horario';

    // Actualización local optimista
    this._itemsLocal.update(items => items.map(p =>
      p.id !== id ? p : { ...p, aula_id: nuevaAulaId, aula_nombre: nuevaAulaNombre,
        grupo_horario_id: nuevoGrupoId, grupo: nuevoGrupoNombre }
    ));

    // Acumular cambio (deduplicar por programacionId)
    const cambio: CambioPendiente = {
      programacionId: id,
      cursoNombre: prog.curso?.nombre ?? '',
      cursoCode: prog.curso?.codigo ?? '',
      seccion: prog.seccion ?? '',
      anteriorAulaId, anteriorAulaNombre,
      anteriorGrupoId, anteriorGrupoNombre,
      nuevaAulaId, nuevaAulaNombre,
      nuevoGrupoId, nuevoGrupoNombre,
    };

    this.cambiosPendientes.update(prev => {
      const sin = prev.filter(c => c.programacionId !== id);
      // Si el nuevo estado es igual al original, quitar el cambio pendiente
      if (nuevaAulaId === anteriorAulaId && nuevoGrupoId === anteriorGrupoId) return sin;
      return [...sin, cambio];
    });

    this.cambiosPendientesOut.emit(this.cambiosPendientes());
  }

  descartarCambios(): void {
    this._itemsLocal.set([...this.items()]);
    this.cambiosPendientes.set([]);
    this.cambiosPendientesOut.emit([]);
  }

  // ─── Helpers comunes ─────────────────────────────────────────────────────────

  aulaNombre(p: Programacion): string {
    return p.aula_nombre || p.aula || p.aula_rel?.nombre || '';
  }

  escuelasLabel(p: Programacion): string {
    if (!p.escuelas?.length) return '';
    return p.escuelas.map(e => e.nombre_corto ?? e.nombre).join(', ');
  }

  getCeldaColor(prog: Programacion): string {
    if (prog.esta_lleno) return 'bg-red-50 border-red-200';
    return 'bg-emerald-50 border-emerald-200';
  }

  isDragging(id: string): boolean { return this.draggingId() === id; }

  trackByGrupo(_: number, g: string)       { return g; }
  trackByAula(_: number, a: string)        { return a; }
  trackByProg(_: number, p: Programacion)  { return p.id; }
  trackById(_: number, x: { id: string | null }) { return x.id; }
}
