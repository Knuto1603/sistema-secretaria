import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface TelegramVinculo {
  codigo: string;
  deep_link: string;
  expira_en_minutos: number;
}

export interface TelegramEstado {
  vinculado: boolean;
  vinculado_desde: string | null;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class TelegramService {
  private http   = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/me/telegram`;

  generarVinculo(): Observable<TelegramVinculo> {
    return this.http.post<ApiResponse<TelegramVinculo>>(`${this.apiUrl}/generar-vinculo`, {}).pipe(
      map(r => r.data)
    );
  }

  getEstado(): Observable<TelegramEstado> {
    return this.http.get<ApiResponse<TelegramEstado>>(`${this.apiUrl}/estado`).pipe(
      map(r => r.data)
    );
  }

  desvincular(): Observable<void> {
    return this.http.delete<ApiResponse<null>>(this.apiUrl).pipe(map(() => undefined));
  }
}
