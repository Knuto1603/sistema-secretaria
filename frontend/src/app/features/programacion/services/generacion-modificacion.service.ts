import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface ModificacionResumen {
  id: string;
  tipo: string;
  curso_codigo: string;
  curso_nombre: string;
  seccion: string;
  grupo: string;
  motivo: string;
  fecha: string;
}

// ModificacionItem completo — usado en historial de modificaciones
export interface ModificacionItem {
  id: string;
  tipo: string;
  estado: string;
  motivo: string;
  datos_anteriores: Record<string, unknown> | null;
  datos_nuevos: Record<string, unknown> | null;
  periodo: { id: string; nombre: string } | null;
  programacion: {
    id: string;
    seccion: string;
    grupo: string;
    ciclo: number;
    curso: { id: string; nombre: string; codigo: string; area: { id: string; nombre: string } | null } | null;
    aula: { id: string; nombre: string } | null;
    grupo_horario: { id: string; nombre: string } | null;
  } | null;
  usuario: { id: string; nombre: string } | null;
  created_at: string;
}

export interface PaginacionMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ModificacionesPageResponse {
  items: ModificacionItem[];
  pagination: PaginacionMeta;
}

export interface PreviewGrupo {
  area_id: string;
  area_nombre: string;
  tipo_documento: string;
  tipo_label: string;
  total_modificaciones: number;
  plantilla_existe: boolean;
  modificaciones: ModificacionResumen[];
}

export interface GeneracionItem {
  id: string;
  numero_oficio: string;
  fecha_desde: string;
  fecha_hasta: string;
  generado_at: string;
  total_documentos: number;
  periodo: { id: string; nombre: string } | null;
  generado_por: string | null;
  documentos: {
    id: string;
    area_id: string;
    area_nombre: string;
    tipo_documento: string;
    nombre_archivo: string;
    modificaciones_count: number;
  }[];
}

export interface PlantillaEstado {
  tipo: string;
  label: string;
  cargada: boolean;
  nombre_archivo: string | null;
  actualizado_at: string | null;
}

@Injectable({ providedIn: 'root' })
export class GeneracionModificacionService {
  private http = inject(HttpClient);
  private base = `${environment.apiUrl}/modificaciones`;
  private plantillasUrl = `${environment.apiUrl}/plantillas-modificacion`;

  // ── Historial modificaciones ──────────────────────────────────────────────

  listarModificaciones(params: Record<string, string | number>): Observable<ModificacionesPageResponse> {
    return this.http.get<ApiResponse<ModificacionesPageResponse>>(this.base, { params: params as Record<string, string> })
      .pipe(map(r => r.data));
  }

  // ── Generación de documentos ──────────────────────────────────────────────

  preview(periodoId: string): Observable<PreviewGrupo[]> {
    return this.http.post<ApiResponse<PreviewGrupo[]>>(
      `${this.base}/generar-preview`,
      { periodo_id: periodoId }
    ).pipe(map(r => r.data));
  }

  generar(periodoId: string, numeroOficio: string, modificacionIds: string[]): Observable<Blob> {
    return this.http.post(
      `${this.base}/generar`,
      { periodo_id: periodoId, numero_oficio: numeroOficio, modificacion_ids: modificacionIds },
      { responseType: 'blob' }
    );
  }

  historialGeneraciones(periodoId?: string): Observable<GeneracionItem[]> {
    const params: Record<string, string> = {};
    if (periodoId) params['periodo_id'] = periodoId;
    return this.http.get<ApiResponse<GeneracionItem[]>>(`${this.base}/generaciones`, { params })
      .pipe(map(r => r.data));
  }

  descargarZip(generacionId: string): Observable<Blob> {
    return this.http.get(`${this.base}/generaciones/${generacionId}/zip`, { responseType: 'blob' });
  }

  eliminarGeneracion(generacionId: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.base}/generaciones/${generacionId}`)
      .pipe(map(() => void 0));
  }

  // ── Plantillas ────────────────────────────────────────────────────────────

  listarPlantillas(): Observable<PlantillaEstado[]> {
    return this.http.get<ApiResponse<PlantillaEstado[]>>(this.plantillasUrl)
      .pipe(map(r => r.data));
  }

  subirPlantilla(tipo: string, archivo: File): Observable<{ tipo: string; label: string; nombre_archivo: string }> {
    const form = new FormData();
    form.append('plantilla', archivo);
    return this.http.post<ApiResponse<{ tipo: string; label: string; nombre_archivo: string }>>(
      `${this.plantillasUrl}/${tipo}`, form
    ).pipe(map(r => r.data));
  }

  eliminarPlantilla(tipo: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.plantillasUrl}/${tipo}`)
      .pipe(map(() => void 0));
  }

  descargarPlantilla(tipo: string): Observable<Blob> {
    return this.http.get(`${this.plantillasUrl}/${tipo}/descargar`, { responseType: 'blob' });
  }

  listarTodasDelPeriodo(periodoId: string): Observable<ModificacionItem[]> {
    return this.http.get<ApiResponse<ModificacionesPageResponse>>(this.base, {
      params: { periodo_id: periodoId, per_page: '500' } as Record<string, string>
    }).pipe(map(r => r.data.items));
  }
}
