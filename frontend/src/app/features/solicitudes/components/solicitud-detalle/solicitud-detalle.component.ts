import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '@core/auth/services/auth.service';
import { SolicitudService, Solicitud, UpdateEstadoDTO } from '../../services/solicitud.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { TrustUrlPipe } from '@shared/pipes/trust-url.pipe';

@Component({
  selector: 'app-solicitud-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent, AppBadgeComponent, TrustUrlPipe],
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
  anulando = signal(false);
  confirmarAnular = signal(false);
  respondiendo = signal(false);
  textoRespuesta = signal('');
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  pdfModalUrl = signal<string | null>(null);
  pdfModalTitulo = signal<string>('');

  // Para el formulario de actualización
  nuevoEstado = signal<string>('');
  observaciones = signal<string>('');
  respuestaApelacion = signal<string>('');

  esAdmin = computed(() => {
    return this.authService.hasRole('admin') ||
           this.authService.hasRole('secretaria') ||
           this.authService.hasRole('decano') ||
           this.authService.hasRole('secretario academico');
  });

  puedeAnular = computed(() => {
    const sol = this.solicitud();
    if (!sol) return false;
    const esEstudiante = this.authService.hasRole('estudiante');
    const estadoAnulable = sol.estado === 'pendiente' || sol.estado === 'en_revision';
    return esEstudiante && estadoAnulable;
  });

  puedeApelar = computed(() => {
    const sol = this.solicitud();
    if (!sol) return false;
    return this.authService.hasRole('estudiante') && sol.estado === 'rechazada' && !sol.respuesta_alumno;
  });

  mostrarRespuestaApelacion = computed(() => !!this.solicitud()?.respuesta_alumno);

  estados = [
    { value: 'pendiente', label: 'Pendiente', color: 'amber' },
    { value: 'en_revision', label: 'En Revisión', color: 'indigo' },
    { value: 'aprobada', label: 'Aprobada', color: 'emerald' },
    { value: 'rechazada', label: 'Rechazada', color: 'red' },
    { value: 'apelado', label: 'Apelado', color: 'violet' },
    { value: 'anulada', label: 'Anulada por el alumno', color: 'slate' }
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
        this.respuestaApelacion.set(solicitud.respuesta_admin || '');
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
      observaciones: this.observaciones() || undefined,
      respuesta_apelacion: this.mostrarRespuestaApelacion() ? (this.respuestaApelacion() || undefined) : undefined
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

  anular(): void {
    const sol = this.solicitud();
    if (!sol || this.anulando()) return;

    this.anulando.set(true);
    this.solicitudService.anularSolicitud(sol.id).subscribe({
      next: () => {
        this.router.navigate(['/app/solicitudes/list']);
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al anular la solicitud');
        this.anulando.set(false);
        this.confirmarAnular.set(false);
      }
    });
  }

  apelar(): void {
    const sol = this.solicitud();
    if (!sol || this.respondiendo()) return;
    if (this.textoRespuesta().trim().length < 10) {
      this.mostrarMensaje('error', 'La apelación debe tener al menos 10 caracteres.');
      return;
    }

    this.respondiendo.set(true);
    this.solicitudService.responderSolicitud(sol.id, this.textoRespuesta()).subscribe({
      next: (updated) => {
        this.solicitud.set(updated);
        this.textoRespuesta.set('');
        this.mostrarMensaje('success', 'Apelación enviada. Tu solicitud está de nuevo en revisión.');
        this.respondiendo.set(false);
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al enviar la apelación.');
        this.respondiendo.set(false);
      }
    });
  }

  getColorEstado(estado: string): 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' | 'violet' {
    const mapping: Record<string, 'amber' | 'indigo' | 'emerald' | 'red' | 'slate' | 'violet'> = {
      'pendiente': 'amber',
      'en_revision': 'indigo',
      'aprobada': 'emerald',
      'rechazada': 'red',
      'apelado': 'violet',
      'anulada': 'slate'
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

  abrirPdfCompleto(url: string, titulo: string): void {
    this.pdfModalUrl.set(url);
    this.pdfModalTitulo.set(titulo);
  }

  cerrarPdfModal(): void {
    this.pdfModalUrl.set(null);
  }

  esPDF(nombre: string | null): boolean {
    return !!nombre && nombre.toLowerCase().endsWith('.pdf');
  }

  esImagen(nombre: string | null): boolean {
    if (!nombre) return false;
    return /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(nombre);
  }

  getIconoArchivo(nombre: string | null): string {
    if (this.esPDF(nombre)) return 'pdf';
    if (this.esImagen(nombre)) return 'image';
    return 'file';
  }
}
