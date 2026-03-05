import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DeveloperService } from '../../services/developer.service';
import { EmailLogItem } from '../../models/developer.models';
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
  selector: 'app-email-viewer',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule, PaginationComponent],
  templateUrl: './email-viewer.component.html',
})
export class EmailViewerComponent implements OnInit {
  private devService = inject(DeveloperService);

  items = signal<EmailLogItem[]>([]);
  pagination = signal<Pagination | null>(null);
  loading = signal(false);

  filters = {
    purpose: '',
    usado: '',
    search: '',
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
    if (this.filters.purpose) params['purpose'] = this.filters.purpose;
    if (this.filters.usado) params['usado'] = this.filters.usado;
    if (this.filters.search) params['search'] = this.filters.search;
    params['page'] = this.filters.page;
    params['per_page'] = this.filters.per_page;

    this.devService.getEmailLogs(params).subscribe({
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
    this.filters.purpose = '';
    this.filters.usado = '';
    this.filters.search = '';
    this.load(1);
  }
}
