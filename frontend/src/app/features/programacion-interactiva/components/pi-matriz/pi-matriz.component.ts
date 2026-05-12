import {
  Component, inject, input, output, signal, computed, effect, ChangeDetectionStrategy, ElementRef
} from '@angular/core';
import { CommonModule, NgTemplateOutlet } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion, BulkCambio } from '../../services/programacion-interactiva.service';
import { Pabellon, Aula } from '../../../configuracion/services/aula.service';
import { GrupoHorario } from '../../../configuracion/services/horario.service';

@Component({
  selector: 'app-pi-matriz',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, NgTemplateOutlet, FormsModule],
  templateUrl: './pi-matriz.component.html'
})
export class PiMatrizComponent {
  private piService = inject(ProgramacionInteractivaService);
  private elRef    = inject(ElementRef);

  borrador   = input.required<BorradorProgramacion>();
  pabellones = input.required<Pabellon[]>();
  grupos     = input.required<GrupoHorario[]>();

  seccionMovida    = output<BorradorSeccion>();
  seccionEliminada = output<string>();

  draggingId    = signal<string | null>(null);
  guardando     = signal(false);
  eliminando    = signal<string | null>(null);
  error         = signal<string | null>(null);

  private _lastDragY      = 0;
  private _rafId: number | null = null;
  private _scrollEl: HTMLElement | null = null;
  private readonly _trackDragY = (e: DragEvent) => {
    e.preventDefault();
    this._lastDragY = e.clientY;
  };

  // Filtros del panel "Sin Asignar"
  readonly filtroEscuelaPool = signal('');
  readonly filtroCicloPool   = signal(0);
  readonly filtroCursoPool   = signal('');

  private _secciones = signal<BorradorSeccion[]>([]);
  secciones = this._secciones.asReadonly();

  aulasActivas = computed((): Aula[] =>
    this.pabellones().flatMap(p => p.aulas.filter(a => a.activo))
  );

  gruposActivos = computed((): GrupoHorario[] =>
    this.grupos().filter(g => g.activo && g.detalles.length > 0)
  );

