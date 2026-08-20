import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Solicitud {
  id: string;
  user_id: string;
  tipo_solicitud_id: string;
  programacion_id: string | null;
  periodo_id: string | null;
  motivo: string;
  estado: string;
  firma_digital_path: string | null;
  archivo_sustento_path: string | null;
  archivo_sustento_url: string | null;
  archivo_sustento_nombre: string | null;
  constancia_pdf_url: string | null;
  asignado_a: string | null;
  observaciones_admin: string | null;
  fuera_de_plan: boolean;
  respuesta_alumno: string | null;
  fecha_respuesta: string | null;
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
  periodo?: { id: string; nombre: string } | null;
}

export interface SeccionMetrica {
  id: string;
  grupo: string;
  seccion: string | null;
  n_inscritos: number | null;
  capacidad: number | null;
  docente: string | null;
  aula: string | null;
  escuela_programada: string | null;
  lleno: boolean;
}

export interface SolicitanteMetrica {
  id: string;
  programacion_id: string | null;
  estado: string;
  fecha: string;
  fuera_de_plan: boolean;
  codigo: string | null;
  nombre: string | null;
  escuela: string | null;
  seccion_solicitada: string | null;
  grupo_solicitado: string | null;
}

export interface MetricaCurso {
  curso_id: string;
  codigo: string;
  nombre: string;
  total: number;
  por_estado: { pendiente: number; en_revision: number; aprobada: number; rechazada: number; apelado: number; anulada: number };
  secciones: SeccionMetrica[];
  solicitantes: SolicitanteMetrica[];
}

export interface CreateSolicitudDTO {
  programacion_id: string;
  motivo: string;
  firma: string;
  archivo_sustento?: File;
  fuera_de_plan?: boolean;
  inscripcion_escuela?: boolean;
  retiro_curso?: boolean;
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

    if (data.retiro_curso) {
      formData.append('retiro_curso', '1');
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
  getAllSolicitudes(page: number = 1, perPage: number = 10, search?: string, estado?: string, programacionId?: string, tipo?: string, escuelaId?: string, escuelaProgramadaId?: string, cursoId?: string, grupo?: string, sortOrder: 'asc' | 'desc' = 'desc'): Observable<PaginatedResponse<Solicitud>> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString())
      .set('sort_order', sortOrder);

    if (search)              params = params.set('search', search);
    if (estado)              params = params.set('estado', estado);
    if (programacionId)      params = params.set('programacion_id', programacionId);
    if (cursoId)             params = params.set('curso_id', cursoId);
    if (grupo)               params = params.set('grupo', grupo);
    if (tipo)                params = params.set('tipo', tipo);
    if (escuelaId)           params = params.set('escuela_id', escuelaId);
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
   * Enviar apelación a una solicitud rechazada (propio estudiante)
   */
  responderSolicitud(id: string, respuesta: string): Observable<Solicitud> {
    return this.http.post<ApiResponse<Solicitud>>(`${this.apiUrl}/${id}/respuesta`, { respuesta }).pipe(
      map(r => r.data)
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
   * Devuelve los curso_ids del periodo activo donde el estudiante ya tiene solicitud activa.
   * Usado para deshabilitar el botón "Solicitar cupo" en la tabla de programación.
   */
  getCursosConSolicitudActiva(): Observable<string[]> {
    return this.http.get<ApiResponse<string[]>>(`${this.apiUrl}/cursos-con-solicitud-activa`).pipe(
      map(response => response.data)
    );
  }

  /**
   * Cursos distintos que tienen solicitudes (para filtro admin)
   */
  getCursosConSolicitud(): Observable<Array<{
    id: string; curso_id: string; clave: string; grupo: string; seccion: string | null;
    curso: { id: string; nombre: string; codigo: string };
    escuela_programada: string | null;
  }>> {
    return this.http.get<ApiResponse<any[]>>(`${this.apiUrl}/cursos-solicitados`).pipe(
      map(r => r.data)
    );
  }

  /**
   * Exporta métricas completas a Excel (4 hojas)
   */
  exportarMetricas(periodoId?: string): Observable<Blob> {
    let params = new HttpParams();
    if (periodoId) params = params.set('periodo_id', periodoId);
    return this.http.get(`${this.apiUrl}/exportar-metricas`, { params, responseType: 'blob' });
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
   * Métricas de cupo extra por curso (secciones + solicitantes)
   */
  getMetricasCupo(tipo: 'CUPO_EXT' | 'INSC_ESCUELA' = 'CUPO_EXT', periodoId?: string): Observable<MetricaCurso[]> {
    let params = new HttpParams().set('tipo', tipo);
    if (periodoId) params = params.set('periodo_id', periodoId);
    return this.http.get<ApiResponse<MetricaCurso[]>>(`${this.apiUrl}/metricas-cupo`, { params }).pipe(
      map(r => r.data)
    );
  }

  /**
   * Estadísticas de solicitudes (admin/secretaria)
   */
  getEstadisticas(periodoId?: string): Observable<{
    periodo_id: string | null;
    por_estado: { pendiente: number; en_revision: number; aprobada: number; rechazada: number; apelado: number; anulada: number };
    total: number;
    por_tipo: { cupo_ext: number; insc_escuela: number; retiro_curso: number };
    por_escuela: Array<{ escuela: string; total: number }>;
    cursos_top: Array<{ curso: string; codigo: string; total_solicitudes: number; escuela_programada?: string }>;
  }> {
    let params = new HttpParams();
    if (periodoId) params = params.set('periodo_id', periodoId);
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/estadisticas`, { params }).pipe(
      map(response => response.data)
    );
  }
}
