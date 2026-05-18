import { Component, inject, signal, OnInit, ChangeDetectionStrategy } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { FormsModule } from '@angular/forms';
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
  readonly estado = inject(ProgramacionEstadoService);

  readonly loading = signal(false);

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
