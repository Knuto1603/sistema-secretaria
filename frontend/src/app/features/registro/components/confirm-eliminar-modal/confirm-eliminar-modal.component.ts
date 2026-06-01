import { Component, input, output } from '@angular/core';
import { Programacion } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-confirm-eliminar-modal',
  standalone: true,
  imports: [AppButtonComponent],
  templateUrl: './confirm-eliminar-modal.component.html'
})
export class ConfirmEliminarModalComponent {
  programacion = input.required<Programacion>();
  eliminando   = input<boolean>(false);
  errorMsg     = input<string | null>(null);

  cancelar  = output<void>();
  confirmar = output<void>();
}
