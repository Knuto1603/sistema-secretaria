import { Component, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AppButtonComponent } from '@shared/button/button.component';

export interface CambioEstadoAperturaPayload {
  estado: 'pendiente' | 'en_revision' | 'aprobada' | 'rechazada';
  observaciones: string;
}

@Component({
  selector: 'app-apertura-estado-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './apertura-estado-modal.component.html'
})
export class AperturaEstadoModalComponent {
  cantidad = input.required<number>();
  titulo = input<string>('Cambiar estado');
  enviando = input<boolean>(false);
  error = input<string | null>(null);

  cerrado = output<void>();
  confirmar = output<CambioEstadoAperturaPayload>();

  estados: Array<{ value: CambioEstadoAperturaPayload['estado']; label: string }> = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'en_revision', label: 'En Revisión' },
    { value: 'aprobada', label: 'Aprobada' },
    { value: 'rechazada', label: 'Rechazada' }
  ];

  estadoSeleccionado = signal<CambioEstadoAperturaPayload['estado'] | ''>('');
  observaciones = signal('');

  onConfirmar(): void {
    if (!this.estadoSeleccionado()) return;
    this.confirmar.emit({
      estado: this.estadoSeleccionado() as CambioEstadoAperturaPayload['estado'],
      observaciones: this.observaciones().trim()
    });
  }
}
