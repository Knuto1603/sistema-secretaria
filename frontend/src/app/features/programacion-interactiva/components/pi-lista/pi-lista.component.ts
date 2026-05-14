import {
  Component, inject, input, output, signal, computed,
  ChangeDetectionStrategy, DestroyRef
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged, forkJoin, Subject, switchMap, of } from 'rxjs';
import { environment } from '@env/environment';
import {
  ProgramacionInteractivaService,
  BorradorProgramacion,
  BorradorSeccion,
  AgregarSeccionDTO
} from '../../services/programacion-interactiva.service';
import {
  DocumentosPiService,
  GeneracionDocumento,
  CursoSinArea
} from '../../services/documentos-pi.service';
import { Pabellon } from '../../../configuracion/services/aula.service';
import { GrupoHorario } from '../../../configuracion/services/horario.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppBadgeComponent } from '@shared/badge/badge.component';

interface CursoResultado {
  id: string;
  codigo: string;
  nombre: string;
}

interface EscuelaRef {
  id: string;
  nombre: string;
  nombre_corto: string;
}

interface GrupoEscuela {
  escuela: EscuelaRef;
  ciclos: GrupoCiclo[];
}

interface GrupoCiclo {
  ciclo: number;
  secciones: BorradorSeccion[];
}

@Component({
  selector: 'app-pi-lista',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, AppButtonComponent, AppBadgeComponent],
  templateUrl: './pi-lista.component.html'
})
export class PiListaComponent {
  private readonly piService   = inject(ProgramacionInteractivaService);
  private readonly docService  = inject(DocumentosPiService);
  private readonly http        = inject(HttpClient);
  private readonly destroyRef  = inject(DestroyRef);

  readonly borrador   = input.required<BorradorProgramacion>();
  readonly pabellones = input.required<Pabellon[]>();
  readonly grupos     = input.required<GrupoHorario[]>();

  readonly seccionActualizada = output<BorradorSeccion>();
  readonly seccionEliminada   = output<string>();
  readonly seccionAgregada    = output<BorradorSeccion>();

  readonly actualizando       = signal<string | null>(null);
  readonly eliminando         = signal<string | null>(null);
  readonly duplicando         = signal<string | null>(null);
  readonly agregando          = signal(false);
  readonly mostrarFormAgregar = signal(false);

  // Generación de documentos
  readonly mostrarModalDoc     = signal(false);
  readonly docNumeroOficio     = signal('');
  readonly docSemestreTexto    = signal('');
  readonly docCursosSinArea    = signal<CursoSinArea[]>([]);
  readonly docGeneraciones     = signal<GeneracionDocumento[]>([]);
  readonly docGenerando        = signal(false);
  readonly docCargando         = signal(false);
  readonly docEliminando       = signal<string | null>(null);
  readonly docMensaje          = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Formulario agregar
  readonly cursoBusqueda    = signal('');
  readonly cursoResultados  = signal<CursoResultado[]>([]);
  readonly cursoBuscando    = signal(false);
  readonly cursoSeleccionado = signal<CursoResultado | null>(null);
  readonly escuelaIdForm    = signal('');
  readonly cicloForm        = signal(1);
  readonly tipoForm         = signal<'O' | 'E'>('E');
  readonly capacidadForm    = signal(30);

  private readonly busqueda$ = new Subject<string>();

