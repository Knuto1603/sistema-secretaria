import { Component, inject, OnInit, signal, output, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { ProgramacionService, Programacion } from '../../services/programacion.service';
import { HorarioService, GrupoHorario } from '../../../configuracion/services/horario.service';
import { AulaService, Pabellon } from '../../../configuracion/services/aula.service';
import { AppButtonComponent } from '@shared/button/button.component';

interface Escuela {
  id: string;
  nombre: string;
  nombre_corto: string | null;
}

interface Docente {
  id: string;
  nombre_completo: string;
}

@Component({
  selector: 'app-programacion-edit-form',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './programacion-edit-form.component.html',
})
export class ProgramacionEditFormComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private horarioService      = inject(HorarioService);
  private aulaService         = inject(AulaService);
  private http                = inject(HttpClient);

  programacion = input.required<Programacion>();
  cerrado      = output<void>();
  guardado     = output<Programacion>();

  // Estado
  loadingDatos = signal(true);
  guardando    = signal(false);
  error        = signal<string | null>(null);

  // Datos
  grupos    = signal<GrupoHorario[]>([]);
  pabellones = signal<Pabellon[]>([]);
  escuelas   = signal<Escuela[]>([]);

  // Campos editables
  grupoHorarioId        = signal<string>('');
  aulaId                = signal<string>('');
  capacidad             = signal<number>(30);
  seccion               = signal<string>('');
  escuelasSeleccionadas = signal<Set<string>>(new Set());
  escuelaProgramadaId   = signal<string>('');

  // Búsqueda de docente
  docenteBusqueda  = '';
  docenteResultados = signal<Docente[]>([]);
  docenteId        = signal<string | null>(null);
  docenteNombre    = signal<string>('');
  showDocenteDropdown = signal(false);

  // Valores de texto actuales (para mostrar cuando FK es null)
  grupoTextoActual  = '';
  aulaTextoActual   = '';

  ngOnInit(): void {
    const prog = this.programacion();

    // Pre-cargar valores actuales
    this.grupoHorarioId.set(prog.grupo_horario_id ?? '');
    this.aulaId.set(prog.aula_id ?? '');
    this.capacidad.set(prog.capacidad ?? 30);
    this.seccion.set(prog.seccion ?? '');
    this.docenteId.set(prog.docente_id ?? prog.docente?.id ?? null);
    this.docenteBusqueda = prog.docente?.nombre_completo ?? '';
    this.docenteNombre.set(prog.docente?.nombre_completo ?? '');
    this.escuelaProgramadaId.set(prog.escuela_programada?.id ?? '');

    // Guardar textos actuales para mostrar como referencia cuando FK es null
    this.grupoTextoActual = prog.grupo || '';
    this.aulaTextoActual  = prog.aula_nombre || prog.aula || prog.aula_rel?.nombre || '';

    forkJoin({
      grupos:    this.horarioService.getGrupos(),
      pabellones: this.aulaService.getPabellones(),
      escuelas:  this.http.get<{ success: boolean; data: Escuela[] }>(`${environment.apiUrl}/escuelas`).pipe(map(r => r.data)),
      detalle:   this.programacionService.getDetalleProgramacion(prog.id),
    }).subscribe({
      next: ({ grupos, pabellones, escuelas, detalle }) => {
        this.grupos.set(grupos.filter(g => g.activo));
        this.pabellones.set(pabellones);
        this.escuelas.set(escuelas);

        // Pre-seleccionar escuelas del detalle
        const sel = new Set<string>(detalle.escuelas?.map(e => e.id) ?? []);
        this.escuelasSeleccionadas.set(sel);

        this.loadingDatos.set(false);
      },
      error: () => {
        this.error.set('Error al cargar datos');
        this.loadingDatos.set(false);
      },
    });
  }

  toggleEscuela(id: string): void {
    const sel = new Set(this.escuelasSeleccionadas());
    if (sel.has(id)) sel.delete(id); else sel.add(id);
    this.escuelasSeleccionadas.set(sel);
  }

  onAulaChange(aulaId: string): void {
    this.aulaId.set(aulaId);
    if (aulaId) {
      const aula = this.pabellones().flatMap(p => p.aulas).find(a => a.id === aulaId);
      if (aula) this.capacidad.set(aula.capacidad);
    }
  }

  onDocenteBusqueda(): void {
    const q = this.docenteBusqueda.trim();
    if (!q || q.length < 2) {
      this.docenteResultados.set([]);
      this.showDocenteDropdown.set(false);
      return;
    }
    this.http
      .get<{ success: boolean; data: Docente[] }>(
        `${environment.apiUrl}/docentes?search=${encodeURIComponent(q)}`
      )
      .subscribe({
        next: r => {
          this.docenteResultados.set(r.data);
          this.showDocenteDropdown.set(true);
        },
      });
  }

  seleccionarDocente(doc: Docente): void {
    this.docenteId.set(doc.id);
    this.docenteNombre.set(doc.nombre_completo);
    this.docenteBusqueda = doc.nombre_completo;
    this.docenteResultados.set([]);
    this.showDocenteDropdown.set(false);
  }

  limpiarDocente(): void {
    this.docenteId.set(null);
    this.docenteNombre.set('');
    this.docenteBusqueda = '';
  }

  getGrupoLabel(grupoId: string): string {
    const g = this.grupos().find(x => x.id === grupoId);
    if (!g) return '';
    if (!g.detalles.length) return g.nombre;
    const slots = g.detalles.slice(0, 2)
      .map(d => `${d.dia_semana.substring(0, 3)} ${d.hora_inicio.substring(0, 5)}`).join(' · ');
    return `${g.nombre} — ${slots}${g.detalles.length > 2 ? ' ...' : ''}`;
  }

  get esValido(): boolean {
    return this.capacidad() > 0 && this.escuelasSeleccionadas().size > 0;
  }

  guardar(): void {
    if (!this.esValido || this.guardando()) return;

    this.guardando.set(true);
    this.error.set(null);

    this.programacionService
      .actualizarProgramacion(this.programacion().id, {
        grupo_horario_id:     this.grupoHorarioId() || null,
        aula_id:              this.aulaId() || null,
        docente_id:           this.docenteId(),
        capacidad:            this.capacidad(),
        seccion:              this.seccion() || null,
        escuelas:             Array.from(this.escuelasSeleccionadas()),
        escuela_programada_id: this.escuelaProgramadaId() || null,
      })
      .subscribe({
        next: updated => {
          this.guardando.set(false);
          this.guardado.emit(updated);
        },
        error: err => {
          this.error.set(err.error?.message || 'Error al guardar');
          this.guardando.set(false);
        },
      });
  }
}
