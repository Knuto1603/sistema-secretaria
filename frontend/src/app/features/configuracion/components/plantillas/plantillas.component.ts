import {
  ChangeDetectionStrategy,
  Component,
  inject,
  OnInit,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { PlantillaInfo, PlantillasService } from './plantillas.service';

@Component({
  selector: 'app-plantillas',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './plantillas.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PlantillasComponent implements OnInit {
  private plantillasService = inject(PlantillasService);
  private http              = inject(HttpClient);

  plantillas = signal<PlantillaInfo[]>([]);
  cargando   = signal(false);
  subiendo   = signal<string | null>(null);
  mensaje    = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.plantillasService.listar().subscribe({
      next: data => { this.plantillas.set(data); this.cargando.set(false); },
      error: ()   => this.cargando.set(false),
    });
  }

  descargar(plantilla: PlantillaInfo): void {
    const url = this.plantillasService.getUrlDescarga(plantilla.clave);
    this.http.get(url, { responseType: 'blob' }).subscribe({
      next:  blob  => this.triggerDownload(blob, `${plantilla.clave}.docx`),
      error: ()    => this.mostrarMensaje('error', 'No se pudo descargar la plantilla.'),
    });
  }

  onArchivoSeleccionado(event: Event, clave: string): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;

    this.subiendo.set(clave);
    this.limpiarMensaje();

    this.plantillasService.subir(clave, file).subscribe({
      next: msg => {
        this.subiendo.set(null);
        this.mostrarMensaje('success', msg);
        this.cargar();
        input.value = '';
      },
      error: err => {
        this.subiendo.set(null);
        const msg = err.error?.message || 'Error al subir la plantilla.';
        this.mostrarMensaje('error', msg);
        input.value = '';
      },
    });
  }

  formatSize(bytes: number | null): string {
    if (bytes === null) return '—';
    if (bytes < 1024) return `${bytes} B`;
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  formatFecha(fecha: string | null): string {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-PE', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  triggerDownload(blob: Blob, nombre: string): void {
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href     = url;
    a.download = nombre;
    a.click();
    URL.revokeObjectURL(url);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.limpiarMensaje(), 5000);
  }

  private limpiarMensaje(): void {
    this.mensaje.set(null);
  }
}
