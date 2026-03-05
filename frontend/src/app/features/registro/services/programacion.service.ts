import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Programacion {
  id: string;
  clave: string;
  grupo: string;
  seccion: string;
  aula: string;
  capacidad: number;
  n_inscritos: number;
  lleno_manual: boolean;
  esta_lleno: boolean;
  curso: {
    id: string;
    nombre: string;
    codigo: string;
  } | null;
  docente?: { nombre_completo: string } | null;
  periodo?: { id: string; nombre: string; activo: boolean } | null;
}

// Interfaz para la respuesta paginada estandarizada
export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

// Interfaz para la respuesta de la API
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
export class ProgramacionService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/programacion`;

  /**
   * Obtiene la programación académica paginada y filtrada
   */
  getProgramacion(page: number = 1, search: string = '', perPage: number = 10, periodoId?: string): Observable<PaginatedResponse<Programacion>> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    if (search) {
      params = params.set('search', search);
    }

    if (periodoId) {
      params = params.set('periodo_id', periodoId);
    }

    return this.http.get<ApiResponse<ApiPaginatedData<Programacion>>>(this.apiUrl, { params }).pipe(
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

  getDetalleProgramacion(id: string): Observable<Programacion> {
    return this.http.get<ApiResponse<Programacion>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }

  importarExcel(file: File, periodoId?: string): Observable<any> {
    const formData = new FormData();
    formData.append('file', file);
    if (periodoId) {
      formData.append('periodo_id', periodoId);
    }
    return this.http.post(`${this.apiUrl}/import`, formData);
  }

  /**
   * Descarga la plantilla de ejemplo para importación
   */
  descargarPlantilla(): void {
    this.http.get(`${this.apiUrl}/template`, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'plantilla_programacion.xlsx';
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: (err) => console.error('Error al descargar plantilla:', err)
    });
  }

  /**
   * Marca/desmarca un curso como lleno manualmente
   */
  toggleLleno(id: string): Observable<Programacion> {
    return this.http.patch<ApiResponse<Programacion>>(`${this.apiUrl}/${id}/toggle-lleno`, {}).pipe(
      map(response => response.data)
    );
  }
}
