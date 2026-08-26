import { Component, input, output } from '@angular/core';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-marcar-llenos-modal',
  standalone: true,
  imports: [AppButtonComponent],
  templateUrl: './marcar-llenos-modal.component.html'
})
export class MarcarLlenosModalComponent {
  periodoNombre = input.required<string>();
  loading       = input<boolean>(false);
  errorMsg      = input<string | null>(null);
  resultado     = input<number | null>(null);

  cancelar        = output<void>();
  marcarTodos     = output<void>();
  desmarcarTodos  = output<void>();
}
