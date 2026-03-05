export interface HealthStatus {
  database: boolean;
  disk_free_gb: number;
  disk_total_gb: number;
  disk_pct: number;
  php_version: string;
  laravel_version: string;
  environment: string;
  timestamp: string;
}

export interface ActivityLogItem {
  id: string;
  user: { id: string; name: string } | null;
  accion: string;
  modelo: string | null;
  modelo_id: string | null;
  valores_anteriores: Record<string, unknown> | null;
  valores_nuevos: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
}

export interface EmailLogItem {
  id: string;
  purpose: string;
  code: string;
  user: { id: string; name: string } | null;
  enviado_a: string | null;
  expires_at: string | null;
  verified_at: string | null;
  usado: boolean;
  expirado: boolean;
  created_at: string;
}

export interface SystemSetting {
  key: string;
  value: string | null;
  type: 'string' | 'boolean' | 'integer' | 'json';
  grupo: string;
  descripcion: string | null;
}

export interface RouteItem {
  method: string;
  uri: string;
  name: string | null;
  middleware: string[];
}
