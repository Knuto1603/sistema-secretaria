import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface CursoPlan {
  id: string;
  ciclo: number | null;
  creditos: number | null;
  tipo: 'O' | 'E';
  horas_teoricas: number | null;
  horas_practicas: number | null;
  curso_id: string;
  codigo_curso: string;
  nombre_curso: string;
  area: string | null;
  requisitos: string[];
}

export interface PlanVersion {
  id: string;
  nombre: string;
  activo: boolean;
  total_creditos_obligatorios: number;
  creditos_electivos_requeridos: number;
  total_cursos: number;
}

export interface PlanInfo {
  id: string;
  nombre: string;
  activo: boolean;
  total_creditos_obligatorios: number;
  creditos_electivos_requeridos: number;
}

export interface PlanEstudios {
  escuela: {
    codigo: string;
    nombre: string;
    nombre_corto: string;
  };
  plan: PlanInfo | null;
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

export interface ImportPdfResumen {
  plan: { id: string; nombre: string; total_creditos_obligatorios: number; creditos_electivos_requeridos: number };
  cursos_importados: number;
  requisitos_vinculados: boolean;
  errores: { codigo: string; error: string }[];
}

export interface CursoEquivalencia {
  id: string;
  codigo: string;
  nombre: string;
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
  private cursosUrl = `${environment.apiUrl}/cursos`;

  // ── Plan activo (cursos) ────────────────────────────────────────────────

  getPlan(escuelaCodigo: string): Observable<PlanEstudios> {
    const params = new HttpParams().set('escuela_codigo', escuelaCodigo);
    return this.http.get<ApiResponse<PlanEstudios>>(this.apiUrl, { params }).pipe(
      map(r => r.data)
    );
  }

  // ── Versiones de planes ─────────────────────────────────────────────────

  getPlanes(escuelaCodigo: string): Observable<{ escuela: { codigo: string; nombre: string }; planes: PlanVersion[] }> {
    const params = new HttpParams().set('escuela_codigo', escuelaCodigo);
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/planes`, { params }).pipe(
      map(r => r.data)
    );
  }

  crearPlan(escuelaCodigo: string, nombre: string, creditosObligatorios = 0, creditosElectivos = 0): Observable<PlanVersion> {
    return this.http.post<ApiResponse<PlanVersion>>(`${this.apiUrl}/planes`, {
      escuela_codigo: escuelaCodigo,
      nombre,
      total_creditos_obligatorios: creditosObligatorios,
      creditos_electivos_requeridos: creditosElectivos,
    }).pipe(map(r => r.data));
  }

  actualizarPlan(planId: string, data: { nombre?: string; total_creditos_obligatorios?: number; creditos_electivos_requeridos?: number }): Observable<PlanVersion> {
    return this.http.patch<ApiResponse<PlanVersion>>(`${this.apiUrl}/planes/${planId}`, data).pipe(
      map(r => r.data)
    );
  }

  activarPlan(planId: string): Observable<{ id: string; activo: boolean }> {
    return this.http.patch<ApiResponse<any>>(`${this.apiUrl}/planes/${planId}/activar`, {}).pipe(
      map(r => r.data)
    );
  }

  eliminarPlan(planId: string): Observable<void> {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/planes/${planId}`).pipe(
      map(() => undefined)
    );
  }

  // ── Importación ─────────────────────────────────────────────────────────

  importar(escuelaCodigo: string, archivo: File, planId?: string): Observable<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] }> {
    const form = new FormData();
    form.append('escuela_codigo', escuelaCodigo);
    form.append('archivo', archivo);
    if (planId) form.append('plan_id', planId);
    return this.http.post<ApiResponse<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] }>>(`${this.apiUrl}/import`, form).pipe(
      map(r => r.data)
    );
  }

  importarPdf(escuelaCodigo: string, archivo: File, planId?: string): Observable<ImportPdfResumen> {
    const form = new FormData();
    form.append('escuela_codigo', escuelaCodigo);
    form.append('archivo', archivo);
    if (planId) form.append('plan_id', planId);
    return this.http.post<ApiResponse<ImportPdfResumen>>(`${this.apiUrl}/import-pdf`, form).pipe(
      map(r => r.data)
    );
  }

  limpiar(escuelaCodigo: string, planId?: string): Observable<{ eliminados: number }> {
    let params = new HttpParams().set('escuela_codigo', escuelaCodigo);
    if (planId) params = params.set('plan_id', planId);
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

  // ── Equivalencias ───────────────────────────────────────────────────────

  getEquivalencias(cursoId: string): Observable<{ curso: { id: string; codigo: string; nombre: string }; equivalencias: CursoEquivalencia[] }> {
    return this.http.get<ApiResponse<any>>(`${this.cursosUrl}/${cursoId}/equivalencias`).pipe(
      map(r => r.data)
    );
  }

  agregarEquivalencia(cursoId: string, equivalenteId: string): Observable<void> {
    return this.http.post<ApiResponse<void>>(`${this.cursosUrl}/${cursoId}/equivalencias`, { equivalente_id: equivalenteId }).pipe(
      map(() => undefined)
    );
  }

  eliminarEquivalencia(cursoId: string, equivalenteId: string): Observable<void> {
    return this.http.delete<ApiResponse<void>>(`${this.cursosUrl}/${cursoId}/equivalencias/${equivalenteId}`).pipe(
      map(() => undefined)
    );
  }
}
