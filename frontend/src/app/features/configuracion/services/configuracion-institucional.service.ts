import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface ConfigItem {
  clave: string;
  valor: string;
  descripcion: string;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class ConfiguracionInstitucionalService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/configuracion-institucional`;

  getAll(): Observable<ConfigItem[]> {
    return this.http.get<ApiResponse<ConfigItem[]>>(this.apiUrl).pipe(map(r => r.data));
  }

  update(items: { clave: string; valor: string }[]): Observable<ConfigItem[]> {
    return this.http.put<ApiResponse<ConfigItem[]>>(this.apiUrl, { items }).pipe(map(r => r.data));
  }
}
