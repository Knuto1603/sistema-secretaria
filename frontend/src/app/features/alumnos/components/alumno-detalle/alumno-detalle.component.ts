import { Component, inject, input, output, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  UsuarioService,
  Estudiante,
  EstudianteHistorial,
  EstudianteInscripcion,
} from '@core/services/usuario.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

type TabDetalle = 'info' | 'historial' | 'inscripciones';

@Component({
  selector: 'app-alumno-detalle',
  standalone: true,
  imports: [CommonModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './alumno-detalle.component.html',
})
export class AlumnoDetalleComponent implements OnInit {
  private service = inject(UsuarioService);

  id      = input.required<string>();
  cerrado = output<void>();

  alumno        = signal<Estudiante | null>(null);
  historial     = signal<EstudianteHistorial | null>(null);
  inscripciones = signal<EstudianteInscripcion[]>([]);

  loading             = signal(true);
  loadingHistorial    = signal(false);
  loadingInscripciones = signal(false);
  error               = signal<string | null>(null);

  tabActiva = signal<TabDetalle>('info');

  ngOnInit(): void {
    this.service.getEstudianteById(this.id()).subscribe({
      next:  a => { this.alumno.set(a); this.loading.set(false); },
      error: () => { this.error.set('Error al cargar el alumno'); this.loading.set(false); },
    });
  }

  cambiarTab(tab: TabDetalle): void {
    this.tabActiva.set(tab);
    if (tab === 'historial' && !this.historial()) this.cargarHistorial();
    if (tab === 'inscripciones' && !this.inscripciones().length) this.cargarInscripciones();
  }

  cargarHistorial(): void {
    this.loadingHistorial.set(true);
    this.service.getEstudianteHistorial(this.id()).subscribe({
      next:  h => { this.historial.set(h); this.loadingHistorial.set(false); },
      error: () => this.loadingHistorial.set(false),
    });
  }

  cargarInscripciones(): void {
    this.loadingInscripciones.set(true);
    this.service.getEstudianteInscripciones(this.id()).subscribe({
      next:  i => { this.inscripciones.set(i); this.loadingInscripciones.set(false); },
      error: () => this.loadingInscripciones.set(false),
    });
  }

  getNotaColor(nota: number | null): string {
    if (nota === null) return 'text-slate-400';
    if (nota >= 14)    return 'text-emerald-600 font-bold';
    if (nota >= 11)    return 'text-amber-600 font-bold';
    return 'text-red-600 font-bold';
  }

  totalCreditos = computed(() => {
    const h = this.historial();
    if (!h) return 0;
    const todos = [
      ...h.por_semestre.flatMap(s => s.cursos),
      ...h.sin_semestre,
    ];
    return todos.reduce((acc, c) => acc + (c.creditos ?? 0), 0);
  });
}
