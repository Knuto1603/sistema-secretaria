import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Periodo {
  id: string;
  nombre: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  activo: boolean;
  created_at: string;
}

export interface CreatePeriodoDTO {
  nombre: string;
  fecha_inicio?: string | null;
  fecha_fin?: string | null;
  activo?: boolean;
}

export interface UpdatePeriodoDTO {
  nombre?: string;
  fecha_inicio?: string | null;
  fecha_fin?: string | null;
  activo?: boolean;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({
  providedIn: 'root'
})
export class PeriodoService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/periodos`;

  /**
   * Obtiene todos los periodos
   */
  getPeriodos(): Observable<Periodo[]> {
    return this.http.get<ApiResponse<Periodo[]>>(this.apiUrl).pipe(
      map(response => response.data)
    );
  }

  /**
   * Obtiene el periodo activo
   */
  getPeriodoActivo(): Observable<Periodo | null> {
    return this.http.get<ApiResponse<Periodo>>(`${this.apiUrl}/active`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Obtiene un periodo por ID
   */
  getPeriodo(id: string): Observable<Periodo> {
    return this.http.get<ApiResponse<Periodo>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Crea un nuevo periodo
   */
  crearPeriodo(data: CreatePeriodoDTO): Observable<Periodo> {
    return this.http.post<ApiResponse<Periodo>>(this.apiUrl, data).pipe(
      map(response => response.data)
    );
  }

  /**
   * Actualiza un periodo existente
   */
  actualizarPeriodo(id: string, data: UpdatePeriodoDTO): Observable<Periodo> {
    return this.http.put<ApiResponse<Periodo>>(`${this.apiUrl}/${id}`, data).pipe(
      map(response => response.data)
    );
  }

  /**
   * Elimina un periodo
   */
  eliminarPeriodo(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      map(() => undefined)
    );
  }

  /**
   * Activa un periodo (desactiva los demás)
   */
  activarPeriodo(id: string): Observable<Periodo> {
    return this.http.patch<ApiResponse<Periodo>>(`${this.apiUrl}/${id}/activate`, {}).pipe(
      map(response => response.data)
    );
  }

  /**
   * Desactiva un periodo
   */
  desactivarPeriodo(id: string): Observable<Periodo> {
    return this.http.patch<ApiResponse<Periodo>>(`${this.apiUrl}/${id}/deactivate`, {}).pipe(
      map(response => response.data)
    );
  }
}
