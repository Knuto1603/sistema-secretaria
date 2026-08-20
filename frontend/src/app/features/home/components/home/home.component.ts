import { Component, inject, signal, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { interval, Subscription, switchMap, takeWhile, tap } from 'rxjs';
import * as QRCode from 'qrcode';
import { AuthService } from '../../../../core/auth/services/auth.service';
import { PeriodoService } from '@core/services/periodo.service';
import { TelegramService } from '@core/services/telegram.service';

/** Regenerar el QR al mismo ritmo que expira el codigo de vinculacion en backend (ver telegram.md). */
const QR_REGEN_MS = 10 * 60 * 1000;
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

  private qrRegenSub?: Subscription;
  private estadoPollSub?: Subscription;

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
            this.iniciarRegeneracionQr();
            this.iniciarPollEstado();
          }
        },
        error: () => {},
      });
    }
  }

  private generarQr(): void {
    this.telegramService.generarVinculo().subscribe({
      next: ({ deep_link }) => {
        QRCode.toDataURL(deep_link, { margin: 1, width: 200 })
          .then(dataUrl => this.telegramQr.set(dataUrl))
          .catch(() => this.telegramQr.set(null));
      },
      error: () => this.telegramQr.set(null),
    });
  }

  private iniciarRegeneracionQr(): void {
    this.qrRegenSub = interval(QR_REGEN_MS).subscribe(() => this.generarQr());
  }

  private iniciarPollEstado(): void {
    this.estadoPollSub = interval(ESTADO_POLL_MS).pipe(
      switchMap(() => this.telegramService.getEstado()),
      tap(estado => {
        if (estado.vinculado) {
          this.telegramVinculado.set(true);
          this.telegramQr.set(null);
          this.qrRegenSub?.unsubscribe();
        }
      }),
      takeWhile(estado => !estado.vinculado),
    ).subscribe();
  }

  ngOnDestroy(): void {
    this.qrRegenSub?.unsubscribe();
    this.estadoPollSub?.unsubscribe();
  }
}