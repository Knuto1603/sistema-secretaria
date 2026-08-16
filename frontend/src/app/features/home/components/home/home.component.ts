import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/services/auth.service';
import { PeriodoService } from '@core/services/periodo.service';
import { TelegramService } from '@core/services/telegram.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit {
  authService = inject(AuthService);
  private periodoService = inject(PeriodoService);
  private telegramService = inject(TelegramService);

  user = this.authService.currentUser;
  today = new Date();
  solicitudesAbiertas = signal<boolean>(true);
  telegramVinculado = signal<boolean | null>(null);

  ngOnInit(): void {
    if (this.authService.isEstudiante()) {
      this.periodoService.getPeriodoActivo().subscribe({
        next: periodo => this.solicitudesAbiertas.set(periodo?.solicitudes_abiertas ?? true),
        error: () => {},
      });
      this.telegramService.getEstado().subscribe({
        next: estado => this.telegramVinculado.set(estado.vinculado),
        error: () => {},
      });
    }
  }
}