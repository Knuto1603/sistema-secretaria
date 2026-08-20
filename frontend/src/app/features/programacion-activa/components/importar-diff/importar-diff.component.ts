import {
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';

import { ProgramacionEstadoService } from '@features/programacion/services/programacion-estado.service';
import {
  DiffAplicarResult,
  DiffCambio,
  DiffCambioCupo,
  DiffOmitido,
  DiffPreview,
  DiffSeccion,
  ImportarDiffService,
} from '../../services/importar-diff.service';

type Paso = 1 | 2 | 3;

@Component({
  selector: 'app-importar-diff',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, CommonModule],
  templateUrl: './importar-diff.component.html',
})
export class ImportarDiffComponent {
  readonly estado      = inject(ProgramacionEstadoService);
  private readonly svc = inject(ImportarDiffService);

  // ─── State ────────────────────────────────────────────────────────────────
  paso       = signal<Paso>(1);
  analizando = signal(false);
  aplicando  = signal(false);
  diff       = signal<DiffPreview | null>(null);
  resultado  = signal<DiffAplicarResult | null>(null);
  motivo     = signal('');
  error      = signal<string | null>(null);

  // Secciones colapsadas
  colapsadas = signal<Record<string, boolean>>({});

  private archivoSeleccionado: File | null = null;

  // ─── Paso 1 ───────────────────────────────────────────────────────────────

  onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.archivoSeleccionado = input.files?.[0] ?? null;
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.archivoSeleccionado = event.dataTransfer?.files?.[0] ?? null;
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
  }

  analizar(): void {
    if (!this.archivoSeleccionado || !this.estado.periodoId()) return;
    this.analizando.set(true);
    this.error.set(null);

    this.svc.preview(this.archivoSeleccionado, this.estado.periodoId()).subscribe({
      next: data => {
        this.diff.set(data);
        this.analizando.set(false);
        this.paso.set(2);
      },
      error: err => {
        this.error.set(err?.error?.message ?? 'Error al analizar el archivo.');
        this.analizando.set(false);
      },
    });
  }

  // ─── Paso 2 ───────────────────────────────────────────────────────────────

  toggleColapso(seccion: string): void {
    this.colapsadas.update(c => ({ ...c, [seccion]: !c[seccion] }));
  }

  esColapsada(seccion: string): boolean {
    return !!this.colapsadas()[seccion];
  }

  aplicar(): void {
    if (!this.archivoSeleccionado || !this.motivo().trim()) return;
    this.aplicando.set(true);
    this.error.set(null);

    this.svc.aplicar(
      this.archivoSeleccionado,
      this.estado.periodoId(),
      this.motivo(),
    ).subscribe({
      next: data => {
        this.resultado.set(data);
        this.aplicando.set(false);
        this.estado.triggerRefresh();
        this.paso.set(3);
      },
      error: err => {
        this.error.set(err?.error?.message ?? 'Error al aplicar los cambios.');
        this.aplicando.set(false);
      },
    });
  }

  // ─── Paso 3 ───────────────────────────────────────────────────────────────

  resetear(): void {
    this.paso.set(1);
    this.diff.set(null);
    this.resultado.set(null);
    this.motivo.set('');
    this.error.set(null);
    this.archivoSeleccionado = null;
    this.colapsadas.set({});
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  get nombreArchivo(): string {
    return this.archivoSeleccionado?.name ?? '';
  }

  contarCambios(d: DiffPreview): number {
    return (
      d.nuevas.length +
      d.eliminadas.length +
      d.reabiertas.length +
      d.cambios_aula.length +
      d.cambios_grupo.length +
      d.cambios_aula_y_grupo.length +
      d.cambios_cupo.length
    );
  }

  totalAplicadas(r: DiffAplicarResult): number {
    return Object.values(r.aplicadas).reduce<number>((a, b) => a + (b ?? 0), 0);
  }

  trackByIdx(_: number, __: unknown): number {
    return _;
  }

  asDiffSeccion(items: unknown[]): DiffSeccion[] {
    return items as DiffSeccion[];
  }

  asDiffCambio(items: unknown[]): DiffCambio[] {
    return items as DiffCambio[];
  }

  asDiffOmitido(items: unknown[]): DiffOmitido[] {
    return items as DiffOmitido[];
  }

  asDiffCambioCupo(items: unknown[]): DiffCambioCupo[] {
    return items as DiffCambioCupo[];
  }
}
