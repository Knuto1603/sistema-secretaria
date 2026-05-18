import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface ModificacionCreada {
  id: string;
  tipo: string;
  motivo: string;
  estado: string;
  created_at: string;
}

@Injectable({ providedIn: 'root' })
export class ModificacionService {
  private http = inject(HttpClient);
  private progUrl = `${environment.apiUrl}/programacion`;

  cerrar(id: string, motivo: string): Observable<ModificacionCreada> {
    return this.http.patch<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/${id}/cerrar`, { motivo }
    ).pipe(map(r => r.data));
  }

  abrirSeccion(data: {
    programacion_id: string;
    aula_id?: string | null;
    grupo_horario_id?: string | null;
    capacidad?: number | null;
    motivo: string;
  }): Observable<ModificacionCreada> {
    return this.http.post<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/abrir-seccion`, data
    ).pipe(map(r => r.data));
  }

  cambiarAula(id: string, data: { aula_id: string; motivo: string }): Observable<ModificacionCreada> {
    return this.http.patch<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/${id}/aula`, data
    ).pipe(map(r => r.data));
  }

  cambiarGrupo(id: string, data: { grupo_horario_id: string; motivo: string }): Observable<ModificacionCreada> {
    return this.http.patch<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/${id}/grupo`, data
    ).pipe(map(r => r.data));
  }

  cambiarAulaYGrupo(id: string, data: {
    aula_id: string;
    grupo_horario_id: string;
    motivo: string;
  }): Observable<ModificacionCreada> {
    return this.http.patch<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/${id}/aula-grupo`, data
    ).pipe(map(r => r.data));
  }

  unificar(data: {
    periodo_id: string;
    programacion_destino_id: string;
    programacion_origen_ids: string[];
    motivo: string;
  }): Observable<ModificacionCreada> {
    return this.http.post<ApiResponse<ModificacionCreada>>(
      `${this.progUrl}/unificar`, data
    ).pipe(map(r => r.data));
  }
}
