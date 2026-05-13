import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface CursoItem {
  id: string;
  codigo: string;
  nombre: string;
  area: { id: string; nombre: string } | null;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class CursosDepartamentoService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/areas`;

  getCursos(opts: { sinArea?: boolean; search?: string; areaId?: string } = {}): Observable<CursoItem[]> {
    let params = new HttpParams();
    if (opts.sinArea)  params = params.set('sin_area', 'true');
    if (opts.search)   params = params.set('search', opts.search);
    if (opts.areaId)   params = params.set('area_id', opts.areaId);
    return this.http.get<ApiResponse<CursoItem[]>>(`${this.apiUrl}/cursos`, { params })
      .pipe(map(r => r.data));
  }

  asignarArea(cursoId: string, areaId: string | null): Observable<CursoItem> {
    return this.http.patch<ApiResponse<CursoItem>>(`${this.apiUrl}/cursos/${cursoId}`, { area_id: areaId })
      .pipe(map(r => r.data));
  }
}
