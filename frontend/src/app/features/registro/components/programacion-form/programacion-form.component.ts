import { Component, inject, OnInit, signal, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { ProgramacionService } from '../../services/programacion.service';
import { HorarioService, GrupoHorario } from '../../../configuracion/services/horario.service';
import { AulaService, Pabellon } from '../../../configuracion/services/aula.service';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppButtonComponent } from '@shared/button/button.component';

interface Escuela {
  id: string;
  nombre: string;
  nombre_corto: string | null;
  codigo: string;
}

interface Curso {
  id: string;
  nombre: string;
  codigo: string;
  creditos?: number;
}

interface SeccionForm {
  grupo_horario_id: string | null;
  aula_id: string | null;
  docente_id: string | null;
  capacidad: number;
}

interface Docente {
  id: string;
  nombre_completo: string;
}

@Component({
  selector: 'app-programacion-form',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './programacion-form.component.html',
})
export class ProgramacionFormComponent implements OnInit {
  private programacionService = inject(ProgramacionService);
  private horarioService = inject(HorarioService);
  private aulaService = inject(AulaService);
  private periodoService = inject(PeriodoService);
  private http = inject(HttpClient);

  cerrado = output<void>();
  guardado = output<void>();

  // Estado de carga
  loadingDatos = signal(true);
  guardando = signal(false);
  error = signal<string | null>(null);

  // Datos del formulario
  periodos = signal<Periodo[]>([]);
  grupos = signal<GrupoHorario[]>([]);
  pabellones = signal<Pabellon[]>([]);
  escuelas = signal<Escuela[]>([]);
  docentes = signal<Docente[]>([]);

  // Búsqueda de curso
  cursoBusqueda = '';
  cursoResultados = signal<Curso[]>([]);
  cursoSeleccionado = signal<Curso | null>(null);
  buscandoCurso = signal(false);
  mostrarResultados = signal(false);

  // Valores del formulario
  periodoId = signal<string>('');
  escuelasSeleccionadas = signal<Set<string>>(new Set());
  secciones = signal<SeccionForm[]>([
    { grupo_horario_id: null, aula_id: null, docente_id: null, capacidad: 30 }
  ]);

  // Búsqueda de docente por sección
  docenteBusqueda: string[] = [''];
  docenteResultados = signal<Docente[]>([]);
  buscandoDocente = signal(false);
  seccionDocenteActiva = signal<number | null>(null);

  ngOnInit(): void {
    forkJoin({
      periodos: this.periodoService.getPeriodos(),
      grupos: this.horarioService.getGrupos(),
      pabellones: this.aulaService.getPabellones(),
      escuelas: this.http.get<{ success: boolean; data: Escuela[] }>(`${environment.apiUrl}/escuelas`).pipe(map(r => r.data)),
    }).subscribe({
      next: ({ periodos, grupos, pabellones, escuelas }) => {
        this.periodos.set(periodos);
        this.grupos.set(grupos.filter(g => g.activo));
        this.pabellones.set(pabellones);
        this.escuelas.set(escuelas);

        const activo = periodos.find(p => p.activo);
        if (activo) this.periodoId.set(activo.id);
        else if (periodos.length) this.periodoId.set(periodos[0].id);

        this.loadingDatos.set(false);
      },
      error: () => {
        this.error.set('Error al cargar datos del formulario');
        this.loadingDatos.set(false);
      },
    });
  }

  // ─── Búsqueda de Curso ──────────────────────────────────────────────────

  onCursoBusqueda(): void {
    const q = this.cursoBusqueda.trim();
    if (q.length < 2) { this.cursoResultados.set([]); this.mostrarResultados.set(false); return; }

    this.buscandoCurso.set(true);
    this.http.get<{ success: boolean; data: { items: Curso[] } }>(
      `${environment.apiUrl}/cursos?search=${encodeURIComponent(q)}&per_page=10`
    ).subscribe({
      next: (r) => {
        this.cursoResultados.set(r.data.items);
        this.mostrarResultados.set(true);
        this.buscandoCurso.set(false);
      },
      error: () => this.buscandoCurso.set(false),
    });
  }

  seleccionarCurso(curso: Curso): void {
    this.cursoSeleccionado.set(curso);
    this.cursoBusqueda = `${curso.codigo} - ${curso.nombre}`;
    this.mostrarResultados.set(false);
    this.cursoResultados.set([]);
  }

