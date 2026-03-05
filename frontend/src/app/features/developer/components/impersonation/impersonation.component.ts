import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { map } from 'rxjs';
import { environment } from '@env/environment';
import { AuthService } from '../../../../core/auth/services/auth.service';
import { DeveloperService } from '../../services/developer.service';
import { PaginationComponent } from '../../../../components/shared/pagination/pagination.component';

interface UserListItem {
  id: string;
  name: string;
  tipo_usuario: string;
  username: string | null;
  codigo_universitario: string | null;
  activo: boolean;
  roles: string[];
}

interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

type Tab = 'administrativos' | 'estudiantes';

@Component({
  selector: 'app-impersonation',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule, PaginationComponent],
  templateUrl: './impersonation.component.html',
})
export class ImpersonationComponent implements OnInit {
  private authService = inject(AuthService);
  private devService = inject(DeveloperService);
  private http = inject(HttpClient);
  private router = inject(Router);

  activeTab = signal<Tab>('administrativos');
  users = signal<UserListItem[]>([]);
  pagination = signal<Pagination | null>(null);
  loading = signal(false);
  impersonatingId = signal<string | null>(null);
  toast = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  searchTerm = '';

  ngOnInit(): void {
    this.load();
  }

  switchTab(tab: Tab): void {
    this.activeTab.set(tab);
    this.searchTerm = '';
    this.load(1);
  }

  load(page = 1): void {
    this.loading.set(true);
    const params: any = { page, per_page: 15 };
    if (this.searchTerm) params['search'] = this.searchTerm;

    const endpoint = this.activeTab() === 'estudiantes'
      ? `${environment.apiUrl}/usuarios/estudiantes`
      : `${environment.apiUrl}/usuarios/administrativos`;

    this.http.get<ApiResponse<{ items: UserListItem[]; pagination: Pagination }>>(
      endpoint, { params }
    ).pipe(map(r => r.data)).subscribe({
      next: data => {
        this.users.set(data.items);
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

  impersonate(user: UserListItem): void {
    if (!confirm(`¿Iniciar sesión como "${user.name}"?`)) return;
    this.impersonatingId.set(user.id);

    this.devService.impersonate(user.id).subscribe({
      next: result => {
        this.impersonatingId.set(null);
        this.authService.startImpersonation(result.token, result.user);
        this.showToast('success', `Sesión iniciada como ${result.user.name}`);
        setTimeout(() => this.router.navigate(['/app/home']), 1000);
      },
      error: () => {
        this.impersonatingId.set(null);
        this.showToast('error', 'Error al iniciar la impersonación');
      },
    });
  }

  private showToast(tipo: 'success' | 'error', texto: string): void {
    this.toast.set({ tipo, texto });
    setTimeout(() => this.toast.set(null), 4000);
  }
}
