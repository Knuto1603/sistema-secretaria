import {
  Component, inject, input, output, signal, computed, ChangeDetectionStrategy
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion, BulkCambio } from '../../services/programacion-interactiva.service';
import { Pabellon, Aula } from '../../../configuracion/services/aula.service';
import { GrupoHorario } from '../../../configuracion/services/horario.service';

@Component({
  selector: 'app-pi-matriz',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule],
  templateUrl: './pi-matriz.component.html'
})
export class PiMatrizComponent {
  private piService = inject(ProgramacionInteractivaService);

  borrador   = input.required<BorradorProgramacion>();
  pabellones = input.required<Pabellon[]>();
  grupos     = input.required<GrupoHorario[]>();

  cambioGuardado = output<void>();

  draggingId    = signal<string | null>(null);
  guardando     = signal(false);
  error         = signal<string | null>(null);

  // Secciones como signal local mutable para optimistic updates
  secciones = computed(() => [...(this.borrador().secciones ?? [])]);

  aulasActivas = computed((): Aula[] =>
    this.pabellones().flatMap(p => p.aulas.filter(a => a.activo))
  );

  gruposActivos = computed((): GrupoHorario[] =>
    this.grupos().filter(g => g.activo && g.detalles.length > 0)
  );

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

    const seccion = this.secciones().find(s => s.id === seccionId);
    if (!seccion) return;

    // No hacer nada si cae en la misma celda
    if (seccion.aula?.id === aulaId && seccion.grupo_horario?.id === grupoId) return;

    const cambios: BulkCambio[] = [{ id: seccionId, aula_id: aulaId, grupo_horario_id: grupoId }];

    this.guardando.set(true);
    this.error.set(null);

    this.piService.bulkUpdate(this.borrador().id, cambios).subscribe({
      next: () => {
        this.guardando.set(false);
        this.cambioGuardado.emit();
      },
      error: () => {
        this.guardando.set(false);
        this.error.set('Error al guardar el cambio. Intenta de nuevo.');
      }
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
