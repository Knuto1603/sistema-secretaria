import { Component, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AppButtonComponent } from '@shared/button/button.component';

export interface CambioEstadoMasivoPayload {
  estado: 'pendiente' | 'en_revision' | 'aprobada' | 'rechazada';
  observaciones: string;
}

@Component({
  selector: 'app-cambiar-estado-masivo-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './cambiar-estado-masivo-modal.component.html'
})
export class CambiarEstadoMasivoModalComponent {
  cantidad = input.required<number>();
  enviando = input<boolean>(false);
  error = input<string | null>(null);

  cerrado = output<void>();
  confirmar = output<CambioEstadoMasivoPayload>();

  estados: Array<{ value: CambioEstadoMasivoPayload['estado']; label: string }> = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'en_revision', label: 'En Revisión' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'rechazada', label: 'Rechazada' }
  ];

  estadoSeleccionado = signal<CambioEstadoMasivoPayload['estado'] | ''>('');
  observaciones = signal('');

  onConfirmar(): void {
    if (!this.estadoSeleccionado()) return;
    this.confirmar.emit({
      estado: this.estadoSeleccionado() as CambioEstadoMasivoPayload['estado'],
      observaciones: this.observaciones().trim()
    });
  }
}
