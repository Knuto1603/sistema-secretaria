import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { UsuarioService, Usuario, UsuarioFilters } from '@core/services/usuario.service';
import { RolService, Rol } from '@core/services/rol.service';
import { AppTableComponent, TableColumn } from '@shared/table/table.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { AppButtonComponent } from '@shared/button/button.component';
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
  selector: 'app-administrativos-lista',
  standalone: true,
  imports: [CommonModule, FormsModule, AppTableComponent, AppBadgeComponent, AppButtonComponent, PaginationComponent],
  templateUrl: './administrativos-lista.component.html'
})
export class AdministrativosListaComponent implements OnInit {
  private usuarioService = inject(UsuarioService);
  private rolService = inject(RolService);
  private router = inject(Router);

  usuarios = signal<Usuario[]>([]);
  roles = signal<Rol[]>([]);
  loading = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Filtros
  search = '';
  rolFilter = '';
  activoFilter: string = '';

  // Paginación
  pagination: Pagination = {
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0,
    from: 0,
    to: 0
  };

  columnas: TableColumn[] = [
    { key: 'name', label: 'Nombre' },
    { key: 'username', label: 'Usuario' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Roles' },
    { key: 'activo', label: 'Estado' }
  ];

  ngOnInit(): void {
    this.cargarRoles();
    this.cargarDatos();
  }

  cargarRoles(): void {
    this.rolService.getAll().subscribe({
      next: (roles) => this.roles.set(roles),
      error: () => console.error('Error cargando roles')
    });
  }

  cargarDatos(): void {
    this.loading.set(true);

    const filters: UsuarioFilters = {
      search: this.search || undefined,
      rol: this.rolFilter || undefined,
      activo: this.activoFilter !== '' ? this.activoFilter === 'true' : undefined,
      per_page: this.pagination.perPage,
      page: this.pagination.currentPage
    };

    this.usuarioService.getAdministrativos(filters).subscribe({
      next: (response) => {
        this.usuarios.set(response.items);
        const pag = response.pagination;
        this.pagination = {
          currentPage: pag.current_page,
          lastPage: pag.last_page,
          perPage: pag.per_page,
          total: pag.total,
          from: (pag.current_page - 1) * pag.per_page + 1,
          to: Math.min(pag.current_page * pag.per_page, pag.total)
        };
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar los usuarios');
        this.loading.set(false);
      }
    });
  }

  buscar(): void {
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  limpiarFiltros(): void {
    this.search = '';
    this.rolFilter = '';
    this.activoFilter = '';
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  nuevoUsuario(): void {
    this.router.navigate(['/app/configuracion/usuarios/nuevo']);
  }

  editarUsuario(usuario: Usuario): void {
    this.router.navigate(['/app/configuracion/usuarios/editar', usuario.id]);
  }

  toggleActivo(usuario: Usuario): void {
    if (usuario.tipo_usuario === 'developer') {
      this.mostrarMensaje('error', 'No se puede cambiar el estado de un usuario developer');
      return;
    }

    this.usuarioService.toggleAdministrativo(usuario.id, !usuario.activo).subscribe({
      next: (updated) => {
        this.usuarios.update(usuarios =>
          usuarios.map(u => u.id === updated.id ? updated : u)
        );
        this.mostrarMensaje('success', `Usuario ${updated.activo ? 'activado' : 'desactivado'} correctamente`);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cambiar el estado');
      }
    });
  }

  eliminarUsuario(usuario: Usuario): void {
    if (usuario.tipo_usuario === 'developer') {
      this.mostrarMensaje('error', 'No se puede eliminar un usuario developer');
      return;
    }

    if (!confirm(`¿Estás seguro de eliminar a "${usuario.name}"?`)) return;

    this.usuarioService.deleteAdministrativo(usuario.id).subscribe({
      next: () => {
        this.usuarios.update(usuarios => usuarios.filter(u => u.id !== usuario.id));
        this.mostrarMensaje('success', 'Usuario eliminado');
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al eliminar el usuario');
      }
    });
  }

  onPageChange(page: number): void {
    this.pagination.currentPage = page;
    this.cargarDatos();
  }

  onPageSizeChange(size: number): void {
    this.pagination.perPage = size;
    this.pagination.currentPage = 1;
    this.cargarDatos();
  }

  getRolBadgeColor(rol: string): 'indigo' | 'emerald' | 'amber' | 'red' | 'slate' | 'cyan' | 'purple' {
    const colors: Record<string, 'indigo' | 'emerald' | 'amber' | 'red' | 'slate' | 'cyan' | 'purple'> = {
      'admin': 'indigo',
      'developer': 'purple',
      'secretaria': 'emerald',
      'decano': 'amber',
      'secretario academico': 'cyan',
      'estudiante': 'slate'
    };
    return colors[rol.toLowerCase()] || 'slate';
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
