import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '@core/auth/services/auth.service';
import { HistorialService, HistorialResponse, ImportPdfResumen } from '@core/services/historial.service';
import { AppBadgeComponent } from '@shared/badge/badge.component';

type Tab = 'datos' | 'password' | 'historial';

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [CommonModule, DecimalPipe, FormsModule, AppBadgeComponent],
  templateUrl: './perfil.component.html',
})
export class PerfilComponent implements OnInit {
  private authService     = inject(AuthService);
  private historialService = inject(HistorialService);

  user    = this.authService.currentUser;
  tabActiva = signal<Tab>('datos');

  esEstudiante = computed(() => this.authService.isEstudiante());

  // Cambio de contraseña
  passwordActual     = '';
  passwordNuevo      = '';
  passwordConfirmar  = '';
  showPasswordActual = false;
  showPasswordNuevo  = false;
  loadingPassword    = signal(false);

  // Historial
  historial        = signal<HistorialResponse | null>(null);
  loadingHistorial = signal(false);
  uploadingPdf     = signal(false);
  mensajeHistorial = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  resumenImport    = signal<ImportPdfResumen | null>(null);

  // Mensaje general
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  ngOnInit(): void {
    if (this.esEstudiante()) {
      this.cargarHistorial();
    }
  }

  setTab(tab: Tab): void {
    this.tabActiva.set(tab);
    this.mensaje.set(null);
    this.mensajeHistorial.set(null);
    this.resumenImport.set(null);
  }

  // ── Cambio de contraseña ────────────────────────────────────────────────

  cambiarPassword(): void {
    if (!this.passwordActual || !this.passwordNuevo || !this.passwordConfirmar) {
      this.mensaje.set({ tipo: 'error', texto: 'Completa todos los campos.' });
      return;
    }
    if (this.passwordNuevo !== this.passwordConfirmar) {
      this.mensaje.set({ tipo: 'error', texto: 'Las contraseñas nuevas no coinciden.' });
      return;
    }
    if (this.passwordNuevo.length < 8) {
      this.mensaje.set({ tipo: 'error', texto: 'La contraseña debe tener al menos 8 caracteres.' });
      return;
    }

    this.loadingPassword.set(true);
    this.mensaje.set(null);

    this.historialService.cambiarPassword(this.passwordActual, this.passwordNuevo).subscribe({
      next: () => {
        this.mensaje.set({ tipo: 'success', texto: 'Contraseña actualizada correctamente.' });
        this.passwordActual    = '';
        this.passwordNuevo     = '';
        this.passwordConfirmar = '';
        this.loadingPassword.set(false);
      },
      error: (err) => {
        const msg = err?.error?.message || 'Error al cambiar la contraseña.';
        this.mensaje.set({ tipo: 'error', texto: msg });
        this.loadingPassword.set(false);
      },
    });
  }

  // ── Historial académico ─────────────────────────────────────────────────

  cargarHistorial(): void {
    this.loadingHistorial.set(true);
    this.historialService.getHistorial().subscribe({
      next: (data) => {
        this.historial.set(data);
        this.loadingHistorial.set(false);
      },
      error: () => this.loadingHistorial.set(false),
    });
  }

  onArchivoPdf(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;

    this.uploadingPdf.set(true);
    this.mensajeHistorial.set(null);
    this.resumenImport.set(null);

    this.historialService.importarPdf(file).subscribe({
      next: (resumen) => {
        this.resumenImport.set(resumen);
        this.mensajeHistorial.set({
          tipo: 'success',
          texto: `Historial importado: ${resumen.importados} cursos aprobados registrados.`,
        });
        this.cargarHistorial();
        this.uploadingPdf.set(false);
        // Actualizar el flag de historial en el usuario
        const u = this.user();
        if (u) {
          this.authService.currentUser.set({ ...u, ultima_actualizacion_historial: new Date().toISOString() });
        }
        input.value = '';
      },
      error: (err) => {
        const msg = err?.error?.message || 'Error al procesar el PDF.';
        this.mensajeHistorial.set({ tipo: 'error', texto: msg });
        this.uploadingPdf.set(false);
        input.value = '';
      },
    });
  }

  limpiarHistorial(): void {
    if (!confirm('¿Eliminar el historial importado del PDF? Se mantendrán los datos ingresados manualmente.')) return;

    this.historialService.limpiar().subscribe({
      next: (r) => {
        this.mensajeHistorial.set({ tipo: 'success', texto: `${r.eliminados} cursos eliminados del historial.` });
        this.cargarHistorial();
        const u = this.user();
        if (u) {
          this.authService.currentUser.set({ ...u, ultima_actualizacion_historial: null });
        }
      },
      error: () => this.mensajeHistorial.set({ tipo: 'error', texto: 'Error al limpiar el historial.' }),
    });
  }

  getEscuelaNombre(): string {
    const escuelas: Record<string, string> = {
      '0': 'Ing. Industrial',
      '1': 'Ing. Informática',
      '2': 'Ing. Mecatrónica',
      '3': 'Ing. Agroindustrial',
    };
    return escuelas[this.user()?.escuela ?? ''] ?? this.user()?.escuela ?? '-';
  }

  getCicloActual(): number | null {
    const codigo = this.user()?.codigo_universitario;
    if (!codigo || codigo.length < 6) return null;
    const anio   = parseInt(codigo.substring(2, 6), 10);
    const actual = new Date().getFullYear();
    return Math.min((actual - anio) * 2 + 1, 12);
  }

  getNotaColor(nota: number | null): 'emerald' | 'indigo' | 'slate' | 'red' | 'amber' {
    if (nota === null) return 'slate';
    if (nota > 15) return 'emerald';
    if (nota > 10) return 'indigo';
    return 'red';
  }

  getTipoLabel(tipo: string | null): string {
    if (tipo === 'O') return 'Obligatorio';
    if (tipo === 'E') return 'Electivo';
    return '-';
  }
}
