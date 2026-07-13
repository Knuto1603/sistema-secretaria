import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

// ─── Interfaces ─────────────────────────────────────────────────────────────

export interface DiffSeccion {
  curso_codigo: string;
  curso_nombre: string;
  escuela_nombre: string | null;
  seccion: string | null;
  ciclo: number | null;
  aula_nombre: string | null;
  grupo_nombre: string | null;
}

export interface DiffCambio extends DiffSeccion {
  programacion_id: string;
  aula_anterior: string | null;
  aula_nueva: string | null;
  grupo_anterior: string | null;
  grupo_nuevo: string | null;
}

export interface DiffOmitido {
  codigo: string;
  nombre: string;
  motivo: string;
}

export interface DiffPreview {
  nuevas: DiffSeccion[];
  eliminadas: DiffSeccion[];
  reabiertas: DiffSeccion[];
  cambios_aula: DiffCambio[];
  cambios_grupo: DiffCambio[];
  cambios_aula_y_grupo: DiffCambio[];
  sin_cambios: number;
  omitidos: DiffOmitido[];
}

export interface DiffAplicarResult {
  aplicadas: Record<string, number | undefined>;
  omitidos: DiffOmitido[];
}

// ─── Service ─────────────────────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class ImportarDiffService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/programacion/importar-diff`;

  preview(file: File, periodoId: string): Observable<DiffPreview> {
    const form = new FormData();
    form.append('file', file);
    form.append('periodo_id', periodoId);
    return this.http
      .post<{ data: DiffPreview }>(this.base + '/preview', form)
      .pipe(map(res => res.data));
  }

  aplicar(
    file: File,
    periodoId: string,
    motivo: string,
  ): Observable<DiffAplicarResult> {
    const form = new FormData();
    form.append('file', file);
    form.append('periodo_id', periodoId);
    form.append('motivo', motivo);
    return this.http
      .post<{ data: DiffAplicarResult }>(this.base + '/aplicar', form)
      .pipe(map(res => res.data));
  }
}
