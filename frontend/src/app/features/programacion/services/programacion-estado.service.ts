import { Injectable, inject, signal } from '@angular/core';
import { Periodo, PeriodoService } from '@core/services/periodo.service';

@Injectable({ providedIn: 'root' })
export class ProgramacionEstadoService {
  private periodoService = inject(PeriodoService);

  readonly periodoId          = signal<string>('');
  readonly periodo            = signal<Periodo | null>(null);
  readonly periodos           = signal<Periodo[]>([]);
  readonly loading            = signal<boolean>(false);
  readonly ultimaModificacion = signal<number>(0);

  cargarPeriodos(): void {
    if (this.periodos().length > 0) return;
    this.loading.set(true);
    this.periodoService.getPeriodos().subscribe({
      next: periodos => {
        this.periodos.set(periodos);
        if (!this.periodoId()) {
          const activo = periodos.find(p => p.activo) ?? periodos[0] ?? null;
          if (activo) {
            this.periodoId.set(activo.id);
            this.periodo.set(activo);
          }
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  seleccionarPeriodo(id: string): void {
    const p = this.periodos().find(p => p.id === id) ?? null;
    this.periodoId.set(id);
    this.periodo.set(p);
  }

  triggerRefresh(): void {
    this.ultimaModificacion.update(v => v + 1);
  }
}
