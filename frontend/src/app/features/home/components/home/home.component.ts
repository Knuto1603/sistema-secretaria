import { Component, inject, signal, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { interval, Subscription, switchMap, takeWhile, tap } from 'rxjs';
import * as QRCode from 'qrcode';
import { AuthService } from '../../../../core/auth/services/auth.service';
import { PeriodoService } from '@core/services/periodo.service';
import { TelegramService } from '@core/services/telegram.service';

/**
 * Margen de seguridad: regenerar el QR un poco antes de que el codigo actual
 * expire de verdad en backend (ver CODIGO_TTL_MINUTOS en telegram.md), para
 * no dejar una ventana en la que el QR en pantalla ya sea invalido.
 */
const QR_REGEN_MARGIN_MS = 30 * 1000;
const ESTADO_POLL_MS = 5000;

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit, OnDestroy {
  authService = inject(AuthService);
  private periodoService = inject(PeriodoService);
  private telegramService = inject(TelegramService);

  user = this.authService.currentUser;
  today = new Date();
  solicitudesAbiertas = signal<boolean>(true);
  telegramVinculado = signal<boolean | null>(null);
  telegramQr = signal<string | null>(null);

  private estadoPollSub?: Subscription;
  /** Timestamp (ms) en el que el codigo actualmente mostrado deja de ser valido en backend. */
  private qrExpiraEn = 0;
  private onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      this.refrescarQrSiCorresponde();
    }
  };

  ngOnInit(): void {
    if (this.authService.isEstudiante()) {
      this.periodoService.getPeriodoActivo().subscribe({
        next: periodo => this.solicitudesAbiertas.set(periodo?.solicitudes_abiertas ?? true),
        error: () => {},
      });
      this.telegramService.getEstado().subscribe({
        next: estado => {
          this.telegramVinculado.set(estado.vinculado);
          if (!estado.vinculado) {
            this.generarQr();
            this.iniciarPollEstado();
            document.addEventListener('visibilitychange', this.onVisibilityChange);
          }
        },
        error: () => {},
      });
    }
  }

  private generarQr(): void {
    this.telegramService.generarVinculo().subscribe({
      next: ({ deep_link, expira_en_minutos }) => {
        this.qrExpiraEn = Date.now() + expira_en_minutos * 60_000;
        QRCode.toDataURL(deep_link, { margin: 1, width: 200 })
          .then(dataUrl => this.telegramQr.set(dataUrl))
          .catch(() => this.telegramQr.set(null));
      },
      error: () => this.telegramQr.set(null),
    });
  }

  /**
   * Se llama en cada tick del poll y al recuperar el foco de la pestaña: un
   * setInterval fijo no alcanza porque las pestañas en segundo plano (el caso
   * normal aca, ya que el alumno suelta la pestaña para escanear con el
   * celular) sufren throttling del navegador y pueden atrasar el timer mucho
   * mas alla de los 10 min reales que dura el codigo en backend.
   */
  private refrescarQrSiCorresponde(): void {
    if (Date.now() >= this.qrExpiraEn - QR_REGEN_MARGIN_MS) {
      this.generarQr();
    }
  }

  private iniciarPollEstado(): void {
    this.estadoPollSub = interval(ESTADO_POLL_MS).pipe(
      tap(() => this.refrescarQrSiCorresponde()),
      switchMap(() => this.telegramService.getEstado()),
      tap(estado => {
        if (estado.vinculado) {
          this.telegramVinculado.set(true);
          this.telegramQr.set(null);
        }
      }),
      takeWhile(estado => !estado.vinculado),
    ).subscribe();
  }

  ngOnDestroy(): void {
    this.estadoPollSub?.unsubscribe();
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
  }
}