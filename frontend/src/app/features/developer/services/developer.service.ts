import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import {
  ActivityLogItem,
  EmailLogItem,
  HealthStatus,
  RouteItem,
  SystemSetting,
} from '../models/developer.models';

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

@Injectable({ providedIn: 'root' })
export class DeveloperService {
  private http = inject(HttpClient);
  private api = `${environment.apiUrl}/dev`;

  getHealth(): Observable<HealthStatus> {
    return this.http
      .get<ApiResponse<HealthStatus>>(`${this.api}/health`)
      .pipe(map(r => r.data));
  }

  getActivityLogs(params: Record<string, string | number> = {}): Observable<PaginatedData<ActivityLogItem>> {
    return this.http
      .get<ApiResponse<PaginatedData<ActivityLogItem>>>(`${this.api}/activity-logs`, { params: params as any })
      .pipe(map(r => r.data));
  }

  getEmailLogs(params: Record<string, string | number> = {}): Observable<PaginatedData<EmailLogItem>> {
    return this.http
      .get<ApiResponse<PaginatedData<EmailLogItem>>>(`${this.api}/email-logs`, { params: params as any })
      .pipe(map(r => r.data));
  }

  getSettings(): Observable<SystemSetting[]> {
    return this.http
      .get<ApiResponse<SystemSetting[]>>(`${this.api}/settings`)
      .pipe(map(r => r.data));
  }

  updateSetting(key: string, value: string): Observable<SystemSetting> {
    return this.http
      .patch<ApiResponse<SystemSetting>>(`${this.api}/settings/${key}`, { value })
      .pipe(map(r => r.data));
  }

  clearCache(): Observable<void> {
    return this.http
      .post<ApiResponse<null>>(`${this.api}/maintenance/cache-clear`, {})
      .pipe(map(() => void 0));
  }

  clearLogs(): Observable<{ files_cleared: number }> {
    return this.http
      .post<ApiResponse<{ files_cleared: number }>>(`${this.api}/maintenance/logs-clear`, {})
      .pipe(map(r => r.data));
  }

  getRoutes(): Observable<RouteItem[]> {
    return this.http
      .get<ApiResponse<RouteItem[]>>(`${this.api}/routes`)
      .pipe(map(r => r.data));
  }

  impersonate(userId: string): Observable<{ token: string; user: any }> {
    return this.http
      .post<ApiResponse<{ token: string; user: any }>>(`${this.api}/impersonate/${userId}`, {})
      .pipe(map(r => r.data));
  }

  stopImpersonation(): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/impersonate`)
      .pipe(map(() => void 0));
  }

  testMail(destinatario: string): Observable<{ enviado_a: string; mailer: string; host: string; puerto: string | number; from: string; tiempo_ms: number }> {
    return this.http
      .post<ApiResponse<any>>(`${this.api}/mail/test`, { destinatario })
      .pipe(map(r => r.data));
  }

  getMailConfig(): Observable<{ mailer: string; host: string; port: string | number; username: string; from: string; env_host: string; env_port: string | number }> {
    return this.http
      .get<ApiResponse<any>>(`${this.api}/mail/config`)
      .pipe(map(r => r.data));
  }

  exportDatabase(): Observable<Blob> {
    return this.http.get(`${this.api}/database/export`, { responseType: 'blob' });
  }

  importDatabase(archivo: File, confirmacion: string): Observable<{ backup_automatico: string; archivo_restaurado: string }> {
    const form = new FormData();
    form.append('archivo', archivo);
    form.append('confirmacion', confirmacion);
    return this.http
      .post<ApiResponse<{ backup_automatico: string; archivo_restaurado: string }>>(`${this.api}/database/import`, form)
      .pipe(map(r => r.data));
  }

  listBackups(): Observable<BackupItem[]> {
    return this.http
      .get<ApiResponse<BackupItem[]>>(`${this.api}/database/backups`)
      .pipe(map(r => r.data));
  }

  downloadBackup(filename: string): Observable<Blob> {
    return this.http.get(`${this.api}/database/backups/${filename}`, { responseType: 'blob' });
  }
}

export interface BackupItem {
  nombre: string;
  tamaño_kb: number;
  creado_at: string;
}