  constructor() {
    this.busqueda$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(term => {
        if (term.length < 2) {
          this.cursoResultados.set([]);
          return of(null);
        }
        this.cursoBuscando.set(true);
        return this.http.get<{ success: boolean; data: { items: CursoResultado[] } }>(
          `${environment.apiUrl}/cursos?search=${encodeURIComponent(term)}&per_page=10`
        );
      }),
      takeUntilDestroyed(this.destroyRef)
    ).subscribe(resp => {
      if (resp) this.cursoResultados.set(resp.data?.items ?? []);
      this.cursoBuscando.set(false);
    });
  }

  readonly filtroEscuelaId = signal('');
  readonly filtroCurso     = signal('');

  readonly escuelas = computed((): EscuelaRef[] => {
    const map = new Map<string, EscuelaRef>();
    for (const s of this.borrador().secciones ?? []) {
      if (!map.has(s.escuela.id)) map.set(s.escuela.id, s.escuela);
    }
    return Array.from(map.values()).sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  readonly gruposEscuela = computed((): GrupoEscuela[] => {
    let secciones = this.borrador().secciones ?? [];

    const escuelaId = this.filtroEscuelaId();
    if (escuelaId) secciones = secciones.filter(s => s.escuela.id === escuelaId);

    const termino = this.filtroCurso().toLowerCase().trim();
    if (termino) secciones = secciones.filter(s =>
      s.curso.nombre.toLowerCase().includes(termino) ||
      s.curso.codigo.toLowerCase().includes(termino)
    );

    const escuelaMap = new Map<string, Map<number, BorradorSeccion[]>>();
    for (const s of secciones) {
      if (!escuelaMap.has(s.escuela.id)) escuelaMap.set(s.escuela.id, new Map());
      const cicloMap = escuelaMap.get(s.escuela.id)!;
      if (!cicloMap.has(s.ciclo)) cicloMap.set(s.ciclo, []);
      cicloMap.get(s.ciclo)!.push(s);
    }

    return this.escuelas()
      .filter(e => escuelaMap.has(e.id))
      .map(escuela => ({
        escuela,
        ciclos: Array.from(escuelaMap.get(escuela.id)!.entries())
          .sort(([a], [b]) => a - b)
          .map(([ciclo, secs]) => ({
            ciclo,
            secciones: [...secs].sort((a, b) => {
              const cmp = a.curso.nombre.localeCompare(b.curso.nombre);
              return cmp !== 0 ? cmp : a.seccion.localeCompare(b.seccion);
            })
          }))
      }));
  });

  readonly totalFiltradas = computed(() =>
    this.gruposEscuela().reduce((acc, g) => acc + this.totalSeccionesGrupo(g), 0)
  );

  readonly hayFiltros = computed(() => !!this.filtroEscuelaId() || !!this.filtroCurso().trim());

  limpiarFiltros(): void {
    this.filtroEscuelaId.set('');
    this.filtroCurso.set('');
  }

  onBuscarCurso(term: string): void {
    this.cursoBusqueda.set(term);
    this.cursoSeleccionado.set(null);
    this.busqueda$.next(term);
  }

  seleccionarCurso(curso: CursoResultado): void {
    this.cursoSeleccionado.set(curso);
    this.cursoBusqueda.set(curso.nombre);
    this.cursoResultados.set([]);
  }

  abrirFormAgregar(): void {
    this.mostrarFormAgregar.set(true);
    this.cursoSeleccionado.set(null);
    this.cursoBusqueda.set('');
    this.cursoResultados.set([]);
    this.escuelaIdForm.set(this.escuelas()[0]?.id ?? '');
    this.cicloForm.set(1);
    this.tipoForm.set('E');
    this.capacidadForm.set(30);
  }

  cancelarFormAgregar(): void {
    this.mostrarFormAgregar.set(false);
  }

  agregarSeccion(): void {
    const curso = this.cursoSeleccionado();
    if (!curso || !this.escuelaIdForm()) return;

    this.agregando.set(true);
    const data: AgregarSeccionDTO = {
      curso_id:   curso.id,
      escuela_id: this.escuelaIdForm(),
      ciclo:      this.cicloForm(),
      tipo:       this.tipoForm(),
      capacidad:  this.capacidadForm()
    };

    this.piService.agregarSeccion(this.borrador().id, data).subscribe({
      next: seccion => {
        this.seccionAgregada.emit(seccion);
        this.agregando.set(false);
        this.mostrarFormAgregar.set(false);
      },
      error: () => this.agregando.set(false)
    });
  }

  onAulaChange(seccion: BorradorSeccion, aulaId: string): void {
    this.actualizando.set(seccion.id);
    this.piService.updateSeccion(this.borrador().id, seccion.id, { aula_id: aulaId || null }).subscribe({
      next: updated => { this.seccionActualizada.emit(updated); this.actualizando.set(null); },
      error: () => this.actualizando.set(null)
    });
  }

  onGrupoChange(seccion: BorradorSeccion, grupoId: string): void {
    this.actualizando.set(seccion.id);
    this.piService.updateSeccion(this.borrador().id, seccion.id, { grupo_horario_id: grupoId || null }).subscribe({
      next: updated => { this.seccionActualizada.emit(updated); this.actualizando.set(null); },
      error: () => this.actualizando.set(null)
    });
  }

  duplicarSeccion(seccion: BorradorSeccion): void {
    this.duplicando.set(seccion.id);
    const data: AgregarSeccionDTO = {
      curso_id:   seccion.curso.id,
      escuela_id: seccion.escuela.id,
      ciclo:      seccion.ciclo,
      tipo:       seccion.tipo,
      capacidad:  seccion.capacidad,
    };
    this.piService.agregarSeccion(this.borrador().id, data).subscribe({
      next: nueva => { this.seccionAgregada.emit(nueva); this.duplicando.set(null); },
      error: () => this.duplicando.set(null)
    });
  }

  eliminarSeccion(seccion: BorradorSeccion): void {
    if (!confirm(`¿Eliminar la sección ${seccion.seccion} de "${seccion.curso.nombre}"?`)) return;
    this.eliminando.set(seccion.id);
    this.piService.deleteSeccion(this.borrador().id, seccion.id).subscribe({
      next: () => { this.seccionEliminada.emit(seccion.id); this.eliminando.set(null); },
      error: () => this.eliminando.set(null)
    });
  }

  totalSeccionesGrupo(grupo: GrupoEscuela): number {
    return grupo.ciclos.reduce((acc, c) => acc + c.secciones.length, 0);
  }

  aulaDisplay(seccion: BorradorSeccion): string {
    if (!seccion.aula) return '';
    const prefijo = seccion.aula.pabellon?.nombre ? `${seccion.aula.pabellon.nombre} - ` : '';
    return `${prefijo}${seccion.aula.nombre}`;
  }

  grupoDisplay(seccion: BorradorSeccion): string {
    return seccion.grupo_horario?.nombre ?? '';
  }

  // --- Generación de documentos ---

  abrirModalDoc(): void {
    this.mostrarModalDoc.set(true);
    this.docMensaje.set(null);
    this.docGenerando.set(false);
    this.docCargando.set(true);

    const id = this.borrador().id;
    forkJoin({
      sinArea:      this.docService.getCursosSinArea(id),
      generaciones: this.docService.getGeneraciones(id),
    }).subscribe({
      next: ({ sinArea, generaciones }) => {
        this.docCursosSinArea.set(sinArea);
        this.docGeneraciones.set(generaciones);
        this.docCargando.set(false);
      },
      error: () => {
        this.docCargando.set(false);
        this.docMensaje.set({ tipo: 'error', texto: 'Error al cargar los datos. Intenta cerrar y abrir el panel.' });
      }
    });
  }

  cerrarModalDoc(): void {
    this.mostrarModalDoc.set(false);
  }

  generarDocumentos(): void {
    const numero  = this.docNumeroOficio().trim();
    const semestre = this.docSemestreTexto().trim();
    if (!numero || !semestre || this.docGenerando()) return;

    this.docGenerando.set(true);
    this.docMensaje.set(null);

    this.docService.generarDocumentos(this.borrador().id, numero, semestre).subscribe({
      next: gen => {
        this.docGeneraciones.update(list => [gen, ...list]);
        this.docGenerando.set(false);
        this.docNumeroOficio.set('');
        this.docMensaje.set({ tipo: 'success', texto: `${gen.total_documentos} documento(s) generado(s) correctamente.` });
      },
      error: err => {
        this.docGenerando.set(false);
        this.docMensaje.set({ tipo: 'error', texto: err.error?.message || 'Error al generar documentos.' });
      }
    });
  }

  descargar(generacionId: string, areaId: string, nombreArchivo: string): void {
    const url = this.docService.getUrlDescarga(generacionId, areaId);
    this.http.get(url, { responseType: 'blob' }).subscribe(blob => {
      this.triggerDownload(blob, nombreArchivo);
    });
  }

  descargarTodos(generacionId: string, numeroOficio: string): void {
    const url = this.docService.getUrlDescargarTodos(generacionId);
    this.http.get(url, { responseType: 'blob' }).subscribe(blob => {
      this.triggerDownload(blob, `oficios_${numeroOficio}.zip`);
    });
  }

  eliminarGeneracion(gen: GeneracionDocumento): void {
    if (!confirm(`¿Eliminar la generación "${gen.numero_oficio}"? Se borrarán los archivos Word del servidor.`)) return;
    this.docEliminando.set(gen.id);
    this.docService.eliminarGeneracion(gen.id).subscribe({
      next: () => {
        this.docGeneraciones.update(list => list.filter(g => g.id !== gen.id));
        this.docEliminando.set(null);
        this.docMensaje.set({ tipo: 'success', texto: 'Generación eliminada correctamente.' });
      },
      error: () => {
        this.docEliminando.set(null);
        this.docMensaje.set({ tipo: 'error', texto: 'Error al eliminar la generación.' });
      }
    });
  }

  private triggerDownload(blob: Blob, nombre: string): void {
    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = objectUrl;
    a.download = nombre;
    a.click();
    URL.revokeObjectURL(objectUrl);
  }

  formatFechaGen(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-PE', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }
}
