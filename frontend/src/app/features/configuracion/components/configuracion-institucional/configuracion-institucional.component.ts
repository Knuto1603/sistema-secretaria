import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ConfiguracionInstitucionalService, ConfigItem } from '../../services/configuracion-institucional.service';

const LABELS: Record<string, string> = {
  universidad:       'Universidad',
  facultad:          'Facultad',
  dependencia:       'Dependencia',
  secretario_titulo: 'Título del Secretario',
  secretario_nombre: 'Nombre del Secretario Académico',
  secretario_cargo:  'Cargo del Secretario',
  institucion_firma: 'Institución en la firma',
  ciudad:            'Ciudad',
  anio_lema:         'Lema del año',
};

@Component({
  selector: 'app-configuracion-institucional',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './configuracion-institucional.component.html',
})
export class ConfiguracionInstitucionalComponent implements OnInit {
  private svc = inject(ConfiguracionInstitucionalService);

  items    = signal<ConfigItem[]>([]);
  loading  = signal(false);
  guardando = signal(false);
  mensaje  = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Copia editable
  valores: Record<string, string> = {};

  ngOnInit(): void { this.cargar(); }

  cargar(): void {
    this.loading.set(true);
    this.svc.getAll().subscribe({
      next: items => {
        this.items.set(items);
        items.forEach(i => (this.valores[i.clave] = i.valor ?? ''));
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  guardar(): void {
    if (this.guardando()) return;
    this.guardando.set(true);
    const payload = Object.entries(this.valores).map(([clave, valor]) => ({ clave, valor }));
    this.svc.update(payload).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrar('success', 'Configuración guardada correctamente.');
      },
      error: err => {
        this.guardando.set(false);
        this.mostrar('error', err.error?.message || 'Error al guardar.');
      },
    });
  }

  labelOf(clave: string): string { return LABELS[clave] ?? clave; }

  private mostrar(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 5000);
  }
}
