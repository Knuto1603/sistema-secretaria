import {
  Component, inject, signal, computed, OnInit, ChangeDetectionStrategy
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { ProgramacionInteractivaService, BorradorProgramacion, BorradorSeccion } from '../../services/programacion-interactiva.service';
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
    CommonModule,
    AppButtonComponent,
    AppBadgeComponent,
    PiListaComponent,
    PiMatrizComponent
  ],
  templateUrl: './pi-editor.component.html'
})
export class PiEditorComponent implements OnInit {
  private piService     = inject(ProgramacionInteractivaService);
  private aulaService   = inject(AulaService);
  private horarioService = inject(HorarioService);
  private route         = inject(ActivatedRoute);
  private router        = inject(Router);

  borrador    = signal<BorradorProgramacion | null>(null);
  pabellones  = signal<Pabellon[]>([]);
  grupos      = signal<GrupoHorario[]>([]);

  loading          = signal(true);
  publicando       = signal(false);
  publicadoExito   = signal(false);
  vistaActiva      = signal<Vista>('lista');

  get borradorId(): string { return this.route.snapshot.paramMap.get('id') ?? ''; }

  totalSecciones  = computed(() => this.borrador()?.secciones?.length ?? 0);
  seccionesAsignadas = computed(() =>
    this.borrador()?.secciones?.filter(s => s.esta_asignado).length ?? 0
  );
  seccionesSinAsignar = computed(() => this.totalSecciones() - this.seccionesAsignadas());

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.loading.set(true);
    const id = this.borradorId;

    Promise.all([
      new Promise<void>((resolve) => {
        this.piService.obtener(id).subscribe({
          next: data => { this.borrador.set(data); resolve(); },
          error: () => { this.router.navigate(['/app/programacion-interactiva']); resolve(); }
        });
      }),
      new Promise<void>((resolve) => {
        this.aulaService.getPabellones().subscribe({
          next: data => { this.pabellones.set(data); resolve(); },
          error: () => resolve()
        });
      }),
      new Promise<void>((resolve) => {
        this.horarioService.getGrupos().subscribe({
          next: data => { this.grupos.set(data.filter(g => g.activo && g.detalles.length > 0)); resolve(); },
          error: () => resolve()
        });
      })
    ]).then(() => this.loading.set(false));
  }

  setVista(v: Vista): void {
    this.vistaActiva.set(v);
  }

  publicar(): void {
    if (!confirm('¿Publicar este borrador? Se crearán los registros en la Programación Académica y no podrá modificarse.')) return;
    this.publicando.set(true);
    this.piService.publicar(this.borradorId).subscribe({
      next: result => {
        this.borrador.update(b => b ? { ...b, estado: result.estado as 'borrador' | 'publicado', publicado_at: result.publicado_at } : b);
        this.publicando.set(false);
        this.publicadoExito.set(true);
        setTimeout(() => this.publicadoExito.set(false), 4000);
      },
      error: () => this.publicando.set(false)
    });
  }

  volver(): void {
    this.router.navigate(['/app/programacion-interactiva']);
  }

  onSeccionActualizada(seccion: BorradorSeccion): void {
    this.borrador.update(b => {
      if (!b || !b.secciones) return b;
      return {
        ...b,
        secciones: b.secciones.map(s => s.id === seccion.id ? seccion : s)
      };
    });
  }

  onSeccionEliminada(id: string): void {
    this.borrador.update(b => {
      if (!b || !b.secciones) return b;
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
