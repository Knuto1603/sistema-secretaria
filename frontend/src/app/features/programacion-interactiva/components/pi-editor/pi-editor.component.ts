import {
  Component, inject, signal, computed, OnInit,
  ChangeDetectionStrategy, DestroyRef
} from '@angular/core';
import { NgClass } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin, switchMap, tap, timer } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion, AutoAsignarResult } from '../../services/programacion-interactiva.service';
import { AulaService, Pabellon } from '../../../configuracion/services/aula.service';
import { HorarioService, GrupoHorario } from '../../../configuracion/services/horario.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';
import { PiListaComponent } from '../pi-lista/pi-lista.component';
import { PiMatrizComponent } from '../pi-matriz/pi-matriz.component';

type Vista = 'lista' | 'matriz';

@Component({
  selector: 'app-pi-editor',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    NgClass,
    AppButtonComponent,
    AppBadgeComponent,
    PiListaComponent,
    PiMatrizComponent
  ],
  templateUrl: './pi-editor.component.html'
})
export class PiEditorComponent implements OnInit {
  private piService      = inject(ProgramacionInteractivaService);
  private aulaService    = inject(AulaService);
  private horarioService = inject(HorarioService);
  private route          = inject(ActivatedRoute);
  private router         = inject(Router);
  private destroyRef     = inject(DestroyRef);

  private readonly borradorId = this.route.snapshot.paramMap.get('id');

  borrador   = signal<BorradorProgramacion | null>(null);
  pabellones = signal<Pabellon[]>([]);
  grupos     = signal<GrupoHorario[]>([]);

  loading            = signal(true);
  publicando         = signal(false);
  publicadoExito     = signal(false);
  autoAsignando      = signal(false);
  resultadoAutoAsign = signal<AutoAsignarResult | null>(null);
  errorAutoAsign     = signal(false);
  vistaActiva        = signal<Vista>('lista');

  totalSecciones      = computed(() => this.borrador()?.secciones?.length ?? 0);
  seccionesAsignadas  = computed(() => this.borrador()?.secciones?.filter(s => s.esta_asignado).length ?? 0);
  seccionesSinAsignar = computed(() => this.totalSecciones() - this.seccionesAsignadas());

  ngOnInit(): void {
    if (!this.borradorId) {
      this.router.navigate(['/app/programacion/borradores']);
      return;
    }
    this.cargar();
  }

  private cargar(): void {
    this.loading.set(true);

    forkJoin({
      borrador:   this.piService.obtener(this.borradorId!),
      pabellones: this.aulaService.getPabellones(),
      grupos:     this.horarioService.getGrupos(),
    }).pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: ({ borrador, pabellones, grupos }) => {
          this.borrador.set(borrador);
          this.pabellones.set(pabellones);
          this.grupos.set(grupos.filter(g => g.activo && g.detalles.length > 0));
          this.loading.set(false);
        },
        error: () => {
          this.loading.set(false);
          this.router.navigate(['/app/programacion/borradores']);
        }
      });
  }

  setVista(v: Vista): void {
    this.vistaActiva.set(v);
  }

  publicar(): void {
    if (!confirm('¿Publicar este borrador? Se crearán los registros en la Programación Académica y no podrá modificarse.')) return;
    this.publicando.set(true);

    this.piService.publicar(this.borradorId!).pipe(
      takeUntilDestroyed(this.destroyRef)
    ).subscribe({
      next: result => {
        this.borrador.update(b => b
          ? { ...b, estado: result.estado as 'borrador' | 'publicado', publicado_at: result.publicado_at }
          : b
        );
        this.publicando.set(false);
        this.publicadoExito.set(true);
        timer(4000).pipe(takeUntilDestroyed(this.destroyRef))
          .subscribe(() => this.publicadoExito.set(false));
      },
      error: (err) => {
        this.publicando.set(false);
        const msg = err?.error?.message ?? 'Error al publicar el borrador.';
        alert(msg);
      }
    });
  }

  volver(): void {
    this.router.navigate(['/app/programacion/borradores']);
  }

  autoAsignar(): void {
    if (!confirm('¿Ejecutar auto-asignación? Esto distribuirá automáticamente todas las secciones sin asignar en aulas y grupos disponibles.')) return;
    this.autoAsignando.set(true);
    this.resultadoAutoAsign.set(null);
    this.errorAutoAsign.set(false);

    this.piService.autoAsignar(this.borradorId!).pipe(
      tap(resultado => {
        this.resultadoAutoAsign.set(resultado);
        this.autoAsignando.set(false);
      }),
      switchMap(() => this.piService.obtener(this.borradorId!)),
      takeUntilDestroyed(this.destroyRef)
    ).subscribe({
      next: data => {
        this.borrador.set(data);
        timer(6000).pipe(takeUntilDestroyed(this.destroyRef))
          .subscribe(() => this.resultadoAutoAsign.set(null));
      },
      error: () => {
        this.autoAsignando.set(false);
        this.resultadoAutoAsign.set(null);
        this.errorAutoAsign.set(true);
        timer(5000).pipe(takeUntilDestroyed(this.destroyRef))
          .subscribe(() => this.errorAutoAsign.set(false));
      }
    });
  }

  onSeccionActualizada(seccion: BorradorSeccion): void {
    this.borrador.update(b => {
      if (!b?.secciones) return b;
      return { ...b, secciones: b.secciones.map(s => s.id === seccion.id ? seccion : s) };
    });
  }

  onSeccionEliminada(id: string): void {
    this.borrador.update(b => {
      if (!b?.secciones) return b;
      return { ...b, secciones: b.secciones.filter(s => s.id !== id) };
    });
  }

  onSeccionAgregada(seccion: BorradorSeccion): void {
    this.borrador.update(b => {
      if (!b) return b;
      return { ...b, secciones: [...(b.secciones ?? []), seccion] };
    });
  }

  onSeccionMovida(seccion: BorradorSeccion): void {
    this.borrador.update(b => {
      if (!b?.secciones) return b;
      return { ...b, secciones: b.secciones.map(s => s.id === seccion.id ? seccion : s) };
    });
  }

  estadoBadge(estado: 'borrador' | 'publicado'): 'amber' | 'emerald' {
    return estado === 'borrador' ? 'amber' : 'emerald';
  }
}
