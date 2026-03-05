import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService, Solicitud, UpdateEstadoDTO } from '../../services/solicitud.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-solicitud-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './solicitud-detalle.component.html'
})
export class SolicitudDetalleComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private solicitudService = inject(SolicitudService);
  public authService = inject(AuthService);

  solicitud = signal<Solicitud | null>(null);
  loading = signal(false);
  updating = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Para el formulario de actualización
  nuevoEstado = signal<string>('');
  observaciones = signal<string>('');

  esAdmin = computed(() => {
    return this.authService.hasRole('admin') ||
           this.authService.hasRole('secretaria') ||
           this.authService.hasRole('decano') ||
           this.authService.hasRole('secretario academico');
  });

  estados = [
    { value: 'pendiente', label: 'Pendiente', color: 'amber' },
    { value: 'en_revision', label: 'En Revisión', color: 'indigo' },
    { value: 'aprobada', label: 'Aprobada', color: 'emerald' },
    { value: 'rechazada', label: 'Rechazada', color: 'red' }
  ];

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.cargarSolicitud(id);
    } else {
      this.router.navigate(['/app/solicitudes']);
    }
  }

  cargarSolicitud(id: string): void {
    this.loading.set(true);
    this.solicitudService.getDetalleSolicitud(id).subscribe({
      next: (solicitud) => {
        this.solicitud.set(solicitud);
        this.nuevoEstado.set(solicitud.estado);
        this.observaciones.set(solicitud.observaciones_admin || '');
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar la solicitud');
        this.loading.set(false);
      }
    });
  }

  actualizarEstado(): void {
    const sol = this.solicitud();
    if (!sol || !this.nuevoEstado()) return;

    this.updating.set(true);
    const data: UpdateEstadoDTO = {
      estado: this.nuevoEstado() as any,
      observaciones: this.observaciones() || undefined
    };

    this.solicitudService.updateEstado(sol.id, data).subscribe({
      next: (updated) => {
        this.solicitud.set(updated);
        this.mostrarMensaje('success', 'Estado actualizado correctamente');
        this.updating.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al actualizar el estado');
        this.updating.set(false);
      }
    });
  }

  volver(): void {
    this.router.navigate(['/app/solicitudes/list']);
  }

  getColorEstado(estado: string): 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' {
    const mapping: Record<string, 'amber' | 'indigo' | 'emerald' | 'red' | 'slate'> = {
      'pendiente': 'amber',
      'en_revision': 'indigo',
      'aprobada': 'emerald',
      'rechazada': 'red'
    };
    return mapping[estado?.toLowerCase()] || 'slate';
  }

  getEstadoLabel(estado: string): string {
    const found = this.estados.find(e => e.value === estado);
    return found?.label || estado;
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }

  formatearFecha(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-PE', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
}
