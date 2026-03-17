import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from '@env/environment';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

interface LineaDebug {
  n: number;
  txt: string;
  hex: string;
}

interface DebugResult {
  parsed: {
    plan_nombre: string;
    escuela_nombre: string;
    total_creditos_obligatorios: number;
    creditos_electivos_requeridos: number;
    cursos: any[];
  };
  total_lineas: number;
  lineas: LineaDebug[];
}

@Component({
  selector: 'app-pdf-debug',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './pdf-debug.component.html',
})
export class PdfDebugComponent {
  private http = inject(HttpClient);
  private router = inject(Router);

  loading = signal(false);
  resultado = signal<DebugResult | null>(null);
  error = signal<string | null>(null);
  mostrarHex = signal(false);

  volver(): void {
    this.router.navigate(['/app/developer']);
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.loading.set(true);
    this.resultado.set(null);
    this.error.set(null);

    const form = new FormData();
    form.append('archivo', archivo);

    this.http
      .post<ApiResponse<DebugResult>>(`${environment.apiUrl}/plan-estudios/debug-pdf`, form)
      .subscribe({
        next: (res) => {
          this.resultado.set(res.data);
          this.loading.set(false);
          input.value = '';
        },
        error: (err) => {
          this.error.set(err.error?.message || 'Error al procesar el PDF');
          this.loading.set(false);
          input.value = '';
        },
      });
  }

  copiarJson(): void {
    navigator.clipboard.writeText(JSON.stringify(this.resultado(), null, 2));
  }
}
