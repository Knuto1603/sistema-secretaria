import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map, tap } from 'rxjs';
import { environment } from '@env/environment';
import { AuthService, AuthResponse } from './auth.service';

// ============================================================================
// Interfaces de Respuesta
// ============================================================================

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

export interface VerificacionResponse {
  codigo: string;
  nombre: string;
  tiene_password: boolean;
  email_institucional: string;
}

export interface OtpResponse {
  email_enviado: string;
  expira_en_minutos: number;
}

export interface TempTokenResponse {
  temp_token: string;
  expira_en_minutos: number;
}

// ============================================================================
// Servicio
// ============================================================================

@Injectable({
  providedIn: 'root'
})
export class EstudianteAuthService {
  private http = inject(HttpClient);
  private authService = inject(AuthService);
  private apiUrl = `${environment.apiUrl}/auth/estudiante`;

  // =========================================================================
  // RECURSO: VERIFICACION
  // =========================================================================

  /**
   * POST /auth/estudiante/verificacion
   * Verifica si un código universitario existe y su estado de activación.
   */
  verificarCodigo(codigo: string): Observable<VerificacionResponse> {
    return this.http.post<ApiResponse<VerificacionResponse>>(
      `${this.apiUrl}/verificacion`,
      { codigo }
    ).pipe(map(res => res.data));
  }

  // =========================================================================
  // RECURSO: OTP (Activación)
  // =========================================================================

  /**
   * POST /auth/estudiante/otp
   * Solicita envío de código OTP al correo institucional.
   */
  solicitarOtp(codigo: string): Observable<OtpResponse> {
    return this.http.post<ApiResponse<OtpResponse>>(
      `${this.apiUrl}/otp`,
      { codigo }
    ).pipe(map(res => res.data));
  }

  /**
   * PATCH /auth/estudiante/otp
   * Valida el código OTP y obtiene token temporal.
   */
  verificarOtp(codigo: string, otp: string): Observable<TempTokenResponse> {
    return this.http.patch<ApiResponse<TempTokenResponse>>(
      `${this.apiUrl}/otp`,
      { codigo, otp }
    ).pipe(map(res => res.data));
  }

  // =========================================================================
  // RECURSO: PASSWORD
  // =========================================================================

  /**
   * POST /auth/estudiante/password
   * Establece la contraseña por primera vez (activación).
   * Inicia sesión automáticamente.
   */
  establecerPassword(
    codigo: string,
    tempToken: string,
    password: string,
    passwordConfirmation: string
  ): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(
      `${this.apiUrl}/password`,
      {
        codigo,
        temp_token: tempToken,
        password,
        password_confirmation: passwordConfirmation
      }
    ).pipe(
      map(res => res.data),
      tap(data => this.setSession(data))
    );
  }

  /**
   * PUT /auth/estudiante/password
   * Restablece la contraseña (proceso de recuperación).
   * Inicia sesión automáticamente.
   */
  restablecerPassword(
    codigo: string,
    tempToken: string,
    password: string,
    passwordConfirmation: string
  ): Observable<AuthResponse> {
    return this.http.put<ApiResponse<AuthResponse>>(
      `${this.apiUrl}/password`,
      {
        codigo,
        temp_token: tempToken,
        password,
        password_confirmation: passwordConfirmation
      }
    ).pipe(
      map(res => res.data),
      tap(data => this.setSession(data))
    );
  }

  // =========================================================================
  // RECURSO: SESION
  // =========================================================================

  /**
   * POST /auth/estudiante/sesion
   * Login con código universitario y contraseña.
   */
  login(codigo: string, password: string): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(
      `${this.apiUrl}/sesion`,
      {
        codigo,
        password,
        device_name: 'angular_web'
      }
    ).pipe(
      map(res => res.data),
      tap(data => this.setSession(data))
    );
  }

  // =========================================================================
  // RECURSO: RECUPERACION
  // =========================================================================

  /**
   * POST /auth/estudiante/recuperacion
   * Inicia proceso de recuperación de contraseña.
   */
  solicitarRecuperacion(codigo: string): Observable<OtpResponse> {
    return this.http.post<ApiResponse<OtpResponse>>(
      `${this.apiUrl}/recuperacion`,
      { codigo }
    ).pipe(map(res => res.data));
  }

  /**
   * PATCH /auth/estudiante/recuperacion
   * Valida OTP de recuperación y obtiene token temporal.
   */
  verificarRecuperacion(codigo: string, otp: string): Observable<TempTokenResponse> {
    return this.http.patch<ApiResponse<TempTokenResponse>>(
      `${this.apiUrl}/recuperacion`,
      { codigo, otp }
    ).pipe(map(res => res.data));
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  private setSession(data: AuthResponse): void {
    localStorage.setItem('access_token', data.access_token);
    localStorage.setItem('user_data', JSON.stringify(data.user));
    this.authService.currentUser.set(data.user);
    this.authService.isAuthenticated.set(true);
  }
}
