import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface BorradorSeccion {
  id: string;
  borrador_id: string;
  curso: { id: string; codigo: string; nombre: string };
  escuela: { id: string; nombre: string; nombre_corto: string };
  ciclo: number;
  tipo: 'O' | 'E';
  seccion: string;
  capacidad: number;
  esta_asignado: boolean;
  docente?: { id: string; nombre_completo: string } | null;
  aula?: {
    id: string;
    nombre: string;
    capacidad: number;
    pabellon?: { id: string; nombre: string } | null;
  } | null;
  grupo_horario?: {
    id: string;
    nombre: string;
    detalles: Array<{ dia_semana: string; hora_inicio: string; hora_fin: string }>;
  } | null;
}

export interface BorradorProgramacion {
  id: string;
  nombre: string;
  ciclo_tipo: 'par' | 'impar';
  estado: 'borrador' | 'publicado';
  periodo: { id: string; nombre: string };
  creado_por: string;
  publicado_por?: string;
  publicado_at?: string;
  created_at: string;
  total_secciones?: number;
  secciones?: BorradorSeccion[];
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface AgregarSeccionDTO {
  curso_id: string;
  escuela_id: string;
  ciclo: number;
  tipo: 'O' | 'E';
  docente_id?: string | null;
  aula_id?: string | null;
  grupo_horario_id?: string | null;
  capacidad?: number;
}

export interface UpdateSeccionDTO {
  docente_id?: string | null;
  aula_id?: string | null;
  grupo_horario_id?: string | null;
  capacidad?: number;
}

export interface BulkCambio {
  id: string;
  aula_id: string | null;
  grupo_horario_id: string | null;
}

@Injectable({ providedIn: 'root' })
export class ProgramacionInteractivaService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/programacion-interactiva`;

  listar(periodoId: string): Observable<BorradorProgramacion[]> {
    const params = new HttpParams().set('periodo_id', periodoId);
    return this.http.get<ApiResponse<BorradorProgramacion[]>>(this.apiUrl, { params }).pipe(
      map(r => r.data)
    );
  }

  generar(data: { periodo_id: string; ciclo_tipo: 'par' | 'impar'; nombre: string }): Observable<BorradorProgramacion> {
    return this.http.post<ApiResponse<BorradorProgramacion>>(`${this.apiUrl}/generar`, data).pipe(
      map(r => r.data)
    );
  }

  obtener(id: string): Observable<BorradorProgramacion> {
    return this.http.get<ApiResponse<BorradorProgramacion>>(`${this.apiUrl}/${id}`).pipe(
      map(r => r.data)
    );
  }

  eliminar(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      map(() => undefined)
    );
  }

  publicar(id: string): Observable<{ estado: string; publicado_at: string }> {
    return this.http.post<ApiResponse<{ estado: string; publicado_at: string }>>(`${this.apiUrl}/${id}/publicar`, {}).pipe(
      map(r => r.data)
    );
  }

  agregarSeccion(id: string, data: AgregarSeccionDTO): Observable<BorradorSeccion> {
    return this.http.post<ApiResponse<BorradorSeccion>>(`${this.apiUrl}/${id}/secciones`, data).pipe(
      map(r => r.data)
    );
  }

  updateSeccion(id: string, seccionId: string, data: UpdateSeccionDTO): Observable<BorradorSeccion> {
    return this.http.put<ApiResponse<BorradorSeccion>>(`${this.apiUrl}/${id}/secciones/${seccionId}`, data).pipe(
      map(r => r.data)
    );
  }

  bulkUpdate(id: string, cambios: BulkCambio[]): Observable<void> {
    return this.http.patch<ApiResponse<null>>(`${this.apiUrl}/${id}/secciones/bulk`, { cambios }).pipe(
      map(() => undefined)
    );
  }

  deleteSeccion(id: string, seccionId: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}/secciones/${seccionId}`).pipe(
      map(() => undefined)
    );
  }

  autoAsignar(id: string): Observable<AutoAsignarResult> {
    return this.http.post<ApiResponse<AutoAsignarResult>>(
      `${this.apiUrl}/${id}/auto-asignar`, {}
    ).pipe(map(r => r.data));
  }
}

export interface ResumenAula {
  aula: string;
  pabellon: string;
  slots_usados: number;
  slots_total: number;
  razon?: string;
}

export interface AutoAsignarResult {
  total: number;
  asignadas: number;
  sin_asignar: number;
  aulas: { usadas: ResumenAula[]; vacias: ResumenAula[] };
}
