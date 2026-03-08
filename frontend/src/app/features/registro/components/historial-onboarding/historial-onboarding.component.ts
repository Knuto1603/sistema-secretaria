import { Component, inject, OnInit, signal, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { forkJoin } from 'rxjs';
import { HistorialService, CicloPlan } from '../../services/historial.service';

@Component({
  selector: 'app-historial-onboarding',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './historial-onboarding.component.html',
})
export class HistorialOnboardingComponent implements OnInit {
  private historialService = inject(HistorialService);

  /** Emite cuando el historial se guardó exitosamente */
  guardado = output<void>();
  /** Emite cuando el usuario cierra sin guardar */
  cerrado = output<void>();

  ciclos = signal<CicloPlan[]>([]);
  escuela = signal<string>('');
  aprobadosIds = signal<Set<string>>(new Set());
  loading = signal(true);
  saving = signal(false);
  error = signal<string | null>(null);

  ngOnInit(): void {
    forkJoin({
      plan:     this.historialService.getMiPlan(),
      historial: this.historialService.getMiHistorial(),
    }).subscribe({
      next: ({ plan, historial }) => {
        this.ciclos.set(plan.ciclos);
        this.escuela.set(plan.escuela.nombre);
        const ids = new Set(historial.cursos.map(c => c.curso_id));
        this.aprobadosIds.set(ids);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('No se pudo cargar el plan de estudios. Intenta nuevamente.');
        this.loading.set(false);
      },
    });
  }

  toggleCurso(cursoId: string): void {
    this.aprobadosIds.update(set => {
      const next = new Set(set);
      if (next.has(cursoId)) {
        next.delete(cursoId);
      } else {
        next.add(cursoId);
      }
      return next;
    });
  }

  estaAprobado(cursoId: string): boolean {
    return this.aprobadosIds().has(cursoId);
  }

  toggleCiclo(ciclo: CicloPlan): void {
    const todosMarcados = ciclo.cursos.every(c => this.estaAprobado(c.curso_id));
    this.aprobadosIds.update(set => {
      const next = new Set(set);
      ciclo.cursos.forEach(c => {
        if (todosMarcados) {
          next.delete(c.curso_id);
        } else {
          next.add(c.curso_id);
        }
      });
      return next;
    });
  }

  cicloCuantos(ciclo: CicloPlan): number {
    return ciclo.cursos.filter(c => this.estaAprobado(c.curso_id)).length;
  }

  guardar(): void {
    this.saving.set(true);
    const ids = Array.from(this.aprobadosIds());
    this.historialService.syncHistorial(ids).subscribe({
      next: () => {
        this.saving.set(false);
        this.guardado.emit();
      },
      error: () => {
        this.error.set('Error al guardar. Intenta nuevamente.');
        this.saving.set(false);
      },
    });
  }

  get totalAprobados(): number {
    return this.aprobadosIds().size;
  }
}
