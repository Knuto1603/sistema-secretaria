import {
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { takeUntilDestroyed, toObservable } from '@angular/core/rxjs-interop';
import { distinctUntilChanged, filter, switchMap } from 'rxjs/operators';

import { ProgramacionEstadoService } from '../../services/programacion-estado.service';
import {
  BorradorProgramacion,
  ProgramacionInteractivaService,
} from '../../../programacion-interactiva/services/programacion-interactiva.service';

@Component({
  selector: 'app-modificaciones-historial',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './modificaciones-historial.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModificacionesHistorialComponent {
  protected readonly estadoSvc = inject(ProgramacionEstadoService);
  private  readonly piSvc     = inject(ProgramacionInteractivaService);
  private  readonly router    = inject(Router);

  readonly borradores = signal<BorradorProgramacion[]>([]);
  readonly cargando   = signal(false);

  constructor() {
    this.estadoSvc.cargarPeriodos();

    toObservable(this.estadoSvc.periodoId)
      .pipe(
        filter(id => !!id),
        distinctUntilChanged(),
        switchMap(id => {
          this.cargando.set(true);
          return this.piSvc.listar(id);
        }),
        takeUntilDestroyed(),
      )
      .subscribe({
        next: data => {
          // Más recientes primero
          this.borradores.set([...data].sort(
            (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
          ));
          this.cargando.set(false);
        },
        error: () => this.cargando.set(false),
      });
  }

  onPeriodoChange(id: string): void {
    this.estadoSvc.seleccionarPeriodo(id);
  }

  verDetalle(borrador: BorradorProgramacion): void {
    this.router.navigate(['/app/programacion/modificaciones', borrador.id]);
  }

  irAGenerar(): void {
    this.router.navigate(['/app/programacion/generar-documentos']);
  }
}
