import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-periodos-lista',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './periodos-lista.component.html'
})
export class PeriodosListaComponent implements OnInit {
  private periodoService = inject(PeriodoService);
  private router = inject(Router);

  periodos = signal<Periodo[]>([]);
  loading = signal(false);
  actionLoading = signal<string | null>(null);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  ngOnInit(): void {
    this.cargarPeriodos();
  }

  cargarPeriodos(): void {
    this.loading.set(true);
    this.periodoService.getPeriodos().subscribe({
      next: (periodos) => {
        this.periodos.set(periodos);
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar los periodos');
        this.loading.set(false);
      }
    });
  }

  volver(): void {
    this.router.navigate(['/app/configuracion']);
  }

  nuevoPeriodo(): void {
    this.router.navigate(['/app/configuracion/periodos/nuevo']);
  }

  editarPeriodo(id: string): void {
    this.router.navigate(['/app/configuracion/periodos/editar', id]);
  }

  activarPeriodo(periodo: Periodo): void {
    if (periodo.activo) return;

    this.actionLoading.set(periodo.id);
    this.periodoService.activarPeriodo(periodo.id).subscribe({
      next: () => {
        this.mostrarMensaje('success', `Periodo "${periodo.nombre}" activado`);
        this.cargarPeriodos();
        this.actionLoading.set(null);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al activar el periodo');
        this.actionLoading.set(null);
      }
    });
  }

  desactivarPeriodo(periodo: Periodo): void {
    if (!periodo.activo) return;

    this.actionLoading.set(periodo.id);
    this.periodoService.desactivarPeriodo(periodo.id).subscribe({
      next: () => {
        this.mostrarMensaje('success', `Periodo "${periodo.nombre}" desactivado`);
        this.cargarPeriodos();
        this.actionLoading.set(null);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al desactivar el periodo');
        this.actionLoading.set(null);
      }
    });
  }

  eliminarPeriodo(periodo: Periodo): void {
    if (!confirm(`¿Estás seguro de eliminar el periodo "${periodo.nombre}"?`)) {
      return;
    }

    this.actionLoading.set(periodo.id);
    this.periodoService.eliminarPeriodo(periodo.id).subscribe({
      next: () => {
        this.mostrarMensaje('success', `Periodo "${periodo.nombre}" eliminado`);
        this.cargarPeriodos();
        this.actionLoading.set(null);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al eliminar el periodo');
        this.actionLoading.set(null);
      }
    });
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }

  formatearFecha(fecha: string | null): string {
    if (!fecha) return '-';
    return new Date(fecha).toLocaleDateString('es-PE', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }
}
