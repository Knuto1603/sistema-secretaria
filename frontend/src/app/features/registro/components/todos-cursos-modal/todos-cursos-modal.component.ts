import { Component, inject, signal, OnInit, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ProgramacionService, Programacion } from '../../services/programacion.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-todos-cursos-modal',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './todos-cursos-modal.component.html'
})
export class TodosCursosModalComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private router = inject(Router);

  cerrado = output<void>();

  todosCursos           = signal<Programacion[]>([]);
  todosCursosPagination = signal<{ current_page: number; last_page: number; per_page: number; total: number } | null>(null);
  todosCursosPage       = signal(1);
  todosCursosSearch     = signal('');
  todosCursosLoading    = signal(false);

  ngOnInit(): void {
    this.cargar();
  }

  cargar(page: number = this.todosCursosPage()): void {
    this.todosCursosLoading.set(true);
    this.todosCursosPage.set(page);
    this.programacionService
      .getTodosParaSolicitud(page, this.todosCursosSearch(), 15)
      .subscribe({
        next: res => {
          this.todosCursos.set(res.items);
          this.todosCursosPagination.set(res.pagination);
          this.todosCursosLoading.set(false);
        },
        error: () => this.todosCursosLoading.set(false),
      });
  }

  onSearch(value: string): void {
    this.todosCursosSearch.set(value);
    this.cargar(1);
  }

  cerrar(): void {
    this.cerrado.emit();
  }

  solicitarFueraDePlan(item: Programacion): void {
    this.cerrar();
    this.router.navigate(['app/solicitudes/nueva/', item.id], {
      queryParams: { fuera_de_plan: '1' },
    });
  }

  solicitarInscripcionEscuela(item: Programacion): void {
    this.cerrar();
    this.router.navigate(['app/solicitudes/nueva/', item.id], {
      queryParams: { inscripcion_escuela: '1' },
    });
  }
}
