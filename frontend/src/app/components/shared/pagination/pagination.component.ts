import { Component, input, output, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-shared-pagination',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './pagination.component.html'
})
export class PaginationComponent {
  currentPage = input.required<number>();
  lastPage = input.required<number>();
  total = input.required<number>();
  from = input.required<number>();
  to = input.required<number>();
  perPage = input.required<number>();

  pageChange = output<number>();
  pageSizeChange = output<number>();

  pageSizeOptions = [10, 20, 50, 100];

  /**
   * Genera el rango de páginas compacto (Lógica de Elipsis)
   * Ejemplo: [1, '...', 12, 13, 14, '...', 27]
   */
  pages = computed(() => {
    const current = this.currentPage();
    const last = this.lastPage();
    const delta = 1; // Cuántas páginas mostrar a los lados de la actual
    const range = [];
    const rangeWithDots = [];
    let l;

    range.push(1);

    if (last <= 1) return range;

    for (let i = current - delta; i <= current + delta; i++) {
      if (i < last && i > 1) {
        range.push(i);
      }
    }
    range.push(last);

    for (let i of range) {
      if (l) {
        if (i - l === 2) {
          rangeWithDots.push(l + 1);
        } else if (i - l !== 1) {
          rangeWithDots.push('...');
        }
      }
      rangeWithDots.push(i);
      l = i;
    }

    return rangeWithDots;
  });

  onPageClick(page: number | string) {
    if (typeof page === 'number' && page !== this.currentPage()) {
      this.pageChange.emit(page);
    }
  }

  onPageSizeChange(event: any) {
    this.pageSizeChange.emit(Number(event.target.value));
  }
}