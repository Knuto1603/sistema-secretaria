import { Injectable, signal } from '@angular/core';

export interface ErrorModalConfig {
  message: string;
  goBack: boolean; // true = volver página anterior, false = solo cerrar
}

@Injectable({ providedIn: 'root' })
export class ErrorModalService {
  visible = signal(false);
  config = signal<ErrorModalConfig>({ message: '', goBack: false });

  show(config: ErrorModalConfig): void {
    this.config.set(config);
    this.visible.set(true);
  }

  hide(): void {
    this.visible.set(false);
  }
}
