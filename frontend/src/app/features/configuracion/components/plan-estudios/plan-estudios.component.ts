import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  PlanEstudiosService,
  PlanEstudios,
  CursoPlan,
  ImportPlanResumen,
  ImportPlanFila,
  ESCUELAS,
} from '@core/services/plan-estudios.service';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-plan-estudios',
  standalone: true,
  imports: [CommonModule, FormsModule, AppBadgeComponent],
  templateUrl: './plan-estudios.component.html',
})
export class PlanEstudiosComponent implements OnInit {
  private service = inject(PlanEstudiosService);
  private router = inject(Router);

  readonly escuelas = ESCUELAS;

  escuelaSeleccionada = signal('0');
  plan = signal<PlanEstudios | null>(null);
  loading = signal(false);
  importando = signal(false);
  eliminando = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  importResultado = signal<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] } | null>(null);
  confirmarLimpiar = signal(false);

  // Filtros
  cicloFiltro = signal<number | null>(null);
  tipoFiltro = signal<'O' | 'E' | null>(null);

  get cursosFiltrados(): CursoPlan[] {
    let cursos = this.plan()?.cursos ?? [];
    const ciclo = this.cicloFiltro();
    const tipo = this.tipoFiltro();
    if (ciclo) cursos = cursos.filter(c => c.ciclo === ciclo);
    if (tipo) cursos = cursos.filter(c => c.tipo === tipo);
    return cursos;
  }

  get ciclosDisponibles(): number[] {
    const ciclos = new Set(
      (this.plan()?.cursos ?? [])
        .map(c => c.ciclo)
        .filter((c): c is number => c !== null)
    );
    return Array.from(ciclos).sort((a, b) => a - b);
  }

  ngOnInit(): void {
    this.cargarPlan();
  }

  volver(): void {
    this.router.navigate(['/app/configuracion']);
  }

  onEscuelaChange(): void {
    this.cicloFiltro.set(null);
    this.cargarPlan();
  }

  cargarPlan(): void {
    this.loading.set(true);
    this.plan.set(null);

    this.service.getPlan(this.escuelaSeleccionada()).subscribe({
      next: (data) => {
        this.plan.set(data);
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar el plan de estudios');
        this.loading.set(false);
      },
    });
  }

  descargarPlantilla(): void {
    this.service.descargarPlantilla();
  }

  onArchivoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.importando.set(true);
    this.importResultado.set(null);

    this.service.importar(this.escuelaSeleccionada(), archivo).subscribe({
      next: (resultado) => {
        this.importResultado.set(resultado);
        this.importando.set(false);
        if (resultado.resumen.importados > 0) {
          this.cargarPlan();
        }
        input.value = '';
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al importar el archivo');
        this.importando.set(false);
        input.value = '';
      },
    });
  }

  pedirConfirmacionLimpiar(): void {
    this.confirmarLimpiar.set(true);
  }

  cancelarLimpiar(): void {
    this.confirmarLimpiar.set(false);
  }

  limpiarPlan(): void {
    this.eliminando.set(true);
    this.confirmarLimpiar.set(false);

    this.service.limpiar(this.escuelaSeleccionada()).subscribe({
      next: (res) => {
        this.mostrarMensaje('success', `Plan eliminado: ${res.eliminados} cursos removidos`);
        this.eliminando.set(false);
        this.cargarPlan();
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al eliminar el plan');
        this.eliminando.set(false);
      },
    });
  }

  cerrarImportResultado(): void {
    this.importResultado.set(null);
  }

  getNombreEscuela(): string {
    return this.escuelas.find(e => e.codigo === this.escuelaSeleccionada())?.nombre ?? '';
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
