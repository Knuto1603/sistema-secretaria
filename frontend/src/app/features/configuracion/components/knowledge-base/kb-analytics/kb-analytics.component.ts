import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ChatService } from '../../../../chatbot/services/chat.service';
import {
  ChatAnalyticsSummary,
  TopTopic,
  KnowledgeGap,
  PaginatedData,
} from '../../../../chatbot/models/chat.models';
import { PaginationComponent } from '../../../../../components/shared/pagination/pagination.component';

@Component({
  selector: 'app-kb-analytics',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, PaginationComponent],
  templateUrl: './kb-analytics.component.html',
})
export class KbAnalyticsComponent implements OnInit {
  private chatSvc = inject(ChatService);

  summary = signal<ChatAnalyticsSummary | null>(null);
  topTopics = signal<TopTopic[]>([]);
  gaps = signal<KnowledgeGap[]>([]);
  gapPagination = signal<PaginatedData<KnowledgeGap>['pagination'] | null>(null);
  gapFrom = signal(0);
  gapTo = signal(0);

  days = 30;
  loadingSummary = signal(true);
  loadingTopics = signal(true);
  loadingGaps = signal(true);

  activeTab: 'summary' | 'topics' | 'gaps' = 'summary';

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loadSummary();
    this.loadTopics();
    this.loadGaps();
  }

  loadSummary(): void {
    this.loadingSummary.set(true);
    this.chatSvc.getSummary(this.days).subscribe({
      next: data => { this.summary.set(data); this.loadingSummary.set(false); },
      error: () => this.loadingSummary.set(false),
    });
  }

  loadTopics(): void {
    this.loadingTopics.set(true);
    this.chatSvc.getTopTopics({ days: this.days, limit: 15 }).subscribe({
      next: data => { this.topTopics.set(data); this.loadingTopics.set(false); },
      error: () => this.loadingTopics.set(false),
    });
  }

  loadGaps(page = 1): void {
    this.loadingGaps.set(true);
    this.chatSvc.getKnowledgeGaps({ days: this.days, per_page: 15, page }).subscribe({
      next: data => {
        this.gaps.set(data.items);
        const pag = data.pagination;
        this.gapPagination.set(pag);
        this.gapFrom.set(pag.total === 0 ? 0 : (pag.current_page - 1) * pag.per_page + 1);
        this.gapTo.set(Math.min(pag.current_page * pag.per_page, pag.total));
        this.loadingGaps.set(false);
      },
      error: () => this.loadingGaps.set(false),
    });
  }

  applyDays(): void {
    this.loadAll();
  }

  getMaxConsultas(): number {
    return Math.max(...this.topTopics().map(t => t.consultas), 1);
  }

  getBarWidth(consultas: number): string {
    return `${Math.round((consultas / this.getMaxConsultas()) * 100)}%`;
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

  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('es-PE', {
      day: '2-digit', month: 'short', year: 'numeric'
    });
  }
}
