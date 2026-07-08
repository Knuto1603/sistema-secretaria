import { Component, inject, OnInit, ChangeDetectionStrategy, computed, signal } from '@angular/core';
import { RouterOutlet, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ProgramacionEstadoService } from '../programacion/services/programacion-estado.service';
import { AuthService } from '@core/auth/services/auth.service';
import { ImportarDiffComponent } from './components/importar-diff/importar-diff.component';

@Component({
  selector: 'app-programacion-activa-shell',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, FormsModule, ImportarDiffComponent],
  templateUrl: './programacion-activa-shell.component.html',
})
export class ProgramacionActivaShellComponent implements OnInit {
  readonly estado     = inject(ProgramacionEstadoService);
  private authService = inject(AuthService);

  readonly esAdmin = computed(() =>
    this.authService.hasRole('secretaria') ||
    this.authService.hasRole('admin') ||
    this.authService.hasRole('developer')
  );

  showImportDiff = signal(false);

  ngOnInit(): void {
    this.estado.cargarPeriodos();
  }

  onChangePeriodo(id: string): void {
    this.estado.seleccionarPeriodo(id);
  }
}
