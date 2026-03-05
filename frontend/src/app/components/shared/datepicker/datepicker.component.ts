import { Component, input, output, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-datepicker',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './datepicker.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppDatepickerComponent {
  /** Etiqueta que aparecerá sobre el campo */
  label = input<string>('');
  
  /** Valor actual de la fecha (formato YYYY-MM-DD) */
  value = input<string | null>(null);
  
  /** Fecha mínima permitida */
  minDate = input<string | null>(null);
  
  /** Fecha máxima permitida */
  maxDate = input<string | null>(null);

  /** Evento que emite el nuevo valor al cambiar */
  dateChange = output<string>();

  onValueChange(newValue: string) {
    this.dateChange.emit(newValue);
  }
}