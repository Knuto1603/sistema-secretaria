import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  GeneracionModificacionService,
  GeneracionItem,
  PreviewGrupo
} from '../../services/generacion-modificacion.service';
import { ProgramacionEstadoService } from '../../services/programacion-estado.service';

const TIPO_DOC_LABELS: Record<string, string> = {
  cierre:          'Cierre de cursos',
  cierre_apertura: 'Cierre y apertura',
  fusion:          'Fusión de secciones',
  cambio_aula:     'Cambio de aula/grupo',
};

@Component({
  selector: 'app-generar-documentos-wizard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './generar-documentos-wizard.component.html'
})
export class GenerarDocumentosWizardComponent implements OnInit {
  private svc    = inject(GeneracionModificacionService);
  private router = inject(Router);
  readonly estado = inject(ProgramacionEstadoService);

  // ── Wizard state ──────────────────────────────────────────────────────────
  paso = signal<1 | 2 | 3>(1);

  // Paso 1 — el período viene del shell via ProgramacionEstadoService
  fechaDesde  = signal('');
  fechaHasta  = signal('');

  paso1Valido = computed(() =>
    this.estado.periodoId().trim().length > 0 &&
    this.fechaDesde().trim().length > 0 &&
    this.fechaHasta().trim().length > 0 &&
    this.fechaHasta() >= this.fechaDesde()
  );

  // Paso 2 — preview
  cargandoPreview = signal(false);
  preview = signal<PreviewGrupo[]>([]);

  // Paso 3 — confirmar
  numeroOficio    = signal('');
  generando       = signal(false);
  errorMsg        = signal('');

  paso3Valido = computed(() => this.numeroOficio().trim().length > 0 && !this.generando());

  // ── Historial ─────────────────────────────────────────────────────────────
  historial       = signal<GeneracionItem[]>([]);
  cargandoHist    = signal(false);
  eliminandoId    = signal<string | null>(null);

  ngOnInit(): void {
    this.cargarHistorial();
  }

  cargarHistorial(): void {
    this.cargandoHist.set(true);
    this.svc.historialGeneraciones().subscribe({
      next: data => { this.historial.set(data); this.cargandoHist.set(false); },
      error: ()  => this.cargandoHist.set(false)
    });
  }

  // ── Navegación wizard ─────────────────────────────────────────────────────

  irAPaso2(): void {
    if (!this.paso1Valido()) return;
    this.cargandoPreview.set(true);
    this.preview.set([]);
    this.errorMsg.set('');

    this.svc.preview(this.estado.periodoId(), this.fechaDesde(), this.fechaHasta()).subscribe({
      next: data => {
        this.preview.set(data);
        this.paso.set(2);
        this.cargandoPreview.set(false);
      },
      error: (err) => {
        this.errorMsg.set(err?.error?.message ?? 'Error al obtener vista previa');
        this.cargandoPreview.set(false);
      }
    });
  }

  irAPaso3(): void {
    this.paso.set(3);
  }

  volver(): void {
    if (this.paso() === 2) this.paso.set(1);
    else if (this.paso() === 3) this.paso.set(2);
  }

  confirmarGeneracion(): void {
    if (!this.paso3Valido()) return;
    this.generando.set(true);
    this.errorMsg.set('');

    this.svc.generar(this.estado.periodoId(), this.fechaDesde(), this.fechaHasta(), this.numeroOficio()).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `modificaciones_${this.numeroOficio()}.zip`;
        a.click();
        URL.revokeObjectURL(url);
        this.generando.set(false);
        this.paso.set(1);
        this.numeroOficio.set('');
        this.fechaDesde.set('');
        this.fechaHasta.set('');
        this.preview.set([]);
        this.cargarHistorial();
      },
      error: (err) => {
        this.errorMsg.set(err?.error?.message ?? 'Error al generar documentos');
        this.generando.set(false);
      }
    });
  }

  descargarZip(id: string, numeroOficio: string): void {
    this.svc.descargarZip(id).subscribe(blob => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `modificaciones_${numeroOficio}.zip`;
      a.click();
      URL.revokeObjectURL(url);
    });
  }

  eliminarGeneracion(id: string): void {
    if (!confirm('¿Eliminar esta generación y sus archivos?')) return;
    this.eliminandoId.set(id);
    this.svc.eliminarGeneracion(id).subscribe({
      next: () => {
        this.historial.update(h => h.filter(g => g.id !== id));
        this.eliminandoId.set(null);
      },
      error: () => this.eliminandoId.set(null)
    });
  }

  tipoDocLabel(tipo: string): string {
    return TIPO_DOC_LABELS[tipo] ?? tipo;
  }

  irAHistorial(): void {
    this.router.navigate(['/app/programacion/modificaciones']);
  }

  totalModificaciones(): number {
    return this.preview().reduce((acc, g) => acc + g.total_modificaciones, 0);
  }
}
