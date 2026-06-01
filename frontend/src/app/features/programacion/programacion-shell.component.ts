import { Component, inject, signal, OnInit, ChangeDetectionStrategy, computed } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { Router, NavigationEnd } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { filter } from 'rxjs';
import { toSignal } from '@angular/core/rxjs-interop';
import { PeriodoService } from '@core/services/periodo.service';
import { ProgramacionEstadoService } from './services/programacion-estado.service';

@Component({
  selector: 'app-programacion-shell',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule],
  templateUrl: './programacion-shell.component.html',
})
export class ProgramacionShellComponent implements OnInit {
  private periodoService = inject(PeriodoService);
  private router         = inject(Router);
  readonly estado = inject(ProgramacionEstadoService);

  readonly loading = signal(false);

  // Actualiza reactivamente con cada NavigationEnd para que el computed se dispare
  private readonly _navEnd = toSignal(
    this.router.events.pipe(filter(e => e instanceof NavigationEnd)),
    { initialValue: null }
  );

  /** Detecta en qué sección del módulo está el usuario según la URL actual. */
  readonly pasoActivo = computed((): 1 | 2 | 3 | 4 => {
    // Acceder a _navEnd() registra la dependencia reactiva; el valor real no se usa
    this._navEnd();
    const url = this.router.url;
    if (url.includes('/borradores')) return 1;
    if (url.includes('/modificaciones') || url.includes('/generar-documentos')) return 4;
    return 3;
  });

  ngOnInit(): void {
    if (this.estado.periodos().length > 0) return;
    this.loading.set(true);
    this.periodoService.getPeriodos().subscribe({
      next: periodos => {
        this.estado.periodos.set(periodos);
        if (!this.estado.periodoId()) {
          const activo = periodos.find(p => p.activo) ?? periodos[0] ?? null;
          if (activo) {
            this.estado.periodoId.set(activo.id);
            this.estado.periodo.set(activo);
          }
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  onChangePeriodo(id: string): void {
    const p = this.estado.periodos().find(p => p.id === id) ?? null;
    this.estado.periodoId.set(id);
    this.estado.periodo.set(p);
  }
}
