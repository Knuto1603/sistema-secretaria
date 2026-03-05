import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DeveloperService } from '../../services/developer.service';
import { ActivityLogItem } from '../../models/developer.models';
import { PaginationComponent } from '../../../../components/shared/pagination/pagination.component';

interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

@Component({
  selector: 'app-activity-log',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule, PaginationComponent],
  templateUrl: './activity-log.component.html',
})
export class ActivityLogComponent implements OnInit {
  private devService = inject(DeveloperService);

  items = signal<ActivityLogItem[]>([]);
  pagination = signal<Pagination | null>(null);
  loading = signal(false);

  filters = {
    accion: '',
    modelo: '',
    desde: '',
    hasta: '',
    page: 1,
    per_page: 20,
  };

  ngOnInit(): void {
    this.load();
  }

  load(page = 1): void {
    this.filters.page = page;
    this.loading.set(true);

    const params: Record<string, string | number> = {};
    if (this.filters.accion) params['accion'] = this.filters.accion;
    if (this.filters.modelo) params['modelo'] = this.filters.modelo;
    if (this.filters.desde) params['desde'] = this.filters.desde;
    if (this.filters.hasta) params['hasta'] = this.filters.hasta;
    params['page'] = this.filters.page;
    params['per_page'] = this.filters.per_page;

    this.devService.getActivityLogs(params).subscribe({
      next: data => {
        this.items.set(data.items);
        const pag = data.pagination;
        this.pagination.set({
          ...pag,
          from: pag.total === 0 ? 0 : (pag.current_page - 1) * pag.per_page + 1,
          to: Math.min(pag.current_page * pag.per_page, pag.total),
        });
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  applyFilters(): void {
    this.load(1);
  }

  clearFilters(): void {
    this.filters.accion = '';
    this.filters.modelo = '';
    this.filters.desde = '';
    this.filters.hasta = '';
    this.load(1);
  }

  shortModelo(modelo: string | null): string {
    if (!modelo) return '—';
    const parts = modelo.split('\\');
    return parts[parts.length - 1];
  }
}
