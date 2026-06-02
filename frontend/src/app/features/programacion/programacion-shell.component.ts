import { Component, inject, OnInit, ChangeDetectionStrategy, computed } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { Router, NavigationEnd } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { filter } from 'rxjs';
import { toSignal } from '@angular/core/rxjs-interop';
import { ProgramacionEstadoService } from './services/programacion-estado.service';

@Component({
  selector: 'app-programacion-shell',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule],
  templateUrl: './programacion-shell.component.html',
})
export class ProgramacionShellComponent implements OnInit {
  private router  = inject(Router);
  readonly estado = inject(ProgramacionEstadoService);

  private readonly _navEnd = toSignal(
    this.router.events.pipe(filter(e => e instanceof NavigationEnd)),
    { initialValue: null }
  );

  readonly pasoActivo = computed((): 1 | 2 | 3 => {
    this._navEnd();
    const url = this.router.url;
    if (url.includes('/borradores')) return 1;
    if (url.includes('/modificaciones') || url.includes('/generar-documentos')) return 3;
    return 1;
  });

  ngOnInit(): void {
    this.estado.cargarPeriodos();
  }

  onChangePeriodo(id: string): void {
    this.estado.seleccionarPeriodo(id);
  }
}
