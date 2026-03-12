import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Usuario {
  id: string;
  name: string;
  username: string;
  email: string;
  tipo_usuario: 'developer' | 'administrativo';
  activo: boolean;
  roles: string[];
  created_at: string;
}

export interface Estudiante {
  id: string;
  name: string;
  codigo_universitario: string;
  email: string;
  escuela: string | null;
  anio_ingreso: number | null;
  cuenta_activada: boolean;
  activo: boolean;
  password_set_at: string | null;
  ultimo_otp_enviado: string | null;
  created_at: string;
}

export interface CreateUsuarioDTO {
  name: string;
  username: string;
  email: string;
  password: string;
  roles?: string[];
}

export interface UpdateUsuarioDTO {
  name?: string;
  username?: string;
  email?: string;
  password?: string;
  roles?: string[];
}

export interface UpdateEstudianteDTO {
  name?: string;
  escuela_id?: string;
}

export interface UsuarioFilters {
  search?: string;
  rol?: string;
  activo?: boolean;
  per_page?: number;
  page?: number;
}

export interface EstudianteFilters {
  search?: string;
  escuela_codigo?: string;
  cuenta_activada?: boolean;
  activo?: boolean;
  per_page?: number;
  page?: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

interface PaginatedData<T> {
  items: T[];
  pagination: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ImportResumen {
  total: number;
  importados: number;
  omitidos: number;
  errores: number;
}

export interface ImportFila {
  fila: number;
  codigo: string;
  estado: 'importado' | 'omitido' | 'error';
  mensaje: string;
}

export interface HistorialCurso {
  id: string;
  curso: { id: string; nombre: string; codigo: string } | null;
  nota: number | null;
  creditos: number | null;
  tipo: string;
  fuente: string;
}

export interface HistorialSemestre {
  semestre: string;
  cursos: HistorialCurso[];
}

export interface EstudianteHistorial {
  por_semestre: HistorialSemestre[];
  sin_semestre: HistorialCurso[];
  total: number;
}

export interface EstudianteInscripcion {
  id: string;
  fuente: string;
  periodo: { id: string; nombre: string } | null;
  programacion: {
    id: string;
    clave: string;
    seccion: string;
    grupo: string;
    curso: { nombre: string; codigo: string } | null;
  } | null;
  created_at: string;
}

export interface HistorialesZipResumen {
  estudiantes_creados: number;
  estudiantes_actualizados: number;
  historial_insertado: number;
  historial_actualizado: number;
  errores: string[];
}

export interface ImportJobStatus {
  id: string;
  tipo: string;
  estado: 'pendiente' | 'procesando' | 'completado' | 'fallido';
  resultado: HistorialesZipResumen | null;
  error_mensaje: string | null;
  created_at: string;
  updated_at: string;
}

export interface InscripcionesHtmlResumen {
  programaciones_procesadas: number;
  inscripciones_creadas: number;
  inscripciones_actualizadas: number;
  alumnos_nuevos: number;
  no_encontrados: number;
  errores: number;
  detalle_errores: string[];
}

@Injectable({
  providedIn: 'root'
})
export class UsuarioService {
  private http = inject(HttpClient);
  private baseUrl = `${environment.apiUrl}/usuarios`;

  // =============================================
  // USUARIOS ADMINISTRATIVOS
  // =============================================

  getAdministrativos(filters: UsuarioFilters = {}): Observable<PaginatedData<Usuario>> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.rol) params = params.set('rol', filters.rol);
    if (filters.activo !== undefined) params = params.set('activo', filters.activo.toString());
    if (filters.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters.page) params = params.set('page', filters.page.toString());

    return this.http.get<ApiResponse<PaginatedData<Usuario>>>(`${this.baseUrl}/administrativos`, { params }).pipe(
      map(response => response.data)
    );
  }

  getAdministrativoById(id: string): Observable<Usuario> {
    return this.http.get<ApiResponse<Usuario>>(`${this.baseUrl}/administrativos/${id}`).pipe(
      map(response => response.data)
    );
  }

  createAdministrativo(data: CreateUsuarioDTO): Observable<Usuario> {
    return this.http.post<ApiResponse<Usuario>>(`${this.baseUrl}/administrativos`, data).pipe(
      map(response => response.data)
    );
  }

  updateAdministrativo(id: string, data: UpdateUsuarioDTO): Observable<Usuario> {
    return this.http.put<ApiResponse<Usuario>>(`${this.baseUrl}/administrativos/${id}`, data).pipe(
      map(response => response.data)
    );
  }

