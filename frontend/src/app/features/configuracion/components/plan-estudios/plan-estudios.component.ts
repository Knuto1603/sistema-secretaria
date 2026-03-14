import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  PlanEstudiosService,
  PlanEstudios,
  PlanVersion,
  CursoPlan,
  CursoEquivalencia,
  ImportPlanResumen,
  ImportPlanFila,
  ImportPdfResumen,
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
  planes = signal<PlanVersion[]>([]);
  loading = signal(false);
  loadingPlanes = signal(false);
  importando = signal(false);
  eliminando = signal(false);
  activando = signal<string | null>(null);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  importResultado = signal<{ resumen: ImportPlanResumen; resultados: ImportPlanFila[] } | null>(null);
  importPdfResultado = signal<ImportPdfResumen | null>(null);
  confirmarLimpiar = signal(false);

  // Nuevo plan
  mostrarFormNuevoPlan = signal(false);
  nuevoPlanNombre = '';
  nuevoPlanCredO = 0;
  nuevoPlanCredE = 0;
  creandoPlan = signal(false);

  // Equivalencias
  cursoEquivalencias = signal<{ curso: { id: string; codigo: string; nombre: string }; equivalencias: CursoEquivalencia[] } | null>(null);
  mostrarModalEquivalencias = signal(false);

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
    this.cargarPlanes();
  }

  volver(): void {
    this.router.navigate(['/app/configuracion']);
  }

  onEscuelaChange(): void {
    this.cicloFiltro.set(null);
    this.planes.set([]);
    this.cargarPlan();
    this.cargarPlanes();
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

  cargarPlanes(): void {
    this.loadingPlanes.set(true);
    this.service.getPlanes(this.escuelaSeleccionada()).subscribe({
      next: (data) => {
        this.planes.set(data.planes);
        this.loadingPlanes.set(false);
      },
      error: () => this.loadingPlanes.set(false),
    });
  }

  activarPlan(planId: string): void {
    this.activando.set(planId);
    this.service.activarPlan(planId).subscribe({
      next: () => {
        this.mostrarMensaje('success', 'Plan activado correctamente');
        this.activando.set(null);
        this.cargarPlan();
        this.cargarPlanes();
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al activar el plan');
        this.activando.set(null);
      },
    });
  }

  abrirFormNuevoPlan(): void {
    this.nuevoPlanNombre = '';
    this.nuevoPlanCredO = 0;
    this.nuevoPlanCredE = 0;
    this.mostrarFormNuevoPlan.set(true);
  }

  cerrarFormNuevoPlan(): void {
    this.mostrarFormNuevoPlan.set(false);
  }

  crearPlan(): void {
    if (!this.nuevoPlanNombre.trim()) return;
    this.creandoPlan.set(true);
    this.service.crearPlan(this.escuelaSeleccionada(), this.nuevoPlanNombre, this.nuevoPlanCredO, this.nuevoPlanCredE).subscribe({
      next: (plan) => {
        this.mostrarMensaje('success', `Plan "${plan.nombre}" creado`);
        this.creandoPlan.set(false);
        this.cerrarFormNuevoPlan();
        this.cargarPlanes();
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al crear el plan');
        this.creandoPlan.set(false);
      },
    });
  }

  eliminarPlan(planId: string): void {
    if (!confirm('¿Eliminar este plan? Esta acción no se puede deshacer.')) return;
    this.service.eliminarPlan(planId).subscribe({
      next: () => {
        this.mostrarMensaje('success', 'Plan eliminado');
        this.cargarPlanes();
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al eliminar el plan');
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
    this.importPdfResultado.set(null);

    const planId = this.plan()?.plan?.id;

    this.service.importar(this.escuelaSeleccionada(), archivo, planId).subscribe({
      next: (resultado) => {
        this.importResultado.set(resultado);
        this.importando.set(false);
        if (resultado.resumen.importados > 0) {
          this.cargarPlan();
          this.cargarPlanes();
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

  onArchivoPdfSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.importando.set(true);
    this.importResultado.set(null);
    this.importPdfResultado.set(null);

    const planId = this.plan()?.plan?.id;

    this.service.importarPdf(this.escuelaSeleccionada(), archivo, planId).subscribe({
      next: (resultado) => {
        this.importPdfResultado.set(resultado);
        this.importando.set(false);
        this.cargarPlan();
        this.cargarPlanes();
        input.value = '';
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al procesar el PDF');
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

    const planId = this.plan()?.plan?.id;

    this.service.limpiar(this.escuelaSeleccionada(), planId).subscribe({
      next: (res) => {
        this.mostrarMensaje('success', `Plan eliminado: ${res.eliminados} cursos removidos`);
        this.eliminando.set(false);
        this.cargarPlan();
        this.cargarPlanes();
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al eliminar el plan');
        this.eliminando.set(false);
      },
    });
  }

  // ── Equivalencias ────────────────────────────────────────────────────────

  abrirEquivalencias(curso: CursoPlan): void {
    this.cursoEquivalencias.set(null);
    this.mostrarModalEquivalencias.set(true);
    this.service.getEquivalencias(curso.curso_id).subscribe({
      next: (data) => this.cursoEquivalencias.set(data),
      error: () => this.mostrarMensaje('error', 'Error al cargar equivalencias'),
    });
  }

  cerrarModalEquivalencias(): void {
    this.mostrarModalEquivalencias.set(false);
    this.cursoEquivalencias.set(null);
  }

  eliminarEquivalencia(equivalenteId: string): void {
    const cursoId = this.cursoEquivalencias()?.curso.id;
    if (!cursoId) return;
    this.service.eliminarEquivalencia(cursoId, equivalenteId).subscribe({
      next: () => {
        this.service.getEquivalencias(cursoId).subscribe(data => this.cursoEquivalencias.set(data));
      },
      error: (err) => this.mostrarMensaje('error', err.error?.message || 'Error al eliminar equivalencia'),
    });
  }

  cerrarImportResultado(): void {
    this.importResultado.set(null);
  }

  cerrarImportPdfResultado(): void {
    this.importPdfResultado.set(null);
  }

  getNombreEscuela(): string {
    return this.escuelas.find(e => e.codigo === this.escuelaSeleccionada())?.nombre ?? '';
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
