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

  /** Activa la columna de checkboxes para selección múltiple de filas */
  selectable = input<boolean>(false);

  /** IDs de las filas actualmente seleccionadas */
  selectedIds = input<Set<string>>(new Set());

  /** Función para obtener el ID de una fila (por defecto row.id) */
  rowId = input<(row: any) => string>((row: any) => row.id);

  /** Permite pasar un template personalizado para las acciones de cada fila */
  @ContentChild('actionsTemplate') actionsTemplate?: TemplateRef<any>;

  /** Permite pasar templates personalizados para celdas específicas */
  @ContentChild('cellTemplate') cellTemplate?: TemplateRef<any>;

  /** Eventos para interactuar con las filas */
  rowClick = output<any>();

  /** Se emite con el ID de la fila al marcar/desmarcar su checkbox */
  rowSelectToggle = output<string>();

  /** Se emite al marcar/desmarcar el checkbox de encabezado (true = seleccionar todo lo visible) */
  allSelectToggle = output<boolean>();

  allVisibleSelected(): boolean {
    const data = this.data();
    if (data.length === 0) return false;
    const ids = this.selectedIds();
    return data.every(row => ids.has(this.rowId()(row)));
  }
}