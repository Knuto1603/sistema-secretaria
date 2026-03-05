import { Injectable, signal, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap, map } from 'rxjs';
import { environment } from '@env/environment';

export interface User {
  id: string;
  name: string;
  email: string;
  tipo_usuario: 'developer' | 'administrativo' | 'estudiante';
  username: string | null;
  codigo_universitario: string | null;
  escuela: string | null;
  anio_ingreso: number | null;
  roles: string[];
  permissions: string[];
}

export interface AuthResponse {
  access_token: string;
  token_type: string;
  user: User;
}

export interface LoginAdminCredentials {
  username: string;
  password: string;
}

export interface LoginEstudianteCredentials {
  codigo: string;
  password: string;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = environment.apiUrl;

  currentUser = signal<User | null>(null);
  isAuthenticated = signal<boolean>(false);
  isImpersonating = signal<boolean>(false);

  constructor() {
    const token = localStorage.getItem('access_token');
    const user = localStorage.getItem('user_data');
    if (token && user) {
      this.currentUser.set(JSON.parse(user));
      this.isAuthenticated.set(true);
    }
    // Restaurar estado de impersonación si la página se refresca
    if (localStorage.getItem('impersonation_token')) {
      this.isImpersonating.set(true);
    }
  }

  /**
   * Login para administrativos y developer (por username)
   */
  loginAdmin(credentials: LoginAdminCredentials): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(`${this.apiUrl}/auth/admin/login`, {
      ...credentials,
      device_name: 'angular_web'
    }).pipe(
      map(response => response.data),
      tap(data => this.setSession(data))
    );
  }

  /**
   * Login para estudiantes (por código + password)
   * Se implementará en la FASE 6
   */
  loginEstudiante(credentials: LoginEstudianteCredentials): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(`${this.apiUrl}/auth/estudiante/login`, {
      ...credentials,
      device_name: 'angular_web'
    }).pipe(
      map(response => response.data),
      tap(data => this.setSession(data))
    );
  }

  /**
   * Login legacy por email (mantener compatibilidad temporal)
   * @deprecated Usar loginAdmin() o loginEstudiante()
   */
  login(credentials: any): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(`${this.apiUrl}/login`, {
      ...credentials,
      device_name: 'angular_web'
    }).pipe(
      map(response => response.data),
      tap(data => this.setSession(data))
    );
  }

  /**
   * Cierra la sesión actual
   */
  logout(): Observable<void> {
    return this.http.post<ApiResponse<null>>(`${this.apiUrl}/logout`, {}).pipe(
      tap(() => this.clearSession()),
      map(() => void 0)
    );
  }

  /**
   * Verifica si el usuario tiene un rol específico.
   * El developer siempre retorna true (acceso total).
   */
  hasRole(role: string): boolean {
    if (this.isDeveloper()) return true;
    return this.currentUser()?.roles.includes(role) || false;
  }

  /**
   * Verifica si el usuario tiene alguno de los roles especificados.
   * El developer siempre retorna true (acceso total).
   */
  hasAnyRole(roles: string[]): boolean {
    if (this.isDeveloper()) return true;
    const userRoles = this.currentUser()?.roles || [];
    return roles.some(role => userRoles.includes(role));
  }

  /**
   * Verifica si es developer (god user)
   */
  isDeveloper(): boolean {
    return this.currentUser()?.tipo_usuario === 'developer';
  }

  /**
   * Verifica si es administrativo
   */
  isAdministrativo(): boolean {
    return this.currentUser()?.tipo_usuario === 'administrativo';
  }

  /**
   * Verifica si es estudiante
   */
  isEstudiante(): boolean {
    return this.currentUser()?.tipo_usuario === 'estudiante';
  }

  /**
   * Inicia impersonación: guarda sesión original y aplica la del usuario target.
   */
  startImpersonation(token: string, targetUser: User): void {
    localStorage.setItem('impersonation_token', localStorage.getItem('access_token')!);
    localStorage.setItem('impersonation_user', localStorage.getItem('user_data')!);
    localStorage.setItem('access_token', token);
    localStorage.setItem('user_data', JSON.stringify(targetUser));
    this.currentUser.set(targetUser);
    this.isImpersonating.set(true);
  }

  /**
   * Finaliza impersonación: restaura la sesión original del developer.
   */
  stopImpersonation(): void {
    const origToken = localStorage.getItem('impersonation_token') ?? '';
    const origUserRaw = localStorage.getItem('impersonation_user') ?? 'null';
    const origUser: User | null = JSON.parse(origUserRaw);

    localStorage.setItem('access_token', origToken);
    if (origUser) {
      localStorage.setItem('user_data', JSON.stringify(origUser));
    }
    localStorage.removeItem('impersonation_token');
    localStorage.removeItem('impersonation_user');
    this.currentUser.set(origUser);
    this.isImpersonating.set(false);
  }

  /**
   * Guarda la sesión en localStorage
   */
  private setSession(data: AuthResponse): void {
    localStorage.setItem('access_token', data.access_token);
    localStorage.setItem('user_data', JSON.stringify(data.user));
    this.currentUser.set(data.user);
    this.isAuthenticated.set(true);
  }

  /**
   * Limpia la sesión de localStorage
   */
  private clearSession(): void {
    localStorage.removeItem('access_token');
    localStorage.removeItem('user_data');
    this.currentUser.set(null);
    this.isAuthenticated.set(false);
  }
}