  readonly escuelasPool = computed(() => {
    const map = new Map<string, BorradorSeccion['escuela']>();
    for (const s of this.secciones().filter(s => !s.esta_asignado)) {
      if (!map.has(s.escuela.id)) map.set(s.escuela.id, s.escuela);
    }
    return Array.from(map.values()).sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  readonly ciclosPool = computed((): number[] => {
    const set = new Set<number>();
    for (const s of this.secciones().filter(s => !s.esta_asignado)) set.add(s.ciclo);
    return Array.from(set).sort((a, b) => a - b);
  });

  readonly seccionesSinAsignarFiltradas = computed((): BorradorSeccion[] => {
    let secs = this.secciones().filter(s => !s.esta_asignado);
    const escuela = this.filtroEscuelaPool();
    const ciclo   = this.filtroCicloPool();
    const curso   = this.filtroCursoPool().toLowerCase().trim();
    if (escuela) secs = secs.filter(s => s.escuela.id === escuela);
    if (ciclo)   secs = secs.filter(s => s.ciclo === ciclo);
    if (curso)   secs = secs.filter(s =>
      s.curso.nombre.toLowerCase().includes(curso) ||
      s.curso.codigo.toLowerCase().includes(curso)
    );
    return secs;
  });

  readonly hayFiltrosPool = computed(() =>
    !!this.filtroEscuelaPool() || !!this.filtroCicloPool() || !!this.filtroCursoPool().trim()
  );

  limpiarFiltrosPool(): void {
    this.filtroEscuelaPool.set('');
    this.filtroCicloPool.set(0);
    this.filtroCursoPool.set('');
  }

  constructor() {
    // Sincroniza secciones locales cuando el padre actualiza el borrador
    // (alta/baja de secciones), sin pisar cambios de drag-and-drop en vuelo
    effect(() => {
      this._secciones.set([...(this.borrador().secciones ?? [])]);
    });
  }

  /**
   * Devuelve las secciones para una celda específica (aulaId x grupoId).
   * aulaId=null && grupoId=null → columna "Sin asignar"
   */
  seccionesEnCelda(aulaId: string | null, grupoId: string | null): BorradorSeccion[] {
    if (aulaId === null && grupoId === null) {
      return this.secciones().filter(s => !s.esta_asignado);
    }
    return this.secciones().filter(s =>
      s.aula?.id === aulaId && s.grupo_horario?.id === grupoId
    );
  }

  private _findScrollContainer(): HTMLElement {
    let el = this.elRef.nativeElement as HTMLElement;
    while (el && el !== document.documentElement) {
      const ov = window.getComputedStyle(el).overflowY;
      if ((ov === 'auto' || ov === 'scroll') && el.scrollHeight > el.clientHeight) return el;
      el = el.parentElement!;
    }
    return document.documentElement;
  }

  onDragStart(event: DragEvent, seccionId: string): void {
    this.draggingId.set(seccionId);
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', seccionId);
    }
    this._scrollEl = this._findScrollContainer();
    document.addEventListener('dragover', this._trackDragY);
    this._startScrollLoop();
  }

  onDragEnd(): void {
    this.draggingId.set(null);
    this._scrollEl = null;
    document.removeEventListener('dragover', this._trackDragY);
    this._stopScrollLoop();
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  }

  private _startScrollLoop(): void {
    const THRESHOLD = 120;
    const loop = () => {
      const container = this._scrollEl;
      if (!container) { this._rafId = requestAnimationFrame(loop); return; }
      const rect = container.getBoundingClientRect();
      const y    = this._lastDragY;
      const relY = y - rect.top;
      const h    = rect.height;
      if (relY > 0 && relY < THRESHOLD) {
        container.scrollBy({ top: -Math.ceil(((THRESHOLD - relY) / THRESHOLD) * 20), behavior: 'instant' });
      } else if (relY > h - THRESHOLD) {
        container.scrollBy({ top: Math.ceil(((relY - (h - THRESHOLD)) / THRESHOLD) * 20), behavior: 'instant' });
      }
      this._rafId = requestAnimationFrame(loop);
    };
    this._rafId = requestAnimationFrame(loop);
  }

  private _stopScrollLoop(): void {
    if (this._rafId !== null) {
      cancelAnimationFrame(this._rafId);
      this._rafId = null;
    }
  }

  onDragEnter(event: DragEvent, cellEl: HTMLElement): void {
    event.preventDefault();
    cellEl.classList.add('ring-2', 'ring-indigo-400', 'bg-indigo-50');
  }

  onDragLeave(event: DragEvent, cellEl: HTMLElement): void {
    // Solo remover si salimos de la celda real (no de un hijo)
    if (!cellEl.contains(event.relatedTarget as Node)) {
      cellEl.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');
    }
  }

  onDrop(event: DragEvent, aulaId: string | null, grupoId: string | null, cellEl: HTMLElement): void {
    event.preventDefault();
    cellEl.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');

    const seccionId = event.dataTransfer?.getData('text/plain') ?? this.draggingId();
    if (!seccionId) return;

    const seccion = this._secciones().find(s => s.id === seccionId);
    if (!seccion) return;

    if (seccion.aula?.id === (aulaId ?? undefined) && seccion.grupo_horario?.id === (grupoId ?? undefined)) return;

    const snapshot = [...this._secciones()];

    const pabellon   = aulaId ? this.pabellones().find(p => p.aulas.some(a => a.id === aulaId)) ?? null : null;
    const aulaObj    = aulaId ? this.aulasActivas().find(a => a.id === aulaId) : null;
    const nuevaAula  = aulaObj
      ? { id: aulaObj.id, nombre: aulaObj.nombre, capacidad: aulaObj.capacidad, pabellon: pabellon ? { id: pabellon.id, nombre: pabellon.nombre } : null }
      : null;
    const nuevoGrupo = grupoId ? (this.gruposActivos().find(g => g.id === grupoId) ?? null) : null;

    // Swap: si la celda destino (aula+grupo específica) ya tiene un ocupante, intercambiar posiciones
    if (aulaId !== null && grupoId !== null) {
      const ocupante = this._secciones().find(s =>
        s.id !== seccionId &&
        s.aula?.id === aulaId &&
        s.grupo_horario?.id === grupoId
      );

      if (ocupante) {
        const aulaOriginalId  = seccion.aula?.id ?? null;
        const grupoOriginalId = seccion.grupo_horario?.id ?? null;

        const pabellonOrig = aulaOriginalId ? this.pabellones().find(p => p.aulas.some(a => a.id === aulaOriginalId)) ?? null : null;
        const aulaObjOrig  = aulaOriginalId ? this.aulasActivas().find(a => a.id === aulaOriginalId) : null;
        const aulaOrigObj  = aulaObjOrig
          ? { id: aulaObjOrig.id, nombre: aulaObjOrig.nombre, capacidad: aulaObjOrig.capacidad, pabellon: pabellonOrig ? { id: pabellonOrig.id, nombre: pabellonOrig.nombre } : null }
          : null;
        const grupoOrigObj = grupoOriginalId ? (this.gruposActivos().find(g => g.id === grupoOriginalId) ?? null) : null;

        this._secciones.update(secs => secs.map(s => {
          if (s.id === seccionId) return { ...s, aula: nuevaAula, grupo_horario: nuevoGrupo, esta_asignado: true };
          if (s.id === ocupante.id) return { ...s, aula: aulaOrigObj, grupo_horario: grupoOrigObj, esta_asignado: aulaOriginalId !== null && grupoOriginalId !== null };
          return s;
        }));

        this.seccionMovida.emit(this._secciones().find(s => s.id === seccionId)!);
        this.seccionMovida.emit(this._secciones().find(s => s.id === ocupante.id)!);

        this.guardando.set(true);
        this.error.set(null);

        const cambios: BulkCambio[] = [
          { id: seccionId,   aula_id: aulaId,        grupo_horario_id: grupoId },
          { id: ocupante.id, aula_id: aulaOriginalId, grupo_horario_id: grupoOriginalId }
        ];

        this.piService.bulkUpdate(this.borrador().id, cambios).subscribe({
          next: () => this.guardando.set(false),
          error: () => {
            this._secciones.set(snapshot);
            this.seccionMovida.emit(seccion);
            this.seccionMovida.emit(ocupante);
            this.guardando.set(false);
            this.error.set('Error al guardar el cambio. Intenta de nuevo.');
          }
        });
        return;
      }
    }

    // Movimiento normal a celda vacía o columna "Sin asignar"
    this._secciones.update(secs => secs.map(s => s.id !== seccionId ? s : {
      ...s,
      aula: nuevaAula,
      grupo_horario: nuevoGrupo,
      esta_asignado: aulaId !== null && grupoId !== null
    }));

    this.seccionMovida.emit(this._secciones().find(s => s.id === seccionId)!);

    this.guardando.set(true);
    this.error.set(null);

    const cambios: BulkCambio[] = [{ id: seccionId, aula_id: aulaId, grupo_horario_id: grupoId }];

    this.piService.bulkUpdate(this.borrador().id, cambios).subscribe({
      next: () => this.guardando.set(false),
      error: () => {
        this._secciones.set(snapshot);
        this.seccionMovida.emit(seccion);
        this.guardando.set(false);
        this.error.set('Error al guardar el cambio. Intenta de nuevo.');
      }
    });
  }

  eliminarSeccion(sec: BorradorSeccion, event: MouseEvent): void {
    event.stopPropagation();
    if (!confirm(`¿Eliminar "${sec.curso.nombre}" (Sec. ${sec.seccion})?`)) return;
    this.eliminando.set(sec.id);
    this.piService.deleteSeccion(this.borrador().id, sec.id).subscribe({
      next: () => {
        this._secciones.update(secs => secs.filter(s => s.id !== sec.id));
        this.seccionEliminada.emit(sec.id);
        this.eliminando.set(null);
      },
      error: () => this.eliminando.set(null)
    });
  }

  formatHorario(grupo: GrupoHorario): string {
    const dias: Record<string, string> = {
      lunes: 'L', martes: 'M', miercoles: 'X', jueves: 'J', viernes: 'V', sabado: 'S'
    };
    const detalles = grupo.detalles
      .map(d => `${dias[d.dia_semana] ?? d.dia_semana} ${d.hora_inicio.slice(0,5)}`)
      .join(' / ');
    return detalles;
  }

  private readonly COLORES = [
    { bg: 'bg-blue-100',    border: 'border-blue-300',    text: 'text-blue-800'    },
    { bg: 'bg-emerald-100', border: 'border-emerald-300', text: 'text-emerald-800' },
    { bg: 'bg-violet-100',  border: 'border-violet-300',  text: 'text-violet-800'  },
    { bg: 'bg-amber-100',   border: 'border-amber-300',   text: 'text-amber-800'   },
    { bg: 'bg-rose-100',    border: 'border-rose-300',    text: 'text-rose-800'    },
    { bg: 'bg-cyan-100',    border: 'border-cyan-300',    text: 'text-cyan-800'    },
    { bg: 'bg-orange-100',  border: 'border-orange-300',  text: 'text-orange-800'  },
    { bg: 'bg-teal-100',    border: 'border-teal-300',    text: 'text-teal-800'    },
    { bg: 'bg-fuchsia-100', border: 'border-fuchsia-300', text: 'text-fuchsia-800' },
    { bg: 'bg-lime-100',    border: 'border-lime-300',    text: 'text-lime-800'    },
    { bg: 'bg-sky-100',     border: 'border-sky-300',     text: 'text-sky-800'     },
    { bg: 'bg-pink-100',    border: 'border-pink-300',    text: 'text-pink-800'    },
    { bg: 'bg-indigo-100',  border: 'border-indigo-300',  text: 'text-indigo-800'  },
    { bg: 'bg-yellow-100',  border: 'border-yellow-300',  text: 'text-yellow-800'  },
    { bg: 'bg-red-100',     border: 'border-red-300',     text: 'text-red-800'     },
    { bg: 'bg-green-100',   border: 'border-green-300',   text: 'text-green-800'   },
    { bg: 'bg-purple-100',  border: 'border-purple-300',  text: 'text-purple-800'  },
    { bg: 'bg-slate-200',   border: 'border-slate-400',   text: 'text-slate-800'   },
    { bg: 'bg-stone-100',   border: 'border-stone-300',   text: 'text-stone-800'   },
    { bg: 'bg-zinc-100',    border: 'border-zinc-300',    text: 'text-zinc-800'    },
  ];

  private readonly _colorMap = computed((): Map<string, typeof this.COLORES[0]> => {
    const map = new Map<string, typeof this.COLORES[0]>();
    // Claves ordenadas para asignación determinista independiente del orden de llegada
    const claves = [...new Set(this.secciones().map(s => `${s.escuela.id}|${s.ciclo}`))].sort();
    claves.forEach((clave, idx) => map.set(clave, this.COLORES[idx % this.COLORES.length]));
    return map;
  });

  seccionColor(sec: BorradorSeccion): typeof this.COLORES[0] {
    return this._colorMap().get(`${sec.escuela.id}|${sec.ciclo}`) ?? this.COLORES[0];
  }

  readonly leyendaColores = computed(() => {
    const map   = this._colorMap();
    const secs  = this.secciones();
    return [...map.entries()].map(([clave, color]) => {
      const [escuelaId, cicloStr] = clave.split('|');
      const ref = secs.find(s => s.escuela.id === escuelaId && String(s.ciclo) === cicloStr);
      return { escuela: ref?.escuela.nombre_corto ?? escuelaId, ciclo: Number(cicloStr), color };
    }).sort((a, b) => a.escuela.localeCompare(b.escuela) || a.ciclo - b.ciclo);
  });

  isDragging(id: string): boolean {
    return this.draggingId() === id;
  }
}
