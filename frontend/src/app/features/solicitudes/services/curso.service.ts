import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Curso {
  id: string;
  codigo: string;
  nombre: string;
  area_id: string | null;
  area: { id: string; nombre: string } | null;
  created_at: string;
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

@Injectable({
  providedIn: 'root'
})
export class CursoService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/cursos`;

  obtenerCursos(page: number = 1, search: string = '', perPage: number = 10): Observable<PaginatedResponse<Curso>> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    if (search) {
      params = params.set('search', search);
    }

    return this.http.get<ApiResponse<ApiPaginatedData<Curso>>>(this.apiUrl, { params }).pipe(
      map(response => {
        const { current_page, last_page, per_page, total } = response.data.pagination;
        const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
        const to = Math.min(current_page * per_page, total);
        return {
          data: response.data.items,
          current_page,
          last_page,
          per_page,
          total,
          from,
          to
        };
      })
    );
  }

  obtenerDetalleCurso(id: string): Observable<Curso> {
    return this.http.get<ApiResponse<Curso>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }
}
