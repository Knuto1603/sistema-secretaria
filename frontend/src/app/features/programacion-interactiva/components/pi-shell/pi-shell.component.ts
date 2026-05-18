import {
  Component, inject, signal, computed, OnInit, ChangeDetectionStrategy, effect
} from '@angular/core';
import { Router } from '@angular/router';
import { toObservable } from '@angular/core/rxjs-interop';
import { filter, switchMap } from 'rxjs';
import { ProgramacionInteractivaService, BorradorProgramacion } from '../../services/programacion-interactiva.service';
import { ProgramacionEstadoService } from '../../../programacion/services/programacion-estado.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-pi-shell',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './pi-shell.component.html',
})
export class PiShellComponent implements OnInit {
  private piService   = inject(ProgramacionInteractivaService);
  readonly estado     = inject(ProgramacionEstadoService);
  private router      = inject(Router);

  borradores          = signal<BorradorProgramacion[]>([]);
  loadingBorradores   = signal(false);
  generando           = signal(false);
  eliminando          = signal<string | null>(null);

  mostrarFormNuevo    = signal(false);
  nuevoNombre         = signal('');
  nuevoCicloTipo      = signal<'par' | 'impar'>('impar');

  // Separa borradores en preparación vs publicados
  readonly enPreparacion = computed(() =>
    this.borradores().filter(b => b.estado === 'borrador')
  );
  readonly publicados = computed(() =>
    this.borradores().filter(b => b.estado === 'publicado')
  );

  constructor() {
    // Reacciona a cambios de período desde el Shell
    toObservable(this.estado.periodoId).pipe(
      filter(id => !!id),
      switchMap(id => {
        this.loadingBorradores.set(true);
        return this.piService.listar(id);
      })
    ).subscribe({
      next: data => {
        this.borradores.set(data);
        this.loadingBorradores.set(false);
      },
      error: () => this.loadingBorradores.set(false),
    });
  }

  ngOnInit(): void {
    // Si el servicio ya tiene período (cargado por el Shell), no necesitamos hacer nada extra.
    // El effect del constructor ya dispara la carga.
  }

  abrirFormNuevo(): void {
    const p = this.estado.periodo();
    if (p) this.nuevoNombre.set(p.nombre);
    this.mostrarFormNuevo.set(true);
  }

  cancelarFormNuevo(): void {
    this.mostrarFormNuevo.set(false);
    this.nuevoNombre.set('');
    this.nuevoCicloTipo.set('impar');
  }

  generar(): void {
    const periodoId = this.estado.periodoId();
    if (!this.nuevoNombre().trim() || !periodoId) return;
    this.generando.set(true);
    this.piService.generar({
      periodo_id: periodoId,
      ciclo_tipo: this.nuevoCicloTipo(),
      nombre: this.nuevoNombre().trim(),
    }).subscribe({
      next: borrador => {
        this.generando.set(false);
        this.router.navigate(['/app/programacion/borradores', borrador.id]);
      },
      error: () => this.generando.set(false),
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
      error: () => this.eliminando.set(null),
    });
  }

  cicloTipoBadge(tipo: 'par' | 'impar'): 'indigo' | 'violet' {
    return tipo === 'par' ? 'indigo' : 'violet';
  }

  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('es-PE', {
      day: '2-digit', month: 'short', year: 'numeric',
    });
  }
}
