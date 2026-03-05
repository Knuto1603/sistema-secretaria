import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TipoSolicitudService } from '@core/services/tipo-solicitud.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-tipo-solicitud-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AppButtonComponent],
  templateUrl: './tipo-solicitud-form.component.html'
})
export class TipoSolicitudFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private tipoSolicitudService = inject(TipoSolicitudService);

  tipoId = signal<string | null>(null);
  loading = signal(false);
  saving = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  form = this.fb.group({
    codigo: ['', [Validators.required, Validators.maxLength(20)]],
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    descripcion: ['', [Validators.maxLength(500)]],
    requiere_archivo: [false],
    activo: [true]
  });

  get isEditing(): boolean {
    return !!this.tipoId();
  }

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.tipoId.set(id);
      this.cargarTipo(id);
    }
  }

  cargarTipo(id: string): void {
    this.loading.set(true);
    this.tipoSolicitudService.getById(id).subscribe({
      next: (tipo) => {
        this.form.patchValue({
          codigo: tipo.codigo,
          nombre: tipo.nombre,
          descripcion: tipo.descripcion || '',
          requiere_archivo: tipo.requiere_archivo,
          activo: tipo.activo
        });
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar el tipo de solicitud');
        this.loading.set(false);
      }
    });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const data = this.form.value as any;

    const request$ = this.isEditing
      ? this.tipoSolicitudService.update(this.tipoId()!, data)
      : this.tipoSolicitudService.create(data);

    request$.subscribe({
      next: () => {
        this.mostrarMensaje('success', this.isEditing ? 'Tipo actualizado' : 'Tipo creado');
        setTimeout(() => this.router.navigate(['/app/configuracion/tipos-solicitud']), 1500);
      },
      error: (err) => {
        const errorMsg = err.error?.message || 'Error al guardar';
        this.mostrarMensaje('error', errorMsg);
        this.saving.set(false);
      }
    });
  }

  cancelar(): void {
    this.router.navigate(['/app/configuracion/tipos-solicitud']);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
