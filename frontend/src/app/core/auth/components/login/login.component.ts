import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { EstudianteAuthService } from '../../services/estudiante-auth.service';
import { HttpErrorResponse } from '@angular/common/http';

type LoginTab = 'estudiante' | 'administrativo';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css'
})
export class LoginComponent {
  private authService = inject(AuthService);
  private estudianteAuth = inject(EstudianteAuthService);
  private router = inject(Router);

  // Tab activo
  activeTab = signal<LoginTab>('estudiante');

  // Estados reactivos
  isLoading = signal(false);
  errorMessage = signal('');
  requiresActivation = signal(false);

  // Credenciales administrativo
  adminCredentials = {
    username: '',
    password: ''
  };

  // Credenciales estudiante
  estudianteCredentials = {
    codigo: '',
    password: ''
  };

  /**
   * Cambia el tab activo
   */
  setTab(tab: LoginTab): void {
    this.activeTab.set(tab);
    this.errorMessage.set('');
    this.requiresActivation.set(false);
  }

  /**
   * Login para administrativos (username + password)
   */
  onLoginAdmin(): void {
    if (!this.adminCredentials.username || !this.adminCredentials.password) {
      this.errorMessage.set('Por favor complete todos los campos.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    this.authService.loginAdmin(this.adminCredentials).subscribe({
      next: () => {
        this.router.navigate(['/app/home']);
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);
        this.errorMessage.set(
          err.error?.message || 'Usuario o contraseña incorrectos.'
        );
      }
    });
  }

  /**
   * Login para estudiantes (código + password)
   */
  onLoginEstudiante(): void {
    if (!this.estudianteCredentials.codigo) {
      this.errorMessage.set('Por favor ingrese su código universitario.');
      return;
    }

    if (!/^\d{10}$/.test(this.estudianteCredentials.codigo)) {
      this.errorMessage.set('El código universitario debe tener 10 dígitos.');
      return;
    }

    if (!this.estudianteCredentials.password) {
      this.errorMessage.set('Por favor ingrese su contraseña.');
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');
    this.requiresActivation.set(false);

    this.estudianteAuth.login(
      this.estudianteCredentials.codigo,
      this.estudianteCredentials.password
    ).subscribe({
      next: () => {
        this.router.navigate(['/app/home']);
      },
      error: (err: HttpErrorResponse) => {
        this.isLoading.set(false);

        // Verificar si requiere activación
        if (err.error?.errors?.requires_activation) {
          this.requiresActivation.set(true);
          this.errorMessage.set('Tu cuenta no ha sido activada.');
          return;
        }

        this.errorMessage.set(
          err.error?.message || 'Error al iniciar sesión. Intente nuevamente.'
        );
      }
    });
  }

  /**
   * Navegar a activación con el código prellenado
   */
  goToActivation(): void {
    this.router.navigate(['/activar-cuenta'], {
      queryParams: { codigo: this.estudianteCredentials.codigo }
    });
  }
}
