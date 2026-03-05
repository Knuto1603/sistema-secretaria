import { Component, input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-empty-state',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './empty-state.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppEmptyStateComponent {
  title = input<string>('No hay datos disponibles');
  description = input<string>('Intenta ajustar tus filtros o recargar la página.');
}