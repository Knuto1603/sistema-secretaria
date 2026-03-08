import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Aula {
  id: string;
  pabellon_id: string;
  nombre: string;
  capacidad: number;
  activo: boolean;
}

export interface Pabellon {
  id: string;
  nombre: string;
  activo: boolean;
  aulas: Aula[];
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class AulaService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/pabellones`;
  private aulasUrl = `${environment.apiUrl}/aulas`;

  getPabellones(): Observable<Pabellon[]> {
    return this.http.get<ApiResponse<Pabellon[]>>(this.apiUrl).pipe(map(r => r.data));
  }

  crearPabellon(nombre: string): Observable<Pabellon> {
    return this.http.post<ApiResponse<Pabellon>>(this.apiUrl, { nombre }).pipe(map(r => r.data));
  }

  actualizarPabellon(id: string, data: Partial<Pick<Pabellon, 'nombre' | 'activo'>>): Observable<Pabellon> {
    return this.http.put<ApiResponse<Pabellon>>(`${this.apiUrl}/${id}`, data).pipe(map(r => r.data));
  }

  eliminarPabellon(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(map(() => undefined));
  }

  crearAula(pabellonId: string, data: { nombre: string; capacidad: number }): Observable<Aula> {
    return this.http.post<ApiResponse<Aula>>(`${this.apiUrl}/${pabellonId}/aulas`, data).pipe(map(r => r.data));
  }

  actualizarAula(id: string, data: Partial<Omit<Aula, 'id' | 'pabellon_id'>>): Observable<Aula> {
    return this.http.put<ApiResponse<Aula>>(`${this.aulasUrl}/${id}`, data).pipe(map(r => r.data));
  }

  eliminarAula(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.aulasUrl}/${id}`).pipe(map(() => undefined));
  }

  toggleAula(id: string): Observable<Aula> {
    return this.http.patch<ApiResponse<Aula>>(`${this.aulasUrl}/${id}/toggle`, {}).pipe(map(r => r.data));
  }
}
