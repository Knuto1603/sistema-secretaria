import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface TelegramVinculo {
  codigo: string;
  deep_link: string;
  expira_en_minutos: number;
}

export interface TelegramEstado {
  vinculado: boolean;
  vinculado_desde: string | null;
}

export interface TelegramEstadisticas {
  total_estudiantes: number;
  vinculados: number;
  porcentaje: number;
}

export interface TelegramVinculado {
  id: string;
  name: string;
  codigo_universitario: string;
  escuela: string | null;
  vinculado_desde: string | null;
}

export interface EnviarMasivoPayload {
  mensaje: string;
  user_ids?: string[];
  search?: string;
  escuela_codigo?: string;
  anio_ingreso?: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

interface PaginatedData<T> {
  items: T[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({ providedIn: 'root' })
export class TelegramService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/me/telegram`;
  private adminUrl = `${environment.apiUrl}/telegram`;

  generarVinculo(): Observable<TelegramVinculo> {
    return this.http.post<ApiResponse<TelegramVinculo>>(`${this.apiUrl}/generar-vinculo`, {}).pipe(
      map(r => r.data)
    );
  }

  getEstado(): Observable<TelegramEstado> {
    return this.http.get<ApiResponse<TelegramEstado>>(`${this.apiUrl}/estado`).pipe(
      map(r => r.data)
    );
  }

  desvincular(): Observable<void> {
    return this.http.delete<ApiResponse<null>>(this.apiUrl).pipe(map(() => undefined));
  }

  getEstadisticas(): Observable<TelegramEstadisticas> {
    return this.http.get<ApiResponse<TelegramEstadisticas>>(`${this.adminUrl}/estadisticas`).pipe(
      map(r => r.data)
    );
  }

  getVinculados(
    page = 1,
    search?: string,
    escuelaCodigo?: string,
    anioIngreso?: number,
  ): Observable<PaginatedData<TelegramVinculado>> {
    let params = new HttpParams().set('page', page.toString());
    if (search) params = params.set('search', search);
    if (escuelaCodigo) params = params.set('escuela_codigo', escuelaCodigo);
    if (anioIngreso) params = params.set('anio_ingreso', anioIngreso.toString());

    return this.http.get<ApiResponse<PaginatedData<TelegramVinculado>>>(`${this.adminUrl}/vinculados`, { params }).pipe(
      map(r => r.data)
    );
  }

  enviarMasivo(payload: EnviarMasivoPayload): Observable<{ enviados: number }> {
    return this.http.post<ApiResponse<{ enviados: number }>>(`${this.adminUrl}/enviar`, payload).pipe(
      map(r => r.data)
    );
  }
}
