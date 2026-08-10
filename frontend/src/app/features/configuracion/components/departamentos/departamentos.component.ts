import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DepartamentoService, Departamento } from '../../services/departamento.service';
import { AuthService } from '@core/auth/services/auth.service';

@Component({
  selector: 'app-departamentos',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './departamentos.component.html',
  styles: [`
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .slide-down { animation: slideDown 140ms ease-out; }
  `],
})
export class DepartamentosComponent implements OnInit {
  private departamentoService = inject(DepartamentoService);
  protected authService       = inject(AuthService);

  departamentos  = signal<Departamento[]>([]);
  loading        = signal(false);
  autoAsignando  = signal(false);
  mensaje        = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  mostrarForm    = signal(false);
  editando       = signal<Departamento | null>(null);

  // Form state
  formNombre         = '';
  formPrefijos: string[] = [];
  formPrefijoNuevo    = '';
  formTituloDirector = '';
  formDirectorNombre = '';
  formDirectorCargo  = '';
  formNombreTabla    = '';
  guardando   = signal(false);

  agregarPrefijo(): void {
    const prefijo = this.formPrefijoNuevo.trim().toUpperCase();
    if (!prefijo || this.formPrefijos.includes(prefijo)) {
      this.formPrefijoNuevo = '';
      return;
    }
    this.formPrefijos.push(prefijo);
    this.formPrefijoNuevo = '';
  }

  quitarPrefijo(prefijo: string): void {
    this.formPrefijos = this.formPrefijos.filter(p => p !== prefijo);
  }

  // Confirmaciones
  mostrarConfirmAutoAsignar = signal(false);
  resultadoAutoAsignar      = signal<number | null>(null);

  ngOnInit(): void {
    this.cargarDepartamentos();
  }

  cargarDepartamentos(): void {
    this.loading.set(true);
    this.departamentoService.getDepartamentos().subscribe({
      next: deps => {
        this.departamentos.set(deps);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.formNombre         = '';
    this.formPrefijos       = [];
    this.formPrefijoNuevo   = '';
    this.formTituloDirector = '';
    this.formDirectorNombre = '';
    this.formDirectorCargo  = '';
    this.formNombreTabla    = '';
    this.mostrarForm.set(true);
  }

  abrirEditar(dep: Departamento): void {
    this.editando.set(dep);
    this.formNombre         = dep.nombre;
    this.formPrefijos       = [...dep.prefijos];
    this.formPrefijoNuevo   = '';
    this.formTituloDirector = dep.titulo_director ?? '';
    this.formDirectorNombre = dep.director_nombre ?? '';
    this.formDirectorCargo  = dep.director_cargo  ?? '';
    this.formNombreTabla    = dep.nombre_tabla    ?? '';
    this.mostrarForm.set(true);
  }

  cancelarForm(): void {
    this.mostrarForm.set(false);
    this.editando.set(null);
    this.formNombre         = '';
    this.formPrefijos       = [];
    this.formPrefijoNuevo   = '';
    this.formTituloDirector = '';
    this.formDirectorNombre = '';
    this.formDirectorCargo  = '';
    this.formNombreTabla    = '';
  }

  guardar(): void {
    if (!this.formNombre.trim() || this.guardando()) return;

    // Si quedó texto sin confirmar en el input de prefijo, lo agregamos también
    this.agregarPrefijo();

    const payload  = {
      nombre: this.formNombre.trim(),
      prefijos: this.formPrefijos,
      titulo_director: this.formTituloDirector.trim() || null,
      director_nombre: this.formDirectorNombre.trim() || null,
      director_cargo:  this.formDirectorCargo.trim()  || null,
      nombre_tabla:    this.formNombreTabla.trim()    || null,
    };
    this.guardando.set(true);
    this.limpiarMensaje();

    const dep = this.editando();
    const op$ = dep
      ? this.departamentoService.actualizarDepartamento(dep.id, payload)
      : this.departamentoService.crearDepartamento(payload);

    op$.subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrarForm.set(false);
        this.editando.set(null);
        this.mostrarMensaje('success', dep ? 'Departamento actualizado correctamente.' : 'Departamento creado correctamente.');
        this.cargarDepartamentos();
      },
      error: err => {
        this.guardando.set(false);
        const msg = err.error?.message || 'Error al guardar el departamento.';
        this.mostrarMensaje('error', msg);
      },
    });
  }

  eliminar(dep: Departamento): void {
    if (!confirm(`¿Eliminar el departamento "${dep.nombre}"? Esta acción no se puede deshacer.`)) return;

    this.limpiarMensaje();
    this.departamentoService.eliminarDepartamento(dep.id).subscribe({
      next: () => {
        this.mostrarMensaje('success', `"${dep.nombre}" eliminado correctamente.`);
        this.cargarDepartamentos();
      },
      error: err => {
        const msg = err.error?.message || 'Error al eliminar el departamento.';
        this.mostrarMensaje('error', msg);
      },
    });
  }

  confirmarAutoAsignar(): void {
    this.mostrarConfirmAutoAsignar.set(true);
  }

  cancelarAutoAsignar(): void {
    this.mostrarConfirmAutoAsignar.set(false);
  }

  ejecutarAutoAsignar(): void {
    this.mostrarConfirmAutoAsignar.set(false);
    this.autoAsignando.set(true);
    this.limpiarMensaje();

    this.departamentoService.autoAsignar().subscribe({
      next: res => {
        this.autoAsignando.set(false);
        this.resultadoAutoAsignar.set(res.asignados);
        this.mostrarMensaje('success', `Auto-asignación completada: ${res.asignados} curso(s) asignados a sus departamentos.`);
        this.cargarDepartamentos();
      },
      error: err => {
        this.autoAsignando.set(false);
        const msg = err.error?.message || 'Error durante la auto-asignación.';
        this.mostrarMensaje('error', msg);
      },
    });
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.limpiarMensaje(), 5000);
  }

  private limpiarMensaje(): void {
    this.mensaje.set(null);
  }
}
