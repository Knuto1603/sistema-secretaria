import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { GeneracionModificacionService, PlantillaEstado } from '../../../programacion/services/generacion-modificacion.service';

@Component({
  selector: 'app-plantillas-modificacion',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './plantillas-modificacion.component.html'
})
export class PlantillasModificacionComponent implements OnInit {
  private svc = inject(GeneracionModificacionService);

  plantillas  = signal<PlantillaEstado[]>([]);
  cargando    = signal(false);
  subiendo    = signal<string | null>(null);
  eliminando  = signal<string | null>(null);
  descargando = signal<string | null>(null);
  errorMsg    = signal('');
  successMsg  = signal('');

  readonly instrucciones: Record<string, string[]> = {
    cierre:          ['${ITEM_C}', '${CURSO}', '${AREA}', '${SECCION}', '${CICLO}', '${DOCENTE}', '${MOTIVO}'],
    cierre_apertura: ['${ITEM_C}', '${ITEM_A}', '${CURSO_C}', '${CURSO_A}', '${AREA}', '${SECCION_C}', '${SECCION_A}'],
    fusion:          ['${ITEM}', '${ORIGEN}', '${DESTINO}', '${AREA}', '${MOTIVO}'],
    cambio_aula:     ['${ITEM}', '${CURSO}', '${AULA_ANT}', '${AULA_NUE}', '${GRUPO_ANT}', '${GRUPO_NUE}'],
  };

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.svc.listarPlantillas().subscribe({
      next: data => { this.plantillas.set(data); this.cargando.set(false); },
      error: ()  => this.cargando.set(false)
    });
  }

  onFileSelected(event: Event, tipo: string): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;
    input.value = '';

    if (!file.name.endsWith('.docx')) {
      this.mostrarError('Solo se aceptan archivos .docx');
      return;
    }

    this.subiendo.set(tipo);
    this.limpiarMensajes();

    this.svc.subirPlantilla(tipo, file).subscribe({
      next: () => {
        this.subiendo.set(null);
        this.mostrarExito('Plantilla cargada correctamente.');
        this.cargar();
      },
      error: (err) => {
        this.subiendo.set(null);
        this.mostrarError(err?.error?.message ?? 'Error al subir la plantilla');
      }
    });
  }

  eliminar(tipo: string): void {
    if (!confirm('¿Eliminar la plantilla para este tipo de documento?')) return;
    this.eliminando.set(tipo);
    this.limpiarMensajes();

    this.svc.eliminarPlantilla(tipo).subscribe({
      next: () => {
        this.eliminando.set(null);
        this.mostrarExito('Plantilla eliminada.');
        this.cargar();
      },
      error: (err) => {
        this.eliminando.set(null);
        this.mostrarError(err?.error?.message ?? 'Error al eliminar');
      }
    });
  }

  descargar(tipo: string, nombre: string): void {
    this.descargando.set(tipo);
    this.svc.descargarPlantilla(tipo).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        a.click();
        URL.revokeObjectURL(url);
        this.descargando.set(null);
      },
      error: () => this.descargando.set(null)
    });
  }

  triggerUpload(tipo: string): void {
    document.getElementById(`upload-${tipo}`)?.click();
  }

  private mostrarError(msg: string): void {
    this.errorMsg.set(msg);
    this.successMsg.set('');
    setTimeout(() => this.errorMsg.set(''), 5000);
  }

  private mostrarExito(msg: string): void {
    this.successMsg.set(msg);
    this.errorMsg.set('');
    setTimeout(() => this.successMsg.set(''), 4000);
  }

  private limpiarMensajes(): void {
    this.errorMsg.set('');
    this.successMsg.set('');
  }
}
