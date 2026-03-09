import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HorarioService, GrupoHorario, GrupoHorarioDetalle } from '../../services/horario.service';
import { AppButtonComponent } from '@shared/button/button.component';

type Dia = 'lunes' | 'martes' | 'miercoles' | 'jueves' | 'viernes' | 'sabado';

interface NuevoSlot {
  dia_semana: Dia;
  hora_inicio: string;
  hora_fin: string;
}

const DIA_LABELS: Record<Dia, string> = {
  lunes: 'Lun',
  martes: 'Mar',
  miercoles: 'Mié',
  jueves: 'Jue',
  viernes: 'Vie',
  sabado: 'Sáb',
};

@Component({
  selector: 'app-horarios',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './horarios.component.html',
})
export class HorariosComponent implements OnInit {
  private horarioService = inject(HorarioService);

  grupos = signal<GrupoHorario[]>([]);
  loading = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Grupo expandido para gestionar sus slots
  grupoActivo = signal<string | null>(null);

  // Formulario nuevo slot
  nuevoSlot: NuevoSlot = { dia_semana: 'lunes', hora_inicio: '08:00', hora_fin: '10:00' };
  guardandoSlot = signal(false);

  // Edición inline de slot existente
  detalleEditando = signal<string | null>(null);
  slotEditData: NuevoSlot = { dia_semana: 'lunes', hora_inicio: '08:00', hora_fin: '10:00' };
  guardandoEdicion = signal(false);

  readonly dias: Dia[] = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
  readonly diaLabels = DIA_LABELS;

  getDiasDisponibles(grupo: GrupoHorario, excludeDetalleId?: string): Dia[] {
    const usados = new Set(
      grupo.detalles
        .filter(d => d.id !== excludeDetalleId)
        .map(d => d.dia_semana)
    );
    return this.dias.filter(d => !usados.has(d));
  }

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.loading.set(true);
    this.horarioService.getGrupos().subscribe({
      next: (grupos) => { this.grupos.set(grupos); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  getGrupoActivo(): GrupoHorario | undefined {
    return this.grupos().find(g => g.id === this.grupoActivo());
  }

  toggleGrupo(id: string): void {
    const siguiente = this.grupoActivo() === id ? null : id;
    this.grupoActivo.set(siguiente);
    this.detalleEditando.set(null);
    if (siguiente) {
      const grupo = this.grupos().find(g => g.id === siguiente);
      const primerDisponible = grupo ? (this.getDiasDisponibles(grupo)[0] ?? 'lunes') : 'lunes';
      this.nuevoSlot = { dia_semana: primerDisponible, hora_inicio: '08:00', hora_fin: '10:00' };
    }
  }

  toggleActivo(grupo: GrupoHorario): void {
    this.horarioService.toggleActivo(grupo.id).subscribe({
      next: (actualizado) => this.actualizarGrupoLocal(actualizado),
      error: () => this.mostrarMensaje('error', 'Error al cambiar estado'),
    });
  }

  agregarSlot(grupoId: string): void {
    if (this.nuevoSlot.hora_inicio >= this.nuevoSlot.hora_fin) {
      this.mostrarMensaje('error', 'La hora de inicio debe ser anterior a la hora de fin');
      return;
    }
    this.guardandoSlot.set(true);
    this.horarioService.agregarDetalle(grupoId, this.nuevoSlot).subscribe({
      next: (actualizado) => {
        this.actualizarGrupoLocal(actualizado);
        this.nuevoSlot = { dia_semana: 'lunes', hora_inicio: '08:00', hora_fin: '10:00' };
        this.guardandoSlot.set(false);
        this.mostrarMensaje('success', 'Horario agregado');
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al agregar horario');
        this.guardandoSlot.set(false);
      },
    });
  }

  editarSlot(detalle: GrupoHorarioDetalle): void {
    this.detalleEditando.set(detalle.id);
    this.slotEditData = {
      dia_semana: detalle.dia_semana,
      hora_inicio: detalle.hora_inicio.substring(0, 5),
      hora_fin: detalle.hora_fin.substring(0, 5),
    };
  }

  cancelarEdicion(): void {
    this.detalleEditando.set(null);
  }

  guardarEdicionSlot(grupoId: string, detalleId: string): void {
    if (this.slotEditData.hora_inicio >= this.slotEditData.hora_fin) {
      this.mostrarMensaje('error', 'La hora de inicio debe ser anterior a la hora de fin');
      return;
    }
    this.guardandoEdicion.set(true);
    this.horarioService.actualizarDetalle(grupoId, detalleId, this.slotEditData).subscribe({
      next: (actualizado) => {
        this.actualizarGrupoLocal(actualizado);
        this.detalleEditando.set(null);
        this.guardandoEdicion.set(false);
        this.mostrarMensaje('success', 'Horario actualizado');
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al actualizar horario');
        this.guardandoEdicion.set(false);
      },
    });
  }

  eliminarSlot(grupoId: string, detalleId: string): void {
    this.horarioService.eliminarDetalle(grupoId, detalleId).subscribe({
      next: (actualizado) => {
        this.actualizarGrupoLocal(actualizado);
        this.mostrarMensaje('success', 'Horario eliminado');
      },
      error: () => this.mostrarMensaje('error', 'Error al eliminar horario'),
    });
  }

  getResumenHorario(grupo: GrupoHorario): string {
    if (!grupo.detalles.length) return 'Sin horarios';
    return grupo.detalles
      .map(d => `${DIA_LABELS[d.dia_semana as Dia]} ${d.hora_inicio.substring(0, 5)}-${d.hora_fin.substring(0, 5)}`)
      .join(' · ');
  }

  private actualizarGrupoLocal(actualizado: GrupoHorario): void {
    this.grupos.update(grupos => grupos.map(g => g.id === actualizado.id ? actualizado : g));
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 3500);
  }
}
