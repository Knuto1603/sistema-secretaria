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
  search       = '';

  pagination: Pagination = {
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0,
    from: 0,
    to: 0,
  };

  columnas: TableColumn[] = [
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
    this.telegramService.getVinculados(this.pagination.currentPage, this.search || undefined).subscribe({
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
}
