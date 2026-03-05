import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { PeriodoService, Periodo } from '@core/services/periodo.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-periodo-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AppButtonComponent],
  templateUrl: './periodo-form.component.html'
})
export class PeriodoFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private periodoService = inject(PeriodoService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  periodoId = signal<string | null>(null);
  loading = signal(false);
  submitting = signal(false);
  errorMessage = signal('');

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(255)]],
    fecha_inicio: [''],
    fecha_fin: [''],
    activo: [false]
  });

  get isEditing(): boolean {
    return this.periodoId() !== null;
  }

  get titulo(): string {
    return this.isEditing ? 'Editar Periodo' : 'Nuevo Periodo';
  }

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.periodoId.set(id);
      this.cargarPeriodo(id);
    }
  }

  cargarPeriodo(id: string): void {
    this.loading.set(true);
    this.periodoService.getPeriodo(id).subscribe({
      next: (periodo) => {
        this.form.patchValue({
          nombre: periodo.nombre,
          fecha_inicio: periodo.fecha_inicio || '',
          fecha_fin: periodo.fecha_fin || '',
          activo: periodo.activo
        });
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('Error al cargar el periodo');
        this.loading.set(false);
      }
    });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set('');

    const data = {
      nombre: this.form.value.nombre!,
      fecha_inicio: this.form.value.fecha_inicio || null,
      fecha_fin: this.form.value.fecha_fin || null,
      activo: this.form.value.activo || false
    };

    const request$ = this.isEditing
      ? this.periodoService.actualizarPeriodo(this.periodoId()!, data)
      : this.periodoService.crearPeriodo(data);

    request$.subscribe({
      next: () => {
        this.router.navigate(['/app/configuracion/periodos']);
      },
      error: (err) => {
        this.errorMessage.set(err.error?.message || 'Error al guardar el periodo');
        this.submitting.set(false);
      }
    });
  }

  cancelar(): void {
    this.router.navigate(['/app/configuracion/periodos']);
  }

  // Helpers para validación en template
  isInvalid(field: string): boolean {
    const control = this.form.get(field);
    return !!(control && control.invalid && control.touched);
  }

  getError(field: string): string {
    const control = this.form.get(field);
    if (control?.errors?.['required']) return 'Este campo es requerido';
    if (control?.errors?.['maxlength']) return 'Máximo 255 caracteres';
    return '';
  }
}
