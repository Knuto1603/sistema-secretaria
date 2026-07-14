import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { DeveloperService } from '../../services/developer.service';
import { ErrorLogItem } from '../../models/developer.models';
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
  selector: 'app-error-log',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, PaginationComponent],
  templateUrl: './error-log.component.html',
})
export class ErrorLogComponent implements OnInit {
  private devService = inject(DeveloperService);

  items      = signal<ErrorLogItem[]>([]);
  pagination = signal<Pagination | null>(null);
  loading    = signal(false);
  fileSizeKb = signal(0);
  expanded   = signal<Set<number>>(new Set());

  filters = {
    level:    '',
    search:   '',
    desde:    '',
    hasta:    '',
    page:     1,
    per_page: 20,
  };

  readonly levels = ['ERROR', 'WARNING', 'INFO', 'DEBUG', 'CRITICAL'];

  ngOnInit(): void {
    this.load();
  }

  load(page = 1): void {
    this.filters.page = page;
    this.loading.set(true);
    this.expanded.set(new Set());

    const params: Record<string, string | number> = {
      page:     this.filters.page,
      per_page: this.filters.per_page,
    };
    if (this.filters.level)  params['level']  = this.filters.level;
    if (this.filters.search) params['search'] = this.filters.search;
    if (this.filters.desde)  params['desde']  = this.filters.desde;
    if (this.filters.hasta)  params['hasta']  = this.filters.hasta;

    this.devService.getErrorLogs(params).subscribe({
      next: data => {
        this.items.set(data.items);
        this.fileSizeKb.set(data.file_size_kb);
        this.pagination.set({
          current_page: data.current_page,
          last_page:    data.last_page,
          per_page:     data.per_page,
          total:        data.total,
          from:         data.total === 0 ? 0 : (data.current_page - 1) * data.per_page + 1,
          to:           Math.min(data.current_page * data.per_page, data.total),
        });
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  applyFilters(): void { this.load(1); }

  clearFilters(): void {
    this.filters.level  = '';
    this.filters.search = '';
    this.filters.desde  = '';
    this.filters.hasta  = '';
    this.load(1);
  }

  toggleExpand(index: number): void {
    const set = new Set(this.expanded());
    set.has(index) ? set.delete(index) : set.add(index);
    this.expanded.set(set);
  }

  isExpanded(index: number): boolean {
    return this.expanded().has(index);
  }

  levelClass(level: string): string {
    const map: Record<string, string> = {
      ERROR:    'bg-red-100 text-red-800 border border-red-200',
      CRITICAL: 'bg-red-200 text-red-900 border border-red-300',
      WARNING:  'bg-yellow-100 text-yellow-800 border border-yellow-200',
      INFO:     'bg-blue-100 text-blue-800 border border-blue-200',
      DEBUG:    'bg-gray-100 text-gray-700 border border-gray-200',
    };
    return map[level] ?? 'bg-gray-100 text-gray-600 border border-gray-200';
  }

  hasDetail(item: ErrorLogItem): boolean {
    return !!(item.exception || item.trace || item.context);
  }

  shortMessage(msg: string): string {
    return msg.length > 120 ? msg.slice(0, 120) + '…' : msg;
  }

  formatContext(ctx: Record<string, unknown> | null): string {
    return ctx ? JSON.stringify(ctx, null, 2) : '';
  }
}
