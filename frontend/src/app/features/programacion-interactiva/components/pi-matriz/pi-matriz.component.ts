import {
  Component, inject, input, output, signal, computed, effect,
  ChangeDetectionStrategy, ElementRef, ViewChild, HostListener
} from '@angular/core';
import { CommonModule, NgTemplateOutlet } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { of, throwError } from 'rxjs';
import { catchError, switchMap, tap } from 'rxjs/operators';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion, BulkCambio } from '../../services/programacion-interactiva.service';
import { Pabellon, Aula } from '../../../configuracion/services/aula.service';
import { GrupoHorario, HorarioService } from '../../../configuracion/services/horario.service';

@Component({
  selector: 'app-pi-matriz',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, NgTemplateOutlet, FormsModule],
  templateUrl: './pi-matriz.component.html'
})
export class PiMatrizComponent {
  private piService     = inject(ProgramacionInteractivaService);
  private horarioService = inject(HorarioService);
  private elRef    = inject(ElementRef);

  @ViewChild('scrollTable') private _scrollTableRef!: ElementRef<HTMLElement>;

  borrador   = input.required<BorradorProgramacion>();
  pabellones = input.required<Pabellon[]>();
  grupos     = input.required<GrupoHorario[]>();

  seccionMovida    = output<BorradorSeccion>();
  seccionEliminada = output<string>();

  draggingId      = signal<string | null>(null);
  guardando       = signal(false);
  eliminando      = signal<string | null>(null);
  error           = signal<string | null>(null);
  pantallaCompleta = signal(false);

  readonly tablaWrapperClases = computed(() =>
    this.pantallaCompleta()
      ? 'fixed inset-0 z-50 bg-white flex flex-col p-4 gap-3'
      : 'flex flex-col gap-0 rounded-md border border-slate-200 shadow-sm overflow-hidden'
  );

