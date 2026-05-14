import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface PlantillaInfo {
  clave: string;
  nombre: string;
  existe: boolean;
  size: number | null;
  updated_at: string | null;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class PlantillasService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/plantillas`;

  listar(): Observable<PlantillaInfo[]> {
    return this.http
      .get<ApiResponse<PlantillaInfo[]>>(this.apiUrl)
      .pipe(map(r => r.data));
  }

  getUrlDescarga(clave: string): string {
    return `${this.apiUrl}/${clave}/descargar`;
  }

  subir(clave: string, archivo: File): Observable<string> {
    const formData = new FormData();
    formData.append('archivo', archivo);
    return this.http
      .post<ApiResponse<null>>(`${this.apiUrl}/${clave}/subir`, formData)
      .pipe(map(r => r.message));
  }
}
