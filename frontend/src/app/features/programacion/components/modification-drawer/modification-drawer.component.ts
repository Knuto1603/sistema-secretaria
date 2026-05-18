import { Component, input, output, signal, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';

import { Programacion, ProgramacionService } from '../../../registro/services/programacion.service';
import { AulaService, Pabellon } from '../../../configuracion/services/aula.service';
import { HorarioService, GrupoHorario } from '../../../configuracion/services/horario.service';
import { ModificacionService } from '../../services/modificacion.service';

export type TipoAccion = 'cerrar' | 'abrir_seccion' | 'cambio_aula' | 'cambio_grupo' | 'unificar';

interface OpcionAccion {
  id: TipoAccion;
  label: string;
  descripcion: string;
  color: string;
  icono: string;
}

const OPCIONES: OpcionAccion[] = [
  {
    id: 'cerrar',
    label: 'Cerrar curso',
    descripcion: 'Marca el curso como lleno (sin nuevas inscripciones)',
    color: 'red',
    icono: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
  },
  {
    id: 'abrir_seccion',
    label: 'Abrir nueva sección',
    descripcion: 'Crea una sección adicional del mismo curso',
    color: 'emerald',
    icono: 'M12 6v6m0 0v6m0-6h6m-6 0H6'
  },
  {
    id: 'cambio_aula',
    label: 'Cambiar aula',
    descripcion: 'Asigna una aula diferente a esta sección',
    color: 'blue',
    icono: 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10'
  },
  {
    id: 'cambio_grupo',
    label: 'Cambiar horario',
    descripcion: 'Asigna un grupo horario diferente',
    color: 'violet',
    icono: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  {
    id: 'unificar',
    label: 'Unificar secciones',
    descripcion: 'Absorbe alumnos de otras secciones del mismo curso',
    color: 'amber',
    icono: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'
  },
];

@Component({
  selector: 'app-modification-drawer',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './modification-drawer.component.html'
})
export class ModificationDrawerComponent implements OnInit {
  private modifService   = inject(ModificacionService);
  private aulaService    = inject(AulaService);
  private horarioService = inject(HorarioService);
  private progService    = inject(ProgramacionService);

  programacion = input.required<Programacion>();
  periodoId    = input.required<string>();

  cerrado    = output<void>();
  modificado = output<void>();

  // Navegación interna
  accionSeleccionada = signal<TipoAccion | null>(null);
  opciones = OPCIONES;

  // Estado de carga y feedback
  loadingDatos = signal(true);
  loading      = signal(false);
  error        = signal<string | null>(null);
  exito        = signal(false);

  // Datos maestros
  pabellones = signal<Pabellon[]>([]);
  grupos     = signal<GrupoHorario[]>([]);
  secciones  = signal<Programacion[]>([]); // Para unificar: secciones del mismo curso

  // Campos del formulario
  aulaId    = signal('');
  grupoId   = signal('');
  capacidad = signal<number | null>(null);
  motivo    = signal('');
  origenIds = signal<string[]>([]);

  // Computed
  accionLabel = computed(() =>
    OPCIONES.find(o => o.id === this.accionSeleccionada())?.label ?? ''
  );

  submitValido = computed(() => {
    if (this.motivo().trim().length < 5) return false;
    const accion = this.accionSeleccionada();
    if (accion === 'cambio_aula')  return !!this.aulaId();
    if (accion === 'cambio_grupo') return !!this.grupoId();
    if (accion === 'unificar')     return this.origenIds().length > 0;
    return true;
  });

  ngOnInit(): void {
    forkJoin({
      pabellones: this.aulaService.getPabellones(),
      grupos: this.horarioService.getGrupos(),
    }).subscribe({
      next: ({ pabellones, grupos }) => {
        this.pabellones.set(pabellones);
        this.grupos.set(grupos.filter(g => g.activo && g.detalles.length > 0));
        // Pre-seleccionar valores actuales
        if (this.programacion().aula_id)          this.aulaId.set(this.programacion().aula_id!);
        if (this.programacion().grupo_horario_id)  this.grupoId.set(this.programacion().grupo_horario_id!);
        this.capacidad.set(this.programacion().capacidad ?? null);
        this.loadingDatos.set(false);
      },
      error: () => this.loadingDatos.set(false),
    });
  }

  seleccionarAccion(accion: TipoAccion): void {
    this.accionSeleccionada.set(accion);
    this.error.set(null);
    this.motivo.set('');
    this.origenIds.set([]);

    if (accion === 'unificar') {
      this.cargarSecciones();
    }
  }

  private cargarSecciones(): void {
    const cursoId = this.programacion().curso?.id;
    if (!cursoId) return;

    this.progService.getProgramacion(1, '', 200, this.periodoId()).subscribe({
      next: res => {
        this.secciones.set(
          res.data.filter(p => p.id !== this.programacion().id && p.curso?.id === cursoId)
        );
      },
      error: () => {},
    });
  }

  toggleOrigen(id: string): void {
    this.origenIds.update(ids =>
      ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id]
    );
  }

  confirmar(): void {
    if (!this.submitValido() || this.loading()) return;
    this.loading.set(true);
    this.error.set(null);

    const prog   = this.programacion();
    const motivo = this.motivo().trim();
    const accion = this.accionSeleccionada()!;

    const llamadas: Record<TipoAccion, () => ReturnType<ModificacionService[keyof ModificacionService]>> = {
      cerrar:        () => this.modifService.cerrar(prog.id, motivo),
      abrir_seccion: () => this.modifService.abrirSeccion({
        programacion_id: prog.id,
        aula_id: this.aulaId() || null,
        grupo_horario_id: this.grupoId() || null,
        capacidad: this.capacidad(),
        motivo,
      }),
      cambio_aula:  () => this.modifService.cambiarAula(prog.id, { aula_id: this.aulaId(), motivo }),
      cambio_grupo: () => this.modifService.cambiarGrupo(prog.id, { grupo_horario_id: this.grupoId(), motivo }),
      unificar:     () => this.modifService.unificar({
        periodo_id: this.periodoId(),
        programacion_destino_id: prog.id,
        secciones_origen_ids: this.origenIds(),
        motivo,
      }),
    };

    (llamadas[accion]() as ReturnType<typeof this.modifService.cerrar>).subscribe({
      next: () => {
        this.loading.set(false);
        this.exito.set(true);
        this.modificado.emit();
        setTimeout(() => this.cerrado.emit(), 1800);
      },
      error: (err: any) => {
        this.error.set(err.error?.message || 'Error al procesar la modificación.');
        this.loading.set(false);
      },
    });
  }

  volver(): void {
    this.accionSeleccionada.set(null);
    this.error.set(null);
  }

  cerrarDrawer(): void {
    this.cerrado.emit();
  }
}
