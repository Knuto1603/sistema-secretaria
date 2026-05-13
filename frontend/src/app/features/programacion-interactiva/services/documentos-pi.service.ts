import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface DocumentoAreaItem {
  id: string;
  area_id: string;
  area_nombre: string;
  nombre_archivo: string;
  cursos_count: number;
}

export interface GeneracionDocumento {
  id: string;
  numero_oficio: string;
  semestre_texto: string;
  generado_at: string;
  total_documentos: number;
  periodo: { id: string; nombre: string } | null;
  generado_por: string | null;
  documentos: DocumentoAreaItem[];
}

export interface CursoSinArea {
  codigo: string;
  nombre: string;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class DocumentosPiService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/programacion-interactiva`;

  getCursosSinArea(borradorId: string): Observable<CursoSinArea[]> {
    return this.http.get<ApiResponse<CursoSinArea[]>>(`${this.apiUrl}/${borradorId}/cursos-sin-area`)
      .pipe(map(r => r.data));
  }

  generarDocumentos(borradorId: string, numeroOficio: string, semestreTexto: string): Observable<GeneracionDocumento> {
    return this.http.post<ApiResponse<GeneracionDocumento>>(
      `${this.apiUrl}/${borradorId}/generar-documentos`,
      { numero_oficio: numeroOficio, semestre_texto: semestreTexto }
    ).pipe(map(r => r.data));
  }

  getGeneraciones(borradorId: string): Observable<GeneracionDocumento[]> {
    return this.http.get<ApiResponse<GeneracionDocumento[]>>(`${this.apiUrl}/${borradorId}/generaciones`)
      .pipe(map(r => r.data));
  }

  getUrlDescarga(generacionId: string, areaId: string): string {
    return `${environment.apiUrl}/programacion-interactiva/generaciones/${generacionId}/descargar/${areaId}`;
  }

  getUrlDescargarTodos(generacionId: string): string {
    return `${environment.apiUrl}/programacion-interactiva/generaciones/${generacionId}/descargar-todos`;
  }
}
