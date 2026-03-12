import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  UsuarioService,
  HistorialesZipResumen,
  InscripcionesHtmlResumen,
} from '@core/services/usuario.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-importaciones-alumnos',
  standalone: true,
  imports: [CommonModule, AppButtonComponent],
  templateUrl: './importaciones-alumnos.component.html',
})
export class ImportacionesAlumnosComponent {
  private service = inject(UsuarioService);

  // ZIP Historiales
  importandoZip     = signal(false);
  resultadoZip      = signal<HistorialesZipResumen | null>(null);
  errorZip          = signal<string | null>(null);

  // HTML Inscripciones
  importandoHtml    = signal(false);
  resultadoHtml     = signal<InscripcionesHtmlResumen | null>(null);
  errorHtml         = signal<string | null>(null);

  onZipSeleccionado(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    this.importandoZip.set(true);
    this.resultadoZip.set(null);
    this.errorZip.set(null);

    this.service.importarHistorialesZip(file).subscribe({
      next:  res => { this.resultadoZip.set(res); this.importandoZip.set(false); },
      error: err => {
        this.errorZip.set(err.error?.message || 'Error al procesar el ZIP');
        this.importandoZip.set(false);
      },
    });
    (event.target as HTMLInputElement).value = '';
  }

  onHtmlSeleccionado(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    this.importandoHtml.set(true);
    this.resultadoHtml.set(null);
    this.errorHtml.set(null);

    this.service.importarInscripcionesHtml(file).subscribe({
      next:  res => { this.resultadoHtml.set(res); this.importandoHtml.set(false); },
      error: err => {
        this.errorHtml.set(err.error?.message || 'Error al procesar el archivo HTML');
        this.importandoHtml.set(false);
      },
    });
    (event.target as HTMLInputElement).value = '';
  }
}
