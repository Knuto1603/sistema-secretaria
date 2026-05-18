import {
  Component, inject, signal, computed, OnInit, ChangeDetectionStrategy
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ProgramacionInteractivaService, BorradorProgramacion } from '../../services/programacion-interactiva.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

@Component({
  selector: 'app-pi-shell',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './pi-shell.component.html'
})
export class PiShellComponent implements OnInit {
  private piService   = inject(ProgramacionInteractivaService);
  private periodoService = inject(PeriodoService);
  private router      = inject(Router);

  periodos            = signal<Periodo[]>([]);
  periodoSeleccionado = signal<string>('');
  borradores          = signal<BorradorProgramacion[]>([]);

  loadingPeriodos  = signal(false);
  loadingBorradores = signal(false);
  generando        = signal(false);
  eliminando       = signal<string | null>(null);

  mostrarFormNuevo = signal(false);
  nuevoNombre      = signal('');
  nuevoCicloTipo   = signal<'par' | 'impar'>('impar');

  asignados = computed(() =>
    this.borradores().filter(b => b.estado === 'publicado').length
  );

  ngOnInit(): void {
    this.cargarPeriodos();
  }

  private cargarPeriodos(): void {
    this.loadingPeriodos.set(true);
    this.periodoService.getPeriodos().subscribe({
      next: periodos => {
        this.periodos.set(periodos);
        const activo = periodos.find(p => p.activo);
        if (activo) {
          this.periodoSeleccionado.set(activo.id);
          this.cargarBorradores();
        }
        this.loadingPeriodos.set(false);
      },
      error: () => this.loadingPeriodos.set(false)
    });
  }

  onSelectPeriodo(id: string): void {
    this.periodoSeleccionado.set(id);
    if (id) this.cargarBorradores();
  }

  cargarBorradores(): void {
    if (!this.periodoSeleccionado()) return;
    this.loadingBorradores.set(true);
    this.piService.listar(this.periodoSeleccionado()).subscribe({
      next: data => {
        this.borradores.set(data);
        this.loadingBorradores.set(false);
      },
      error: () => this.loadingBorradores.set(false)
    });
  }

  abrirFormNuevo(): void {
    const periodo = this.periodos().find(p => p.id === this.periodoSeleccionado());
    if (periodo) {
      this.nuevoNombre.set(periodo.nombre);
    }
    this.mostrarFormNuevo.set(true);
  }

  cancelarFormNuevo(): void {
    this.mostrarFormNuevo.set(false);
    this.nuevoNombre.set('');
    this.nuevoCicloTipo.set('impar');
  }

  generar(): void {
    if (!this.nuevoNombre().trim() || !this.periodoSeleccionado()) return;
    this.generando.set(true);
    this.piService.generar({
      periodo_id: this.periodoSeleccionado(),
      ciclo_tipo: this.nuevoCicloTipo(),
      nombre: this.nuevoNombre().trim()
    }).subscribe({
      next: borrador => {
        this.generando.set(false);
        this.router.navigate(['/app/programacion/borradores', borrador.id]);
      },
      error: () => this.generando.set(false)
    });
  }

  abrirEditor(id: string): void {
    this.router.navigate(['/app/programacion/borradores', id]);
  }

  eliminar(borrador: BorradorProgramacion, event: MouseEvent): void {
    event.stopPropagation();
    if (!confirm(`¿Eliminar el borrador "${borrador.nombre}"? Esta acción no se puede deshacer.`)) return;
    this.eliminando.set(borrador.id);
    this.piService.eliminar(borrador.id).subscribe({
      next: () => {
        this.borradores.update(list => list.filter(b => b.id !== borrador.id));
        this.eliminando.set(null);
      },
      error: () => this.eliminando.set(null)
    });
  }

  cicloTipoBadge(tipo: 'par' | 'impar'): 'indigo' | 'violet' {
    return tipo === 'par' ? 'indigo' : 'violet';
  }

  estadoBadge(estado: 'borrador' | 'publicado'): 'amber' | 'emerald' {
    return estado === 'borrador' ? 'amber' : 'emerald';
  }

  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('es-PE', {
      day: '2-digit', month: 'short', year: 'numeric'
    });
  }
}
