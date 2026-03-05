import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { EstudianteAuthService } from '../../services/estudiante-auth.service';
import { HttpErrorResponse } from '@angular/common/http';

type Step = 'codigo' | 'otp' | 'password';

@Component({
  selector: 'app-recuperar-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './recuperar-password.component.html'
})
export class RecuperarPasswordComponent {
  private estudianteAuth = inject(EstudianteAuthService);
  private router = inject(Router);

  // Estado del flujo
  currentStep = signal<Step>('codigo');
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  // Datos
  codigo = '';
  otp = '';
  tempToken = '';
  emailEnviado = signal('');
  countdown = signal(0);
  private countdownInterval: any;

  // Password
  password = '';
  passwordConfirmation = '';
  showPassword = signal(false);

  // =========================================================================
  // PASO 1: SOLICITAR RECUPERACIÓN
  // =========================================================================

  onSolicitarRecuperacion(): void {
    if (this.codigo.length !== 10) {
      this.errorMessage.set('El código debe tener 10 dígitos.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.estudianteAuth.solicitarRecuperacion(this.codigo).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        this.emailEnviado.set(response.email_enviado);
        this.currentStep.set('otp');
        this.startCountdown(60);
        this.successMessage.set('Código de recuperación enviado.');
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.message || 'Error al procesar la solicitud.');
      }
    });
  }

  // =========================================================================
  // PASO 2: VERIFICAR OTP
  // =========================================================================

  reenviarOtp(): void {
    if (this.countdown() > 0) return;
    this.otp = '';
    this.onSolicitarRecuperacion();
  }

  onVerificarOtp(): void {
    if (this.otp.length !== 6) {
      this.errorMessage.set('El código debe tener 6 dígitos.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.estudianteAuth.verificarRecuperacion(this.codigo, this.otp).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        this.tempToken = response.temp_token;
        this.currentStep.set('password');
        this.stopCountdown();
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.message || 'Código incorrecto o expirado.');
      }
    });
  }

  // =========================================================================
  // PASO 3: RESTABLECER CONTRASEÑA
  // =========================================================================

  onRestablecerPassword(): void {
    if (this.password.length < 8) {
      this.errorMessage.set('La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    if (this.password !== this.passwordConfirmation) {
      this.errorMessage.set('Las contraseñas no coinciden.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.estudianteAuth.restablecerPassword(
      this.codigo,
      this.tempToken,
      this.password,
      this.passwordConfirmation
    ).subscribe({
      next: () => {
        this.isLoading.set(false);
        this.router.navigate(['/app']);
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.message || 'Error al restablecer la contraseña.');
      }
    });
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  togglePasswordVisibility(): void {
    this.showPassword.update(v => !v);
  }

  private startCountdown(seconds: number): void {
    this.countdown.set(seconds);
    this.countdownInterval = setInterval(() => {
      this.countdown.update(v => {
        if (v <= 1) {
          this.stopCountdown();
          return 0;
        }
        return v - 1;
      });
    }, 1000);
  }

  private stopCountdown(): void {
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
      this.countdownInterval = null;
    }
  }

  goBack(): void {
    this.stopCountdown();
    if (this.currentStep() === 'otp') {
      this.currentStep.set('codigo');
      this.otp = '';
    } else if (this.currentStep() === 'password') {
      this.currentStep.set('otp');
      this.password = '';
      this.passwordConfirmation = '';
    }
    this.errorMessage.set('');
    this.successMessage.set('');
  }
}
