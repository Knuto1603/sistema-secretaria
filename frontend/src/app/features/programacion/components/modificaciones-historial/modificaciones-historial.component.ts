import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { GeneracionModificacionService, ModificacionItem, PaginacionMeta } from '../../services/generacion-modificacion.service';

type TipoFiltro = '' | 'cerrar_curso' | 'abrir_seccion' | 'cambio_aula' | 'cambio_grupo' | 'unificar';

interface Filtros {
  tipo: TipoFiltro;
  estado: '' | 'pendiente' | 'documentado';
  fecha_desde: string;
  fecha_hasta: string;
  page: number;
}

const TIPO_LABELS: Record<string, { label: string; color: string }> = {
  cerrar_curso:  { label: 'Cierre',         color: 'bg-red-100 text-red-700' },
  abrir_seccion: { label: 'Apertura',       color: 'bg-emerald-100 text-emerald-700' },
  cambio_aula:   { label: 'Cambio Aula',    color: 'bg-blue-100 text-blue-700' },
  cambio_grupo:  { label: 'Cambio Grupo',   color: 'bg-violet-100 text-violet-700' },
  unificar:      { label: 'Unificación',    color: 'bg-amber-100 text-amber-700' },
};

@Component({
  selector: 'app-modificaciones-historial',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './modificaciones-historial.component.html'
})
export class ModificacionesHistorialComponent implements OnInit {
  private svc = inject(GeneracionModificacionService);
  private router = inject(Router);

  items = signal<ModificacionItem[]>([]);
  meta  = signal<PaginacionMeta | null>(null);
  cargando = signal(false);

  filtros: Filtros = {
    tipo: '',
    estado: '',
    fecha_desde: '',
    fecha_hasta: '',
    page: 1
  };

  readonly tiposOpciones: { value: TipoFiltro; label: string }[] = [
    { value: '', label: 'Todos los tipos' },
    { value: 'cerrar_curso',  label: 'Cierre de curso' },
    { value: 'abrir_seccion', label: 'Apertura de sección' },
    { value: 'cambio_aula',   label: 'Cambio de aula' },
    { value: 'cambio_grupo',  label: 'Cambio de grupo' },
    { value: 'unificar',      label: 'Unificación' },
  ];

  pages = computed(() => {
    const m = this.meta();
    if (!m) return [];
    return Array.from({ length: m.last_page }, (_, i) => i + 1);
  });

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    const params: Record<string, string | number> = { per_page: 20, page: this.filtros.page };
    if (this.filtros.tipo)       params['tipo']        = this.filtros.tipo;
    if (this.filtros.estado)     params['estado']      = this.filtros.estado;
    if (this.filtros.fecha_desde) params['fecha_desde'] = this.filtros.fecha_desde;
    if (this.filtros.fecha_hasta) params['fecha_hasta'] = this.filtros.fecha_hasta;

    this.svc.listarModificaciones(params).subscribe({
      next: resp => {
        this.items.set(resp.items);
        this.meta.set(resp.pagination);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false)
    });
  }

  aplicarFiltros(): void {
    this.filtros.page = 1;
    this.cargar();
  }

  irAPagina(p: number): void {
    this.filtros.page = p;
    this.cargar();
  }

  limpiar(): void {
    this.filtros = { tipo: '', estado: '', fecha_desde: '', fecha_hasta: '', page: 1 };
    this.cargar();
  }

  tipoInfo(tipo: string): { label: string; color: string } {
    return TIPO_LABELS[tipo] ?? { label: tipo, color: 'bg-slate-100 text-slate-600' };
  }

  irAGenerar(): void {
    this.router.navigate(['/app/programacion/generar-documentos']);
  }
}
