import { Component, input, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Periodo } from '@core/services/periodo.service';
import { Departamento } from '../../../configuracion/services/departamento.service';

@Component({
  selector: 'app-programacion-filtros',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './programacion-filtros.component.html'
})
export class ProgramacionFiltrosComponent {
  // Data de referencia
  periodos      = input<Periodo[]>([]);
  escuelas      = input<Array<{ id: string; nombre: string; nombre_corto: string | null }>>([]);
  departamentos = input<Departamento[]>([]);
  grupos        = input<string[]>([]);
  readonly ciclos = [1,2,3,4,5,6,7,8,9,10];
  loadingPeriodos = input<boolean>(false);

  // Valores actuales
  periodoSeleccionado           = input<string | null>(null);
  escuelaSeleccionada           = input<string>('');
  escuelaProgramadaSeleccionada = input<string>('');
  cicloSeleccionado             = input<number | null>(null);
  areaSeleccionada              = input<string>('');
  grupoSeleccionado             = input<string>('');
  tipoSeleccionado              = input<string>('');
  searchTerm                    = input<string>('');
  hayFiltrosActivos             = input<boolean>(false);

  // Eventos
  periodoChange             = output<string>();
  escuelaChange             = output<string>();
  escuelaProgramadaChange   = output<string>();
  cicloChange               = output<string>();
  areaChange                = output<string>();
  grupoChange               = output<string>();
  tipoChange                = output<string>();
  searchChange              = output<string>();
  limpiar                   = output<void>();
}
