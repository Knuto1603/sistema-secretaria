import { Component, OnInit, signal, computed, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { DeveloperService, BackupItem } from '../../services/developer.service';
import { DownloadManagerService } from '../../../../shared/services/download-manager.service';

@Component({
  selector: 'app-database-manager',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './database-manager.component.html',
})
export class DatabaseManagerComponent implements OnInit {
  private devService = inject(DeveloperService);
  private router = inject(Router);
  private dm = inject(DownloadManagerService);

  archivoSeleccionado = signal<File | null>(null);
  confirmacionTexto = signal('');
  importando = signal(false);
  importError = signal('');
  importExito = signal<{ backup_automatico: string; archivo_restaurado: string } | null>(null);

  backups = signal<BackupItem[]>([]);
  cargandoBackups = signal(false);
  descargandoBackup = signal('');

  readonly FRASE = 'RESTAURAR BASE DE DATOS';

  puedeImportar = computed(() =>
    this.archivoSeleccionado() !== null &&
    this.confirmacionTexto() === this.FRASE &&
    !this.importando()
  );

  // Limpiar estudiantes
  confirmacionLimpiarEstudiantes = signal('');
  limpiandoEstudiantes = signal(false);
  limpiarEstudiantesError = signal('');
  limpiarEstudiantesExito = signal<{ eliminados: number } | null>(null);

  readonly FRASE_LIMPIAR_ESTUDIANTES = 'ELIMINAR TODOS LOS ESTUDIANTES';

  puedeLimpiarEstudiantes = computed(() =>
    this.confirmacionLimpiarEstudiantes() === this.FRASE_LIMPIAR_ESTUDIANTES &&
    !this.limpiandoEstudiantes()
  );

  ngOnInit(): void {
    this.cargarBackups();
  }

  descargarBD(): void {
    const fecha = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    const filename = `backup_secretaria_${fecha}.sql`;
    this.dm.start(filename, this.devService.exportDatabase());
  }

  onArchivoChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    this.archivoSeleccionado.set(file);
    this.importError.set('');
    this.importExito.set(null);
  }

  restaurarBD(): void {
    const archivo = this.archivoSeleccionado();
    if (!archivo || !this.puedeImportar()) return;

    this.importando.set(true);
    this.importError.set('');
    this.importExito.set(null);

    this.devService.importDatabase(archivo, this.confirmacionTexto()).subscribe({
      next: (result) => {
        this.importExito.set(result);
        this.importando.set(false);
        this.confirmacionTexto.set('');
        this.archivoSeleccionado.set(null);
        this.cargarBackups();
      },
      error: (err) => {
        this.importError.set(err?.error?.message ?? 'Error al restaurar la base de datos.');
        this.importando.set(false);
      },
    });
  }

  cargarBackups(): void {
    this.cargandoBackups.set(true);
    this.devService.listBackups().subscribe({
      next: (data) => {
        this.backups.set(data);
        this.cargandoBackups.set(false);
      },
      error: () => this.cargandoBackups.set(false),
    });
  }

  descargarBackup(backup: BackupItem): void {
    this.descargandoBackup.set(backup.nombre);
    this.devService.downloadBackup(backup.nombre).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = backup.nombre;
        a.click();
        URL.revokeObjectURL(url);
        this.descargandoBackup.set('');
      },
      error: () => this.descargandoBackup.set(''),
    });
  }

  limpiarEstudiantes(): void {
    if (!this.puedeLimpiarEstudiantes()) return;
    if (!confirm('¿Seguro? Esto elimina PERMANENTEMENTE a todos los estudiantes y sus datos asociados (historial, inscripciones, solicitudes).')) return;

    this.limpiandoEstudiantes.set(true);
    this.limpiarEstudiantesError.set('');
    this.limpiarEstudiantesExito.set(null);

    this.devService.limpiarEstudiantes(this.confirmacionLimpiarEstudiantes()).subscribe({
      next: (result) => {
        this.limpiarEstudiantesExito.set(result);
        this.limpiandoEstudiantes.set(false);
        this.confirmacionLimpiarEstudiantes.set('');
      },
      error: (err) => {
        this.limpiarEstudiantesError.set(err?.error?.message ?? 'Error al eliminar los estudiantes.');
        this.limpiandoEstudiantes.set(false);
      },
    });
  }

  volver(): void {
    this.router.navigate(['/app/developer']);
  }
}
