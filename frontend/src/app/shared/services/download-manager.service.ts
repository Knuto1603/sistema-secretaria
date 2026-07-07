import { Injectable, signal } from '@angular/core';
import { Observable, Subscription } from 'rxjs';

export interface DownloadTask {
  id: string;
  filename: string;
  status: 'downloading' | 'completed' | 'error';
  errorMsg?: string;
  startedAt: Date;
}

@Injectable({ providedIn: 'root' })
export class DownloadManagerService {
  tasks = signal<DownloadTask[]>([]);

  private subscriptions = new Map<string, Subscription>();

  start(filename: string, source$: Observable<Blob>): void {
    const id = crypto.randomUUID();

    this.tasks.update(list => [...list, {
      id,
      filename,
      status: 'downloading',
      startedAt: new Date(),
    }]);

    const sub = source$.subscribe({
      next: (blob) => {
        this.triggerSave(blob, filename);
        this.updateTask(id, { status: 'completed' });
        this.scheduleAutoDismiss(id, 6000);
      },
      error: (err) => {
        const errorMsg = err?.error?.message ?? 'Error al descargar el archivo.';
        this.updateTask(id, { status: 'error', errorMsg });
        this.subscriptions.delete(id);
      },
      complete: () => this.subscriptions.delete(id),
    });

    this.subscriptions.set(id, sub);
  }

  dismiss(id: string): void {
    this.subscriptions.get(id)?.unsubscribe();
    this.subscriptions.delete(id);
    this.tasks.update(list => list.filter(t => t.id !== id));
  }

  private updateTask(id: string, changes: Partial<DownloadTask>): void {
    this.tasks.update(list =>
      list.map(t => t.id === id ? { ...t, ...changes } : t)
    );
  }

  private triggerSave(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  private scheduleAutoDismiss(id: string, delayMs: number): void {
    setTimeout(() => this.dismiss(id), delayMs);
  }
}
