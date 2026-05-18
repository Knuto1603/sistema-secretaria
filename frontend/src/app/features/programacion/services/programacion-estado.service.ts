import { Injectable, signal } from '@angular/core';
import { Periodo } from '@core/services/periodo.service';

@Injectable({ providedIn: 'root' })
export class ProgramacionEstadoService {
  readonly periodoId = signal<string>('');
  readonly periodo   = signal<Periodo | null>(null);
  readonly periodos  = signal<Periodo[]>([]);
}
