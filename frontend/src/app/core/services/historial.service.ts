import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface HistorialCurso {
  id: string;
  curso_id: string;
  codigo: string;
  nombre: string;
  semestre: string | null;
  tipo: 'O' | 'E' | null;
  creditos: number | null;
  nota: number | null;
  aprobado: boolean;
  fuente: 'importado' | 'autoreporte';
}

export interface HistorialSemestre {
  semestre: string;
  cursos: HistorialCurso[];
}

export interface HistorialResponse {
  por_semestre: HistorialSemestre[];
  sin_semestre: HistorialCurso[];
  total: number;
  tiene_historial: boolean;
  ultima_actualizacion: string | null;
}

export interface ImportPdfResumen {
  codigo_alumno: string;
  importados: number;
  omitidos: number;
  errores: number;
  detalle_errores: string[];
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class HistorialService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/historial`;

  getHistorial(): Observable<HistorialResponse> {
    return this.http.get<ApiResponse<HistorialResponse>>(this.apiUrl).pipe(
      map(r => r.data)
    );
  }

  importarPdf(archivo: File): Observable<ImportPdfResumen> {
    const form = new FormData();
    form.append('archivo', archivo);
    return this.http.post<ApiResponse<ImportPdfResumen>>(`${this.apiUrl}/importar-pdf`, form).pipe(
      map(r => r.data)
    );
  }

  limpiar(): Observable<{ eliminados: number }> {
    return this.http.delete<ApiResponse<{ eliminados: number }>>(`${this.apiUrl}/limpiar`).pipe(
      map(r => r.data)
    );
  }

  cambiarPassword(passwordActual: string, passwordNuevo: string): Observable<void> {
    return this.http.patch<ApiResponse<null>>(`${environment.apiUrl}/me/password`, {
      password_actual: passwordActual,
      password_nuevo: passwordNuevo,
      password_nuevo_confirmation: passwordNuevo,
    }).pipe(map(() => undefined));
  }
}
