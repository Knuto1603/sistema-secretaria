import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { DeveloperService } from '../../services/developer.service';

interface Toast {
  tipo: 'success' | 'error';
  texto: string;
}

@Component({
  selector: 'app-maintenance-tools',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './maintenance-tools.component.html',
})
export class MaintenanceToolsComponent {
  private devService = inject(DeveloperService);

  loadingCache = signal(false);
  loadingLogs = signal(false);
  toast = signal<Toast | null>(null);

  clearCache(): void {
    if (!confirm('¿Limpiar caché, configuración y vistas cacheadas?')) return;
    this.loadingCache.set(true);
    this.devService.clearCache().subscribe({
      next: () => {
        this.loadingCache.set(false);
        this.showToast('success', 'Caché limpiado correctamente');
      },
      error: () => {
        this.loadingCache.set(false);
        this.showToast('error', 'Error al limpiar el caché');
      },
    });
  }

  clearLogs(): void {
    if (!confirm('¿Vaciar todos los archivos de log? (No se eliminan, solo se limpian)')) return;
    this.loadingLogs.set(true);
    this.devService.clearLogs().subscribe({
      next: data => {
        this.loadingLogs.set(false);
        this.showToast('success', `Se limpiaron ${data.files_cleared} archivo(s) de log`);
      },
      error: () => {
        this.loadingLogs.set(false);
        this.showToast('error', 'Error al limpiar los logs');
      },
    });
  }

  private showToast(tipo: 'success' | 'error', texto: string): void {
    this.toast.set({ tipo, texto });
    setTimeout(() => this.toast.set(null), 4000);
  }
}
