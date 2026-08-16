import { Component, inject, signal, computed, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { interval, Subscription, switchMap, takeWhile, tap } from 'rxjs';
import { AuthService } from '@core/auth/services/auth.service';
import { HistorialService, HistorialResponse, ImportPdfResumen } from '@core/services/historial.service';
import { ProgresoService, ProgresoAcademico } from '@core/services/progreso.service';
import { TelegramService, TelegramEstado } from '@core/services/telegram.service';
import { AppBadgeComponent } from '@shared/badge/badge.component';

type Tab = 'datos' | 'password' | 'historial' | 'progreso' | 'telegram';

@Component({
  selector: 'app-perfil',
  standalone: true,
  imports: [CommonModule, DecimalPipe, FormsModule, AppBadgeComponent],
  templateUrl: './perfil.component.html',
})
export class PerfilComponent implements OnInit, OnDestroy {
  private authService      = inject(AuthService);
  private historialService = inject(HistorialService);
  private progresoService  = inject(ProgresoService);
  private telegramService  = inject(TelegramService);
  private route             = inject(ActivatedRoute);
  private pollSubTelegram?: Subscription;

  user    = this.authService.currentUser;
  tabActiva = signal<Tab>('datos');

  esEstudiante    = computed(() => this.authService.isEstudiante());
  esImpersonando  = computed(() => this.authService.isImpersonating());
  cambioObligatorio = computed(() => this.user()?.must_change_password ?? false);

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

  // Progreso académico
  progreso         = signal<ProgresoAcademico | null>(null);
  loadingProgreso  = signal(false);

  // Telegram
  telegramEstado   = signal<TelegramEstado | null>(null);
  telegramLoading  = signal(false);
  telegramVinculando = signal(false);
  telegramDeepLink = signal<string | null>(null);

  // Mensaje general
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  ngOnInit(): void {
    if (this.esEstudiante()) {
      this.cargarHistorial();
    }
    if (this.cambioObligatorio() || this.route.snapshot.queryParamMap.get('cambio_obligatorio') === '1') {
      this.tabActiva.set('password');
    } else if (this.esEstudiante() && this.route.snapshot.queryParamMap.get('tab') === 'telegram') {
      this.setTab('telegram');
    }
  }

  cargarProgreso(): void {
    if (this.progreso()) return;
    this.loadingProgreso.set(true);
    this.progresoService.getMiProgreso().subscribe({
      next: (data) => {
        this.progreso.set(data);
        this.loadingProgreso.set(false);
      },
      error: () => this.loadingProgreso.set(false),
    });
  }

  setTab(tab: Tab): void {
    if (this.cambioObligatorio() && tab !== 'password') return;
    this.tabActiva.set(tab);
    this.mensaje.set(null);
    this.mensajeHistorial.set(null);
    this.resumenImport.set(null);
    if (tab === 'progreso' && this.esEstudiante()) {
      this.cargarProgreso();
    }
    if (tab === 'telegram' && this.esEstudiante()) {
      this.cargarEstadoTelegram();
    }
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
        this.authService.patchCurrentUser({ must_change_password: false });
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
    // El backend ya manda el nombre resuelto (user.escuela = nombre_corto), este
    // mapa por codigo nunca matchea contra ese valor; se deja como fallback.
    const escuelas: Record<string, string> = {
      '0': 'Ing. Industrial',
      '1': 'Ing. Informática',
      '2': 'Ing. Agroindustrial',
      '3': 'Ing. Mecatrónica',
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

  // ── Telegram ─────────────────────────────────────────────────────────────

  cargarEstadoTelegram(): void {
    this.telegramLoading.set(true);
    this.telegramService.getEstado().subscribe({
      next: (estado) => {
        this.telegramEstado.set(estado);
        this.telegramLoading.set(false);
      },
      error: () => this.telegramLoading.set(false),
    });
  }

  iniciarVinculacionTelegram(): void {
    this.telegramVinculando.set(true);
    this.pollSubTelegram?.unsubscribe();

    this.telegramService.generarVinculo().subscribe({
      next: ({ deep_link }) => {
        this.telegramDeepLink.set(deep_link);
        window.open(deep_link, '_blank');
        this.pollEstadoTelegram();
      },
      error: () => this.telegramVinculando.set(false),
    });
  }

  private pollEstadoTelegram(): void {
    this.pollSubTelegram = interval(3000).pipe(
      switchMap(() => this.telegramService.getEstado()),
      tap((estado) => {
        this.telegramEstado.set(estado);
        if (estado.vinculado) {
          this.telegramVinculando.set(false);
          this.telegramDeepLink.set(null);
        }
      }),
      takeWhile((estado) => !estado.vinculado),
    ).subscribe();
  }

  desvincularTelegram(): void {
    if (!confirm('¿Desvincular tu cuenta de Telegram? Dejarás de recibir notificaciones ahí.')) return;

    this.telegramService.desvincular().subscribe({
      next: () => this.telegramEstado.set({ vinculado: false, vinculado_desde: null }),
    });
  }

  ngOnDestroy(): void {
    this.pollSubTelegram?.unsubscribe();
  }
}
