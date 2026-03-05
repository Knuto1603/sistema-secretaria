import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface TipoSolicitud {
  id: string;
  codigo: string;
  nombre: string;
  descripcion: string | null;
  requiere_archivo: boolean;
  activo: boolean;
  created_at: string;
}

export interface CreateTipoSolicitudDTO {
  codigo: string;
  nombre: string;
  descripcion?: string;
  requiere_archivo?: boolean;
  activo?: boolean;
}

export interface UpdateTipoSolicitudDTO {
  codigo?: string;
  nombre?: string;
  descripcion?: string;
  requiere_archivo?: boolean;
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
export class TipoSolicitudService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/tipos-solicitud`;

  getAll(): Observable<TipoSolicitud[]> {
    return this.http.get<ApiResponse<TipoSolicitud[]>>(this.apiUrl).pipe(
      map(response => response.data)
    );
  }

  getById(id: string): Observable<TipoSolicitud> {
    return this.http.get<ApiResponse<TipoSolicitud>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }

  create(data: CreateTipoSolicitudDTO): Observable<TipoSolicitud> {
    return this.http.post<ApiResponse<TipoSolicitud>>(this.apiUrl, data).pipe(
      map(response => response.data)
    );
  }

  update(id: string, data: UpdateTipoSolicitudDTO): Observable<TipoSolicitud> {
    return this.http.put<ApiResponse<TipoSolicitud>>(`${this.apiUrl}/${id}`, data).pipe(
      map(response => response.data)
    );
  }

  delete(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      map(() => void 0)
    );
  }

  toggleActivo(id: string, activo: boolean): Observable<TipoSolicitud> {
    return this.http.patch<ApiResponse<TipoSolicitud>>(`${this.apiUrl}/${id}/toggle`, { activo }).pipe(
      map(response => response.data)
    );
  }
}
