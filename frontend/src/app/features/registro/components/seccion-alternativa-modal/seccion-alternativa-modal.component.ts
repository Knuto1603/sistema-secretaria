import { Component, input, output } from '@angular/core';
import { Programacion } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-seccion-alternativa-modal',
  standalone: true,
  imports: [AppButtonComponent],
  templateUrl: './seccion-alternativa-modal.component.html'
})
export class SeccionAlternativaModalComponent {
  programacion = input.required<Programacion>();

  cerrado           = output<void>();
  verDisponible     = output<void>();
  continuarSolicitud = output<void>();
}
