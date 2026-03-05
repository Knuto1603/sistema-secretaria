import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { TipoSolicitudService, TipoSolicitud } from '@core/services/tipo-solicitud.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-tipos-solicitud-lista',
  standalone: true,
  imports: [CommonModule, AppTableComponent, AppBadgeComponent, AppButtonComponent],
  templateUrl: './tipos-solicitud-lista.component.html'
})
export class TiposSolicitudListaComponent implements OnInit {
  private tipoSolicitudService = inject(TipoSolicitudService);
  private router = inject(Router);

  tipos = signal<TipoSolicitud[]>([]);
  loading = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  columnas: TableColumn[] = [
    { key: 'codigo', label: 'Código' },
    { key: 'nombre', label: 'Nombre' },
    { key: 'descripcion', label: 'Descripción' },
    { key: 'requiere_archivo', label: 'Requiere Archivo' },
    { key: 'activo', label: 'Estado' }
  ];

  ngOnInit(): void {
    this.cargarDatos();
  }

  cargarDatos(): void {
    this.loading.set(true);
    this.tipoSolicitudService.getAll().subscribe({
      next: (tipos) => {
        this.tipos.set(tipos);
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar los tipos de solicitud');
        this.loading.set(false);
      }
    });
  }

  nuevoTipo(): void {
    this.router.navigate(['/app/configuracion/tipos-solicitud/nuevo']);
  }

  editarTipo(tipo: TipoSolicitud): void {
    this.router.navigate(['/app/configuracion/tipos-solicitud/editar', tipo.id]);
  }

  toggleActivo(tipo: TipoSolicitud): void {
    this.tipoSolicitudService.toggleActivo(tipo.id, !tipo.activo).subscribe({
      next: (updated) => {
        this.tipos.update(tipos =>
          tipos.map(t => t.id === updated.id ? updated : t)
        );
        this.mostrarMensaje('success', `Tipo ${updated.activo ? 'activado' : 'desactivado'} correctamente`);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cambiar el estado');
      }
    });
  }

  eliminarTipo(tipo: TipoSolicitud): void {
    if (!confirm(`¿Estás seguro de eliminar "${tipo.nombre}"?`)) return;

    this.tipoSolicitudService.delete(tipo.id).subscribe({
      next: () => {
        this.tipos.update(tipos => tipos.filter(t => t.id !== tipo.id));
        this.mostrarMensaje('success', 'Tipo de solicitud eliminado');
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al eliminar. Puede tener solicitudes asociadas.');
      }
    });
  }

  volver(): void {
    this.router.navigate(['/app/configuracion']);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
