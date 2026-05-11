import {
  Component, inject, input, output, signal, computed, effect, ChangeDetectionStrategy
} from '@angular/core';
import { CommonModule, NgTemplateOutlet } from '@angular/common';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion, BulkCambio } from '../../services/programacion-interactiva.service';
import { Pabellon, Aula } from '../../../configuracion/services/aula.service';
import { GrupoHorario } from '../../../configuracion/services/horario.service';

@Component({
  selector: 'app-pi-matriz',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, NgTemplateOutlet],
  templateUrl: './pi-matriz.component.html'
})
export class PiMatrizComponent {
  private piService = inject(ProgramacionInteractivaService);

  borrador   = input.required<BorradorProgramacion>();
  pabellones = input.required<Pabellon[]>();
  grupos     = input.required<GrupoHorario[]>();

  seccionMovida    = output<BorradorSeccion>();
  seccionEliminada = output<string>();

  draggingId    = signal<string | null>(null);
  guardando     = signal(false);
  eliminando    = signal<string | null>(null);
  error         = signal<string | null>(null);

  private _secciones = signal<BorradorSeccion[]>([]);
  secciones = this._secciones.asReadonly();

  aulasActivas = computed((): Aula[] =>
    this.pabellones().flatMap(p => p.aulas.filter(a => a.activo))
  );

  gruposActivos = computed((): GrupoHorario[] =>
    this.grupos().filter(g => g.activo && g.detalles.length > 0)
  );

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

  onDragStart(event: DragEvent, seccionId: string): void {
    this.draggingId.set(seccionId);
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', seccionId);
    }
  }

  onDragEnd(): void {
    this.draggingId.set(null);
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
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

  private readonly _escuelaColorMap = computed((): Map<string, string> => {
    const colors = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700',
                    'bg-amber-100 text-amber-700', 'bg-violet-100 text-violet-700'];
    const map = new Map<string, string>();
    let idx = 0;
    for (const s of this.secciones()) {
      if (!map.has(s.escuela.id)) {
        map.set(s.escuela.id, colors[idx % colors.length]);
        idx++;
      }
    }
    return map;
  });

  escuelaColor(escuela: BorradorSeccion['escuela']): string {
    return this._escuelaColorMap().get(escuela.id) ?? 'bg-indigo-100 text-indigo-700';
  }

  isDragging(id: string): boolean {
    return this.draggingId() === id;
  }
}
