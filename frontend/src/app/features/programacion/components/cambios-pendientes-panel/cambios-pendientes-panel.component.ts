import { Component, input, output, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CambioPendiente } from '../../../registro/components/programacion-matriz/programacion-matriz.component';

@Component({
  selector: 'app-cambios-pendientes-panel',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cambios-pendientes-panel.component.html'
})
export class CambiosPendientesPanelComponent {
  cambios   = input.required<CambioPendiente[]>();
  guardando = input<boolean>(false);

  confirmar = output<string>();
  descartar = output<void>();

  motivo = signal('');

  puedeConfirmar = computed(() =>
    this.cambios().length > 0 && this.motivo().trim().length >= 5 && !this.guardando()
  );

  onConfirmar(): void {
    if (!this.puedeConfirmar()) return;
    this.confirmar.emit(this.motivo().trim());
  }

  onDescartar(): void {
    this.motivo.set('');
    this.descartar.emit();
  }
}
