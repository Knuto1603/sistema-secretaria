import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Departamento {
  id: string;
  nombre: string;
  prefijos: string[];
  cursos_count: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class DepartamentoService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/areas`;

  getDepartamentos(): Observable<Departamento[]> {
    return this.http
      .get<ApiResponse<Departamento[]>>(this.apiUrl)
      .pipe(map(r => r.data));
  }

  crearDepartamento(data: { nombre: string; prefijos: string[] }): Observable<Departamento> {
    return this.http
      .post<ApiResponse<Departamento>>(this.apiUrl, data)
      .pipe(map(r => r.data));
  }

  actualizarDepartamento(id: string, data: { nombre: string; prefijos: string[] }): Observable<Departamento> {
    return this.http
      .put<ApiResponse<Departamento>>(`${this.apiUrl}/${id}`, data)
      .pipe(map(r => r.data));
  }

  eliminarDepartamento(id: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.apiUrl}/${id}`)
      .pipe(map(() => undefined));
  }

  autoAsignar(): Observable<{ asignados: number }> {
    return this.http
      .post<ApiResponse<{ asignados: number }>>(`${this.apiUrl}/auto-asignar`, {})
      .pipe(map(r => r.data));
  }
}
