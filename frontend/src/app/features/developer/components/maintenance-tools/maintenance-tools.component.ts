import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { DeveloperService } from '../../services/developer.service';

interface Toast {
  tipo: 'success' | 'error';
  texto: string;
}

interface MailResult {
  enviado_a: string;
  mailer: string;
  host: string;
  puerto: string | number;
  from: string;
  tiempo_ms: number;
}

@Component({
  selector: 'app-maintenance-tools',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './maintenance-tools.component.html',
})
export class MaintenanceToolsComponent {
  private devService = inject(DeveloperService);

  loadingCache = signal(false);
  loadingLogs  = signal(false);
  loadingMail  = signal(false);
  mailDest     = signal('');
  mailResult   = signal<MailResult | null>(null);
  toast        = signal<Toast | null>(null);

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

  testMail(): void {
    const dest = this.mailDest().trim();
    if (!dest) return;
    this.loadingMail.set(true);
    this.mailResult.set(null);
    this.devService.testMail(dest).subscribe({
      next: data => {
        this.loadingMail.set(false);
        this.mailResult.set(data);
        this.showToast('success', `Correo enviado a ${data.enviado_a} en ${data.tiempo_ms}ms`);
      },
      error: err => {
        this.loadingMail.set(false);
        const msg = err?.error?.message ?? 'Error desconocido al enviar el correo';
        this.showToast('error', msg);
      },
    });
  }

  private showToast(tipo: 'success' | 'error', texto: string): void {
    this.toast.set({ tipo, texto });
    setTimeout(() => this.toast.set(null), 5000);
  }
}
