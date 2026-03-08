import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface CursoHistorial {
  id: string;
  curso_id: string;
  codigo: string;
  nombre: string;
  fuente: string;
}

export interface CursoPlan {
  ciclo: number;
  curso_id: string;
  codigo: string;
  nombre: string;
  tipo: 'O' | 'E';
}

export interface CicloPlan {
  ciclo: number;
  cursos: CursoPlan[];
}

export interface MiPlan {
  escuela: { nombre: string; nombre_corto: string };
  ciclos: CicloPlan[];
  total_cursos: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class HistorialService {
  private http = inject(HttpClient);
  private apiUrl = environment.apiUrl;

  getMiHistorial(): Observable<{ cursos: CursoHistorial[]; total: number }> {
    return this.http
      .get<ApiResponse<{ cursos: CursoHistorial[]; total: number }>>(`${this.apiUrl}/historial`)
      .pipe(map(r => r.data));
  }

  /**
   * Reemplaza el historial completo del estudiante con la lista de curso_ids.
   * Acepta array vacío para ciclo 1 (sin aprobados aún).
   */
  syncHistorial(cursoIds: string[]): Observable<{ total: number }> {
    return this.http
      .post<ApiResponse<{ total: number }>>(`${this.apiUrl}/historial/sync`, { curso_ids: cursoIds })
      .pipe(map(r => r.data));
  }

  getMiPlan(): Observable<MiPlan> {
    return this.http
      .get<ApiResponse<MiPlan>>(`${this.apiUrl}/plan-estudios/mi-plan`)
      .pipe(map(r => r.data));
  }
}
