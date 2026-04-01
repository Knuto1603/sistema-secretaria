import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/services/auth.service';
import { PeriodoService } from '@core/services/periodo.service';

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

  user = this.authService.currentUser;
  today = new Date();
  solicitudesAbiertas = signal<boolean>(true);

  ngOnInit(): void {
    if (this.authService.isEstudiante()) {
      this.periodoService.getPeriodoActivo().subscribe({
        next: periodo => this.solicitudesAbiertas.set(periodo?.solicitudes_abiertas ?? true),
        error: () => {},
      });
    }
  }
}