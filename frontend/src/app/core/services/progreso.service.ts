import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface CursoPendiente {
  ciclo: number;
  codigo: string;
  nombre: string;
  creditos: number;
}

export interface CiclosPendientes {
  ciclo: number;
  cursos: CursoPendiente[];
}

export interface ProgresoAcademico {
  plan: { id: string; nombre: string; activo: boolean } | null;
  obligatorios: {
    requeridos: number;
    hechos: number;
    porcentaje: number;
    pendientes_por_ciclo: CiclosPendientes[];
  };
  electivos: {
    requeridos: number;
    hechos: number;
    porcentaje: number;
  };
  egresante_calculado: boolean;
  egresante_manual: boolean;
  mensaje?: string;
}

export interface ProgresoAlumno extends ProgresoAcademico {
  estudiante: {
    id: string;
    name: string;
    codigo_universitario: string;
    egresante: boolean;
  };
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class ProgresoService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/progreso`;

  getMiProgreso(): Observable<ProgresoAcademico> {
    return this.http.get<ApiResponse<ProgresoAcademico>>(`${this.apiUrl}/mi-progreso`).pipe(
      map(r => r.data)
    );
  }

  getProgresoAlumno(userId: string): Observable<ProgresoAlumno> {
    return this.http.get<ApiResponse<ProgresoAlumno>>(`${this.apiUrl}/${userId}`).pipe(
      map(r => r.data)
    );
  }

  toggleEgresante(userId: string): Observable<{ egresante: boolean }> {
    return this.http.patch<ApiResponse<{ egresante: boolean }>>(`${this.apiUrl}/${userId}/egresante`, {}).pipe(
      map(r => r.data)
    );
  }
}
