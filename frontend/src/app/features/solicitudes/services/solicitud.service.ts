import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Solicitud {
  id: string;
  user_id: string;
  tipo_solicitud_id: string;
  programacion_id: string | null;
  motivo: string;
  estado: string;
  firma_digital_path: string | null;
  archivo_sustento_path: string | null;
  archivo_sustento_url: string | null;
  archivo_sustento_nombre: string | null;
  asignado_a: string | null;
  observaciones_admin: string | null;
  fuera_de_plan: boolean;
  metadatos: any;
  created_at: string;
  updated_at: string;
  user?: {
    id: string;
    name: string;
    email: string;
    codigo_universitario?: string;
    escuela?: string;
    anio_ingreso?: number;
  };
  tipo_solicitud?: { id: string; codigo: string; nombre: string };
  programacion?: {
    id: string;
    clave: string;
    grupo: string;
    seccion: string | null;
    capacidad: number | null;
    n_inscritos: number | null;
    escuela_programada: { id: string; nombre: string; nombre_corto: string | null } | null;
    curso: { id: string; nombre: string; codigo: string } | null;
    docente: { nombre: string } | null;
    aula: { nombre: string } | null;
  } | null;
}

export interface CreateSolicitudDTO {
  programacion_id: string;
  motivo: string;
  firma: string;
  archivo_sustento?: File;
  fuera_de_plan?: boolean;
  inscripcion_escuela?: boolean;
}

export interface UpdateEstadoDTO {
  estado: 'pendiente' | 'en_revision' | 'aprobada' | 'rechazada';
  observaciones?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

interface ApiPaginatedData<T> {
  items: T[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class SolicitudService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/solicitudes`;

  /**
   * Crea una nueva solicitud
   */
  crearSolicitud(data: CreateSolicitudDTO): Observable<Solicitud> {
    const formData = new FormData();
    formData.append('programacion_id', data.programacion_id);
    formData.append('motivo', data.motivo);
    formData.append('firma', data.firma);

    if (data.archivo_sustento) {
      formData.append('archivo_sustento', data.archivo_sustento);
    }

    if (data.fuera_de_plan) {
      formData.append('fuera_de_plan', '1');
    }

    if (data.inscripcion_escuela) {
      formData.append('inscripcion_escuela', '1');
    }

    return this.http.post<ApiResponse<Solicitud>>(this.apiUrl, formData).pipe(
      map(response => response.data)
    );
  }

  /**
   * Obtiene las solicitudes del usuario actual (estudiantes)
   */
  getMisSolicitudes(page: number = 1, perPage: number = 10): Observable<PaginatedResponse<Solicitud>> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    return this.http.get<ApiResponse<ApiPaginatedData<Solicitud>>>(`${this.apiUrl}/mis-solicitudes`, { params }).pipe(
      map(response => {
        const { current_page, last_page, per_page, total } = response.data.pagination;
        const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
        const to = Math.min(current_page * per_page, total);
        return {
          data: response.data.items,
          current_page,
          last_page,
          per_page,
          total,
          from,
          to
        };
      })
    );
  }

  /**
   * Obtiene todas las solicitudes (admin/secretaria/decano)
   */
  getAllSolicitudes(page: number = 1, perPage: number = 10, search?: string, estado?: string, programacionId?: string, tipo?: string, escuelaId?: string, escuelaProgramadaId?: string, sortOrder: 'asc' | 'desc' = 'desc'): Observable<PaginatedResponse<Solicitud>> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString())
      .set('sort_order', sortOrder);

    if (search)             params = params.set('search', search);
    if (estado)             params = params.set('estado', estado);
    if (programacionId)     params = params.set('programacion_id', programacionId);
    if (tipo)               params = params.set('tipo', tipo);
    if (escuelaId)          params = params.set('escuela_id', escuelaId);
    if (escuelaProgramadaId) params = params.set('escuela_programada_id', escuelaProgramadaId);

    return this.http.get<ApiResponse<ApiPaginatedData<Solicitud>>>(this.apiUrl, { params }).pipe(
      map(response => {
        const { current_page, last_page, per_page, total } = response.data.pagination;
        const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
        const to = Math.min(current_page * per_page, total);
        return {
          data: response.data.items,
          current_page,
          last_page,
          per_page,
          total,
          from,
          to
        };
      })
    );
  }

  /**
   * Obtiene el detalle de una solicitud
   */
  getDetalleSolicitud(id: string): Observable<Solicitud> {
    return this.http.get<ApiResponse<Solicitud>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Actualiza el estado de una solicitud (admin/secretaria/decano)
   */
  updateEstado(id: string, data: UpdateEstadoDTO): Observable<Solicitud> {
    return this.http.patch<ApiResponse<Solicitud>>(`${this.apiUrl}/${id}/estado`, data).pipe(
      map(response => response.data)
    );
  }

  /**
   * Anular solicitud (solo el propio estudiante)
   */
  anularSolicitud(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      map(() => undefined)
    );
  }

  /**
   * Devuelve los programacion_ids donde el estudiante ya tiene solicitud activa.
   * Usado para mostrar badge "Solicitado" en la tabla de programación.
   */
  getProgramacionesConSolicitudActiva(): Observable<string[]> {
    return this.http.get<ApiResponse<string[]>>(`${this.apiUrl}/programaciones-activas`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Cursos distintos que tienen solicitudes (para filtro admin)
   */
  getCursosConSolicitud(): Observable<Array<{
    id: string; clave: string; grupo: string; seccion: string | null;
    curso: { nombre: string; codigo: string };
    escuela_programada: string | null;
  }>> {
    return this.http.get<ApiResponse<any[]>>(`${this.apiUrl}/cursos-solicitados`).pipe(
      map(r => r.data)
    );
  }

  /**
   * Descarga CSV exportado con los filtros actuales
   */
  exportarCSV(params: Record<string, string>): Observable<Blob> {
    let httpParams = new HttpParams();
    Object.entries(params).forEach(([k, v]) => { if (v) httpParams = httpParams.set(k, v); });
    return this.http.get(`${this.apiUrl}/exportar`, { params: httpParams, responseType: 'blob' });
  }

  /**
   * Estadísticas de solicitudes (admin/secretaria)
   */
  getEstadisticas(): Observable<{
    por_estado: { pendiente: number; en_revision: number; aprobada: number; rechazada: number };
    total: number;
    por_tipo: { cupo_ext: number; insc_escuela: number };
    por_escuela: Array<{ escuela: string; total: number }>;
    cursos_top: Array<{ curso: string; codigo: string; total_solicitudes: number; escuela_programada?: string }>;
  }> {
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/estadisticas`).pipe(
      map(response => response.data)
    );
  }
}
