import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface CursoPlan {
  id: string;
  ciclo: number | null;
  creditos: number | null;
  tipo: 'O' | 'E';  // O = Obligatorio, E = Electivo
  curso_id: string;
  codigo_curso: string;
  nombre_curso: string;
  area: string | null;
}

export interface PlanEstudios {
  escuela: {
    codigo: string;
    nombre: string;
    nombre_corto: string;
  };
  cursos: CursoPlan[];
  total: number;
}

export interface ImportPlanResumen {
  total: number;
  importados: number;
  errores: number;
}

export interface ImportPlanFila {
  fila: number;
  codigo: string;
  estado: 'importado' | 'error';
  mensaje: string;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const ESCUELAS = [
  { codigo: '0', nombre: 'Ingeniería Industrial', nombre_corto: 'Industrial' },
  { codigo: '1', nombre: 'Ingeniería Informática', nombre_corto: 'Informática' },
  { codigo: '2', nombre: 'Ingeniería Mecatrónica', nombre_corto: 'Mecatrónica' },
  { codigo: '3', nombre: 'Ing. Agroindustrial e Industrias Alimentarias', nombre_corto: 'Agroindustrial' },
];

@Injectable({ providedIn: 'root' })
export class PlanEstudiosService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/plan-estudios`;

  getPlan(escuelaCodigo: string): Observable<PlanEstudios> {
    const params = new HttpParams().set('escuela_codigo', escuelaCodigo);
    return this.http.get<ApiResponse<PlanEstudios>>(this.apiUrl, { params }).pipe(
      map(r => r.data)
    );
  }

  importar(escuelaCodigo: string, archivo: File): Observable<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] }> {
    const form = new FormData();
    form.append('escuela_codigo', escuelaCodigo);
    form.append('archivo', archivo);
    return this.http.post<ApiResponse<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] }>>(`${this.apiUrl}/import`, form).pipe(
      map(r => r.data)
    );
  }

  limpiar(escuelaCodigo: string): Observable<{ eliminados: number }> {
    const params = new HttpParams().set('escuela_codigo', escuelaCodigo);
    return this.http.delete<ApiResponse<{ eliminados: number }>>(this.apiUrl, { params }).pipe(
      map(r => r.data)
    );
  }

  descargarPlantilla(): void {
    this.http.get(`${this.apiUrl}/template`, { responseType: 'blob' }).subscribe(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'plantilla_plan_estudios.xlsx';
      a.click();
      URL.revokeObjectURL(url);
    });
  }
}
