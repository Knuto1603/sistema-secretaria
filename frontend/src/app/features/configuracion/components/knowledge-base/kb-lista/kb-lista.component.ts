import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { KnowledgeBaseService } from '../../../../chatbot/services/knowledge-base.service';
import { KbArticle } from '../../../../chatbot/models/chat.models';
import { PaginationComponent } from '../../../../../components/shared/pagination/pagination.component';

@Component({
  selector: 'app-kb-lista',
  standalone: true,
  imports: [CommonModule, FormsModule, PaginationComponent],
  templateUrl: './kb-lista.component.html',
})
export class KbListaComponent implements OnInit {
  private kbSvc = inject(KnowledgeBaseService);
  private router = inject(Router);

  articles = signal<KbArticle[]>([]);
  loading = signal(true);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);
  perPage = signal(15);
  from = signal(0);
  to = signal(0);

  searchText = '';
  filterTipo = '';
  filterCategoria = '';
  filterActivo = '';

  tipos = ['proceso', 'faq', 'norma', 'requisito', 'resolucion'];

  ngOnInit(): void {
    this.load();
  }

  load(page = 1): void {
    this.loading.set(true);
    const params: any = { page, per_page: 15 };
    if (this.searchText)     params['search']    = this.searchText;
    if (this.filterTipo)     params['tipo']      = this.filterTipo;
    if (this.filterCategoria) params['categoria'] = this.filterCategoria;
    if (this.filterActivo !== '') params['activo'] = this.filterActivo;

    this.kbSvc.getArticles(params).subscribe({
      next: data => {
        this.articles.set(data.items);
        const pag = data.pagination;
        this.currentPage.set(pag.current_page);
        this.lastPage.set(pag.last_page);
        this.total.set(pag.total);
        this.perPage.set(pag.per_page);
        this.from.set(pag.total === 0 ? 0 : (pag.current_page - 1) * pag.per_page + 1);
        this.to.set(Math.min(pag.current_page * pag.per_page, pag.total));
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  applyFilters(): void {
    this.load(1);
  }

  clearFilters(): void {
    this.searchText = '';
    this.filterTipo = '';
    this.filterCategoria = '';
    this.filterActivo = '';
    this.load(1);
  }

  navigate(route: string): void {
    this.router.navigate([route]);
  }

  toggleActivo(article: KbArticle): void {
    this.kbSvc.toggleArticle(article.id).subscribe(res => {
      this.articles.update(list =>
        list.map(a => a.id === article.id ? { ...a, activo: res.activo } : a)
      );
    });
  }

  delete(article: KbArticle): void {
    if (!confirm(`¿Eliminar "${article.titulo}"?`)) return;
    this.kbSvc.deleteArticle(article.id).subscribe(() => {
      this.articles.update(list => list.filter(a => a.id !== article.id));
      this.total.update(t => t - 1);
    });
  }

  getTypeBadgeClass(tipo: string): string {
    const map: Record<string, string> = {
      proceso:    'bg-blue-100 text-blue-700',
      faq:        'bg-green-100 text-green-700',
      norma:      'bg-purple-100 text-purple-700',
      requisito:  'bg-orange-100 text-orange-700',
      resolucion: 'bg-red-100 text-red-700',
    };
    return map[tipo] ?? 'bg-gray-100 text-gray-700';
  }
}
