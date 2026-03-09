import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AulaService, Pabellon, Aula } from '../../services/aula.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-aulas',
  standalone: true,
  imports: [CommonModule, FormsModule, AppButtonComponent],
  templateUrl: './aulas.component.html',
})
export class AulasComponent implements OnInit {
  private aulaService = inject(AulaService);

  pabellones      = signal<Pabellon[]>([]);
  aulasHuerfanas  = signal<Aula[]>([]);
  loading         = signal(false);
  mensaje         = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  // Pabellón expandido
  pabellonActivo = signal<string | null>(null);

  // Formulario nuevo pabellón
  mostrarFormPabellon = signal(false);
  nombrePabellon = '';
  guardandoPabellon = signal(false);

  // Formulario nueva aula (por pabellón)
  aulaFormPabellon = signal<string | null>(null);
  nuevaAula = { nombre: '', capacidad: 30 };
  guardandoAula = signal(false);

  // Edición de aula
  aulaEditando = signal<Aula | null>(null);
  aulaEditData = { nombre: '', capacidad: 30 };

  // Asignar aula huérfana a pabellón
  aulaAsignando       = signal<string | null>(null); // aula id en proceso
  pabellonSeleccionado: Record<string, string> = {}; // aulaId -> pabellonId

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.loading.set(true);
    this.aulaService.getPabellones().subscribe({
      next: (p) => {
        this.pabellones.set(p);
        this.loading.set(false);
        this.cargarHuerfanas();
      },
      error: () => this.loading.set(false),
    });
  }

  cargarHuerfanas(): void {
    this.aulaService.getAulasHuerfanas().subscribe({
      next: (a) => this.aulasHuerfanas.set(a),
      error: () => {},
    });
  }

  togglePabellon(id: string): void {
    this.pabellonActivo.set(this.pabellonActivo() === id ? null : id);
    this.aulaFormPabellon.set(null);
    this.aulaEditando.set(null);
  }

  // ─── Pabellones ────────────────────────────────────────────────────────

  crearPabellon(): void {
    if (!this.nombrePabellon.trim()) return;
    this.guardandoPabellon.set(true);
    this.aulaService.crearPabellon(this.nombrePabellon.trim()).subscribe({
      next: (p) => {
        this.pabellones.update(list => [...list, p].sort((a, b) => a.nombre.localeCompare(b.nombre)));
        this.nombrePabellon = '';
        this.mostrarFormPabellon.set(false);
        this.guardandoPabellon.set(false);
        this.mostrarMensaje('success', 'Pabellón creado');
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al crear pabellón');
        this.guardandoPabellon.set(false);
      },
    });
  }

  eliminarPabellon(pabellon: Pabellon): void {
    if (!confirm(`¿Eliminar "${pabellon.nombre}"? Esta acción es irreversible.`)) return;
    this.aulaService.eliminarPabellon(pabellon.id).subscribe({
      next: () => {
        this.pabellones.update(list => list.filter(p => p.id !== pabellon.id));
        this.mostrarMensaje('success', 'Pabellón eliminado');
      },
      error: (err) => this.mostrarMensaje('error', err.error?.message || 'No se pudo eliminar'),
    });
  }

  // ─── Aulas ──────────────────────────────────────────────────────────────

  toggleAulaForm(pabellonId: string): void {
    this.aulaFormPabellon.set(this.aulaFormPabellon() === pabellonId ? null : pabellonId);
    this.nuevaAula = { nombre: '', capacidad: 30 };
    this.aulaEditando.set(null);
  }

  crearAula(pabellonId: string): void {
    if (!this.nuevaAula.nombre.trim()) return;
    this.guardandoAula.set(true);
    this.aulaService.crearAula(pabellonId, this.nuevaAula).subscribe({
      next: (aula) => {
        this.pabellones.update(list =>
          list.map(p => p.id === pabellonId
            ? { ...p, aulas: [...p.aulas, aula].sort((a, b) => a.nombre.localeCompare(b.nombre)) }
            : p)
        );
        this.nuevaAula = { nombre: '', capacidad: 30 };
        this.aulaFormPabellon.set(null);
        this.guardandoAula.set(false);
        this.mostrarMensaje('success', 'Aula creada');
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al crear aula');
        this.guardandoAula.set(false);
      },
    });
  }

  editarAula(aula: Aula): void {
    this.aulaEditando.set(aula);
    this.aulaEditData = { nombre: aula.nombre, capacidad: aula.capacidad };
    this.aulaFormPabellon.set(null);
  }

  guardarEdicionAula(): void {
    const aula = this.aulaEditando();
    if (!aula) return;
    this.aulaService.actualizarAula(aula.id, this.aulaEditData).subscribe({
      next: (updated) => {
        this.pabellones.update(list =>
          list.map(p => ({
            ...p,
            aulas: p.aulas.map(a => a.id === updated.id ? updated : a)
          }))
        );
        this.aulaEditando.set(null);
        this.mostrarMensaje('success', 'Aula actualizada');
      },
      error: () => this.mostrarMensaje('error', 'Error al actualizar aula'),
    });
  }

  toggleAula(aula: Aula): void {
    this.aulaService.toggleAula(aula.id).subscribe({
      next: (updated) => {
        this.pabellones.update(list =>
          list.map(p => ({ ...p, aulas: p.aulas.map(a => a.id === updated.id ? updated : a) }))
        );
      },
      error: () => this.mostrarMensaje('error', 'Error al cambiar estado'),
    });
  }

  eliminarAula(aula: Aula, pabellonId: string): void {
    if (!confirm(`¿Eliminar aula "${aula.nombre}"?`)) return;
    this.aulaService.eliminarAula(aula.id).subscribe({
      next: () => {
        this.pabellones.update(list =>
          list.map(p => p.id === pabellonId
            ? { ...p, aulas: p.aulas.filter(a => a.id !== aula.id) }
            : p)
        );
        this.mostrarMensaje('success', 'Aula eliminada');
      },
      error: (err) => this.mostrarMensaje('error', err.error?.message || 'No se pudo eliminar'),
    });
  }

  asignarPabellonAula(aula: Aula): void {
    const pabellonId = this.pabellonSeleccionado[aula.id];
    if (!pabellonId || this.aulaAsignando() === aula.id) return;

    this.aulaAsignando.set(aula.id);
    this.aulaService.actualizarAula(aula.id, { pabellon_id: pabellonId }).subscribe({
      next: (updated) => {
        this.aulasHuerfanas.update(list => list.filter(a => a.id !== aula.id));
        this.pabellones.update(list =>
          list.map(p => p.id === pabellonId
            ? { ...p, aulas: [...p.aulas, updated].sort((a, b) => a.nombre.localeCompare(b.nombre)) }
            : p)
        );
        delete this.pabellonSeleccionado[aula.id];
        this.aulaAsignando.set(null);
        this.mostrarMensaje('success', `Aula "${updated.nombre}" asignada correctamente`);
      },
      error: (err) => {
        this.mostrarMensaje('error', err.error?.message || 'Error al asignar aula');
        this.aulaAsignando.set(null);
      },
    });
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 3500);
  }
}
