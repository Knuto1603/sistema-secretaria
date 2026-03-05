import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';
import { EstudianteAuthService, VerificacionResponse } from '../../services/estudiante-auth.service';
import { HttpErrorResponse } from '@angular/common/http';

type Step = 'codigo' | 'otp' | 'password';

@Component({
  selector: 'app-activar-cuenta',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './activar-cuenta.component.html'
})
export class ActivarCuentaComponent implements OnInit {
  private estudianteAuth = inject(EstudianteAuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  // Estado del flujo
  currentStep = signal<Step>('codigo');
  isLoading = signal(false);
  errorMessage = signal('');
  successMessage = signal('');

  // Datos del estudiante
  codigo = '';
  estudianteInfo = signal<VerificacionResponse | null>(null);

  ngOnInit(): void {
    // Prellenar código si viene desde queryParams
    this.route.queryParams.subscribe(params => {
      if (params['codigo']) {
        this.codigo = params['codigo'];
      }
    });
  }

  // Datos OTP
  otp = '';
  tempToken = '';
  emailEnviado = signal('');
  countdown = signal(0);
  private countdownInterval: any;

  // Datos password
  password = '';
  passwordConfirmation = '';
  showPassword = signal(false);

  // =========================================================================
  // PASO 1: VERIFICAR CÓDIGO
  // =========================================================================

  onVerificarCodigo(): void {
    if (this.codigo.length !== 10) {
      this.errorMessage.set('El código debe tener 10 dígitos.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.estudianteAuth.verificarCodigo(this.codigo).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        this.estudianteInfo.set(response);

        if (response.tiene_password) {
          this.errorMessage.set('Esta cuenta ya está activada. Usa el login normal.');
          return;
        }

        // Solicitar OTP automáticamente
        this.solicitarOtp();
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.message || 'Error al verificar el código.');
      }
    });
  }

  // =========================================================================
  // PASO 2: SOLICITAR Y VERIFICAR OTP
  // =========================================================================

  solicitarOtp(): void {
    this.isLoading.set(true);
    this.errorMessage.set('');

    this.estudianteAuth.solicitarOtp(this.codigo).subscribe({
      next: (response) => {
        this.isLoading.set(false);
        this.emailEnviado.set(response.email_enviado);
        this.currentStep.set('otp');
        this.startCountdown(60);
        this.successMessage.set('Código enviado a tu correo institucional.');
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(err.error?.message || 'Error al enviar el código.');
      }
    });
  }

  reenviarOtp(): void {
    if (this.countdown() > 0) return;
    this.otp = '';
    this.solicitarOtp();
  }

  onVerificarOtp(): void {
    if (this.otp.length !== 6) {
      this.errorMessage.set('El código debe tener 6 dígitos.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.estudianteAuth.verificarOtp(this.codigo, this.otp).subscribe({
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
  // PASO 3: ESTABLECER CONTRASEÑA
  // =========================================================================

  onEstablecerPassword(): void {
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

    this.estudianteAuth.establecerPassword(
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
        this.errorMessage.set(err.error?.message || 'Error al establecer la contraseña.');
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
