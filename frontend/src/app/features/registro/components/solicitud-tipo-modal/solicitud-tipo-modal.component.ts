import { Component, input, output } from '@angular/core';
import { Programacion } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-solicitud-tipo-modal',
  standalone: true,
  imports: [AppButtonComponent],
  templateUrl: './solicitud-tipo-modal.component.html'
})
export class SolicitudTipoModalComponent {
  programacion = input.required<Programacion>();
  mostrarCupoExtra = input<boolean>(false);
  mostrarInscripcionEscuela = input<boolean>(false);

  cerrado                   = output<void>();
  elegirCupoExtra           = output<void>();
  elegirInscripcionEscuela  = output<void>();
  elegirRetiro              = output<void>();
}
