import { Component, inject, OnInit, signal, output, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgramacionService, Programacion } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-programacion-detalle',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './programacion-detalle.component.html',
})
export class ProgramacionDetalleComponent implements OnInit {
  private programacionService = inject(ProgramacionService);

  id      = input.required<string>();
  cerrado = output<void>();
  editar  = output<Programacion>();

  detalle = signal<Programacion | null>(null);
  loading = signal(true);
  error   = signal<string | null>(null);

  ngOnInit(): void {
    this.programacionService.getDetalleProgramacion(this.id()).subscribe({
      next:  d => { this.detalle.set(d); this.loading.set(false); },
      error: () => { this.error.set('Error al cargar detalles'); this.loading.set(false); },
    });
  }

  get ocupacion(): number {
    const d = this.detalle();
    if (!d || !d.capacidad) return 0;
    return Math.round((d.n_inscritos / d.capacidad) * 100);
  }

  getHorarioTexto(detalle: Programacion['grupo_horario']): string {
    if (!detalle?.detalles?.length) return 'Sin horario asignado';
    return detalle.detalles.map(d =>
      `${this.diaNombre(d.dia_semana)} ${d.hora_inicio.substring(0,5)}–${d.hora_fin.substring(0,5)}`
    ).join(' · ');
  }

  diaNombre(dia: string): string {
    const map: Record<string, string> = {
      lunes: 'Lun', martes: 'Mar', miercoles: 'Mié',
      jueves: 'Jue', viernes: 'Vie', sabado: 'Sáb',
    };
    return map[dia] ?? dia;
  }

  aulaNombre(d: Programacion): string {
    return d.aula_nombre || d.aula || d.aula_rel?.nombre || 'Sin aula';
  }
}
