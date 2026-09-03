import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export type TipoApertura = 'nueva_apertura' | 'cambio_grupo';
export type EstadoApertura = 'pendiente' | 'en_revision' | 'aprobada' | 'rechazada' | 'anulada';

export interface SolicitudApertura {
  id: string;
  user_id: string;
  curso_id: string;
  periodo_id: string;
  escuela_id: string;
  tipo: TipoApertura;
  programacion_referencia_id: string | null;
  motivo: string;
  firma_digital_path: string | null;
  estado: EstadoApertura;
  observaciones_admin: string | null;
  user?: { id: string; name: string; codigo_universitario?: string; escuela?: string; anio_ingreso?: number } | null;
  curso?: { id: string; codigo: string; nombre: string } | null;
  periodo?: { id: string; nombre: string } | null;
  escuela?: { id: string; nombre: string; nombre_corto: string | null } | null;
  programacion_referencia?: { id: string; seccion: string | null; grupo: string | null } | null;
  created_at: string;
  updated_at: string;
}

export interface SeccionCurso {
  id: string;
  seccion: string | null;
  grupo: string | null;
  con_cupo: boolean;
}

export interface CursoBusqueda {
  id: string;
  codigo: string;
  nombre: string;
  ciclo_en_mi_plan: number | null;
  ya_tiene_solicitud_activa: boolean;
  programado_este_periodo: boolean;
  secciones: SeccionCurso[];
  tipo_sugerido: TipoApertura;
}

export interface SolicitanteApertura {
  solicitud_id: string;
  user_id: string;
  nombre: string | null;
  codigo: string | null;
  tipo: TipoApertura;
  estado: EstadoApertura;
  fecha: string;
  cumple_prerequisitos: boolean | null;
}

export interface EscuelaIndicador {
  escuela_id: string;
  escuela_nombre: string | null;
  ciclo_en_plan: number | null;
  total_solicitantes: number;
  es_cadena: boolean;
  cursos_cadena: Array<{ codigo: string; nombre: string }>;
  fuera_de_periodo: boolean | null;
  pct_cumple_prerequisitos: number | null;
  solicitantes: SolicitanteApertura[];
}

export interface CursoAgrupado {
  curso_id: string;
  codigo: string;
  nombre: string;
  periodo_id: string;
  total: number;
  total_activas: number;
  cumple_minimo: boolean;
  es_cadena: boolean;
  fuera_de_periodo: boolean;
  por_estado: { pendiente: number; en_revision: number; aprobada: number; rechazada: number; anulada: number };
  por_tipo: { nueva_apertura: number; cambio_grupo: number };
  escuelas: EscuelaIndicador[];
}

export interface CreateSolicitudAperturaDTO {
  curso_id: string;
  tipo: TipoApertura;
  programacion_referencia_id?: string | null;
  motivo: string;
  firma: string;
}

export interface UpdateEstadoAperturaDTO {
  estado: Exclude<EstadoApertura, 'anulada'>;
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
  pagination: { current_page: number; last_page: number; per_page: number; total: number };
}

@Injectable({ providedIn: 'root' })
export class SolicitudAperturaService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/solicitudes-apertura`;

  private toPaginated<T>(res: ApiResponse<ApiPaginatedData<T>>): PaginatedResponse<T> {
    const { current_page, last_page, per_page, total } = res.data.pagination;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to = Math.min(current_page * per_page, total);
    return { data: res.data.items, current_page, last_page, per_page, total, from, to };
  }

  buscarCurso(search: string, page = 1, perPage = 15): Observable<PaginatedResponse<CursoBusqueda>> {
    let params = new HttpParams().set('page', page).set('per_page', perPage);
    if (search) params = params.set('search', search);

    return this.http.get<ApiResponse<ApiPaginatedData<CursoBusqueda>>>(`${this.apiUrl}/buscar-curso`, { params })
      .pipe(map(res => this.toPaginated(res)));
  }

  crearSolicitud(data: CreateSolicitudAperturaDTO): Observable<SolicitudApertura> {
    return this.http.post<ApiResponse<SolicitudApertura>>(this.apiUrl, data).pipe(map(r => r.data));
  }

  getMisSolicitudes(page = 1, perPage = 10): Observable<PaginatedResponse<SolicitudApertura>> {
    const params = new HttpParams().set('page', page).set('per_page', perPage);
    return this.http.get<ApiResponse<ApiPaginatedData<SolicitudApertura>>>(`${this.apiUrl}/mis-solicitudes`, { params })
      .pipe(map(res => this.toPaginated(res)));
  }

  getAgrupado(periodoId?: string, escuelaId?: string, tipo?: TipoApertura): Observable<CursoAgrupado[]> {
    let params = new HttpParams();
    if (periodoId) params = params.set('periodo_id', periodoId);
    if (escuelaId) params = params.set('escuela_id', escuelaId);
    if (tipo) params = params.set('tipo', tipo);

    return this.http.get<ApiResponse<CursoAgrupado[]>>(`${this.apiUrl}/agrupado`, { params }).pipe(map(r => r.data));
  }

  getDetalle(id: string): Observable<SolicitudApertura> {
    return this.http.get<ApiResponse<SolicitudApertura>>(`${this.apiUrl}/${id}`).pipe(map(r => r.data));
  }

  anular(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(map(() => undefined));
  }

  updateEstado(id: string, data: UpdateEstadoAperturaDTO): Observable<SolicitudApertura> {
    return this.http.patch<ApiResponse<SolicitudApertura>>(`${this.apiUrl}/${id}/estado`, data).pipe(map(r => r.data));
  }

  updateEstadoMasivo(ids: string[], data: UpdateEstadoAperturaDTO): Observable<{ actualizadas: number }> {
    return this.http.patch<ApiResponse<{ actualizadas: number }>>(`${this.apiUrl}/estado-masivo`, { ids, ...data })
      .pipe(map(r => r.data));
  }
}
