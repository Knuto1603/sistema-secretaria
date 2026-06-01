import { Component, input, output } from '@angular/core';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-limpiar-periodo-modal',
  standalone: true,
  imports: [AppButtonComponent],
  templateUrl: './limpiar-periodo-modal.component.html'
})
export class LimpiarPeriodoModalComponent {
  periodoNombre = input.required<string>();
  loading       = input<boolean>(false);
  errorMsg      = input<string | null>(null);

  cancelar  = output<void>();
  confirmar = output<void>();
}
