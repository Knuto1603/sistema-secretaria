import { Component, input, output, signal, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
import { SolicitudAperturaService, CursoAgrupado } from '../../services/solicitud-apertura.service';
import { AperturaEstadoModalComponent, CambioEstadoAperturaPayload } from '../apertura-estado-modal/apertura-estado-modal.component';

interface SolicitanteFila {
  solicitud_id: string;
  user_id: string;
  nombre: string | null;
  codigo: string | null;
  escuela_nombre: string | null;
  tipo: string;
  estado: string;
  fecha: string;
  cumple_prerequisitos: boolean | null;
}

@Component({
  selector: 'app-apertura-curso-card',
  standalone: true,
  imports: [CommonModule, AppBadgeComponent, AppButtonComponent, AperturaEstadoModalComponent],
  templateUrl: './apertura-curso-card.component.html'
})
export class AperturaCursoCardComponent {
  private service = inject(SolicitudAperturaService);

  curso = input.required<CursoAgrupado>();
  cambiado = output<void>();

  expandido = signal(false);
  seleccionadas = signal<Set<string>>(new Set());
  mostrarModal = signal(false);
  modalIds = signal<string[]>([]);
  enviando = signal(false);
  errorModal = signal<string | null>(null);

  mostrarEscuelas = computed(() => this.curso().escuelas.length > 1);

  solicitantes = computed<SolicitanteFila[]>(() =>
    this.curso().escuelas.flatMap(e =>
      e.solicitantes.map(s => ({ ...s, escuela_nombre: e.escuela_nombre }))
    )
  );

  toggleExpandir(): void {
    this.expandido.update(v => !v);
  }

  toggleSeleccion(id: string, event: Event): void {
    event.stopPropagation();
    const next = new Set(this.seleccionadas());
    if (next.has(id)) next.delete(id); else next.add(id);
    this.seleccionadas.set(next);
  }

  toggleSeleccionTodas(marcar: boolean): void {
    if (!marcar) {
      this.seleccionadas.set(new Set());
      return;
    }
    this.seleccionadas.set(new Set(this.solicitantes().map(s => s.solicitud_id)));
  }

  todasSeleccionadas(): boolean {
    const total = this.solicitantes().length;
    return total > 0 && this.seleccionadas().size === total;
  }

  abrirModalIndividual(id: string, event: Event): void {
    event.stopPropagation();
    this.errorModal.set(null);
    this.modalIds.set([id]);
    this.mostrarModal.set(true);
  }

  abrirModalMasivo(): void {
    if (this.seleccionadas().size === 0) return;
    this.errorModal.set(null);
    this.modalIds.set(Array.from(this.seleccionadas()));
    this.mostrarModal.set(true);
  }

  cerrarModal(): void {
    if (this.enviando()) return;
    this.mostrarModal.set(false);
  }

  confirmarCambio(payload: CambioEstadoAperturaPayload): void {
    const ids = this.modalIds();
    const data = { estado: payload.estado, observaciones: payload.observaciones || undefined };
    this.enviando.set(true);
    this.errorModal.set(null);

    const onSuccess = () => {
      this.enviando.set(false);
      this.mostrarModal.set(false);
      this.seleccionadas.set(new Set());
      this.cambiado.emit();
    };
    const onError = (err: { error?: { message?: string } }) => {
      this.enviando.set(false);
      this.errorModal.set(err.error?.message || 'Error al cambiar el estado.');
    };

    if (ids.length === 1) {
      this.service.updateEstado(ids[0], data).subscribe({ next: onSuccess, error: onError });
    } else {
      this.service.updateEstadoMasivo(ids, data).subscribe({ next: onSuccess, error: onError });
    }
  }

  colorEstado(estado: string): 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' {
    const mapping: Record<string, 'amber' | 'indigo' | 'emerald' | 'red' | 'slate'> = {
      pendiente: 'amber',
      en_revision: 'indigo',
      aprobada: 'emerald',
      rechazada: 'red',
      anulada: 'slate'
    };
    return mapping[estado] || 'slate';
  }

  labelEstado(estado: string): string {
    const labels: Record<string, string> = {
      pendiente: 'Pendiente',
      en_revision: 'En Revisión',
      aprobada: 'Aprobada',
      rechazada: 'Rechazada',
      anulada: 'Anulada'
    };
    return labels[estado] || estado;
  }

  labelTipo(tipo: string): string {
    return tipo === 'cambio_grupo' ? 'Otro grupo' : 'Apertura';
  }

  cadenaResumen(cursosCadena: Array<{ codigo: string; nombre: string }>): string {
    return cursosCadena.map(c => c.codigo).join(', ');
  }
}
