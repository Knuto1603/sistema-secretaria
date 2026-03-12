import { Component, inject, OnDestroy, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  UsuarioService,
  HistorialesZipResumen,
  InscripcionesHtmlResumen,
} from '@core/services/usuario.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { Subscription, interval, switchMap, takeWhile, tap } from 'rxjs';

type ZipEstado = 'idle' | 'subiendo' | 'procesando' | 'completado' | 'fallido';

@Component({
  selector: 'app-importaciones-alumnos',
  standalone: true,
  imports: [CommonModule, AppButtonComponent],
  templateUrl: './importaciones-alumnos.component.html',
})
export class ImportacionesAlumnosComponent implements OnDestroy {
  private service = inject(UsuarioService);
  private pollSub?: Subscription;

  // ZIP Historiales
  zipEstado      = signal<ZipEstado>('idle');
  resultadoZip   = signal<HistorialesZipResumen | null>(null);
  errorZip       = signal<string | null>(null);

  // HTML Inscripciones
  importandoHtml = signal(false);
  resultadoHtml  = signal<InscripcionesHtmlResumen | null>(null);
  errorHtml      = signal<string | null>(null);

  get importandoZip(): boolean {
    const e = this.zipEstado();
    return e === 'subiendo' || e === 'procesando';
  }

  get zipMensajeBoton(): string {
    switch (this.zipEstado()) {
      case 'subiendo':   return 'Subiendo archivo...';
      case 'procesando': return 'Procesando en servidor...';
      default:           return 'Seleccionar ZIP';
    }
  }

  onZipSeleccionado(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    this.zipEstado.set('subiendo');
    this.resultadoZip.set(null);
    this.errorZip.set(null);
    this.pollSub?.unsubscribe();

    this.service.importarHistorialesZip(file).subscribe({
      next: ({ job_id }) => {
        this.zipEstado.set('procesando');
        this.iniciarPolling(job_id);
      },
      error: err => {
        this.errorZip.set(err.error?.message || 'Error al subir el ZIP');
        this.zipEstado.set('fallido');
      },
    });

    (event.target as HTMLInputElement).value = '';
  }

  private iniciarPolling(jobId: string): void {
    this.pollSub = interval(3000).pipe(
      switchMap(() => this.service.getImportJobStatus(jobId)),
      tap(status => {
        if (status.estado === 'completado') {
          this.resultadoZip.set(status.resultado);
          this.zipEstado.set('completado');
        } else if (status.estado === 'fallido') {
          this.errorZip.set(status.error_mensaje || 'Error en el procesamiento');
          this.zipEstado.set('fallido');
        }
      }),
      takeWhile(status => status.estado === 'pendiente' || status.estado === 'procesando'),
    ).subscribe();
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

  ngOnDestroy(): void {
    this.pollSub?.unsubscribe();
  }
}
