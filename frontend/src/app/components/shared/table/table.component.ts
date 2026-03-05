import { Component, input, output, ChangeDetectionStrategy, ContentChild, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AppLoadingComponent } from '../loading/loading.component';

export interface TableColumn {
  key: string;
  label: string;
  sortable?: boolean;
}

@Component({
  selector: 'app-table',
  standalone: true,
  imports: [CommonModule, AppLoadingComponent],
  templateUrl: './table.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppTableComponent {
  /** Configuración de las columnas a mostrar */
  columns = input.required<TableColumn[]>();

  /** Datos a renderizar en las filas */
  data = input.required<any[]>();

  /** Indica si la tabla está en estado de carga */
  loading = input<boolean>(false);

  /** Título opcional para la tabla */
  title = input<string>('');

  /** Permite pasar un template personalizado para las acciones de cada fila */
  @ContentChild('actionsTemplate') actionsTemplate?: TemplateRef<any>;

  /** Permite pasar templates personalizados para celdas específicas */
  @ContentChild('cellTemplate') cellTemplate?: TemplateRef<any>;

  /** Eventos para interactuar con las filas */
  rowClick = output<any>();
}