  limpiarCurso(): void {
    this.cursoSeleccionado.set(null);
    this.cursoBusqueda = '';
    this.cursoResultados.set([]);
  }

  // ─── Escuelas ───────────────────────────────────────────────────────────

  toggleEscuela(id: string): void {
    const sel = new Set(this.escuelasSeleccionadas());
    if (sel.has(id)) sel.delete(id); else sel.add(id);
    this.escuelasSeleccionadas.set(sel);
  }

  // ─── Secciones ──────────────────────────────────────────────────────────

  agregarSeccion(): void {
    this.secciones.update(s => [...s, { grupo_horario_id: null, aula_id: null, docente_id: null, capacidad: 30 }]);
    this.docenteBusqueda.push('');
  }

  eliminarSeccion(index: number): void {
    if (this.secciones().length <= 1) return;
    this.secciones.update(s => s.filter((_, i) => i !== index));
    this.docenteBusqueda.splice(index, 1);
  }

  onAulaChange(index: number, aulaId: string): void {
    const aula = this.pabellones().flatMap(p => p.aulas).find(a => a.id === aulaId);
    this.secciones.update(s =>
      s.map((sec, i) => i === index
        ? { ...sec, aula_id: aulaId, capacidad: aula ? aula.capacidad : sec.capacidad }
        : sec)
    );
  }

  updateSeccion(index: number, field: keyof SeccionForm, value: any): void {
    this.secciones.update(s => s.map((sec, i) => i === index ? { ...sec, [field]: value } : sec));
  }

  // ─── Búsqueda de Docente ────────────────────────────────────────────────

  onDocenteBusqueda(index: number): void {
    const q = this.docenteBusqueda[index]?.trim();
    if (!q || q.length < 2) { this.docenteResultados.set([]); this.seccionDocenteActiva.set(null); return; }

    this.buscandoDocente.set(true);
    this.seccionDocenteActiva.set(index);
    this.http.get<{ success: boolean; data: Docente[] }>(
      `${environment.apiUrl}/docentes?search=${encodeURIComponent(q)}`
    ).subscribe({
      next: (r) => { this.docenteResultados.set(r.data); this.buscandoDocente.set(false); },
      error: () => this.buscandoDocente.set(false),
    });
  }

  seleccionarDocente(index: number, docente: Docente): void {
    this.docenteBusqueda[index] = docente.nombre_completo;
    this.updateSeccion(index, 'docente_id', docente.id);
    this.seccionDocenteActiva.set(null);
    this.docenteResultados.set([]);
  }

  // ─── Resumen de grupo ───────────────────────────────────────────────────

  getGrupoLabel(grupoId: string | null): string {
    if (!grupoId) return '';
    const g = this.grupos().find(x => x.id === grupoId);
    if (!g) return '';
    if (!g.detalles.length) return g.nombre;
    const slots = g.detalles.slice(0, 2)
      .map(d => `${d.dia_semana.substring(0, 3)} ${d.hora_inicio.substring(0, 5)}`)
      .join(' · ');
    return `${g.nombre} — ${slots}${g.detalles.length > 2 ? ' ...' : ''}`;
  }

  getAulaLabel(aulaId: string | null): string {
    if (!aulaId) return '';
    for (const p of this.pabellones()) {
      const a = p.aulas.find(x => x.id === aulaId);
      if (a) return `${p.nombre} › ${a.nombre} (${a.capacidad})`;
    }
    return '';
  }

  // ─── Enviar ─────────────────────────────────────────────────────────────

  get esValido(): boolean {
    return !!(
      this.periodoId() &&
      this.cursoSeleccionado() &&
      this.escuelasSeleccionadas().size > 0 &&
      this.secciones().every(s => s.capacidad > 0)
    );
  }

  guardar(): void {
    if (!this.esValido || this.guardando()) return;

    this.guardando.set(true);
    this.error.set(null);

    this.programacionService.crearProgramacion({
      periodo_id: this.periodoId(),
      curso_id: this.cursoSeleccionado()!.id,
      escuelas: Array.from(this.escuelasSeleccionadas()),
      secciones: this.secciones(),
    }).subscribe({
      next: (res) => {
        this.guardando.set(false);
        this.guardado.emit();
      },
      error: (err) => {
        this.error.set(err.error?.message || 'Error al guardar');
        this.guardando.set(false);
      },
    });
  }
}