  deleteAdministrativo(id: string): Observable<void> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/administrativos/${id}`).pipe(
      map(() => void 0)
    );
  }

  toggleAdministrativo(id: string, activo: boolean): Observable<Usuario> {
    return this.http.patch<ApiResponse<Usuario>>(`${this.baseUrl}/administrativos/${id}/toggle`, { activo }).pipe(
      map(response => response.data)
    );
  }

  asignarRoles(id: string, roles: string[]): Observable<Usuario> {
    return this.http.patch<ApiResponse<Usuario>>(`${this.baseUrl}/administrativos/${id}/roles`, { roles }).pipe(
      map(response => response.data)
    );
  }

  // =============================================
  // ESTUDIANTES
  // =============================================

  getEstudiantes(filters: EstudianteFilters = {}): Observable<PaginatedData<Estudiante>> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.escuela_codigo !== undefined && filters.escuela_codigo !== '') params = params.set('escuela_codigo', filters.escuela_codigo);
    if (filters.cuenta_activada !== undefined) params = params.set('cuenta_activada', filters.cuenta_activada.toString());
    if (filters.activo !== undefined) params = params.set('activo', filters.activo.toString());
    if (filters.per_page) params = params.set('per_page', filters.per_page.toString());
    if (filters.page) params = params.set('page', filters.page.toString());

    return this.http.get<ApiResponse<PaginatedData<Estudiante>>>(`${this.baseUrl}/estudiantes`, { params }).pipe(
      map(response => response.data)
    );
  }

  getEstudianteById(id: string): Observable<Estudiante> {
    return this.http.get<ApiResponse<Estudiante>>(`${this.baseUrl}/estudiantes/${id}`).pipe(
      map(response => response.data)
    );
  }

  updateEstudiante(id: string, data: UpdateEstudianteDTO): Observable<Estudiante> {
    return this.http.put<ApiResponse<Estudiante>>(`${this.baseUrl}/estudiantes/${id}`, data).pipe(
      map(response => response.data)
    );
  }

  crearEstudiante(data: { name: string; codigo_universitario: string }): Observable<Estudiante> {
    return this.http.post<ApiResponse<Estudiante>>(`${this.baseUrl}/estudiantes`, data).pipe(
      map(response => response.data)
    );
  }

  toggleEstudiante(id: string, activo: boolean): Observable<Estudiante> {
    return this.http.patch<ApiResponse<Estudiante>>(`${this.baseUrl}/estudiantes/${id}/toggle`, { activo }).pipe(
      map(response => response.data)
    );
  }

  reenviarOtp(id: string): Observable<{ email: string; expires_at: string }> {
    return this.http.post<ApiResponse<{ email: string; expires_at: string }>>(`${this.baseUrl}/estudiantes/${id}/reenviar-otp`, {}).pipe(
      map(response => response.data)
    );
  }

  importarEstudiantes(archivo: File): Observable<{ resumen: ImportResumen; resultados: ImportFila[] }> {
    const form = new FormData();
    form.append('archivo', archivo);
    return this.http.post<ApiResponse<{ resumen: ImportResumen; resultados: ImportFila[] }>>(`${this.baseUrl}/estudiantes/import`, form).pipe(
      map(response => response.data)
    );
  }

  /**
   * Importa estudiantes desde padrón HTML del SIGA (ALUMNOS.htm)
   */
  importarEstudiantesHtml(archivo: File): Observable<{ total: number; importados: number; errores: number }> {
    const form = new FormData();
    form.append('file', archivo);
    return this.http.post<ApiResponse<{ total: number; importados: number; errores: number }>>(
      `${this.baseUrl}/estudiantes/import-html`, form
    ).pipe(map(response => response.data));
  }

  descargarPlantillaEstudiantes(): void {
    this.http.get(`${this.baseUrl}/estudiantes/import/template`, { responseType: 'blob' }).subscribe(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'plantilla_estudiantes.xlsx';
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  getEstudianteHistorial(id: string): Observable<EstudianteHistorial> {
    return this.http
      .get<ApiResponse<EstudianteHistorial>>(`${this.baseUrl}/estudiantes/${id}/historial`)
      .pipe(map(r => r.data));
  }

  getEstudianteInscripciones(id: string): Observable<EstudianteInscripcion[]> {
    return this.http
      .get<ApiResponse<EstudianteInscripcion[]>>(`${this.baseUrl}/estudiantes/${id}/inscripciones`)
      .pipe(map(r => r.data));
  }

  importarHistorialesZip(archivo: File): Observable<{ job_id: string }> {
    const form = new FormData();
    form.append('archivo', archivo);
    return this.http
      .post<ApiResponse<{ job_id: string }>>(`${this.baseUrl}/estudiantes/import-historiales-zip`, form)
      .pipe(map(r => r.data));
  }

  getImportJobStatus(jobId: string): Observable<ImportJobStatus> {
    return this.http
      .get<ApiResponse<ImportJobStatus>>(`${this.baseUrl}/estudiantes/import-status/${jobId}`)
      .pipe(map(r => r.data));
  }

  importarInscripcionesHtml(archivo: File): Observable<InscripcionesHtmlResumen> {
    const form = new FormData();
    form.append('file', archivo);
    return this.http
      .post<ApiResponse<InscripcionesHtmlResumen>>(`${environment.apiUrl}/programacion/inscripciones/import-html`, form)
      .pipe(map(r => r.data));
  }
}
