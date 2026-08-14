import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TelegramService, TelegramEstadisticas, TelegramVinculado } from '@core/services/telegram.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { PaginationComponent } from '@shared/pagination/pagination.component';

interface Pagination {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
  from: number;
  to: number;
}

interface Escuela {
  id: string;
  nombre: string;
}

@Component({
  selector: 'app-telegram-panel',
  standalone: true,
  imports: [CommonModule, DatePipe, FormsModule, AppTableComponent, PaginationComponent],
  templateUrl: './telegram-panel.component.html',
})
export class TelegramPanelComponent implements OnInit {
  private telegramService = inject(TelegramService);

  estadisticas = signal<TelegramEstadisticas | null>(null);
  vinculados   = signal<TelegramVinculado[]>([]);
  loading      = signal(false);

  // Filtros (mismo patrón hardcodeado que estudiantes-lista.component.ts: escuelas fijas de la FII)
  search = '';
  escuelas: Escuela[] = [
    { id: '0', nombre: 'Industrial' },
    { id: '1', nombre: 'Informática' },
    { id: '2', nombre: 'Agroindustrial' },
    { id: '3', nombre: 'Mecatrónica' },
  ];
  escuelaFilter = '';
  anioIngresoFilter: number | null = null;

  // Selección manual (persiste entre páginas)
  seleccionados = signal<Set<string>>(new Set());

  // Compositor de mensaje
  mostrarComposer = signal(false);
  mensajeTexto = '';
  enviando = signal(false);
  resultadoEnvio = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  pagination: Pagination = {
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
    from: 0,
    to: 0,
  };

  columnas: TableColumn[] = [
    { key: 'seleccion', label: '' },
    { key: 'name', label: 'Nombre' },
    { key: 'codigo_universitario', label: 'Código' },
    { key: 'escuela', label: 'Escuela' },
    { key: 'vinculado_desde', label: 'Vinculado desde' },
  ];

  ngOnInit(): void {
    this.cargarEstadisticas();
    this.cargarVinculados();
  }

  cargarEstadisticas(): void {
    this.telegramService.getEstadisticas().subscribe({
      next: (data) => this.estadisticas.set(data),
    });
  }

  cargarVinculados(): void {
    this.loading.set(true);
    this.telegramService.getVinculados(
      this.pagination.currentPage,
      this.search || undefined,
      this.escuelaFilter || undefined,
      this.anioIngresoFilter ?? undefined,
    ).subscribe({
      next: (response) => {
        this.vinculados.set(response.items);
        const pag = response.pagination;
        this.pagination = {
          currentPage: pag.current_page,
          lastPage: pag.last_page,
          perPage: pag.per_page,
          total: pag.total,
          from: pag.total === 0 ? 0 : (pag.current_page - 1) * pag.per_page + 1,
          to: Math.min(pag.current_page * pag.per_page, pag.total),
        };
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  buscar(): void {
    this.pagination.currentPage = 1;
    this.cargarVinculados();
  }

  onPageChange(page: number): void {
    this.pagination.currentPage = page;
    this.cargarVinculados();
  }

  // ── Selección manual ────────────────────────────────────────────────────

  estaSeleccionado(id: string): boolean {
    return this.seleccionados().has(id);
  }

  toggleSeleccion(id: string): void {
    const set = new Set(this.seleccionados());
    if (set.has(id)) set.delete(id); else set.add(id);
    this.seleccionados.set(set);
  }

  limpiarSeleccion(): void {
    this.seleccionados.set(new Set());
  }

  // ── Compositor de mensaje ────────────────────────────────────────────────

  abrirComposer(): void {
    this.mensajeTexto = '';
    this.resultadoEnvio.set(null);
    this.mostrarComposer.set(true);
  }

  cerrarComposer(): void {
    this.mostrarComposer.set(false);
  }

  descripcionDestinatarios(): string {
    const nSeleccionados = this.seleccionados().size;
    if (nSeleccionados > 0) {
      return `Se enviará a los ${nSeleccionados} estudiante(s) seleccionados.`;
    }
    if (this.search || this.escuelaFilter || this.anioIngresoFilter) {
      return `Se enviará a los ${this.pagination.total} estudiante(s) vinculados que coinciden con el filtro actual.`;
    }
    return `Se enviará a TODOS los ${this.pagination.total} estudiantes vinculados.`;
  }

  enviarMensaje(): void {
    if (!this.mensajeTexto.trim()) return;

    if (!confirm(`${this.descripcionDestinatarios()}\n\n¿Confirmas el envío?`)) return;

    this.enviando.set(true);
    this.resultadoEnvio.set(null);

    const payload = this.seleccionados().size > 0
      ? { mensaje: this.mensajeTexto, user_ids: Array.from(this.seleccionados()) }
      : {
          mensaje: this.mensajeTexto,
          search: this.search || undefined,
          escuela_codigo: this.escuelaFilter || undefined,
          anio_ingreso: this.anioIngresoFilter ?? undefined,
        };

    this.telegramService.enviarMasivo(payload).subscribe({
      next: ({ enviados }) => {
        this.enviando.set(false);
        this.resultadoEnvio.set({ tipo: 'success', texto: `Mensaje encolado para ${enviados} estudiante(s).` });
        this.limpiarSeleccion();
        this.mensajeTexto = '';
        setTimeout(() => this.mostrarComposer.set(false), 1500);
      },
      error: () => {
        this.enviando.set(false);
        this.resultadoEnvio.set({ tipo: 'error', texto: 'Error al enviar el mensaje.' });
      },
    });
  }
}
