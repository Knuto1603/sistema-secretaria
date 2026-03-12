import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface AulaRel {
  id: string;
  nombre: string;
  capacidad: number;
  pabellon?: { nombre: string } | null;
}

export interface GrupoHorarioRef {
  id: string;
  nombre: string;
  detalles: Array<{ dia_semana: string; hora_inicio: string; hora_fin: string }>;
}

export interface EscuelaRef {
  id: string;
  nombre: string;
  nombre_corto: string | null;
}

export interface InscripcionEscuelaStat {
  escuela_id: string | null;
  nombre: string;
  nombre_corto: string;
  cantidad: number;
  porcentaje: number;
}

export interface InscripcionesStats {
  total: number;
  por_escuela: InscripcionEscuelaStat[];
}

export interface Programacion {
  id: string;
  clave: string;
  grupo: string;
  seccion: string;
  aula: string;
  aula_nombre: string | null;
  aula_id: string | null;
  grupo_horario_id: string | null;
  capacidad: number;
  n_inscritos: number;
  lleno_manual: boolean;
  esta_lleno: boolean;
  curso: { id: string; nombre: string; codigo: string } | null;
  docente?: { id?: string; nombre_completo: string } | null;
  periodo?: { id: string; nombre: string; activo: boolean } | null;
  aula_rel?: AulaRel | null;
  grupo_horario?: GrupoHorarioRef | null;
  escuelas?: EscuelaRef[];
  escuela_programada?: EscuelaRef | null;
  docente_id?: string | null;
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

@Injectable({ providedIn: 'root' })
export class ProgramacionService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/programacion`;

  getProgramacion(
    page: number = 1,
    search: string = '',
    perPage: number = 10,
    periodoId?: string,
    escuelaId?: string,
    ciclo?: number,
    areaId?: string,
    grupo?: string,
    escuelaProgramadaId?: string
  ): Observable<PaginatedResponse<Programacion>> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());
    if (search)              params = params.set('search', search);
    if (periodoId)           params = params.set('periodo_id', periodoId);
    if (escuelaId)           params = params.set('escuela_id', escuelaId);
    if (ciclo)               params = params.set('ciclo', ciclo.toString());
    if (areaId)              params = params.set('area_id', areaId);
    if (grupo)               params = params.set('grupo', grupo);
    if (escuelaProgramadaId) params = params.set('escuela_programada_id', escuelaProgramadaId);

    return this.http
      .get<ApiResponse<ApiPaginatedData<Programacion>>>(this.apiUrl, { params })
      .pipe(
        map(response => {
          const { current_page, last_page, per_page, total } = response.data.pagination;
          const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
          const to   = Math.min(current_page * per_page, total);
          return { data: response.data.items, current_page, last_page, per_page, total, from, to };
        })
      );
  }

  getDetalleProgramacion(id: string): Observable<Programacion> {
    return this.http
      .get<ApiResponse<Programacion>>(`${this.apiUrl}/${id}`)
      .pipe(map(response => response.data));
  }

  getInscripcionesStats(id: string): Observable<InscripcionesStats> {
    return this.http
      .get<ApiResponse<InscripcionesStats>>(`${this.apiUrl}/${id}/inscripciones/stats`)
      .pipe(map(r => r.data));
  }

  crearProgramacion(data: {
    periodo_id: string;
    curso_id: string;
    escuelas: string[];
    secciones: Array<{
      grupo_horario_id: string | null;
      aula_id: string | null;
      docente_id: string | null;
      capacidad: number;
    }>;
  }): Observable<{ creadas: number }> {
    return this.http
      .post<ApiResponse<{ creadas: number }>>(this.apiUrl, data)
      .pipe(map(r => r.data));
  }

  actualizarProgramacion(
    id: string,
    data: {
      grupo_horario_id: string | null;
      aula_id: string | null;
      docente_id: string | null;
      capacidad: number;
      escuelas?: string[];
    }
  ): Observable<Programacion> {
    return this.http
      .put<ApiResponse<Programacion>>(`${this.apiUrl}/${id}`, data)
      .pipe(map(r => r.data));
  }

  eliminarProgramacion(id: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.apiUrl}/${id}`)
      .pipe(map(() => undefined));
  }

  importarExcel(file: File, periodoId?: string): Observable<any> {
    const formData = new FormData();
    formData.append('file', file);
    if (periodoId) formData.append('periodo_id', periodoId);
    return this.http.post(`${this.apiUrl}/import`, formData);
  }

  importarHtml(
    file: File,
    periodoId?: string
  ): Observable<{ total: number; importados: number; errores: number }> {
    const formData = new FormData();
    formData.append('file', file);
    if (periodoId) formData.append('periodo_id', periodoId);
    return this.http
      .post<{ success: boolean; data: { total: number; importados: number; errores: number } }>(
        `${this.apiUrl}/import-html`,
        formData
      )
      .pipe(map(r => r.data));
  }

  descargarPlantilla(): void {
    this.http.get(`${this.apiUrl}/template`, { responseType: 'blob' }).subscribe({
      next: blob => {
        const url  = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'plantilla_programacion.xlsx';
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: err => console.error('Error al descargar plantilla:', err),
    });
  }

  exportarExcel(periodoId?: string, search?: string, escuelaId?: string, ciclo?: number, areaId?: string, conHorario?: boolean): void {
    let params = new HttpParams();
    if (periodoId)   params = params.set('periodo_id', periodoId);
    if (search)      params = params.set('search', search);
    if (escuelaId)   params = params.set('escuela_id', escuelaId);
    if (ciclo)       params = params.set('ciclo', ciclo.toString());
    if (areaId)      params = params.set('area_id', areaId);
    if (conHorario)  params = params.set('con_horario', '1');

    this.http
      .get(`${this.apiUrl}/export`, { params, responseType: 'blob' })
      .subscribe({
        next: blob => {
          const url  = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = 'programacion_export.xlsx';
          link.click();
          window.URL.revokeObjectURL(url);
        },
        error: err => console.error('Error al exportar:', err),
      });
  }

  getParaMi(
    page: number = 1,
    search: string = '',
    perPage: number = 10
  ): Observable<{
    cicloActual: number;
    historialRegistrado: boolean;
    paginatedData: PaginatedResponse<Programacion>;
  }> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());
    if (search) params = params.set('search', search);

    return this.http
      .get<{
        success: boolean;
        data: {
          ciclo_actual: number;
          historial_registrado: boolean;
          items: Programacion[];
          pagination: { current_page: number; last_page: number; per_page: number; total: number };
        };
      }>(`${this.apiUrl}/para-mi`, { params })
      .pipe(
        map(response => {
          const d = response.data;
          const { current_page, last_page, per_page, total } = d.pagination;
          return {
            cicloActual: d.ciclo_actual,
            historialRegistrado: d.historial_registrado,
            paginatedData: {
              data: d.items,
              current_page,
              last_page,
              per_page,
              total,
              from: total > 0 ? (current_page - 1) * per_page + 1 : 0,
              to: Math.min(current_page * per_page, total),
            },
          };
        })
      );
  }

  toggleLleno(id: string): Observable<Programacion> {
    return this.http
      .patch<ApiResponse<Programacion>>(`${this.apiUrl}/${id}/toggle-lleno`, {})
      .pipe(map(response => response.data));
  }

  eliminarPorPeriodo(periodoId: string): Observable<{ eliminados: number }> {
    return this.http
      .delete<ApiResponse<{ eliminados: number }>>(`${this.apiUrl}/periodo/${periodoId}`)
      .pipe(map(r => r.data));
  }
}
