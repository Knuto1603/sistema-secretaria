import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface GrupoHorarioDetalle {
  id: string;
  dia_semana: 'lunes' | 'martes' | 'miercoles' | 'jueves' | 'viernes' | 'sabado';
  hora_inicio: string;
  hora_fin: string;
}

export interface GrupoHorario {
  id: string;
  nombre: string;
  descripcion: string | null;
  activo: boolean;
  detalles: GrupoHorarioDetalle[];
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class HorarioService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/grupos-horario`;

  getGrupos(): Observable<GrupoHorario[]> {
    return this.http.get<ApiResponse<GrupoHorario[]>>(this.apiUrl).pipe(
      map(r => r.data)
    );
  }

  crearGrupo(nombre: string, descripcion?: string): Observable<GrupoHorario> {
    return this.http.post<ApiResponse<GrupoHorario>>(this.apiUrl, { nombre, descripcion }).pipe(
      map(r => r.data)
    );
  }

  actualizarGrupo(id: string, data: Partial<Pick<GrupoHorario, 'nombre' | 'descripcion'>>): Observable<GrupoHorario> {
    return this.http.put<ApiResponse<GrupoHorario>>(`${this.apiUrl}/${id}`, data).pipe(
      map(r => r.data)
    );
  }

  eliminarGrupo(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(map(() => undefined));
  }

  toggleActivo(id: string): Observable<GrupoHorario> {
    return this.http.patch<ApiResponse<GrupoHorario>>(`${this.apiUrl}/${id}/toggle`, {}).pipe(
      map(r => r.data)
    );
  }

  agregarDetalle(grupoId: string, detalle: Omit<GrupoHorarioDetalle, 'id'>): Observable<GrupoHorario> {
    return this.http.post<ApiResponse<GrupoHorario>>(`${this.apiUrl}/${grupoId}/detalle`, detalle).pipe(
      map(r => r.data)
    );
  }

  eliminarDetalle(grupoId: string, detalleId: string): Observable<GrupoHorario> {
    return this.http.delete<ApiResponse<GrupoHorario>>(`${this.apiUrl}/${grupoId}/detalle/${detalleId}`).pipe(
      map(r => r.data)
    );
  }
}
