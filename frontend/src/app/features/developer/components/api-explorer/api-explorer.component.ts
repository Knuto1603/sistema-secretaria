import { Component, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DeveloperService } from '../../services/developer.service';
import { RouteItem } from '../../models/developer.models';

@Component({
  selector: 'app-api-explorer',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './api-explorer.component.html',
})
export class ApiExplorerComponent implements OnInit {
  private devService = inject(DeveloperService);

  allRoutes = signal<RouteItem[]>([]);
  loading = signal(false);
  filterMethod = signal('');
  filterUri = signal('');

  filteredRoutes = computed(() => {
    let routes = this.allRoutes();
    if (this.filterMethod()) {
      routes = routes.filter(r => r.method === this.filterMethod());
    }
    if (this.filterUri()) {
      const q = this.filterUri().toLowerCase();
      routes = routes.filter(r => r.uri.toLowerCase().includes(q));
    }
    return routes;
  });

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.devService.getRoutes().subscribe({
      next: data => { this.allRoutes.set(data); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  methodColor(method: string): string {
    const map: Record<string, string> = {
      GET:    'bg-emerald-100 text-emerald-700',
      POST:   'bg-blue-100 text-blue-700',
      PUT:    'bg-amber-100 text-amber-700',
      PATCH:  'bg-orange-100 text-orange-700',
      DELETE: 'bg-red-100 text-red-700',
    };
    return map[method] ?? 'bg-slate-100 text-slate-600';
  }
}
