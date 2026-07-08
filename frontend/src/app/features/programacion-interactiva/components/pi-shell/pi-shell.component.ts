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
  revirtiendo         = signal<string | null>(null);

  // 'selector' muestra las dos opciones; 'generar'/'cargar' muestran el formulario respectivo
  modoFormulario      = signal<null | 'selector' | 'generar' | 'cargar'>(null);

  // Formulario "Generar"
  nuevoNombre         = signal('');
  nuevoCicloTipo      = signal<'par' | 'impar'>('impar');

  // Formulario "Cargar desde archivo"
  cargarNombre        = signal('');
  cargarCicloTipo     = signal<'par' | 'impar'>('impar');
  archivoMatriz       = signal<File | null>(null);
  cargando            = signal(false);
  resumenCarga        = signal<{ importados: number; omitidos: number; detalle: any[] } | null>(null);

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
    if (p) {
      this.nuevoNombre.set(p.nombre);
      this.cargarNombre.set(p.nombre);
    }
    this.modoFormulario.set('selector');
  }

  cancelarFormNuevo(): void {
    this.modoFormulario.set(null);
    this.nuevoNombre.set('');
    this.nuevoCicloTipo.set('impar');
    this.cargarNombre.set('');
    this.cargarCicloTipo.set('impar');
    this.archivoMatriz.set(null);
    this.resumenCarga.set(null);
  }

  seleccionarArchivo(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.archivoMatriz.set(input.files?.[0] ?? null);
    this.resumenCarga.set(null);
  }

  cargarDesdeMatriz(): void {
    const periodoId = this.estado.periodoId();
    const archivo   = this.archivoMatriz();
    if (!this.cargarNombre().trim() || !periodoId || !archivo) return;

    this.cargando.set(true);
    this.resumenCarga.set(null);

    this.piService.importarMatriz({
      file:       archivo,
      periodo_id: periodoId,
      nombre:     this.cargarNombre().trim(),
      ciclo_tipo: this.cargarCicloTipo(),
    }).subscribe({
      next: ({ borrador, resumen }) => {
        this.cargando.set(false);
        this.borradores.update(list => [borrador, ...list]);
        this.resumenCarga.set(resumen);

        if (resumen.omitidos === 0) {
          this.cancelarFormNuevo();
          this.router.navigate(['/app/programacion/borradores', borrador.id]);
        }
      },
      error: () => this.cargando.set(false),
    });
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
    const advertencia = borrador.estado === 'publicado'
      ? `¿Eliminar el borrador publicado "${borrador.nombre}"?\n\nSe eliminarán también todos los registros de programación académica que generó. Esta acción no se puede deshacer.`
      : `¿Eliminar el borrador "${borrador.nombre}"? Esta acción no se puede deshacer.`;
    if (!confirm(advertencia)) return;
    this.eliminando.set(borrador.id);
    this.piService.eliminar(borrador.id).subscribe({
      next: () => {
        this.borradores.update(list => list.filter(b => b.id !== borrador.id));
        this.eliminando.set(null);
      },
      error: () => this.eliminando.set(null),
    });
  }

  revertir(borrador: BorradorProgramacion, event: MouseEvent): void {
    event.stopPropagation();
    if (!confirm(`¿Regresar "${borrador.nombre}" a borrador?\n\nSe eliminarán los registros de programación académica generados al publicar. Esta acción no se puede deshacer.`)) return;
    this.revirtiendo.set(borrador.id);
    this.piService.revertir(borrador.id).subscribe({
      next: () => {
        this.borradores.update(list =>
          list.map(b => b.id === borrador.id
            ? { ...b, estado: 'borrador' as const, publicado_por: undefined, publicado_at: undefined }
            : b
          )
        );
        this.revirtiendo.set(null);
      },
      error: () => this.revirtiendo.set(null),
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