  togglePantallaCompleta(): void {
    this.pantallaCompleta.update(v => !v);
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.pantallaCompleta()) this.pantallaCompleta.set(false);
  }

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

  /** Columnas de la matriz: aulas normales primero (por pabellón), laboratorios al final. */
  columnas = computed(() => {
    interface Columna { aula: Aula; pabellon: Pabellon; esInicioLabs: boolean; mostrarSepPabellon: boolean; }
    const filas: Columna[] = [];

    const normales: { aula: Aula; pabellon: Pabellon }[] = [];
    const labs:     { aula: Aula; pabellon: Pabellon }[] = [];

    for (const p of this.pabellones()) {
      for (const a of p.aulas) {
        if (!a.activo) continue;
        (a.es_laboratorio ? labs : normales).push({ aula: a, pabellon: p });
      }
    }

    let lastPabId = '';
    for (const item of normales) {
      filas.push({ ...item, esInicioLabs: false, mostrarSepPabellon: item.pabellon.id !== lastPabId });
      lastPabId = item.pabellon.id;
    }
    lastPabId = '';
    for (let i = 0; i < labs.length; i++) {
      const item = labs[i];
      filas.push({ ...item, esInicioLabs: i === 0, mostrarSepPabellon: item.pabellon.id !== lastPabId });
      lastPabId = item.pabellon.id;
    }

    return filas;
  });

  /** Grupos creados sobre la marcha (al soltar en una columna cuyo código G{n}{letras} aún no existía) */
  private _gruposExtra = signal<GrupoHorario[]>([]);

  private todosGrupos = computed((): GrupoHorario[] => [...this.grupos(), ...this._gruposExtra()]);

  gruposActivos = computed((): GrupoHorario[] =>
    this.todosGrupos().filter(g => g.activo && g.detalles.length > 0)
  );

  /** Columnas simplificadas de la matriz: los 14 grupos base de la Plantilla Horaria, sin importar el subgrupo (letras). */
  readonly gruposBase = computed((): number[] => Array.from({ length: 14 }, (_, i) => i + 1));

  private readonly DIAS_LABEL: Record<string, string> = {
    lunes: 'L', martes: 'M', miercoles: 'X', jueves: 'J', viernes: 'V', sabado: 'S'
  };
  private readonly ORDEN_DIAS = ['L', 'M', 'X', 'J', 'V', 'S'];

  /** Código de grupo (ej. "G7ABH") → { numero: 7, letras: "ABH" }. Null si no sigue la convención G+número[+letras]. */
  private parseGrupoCodigo(nombre: string | null | undefined): { numero: number; letras: string } | null {
    if (!nombre) return null;
    const m = /^G(\d+)([A-Za-z]*)$/i.exec(nombre.trim());
    if (!m) return null;
    return { numero: parseInt(m[1], 10), letras: m[2].toUpperCase() };
  }

  numeroGrupoDe(sec: BorradorSeccion): number | null {
    return this.parseGrupoCodigo(sec.grupo_horario?.nombre)?.numero ?? null;
  }

  /** Días (unión) que ocupa un grupo base, a partir de los subgrupos ya existentes; si ninguno existe aún, usa el patrón institucional (impar: L,M,X · par: J,V,X). */
  diasBaseGrupo(numero: number): string {
    const dias = new Set<string>();
    for (const g of this.gruposActivos()) {
      if (this.parseGrupoCodigo(g.nombre)?.numero === numero) {
        g.detalles.forEach(d => dias.add(this.DIAS_LABEL[d.dia_semana] ?? d.dia_semana));
      }
    }
    if (dias.size > 0) return this.ORDEN_DIAS.filter(d => dias.has(d)).join(', ');
    return numero % 2 === 1 ? 'L, M, X' : 'J, V, X';
  }

  /** Letras por defecto según las horas semanales del curso (1=H, 2=A, 3=AH, 4=AB, 5+=ABH). */
  private letrasPorDefecto(horas: number | null | undefined): string {
    if (horas === 1) return 'H';
    if (horas === 2) return 'A';
    if (horas === 3) return 'AH';
    if (horas === 4) return 'AB';
    return 'ABH';
  }

  /** Busca (o crea) el GrupoHorario para un código como "G1ABH", generando su horario automáticamente si es nuevo. */
  private resolverGrupoPorCodigo(codigo: string) {
    const existente = this.todosGrupos().find(g => g.nombre.toUpperCase() === codigo.toUpperCase());
    if (existente) return of(existente);

    return this.horarioService.crearGrupo(codigo).pipe(
      tap(g => this._gruposExtra.update(arr => [...arr, g])),
      catchError(() => this.horarioService.getGrupos().pipe(
        switchMap(gs => {
          this._gruposExtra.set(gs.filter(g => !this.grupos().some(orig => orig.id === g.id)));
          const encontrado = gs.find(g => g.nombre.toUpperCase() === codigo.toUpperCase());
          return encontrado ? of(encontrado) : throwError(() => new Error('No se pudo resolver el grupo ' + codigo));
        })
      ))
    );
  }

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
   * Devuelve las secciones para una celda específica (aulaId x número de grupo base 1-14).
   * aulaId=null && numeroGrupo=null → columna "Sin asignar"
   * El match ignora las letras del subgrupo: G1AB y G1H caen en la misma celda (grupo 1).
   */
  seccionesEnCelda(aulaId: string | null, numeroGrupo: number | null): BorradorSeccion[] {
    if (aulaId === null && numeroGrupo === null) {
      return this.secciones().filter(s => !s.esta_asignado);
    }
    return this.secciones().filter(s =>
      s.aula?.id === aulaId && this.numeroGrupoDe(s) === numeroGrupo
    );
  }

  private _findScrollContainer(): HTMLElement {
    if (this._scrollTableRef?.nativeElement) return this._scrollTableRef.nativeElement;
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

  /**
   * aulaId/numeroGrupo son la celda destino (numeroGrupo = número base 1-14, sin letras).
   * Si la sección ya tenía grupo, conserva sus mismas letras (mismas horas/semana, solo cambia el bloque horario).
   * Si no tenía grupo (viene de "Sin Asignar"), las letras se derivan de las horas semanales del curso.
   */
  onDrop(event: DragEvent, aulaId: string | null, numeroGrupo: number | null, cellEl: HTMLElement): void {
    event.preventDefault();
    cellEl.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');

    const seccionId = event.dataTransfer?.getData('text/plain') ?? this.draggingId();
    if (!seccionId) return;

    const seccion = this._secciones().find(s => s.id === seccionId);
    if (!seccion) return;

    if (seccion.aula?.id === (aulaId ?? undefined) && this.numeroGrupoDe(seccion) === numeroGrupo) return;

    // Destino "Sin Asignar": limpiar aula y grupo, no requiere resolver ningún código
    if (aulaId === null || numeroGrupo === null) {
      this._moverSeccion(seccion, null, null, null);
      return;
    }

    const letras = this.parseGrupoCodigo(seccion.grupo_horario?.nombre)?.letras
      || this.letrasPorDefecto(seccion.horas_semanales);
    const codigo = `G${numeroGrupo}${letras}`;

    this.resolverGrupoPorCodigo(codigo).subscribe({
      next: grupo => this._moverSeccion(seccion, aulaId, grupo, numeroGrupo),
      error: () => this.error.set(`No se pudo preparar el horario ${codigo}.`)
    });
  }

  /** Aplica el movimiento (con swap si la celda destino ya tiene ocupante) y persiste el cambio. */
  private _moverSeccion(seccion: BorradorSeccion, aulaId: string | null, grupo: GrupoHorario | null, numeroGrupo: number | null): void {
    const seccionId = seccion.id;
    const snapshot = [...this._secciones()];

    const pabellon  = aulaId ? this.pabellones().find(p => p.aulas.some(a => a.id === aulaId)) ?? null : null;
    const aulaObj   = aulaId ? this.aulasActivas().find(a => a.id === aulaId) : null;
    const nuevaAula = aulaObj
      ? { id: aulaObj.id, nombre: aulaObj.nombre, capacidad: aulaObj.capacidad, pabellon: pabellon ? { id: pabellon.id, nombre: pabellon.nombre } : null }
      : null;

    // Swap: si la celda destino (aula + grupo base) ya tiene un ocupante, intercambiar posiciones
    const ocupante = (aulaId !== null && numeroGrupo !== null)
      ? this._secciones().find(s => s.id !== seccionId && s.aula?.id === aulaId && this.numeroGrupoDe(s) === numeroGrupo)
      : undefined;

    if (ocupante) {
      const aulaOriginalId = seccion.aula?.id ?? null;
      const grupoOriginal  = seccion.grupo_horario ?? null;

      const pabellonOrig = aulaOriginalId ? this.pabellones().find(p => p.aulas.some(a => a.id === aulaOriginalId)) ?? null : null;
      const aulaObjOrig  = aulaOriginalId ? this.aulasActivas().find(a => a.id === aulaOriginalId) : null;
      const aulaOrigObj  = aulaObjOrig
        ? { id: aulaObjOrig.id, nombre: aulaObjOrig.nombre, capacidad: aulaObjOrig.capacidad, pabellon: pabellonOrig ? { id: pabellonOrig.id, nombre: pabellonOrig.nombre } : null }
        : null;

      this._secciones.update(secs => secs.map(s => {
        if (s.id === seccionId) return { ...s, aula: nuevaAula, grupo_horario: grupo, esta_asignado: true };
        if (s.id === ocupante.id) return { ...s, aula: aulaOrigObj, grupo_horario: grupoOriginal, esta_asignado: aulaOriginalId !== null && !!grupoOriginal };
        return s;
      }));

      this.seccionMovida.emit(this._secciones().find(s => s.id === seccionId)!);
      this.seccionMovida.emit(this._secciones().find(s => s.id === ocupante.id)!);

      this.guardando.set(true);
      this.error.set(null);

      const cambios: BulkCambio[] = [
        { id: seccionId,   aula_id: aulaId,         grupo_horario_id: grupo?.id ?? null },
        { id: ocupante.id, aula_id: aulaOriginalId, grupo_horario_id: grupoOriginal?.id ?? null }
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

    // Movimiento normal a celda vacía o columna "Sin asignar"
    this._secciones.update(secs => secs.map(s => s.id !== seccionId ? s : {
      ...s,
      aula: nuevaAula,
      grupo_horario: grupo,
      esta_asignado: aulaId !== null && !!grupo
    }));

    this.seccionMovida.emit(this._secciones().find(s => s.id === seccionId)!);

    this.guardando.set(true);
    this.error.set(null);

    const cambios: BulkCambio[] = [{ id: seccionId, aula_id: aulaId, grupo_horario_id: grupo?.id ?? null }];

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
