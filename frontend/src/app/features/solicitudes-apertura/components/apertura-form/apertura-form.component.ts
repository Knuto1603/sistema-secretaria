import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { SolicitudAperturaService, TipoApertura } from '../../services/solicitud-apertura.service';
import { AppButtonComponent } from '@shared/button/button.component';
import { AppAlertComponent } from '@shared/alert/alert.component';
import { AppSignaturePadComponent } from '@shared/signature-pad/signature-pad.component';

@Component({
  selector: 'app-apertura-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AppButtonComponent, AppAlertComponent, AppSignaturePadComponent],
  templateUrl: './apertura-form.component.html'
})
export class AperturaFormComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private fb = inject(FormBuilder);
  private service = inject(SolicitudAperturaService);

  cursoId = signal('');
  codigo = signal('');
  nombre = signal('');
  tipo = signal<TipoApertura>('nueva_apertura');
  referenciaId = signal<string | null>(null);
  referenciaLabel = signal<string | null>(null);

  isSubmitting = signal(false);
  solicitudExitosa = signal(false);
  errorMessage = signal('');
  firmaBase64 = signal('');

  form = this.fb.group({
    motivo: ['', [Validators.required, Validators.minLength(20)]]
  });

  ngOnInit(): void {
    const qp = this.route.snapshot.queryParamMap;
    const cursoId = qp.get('curso_id');

    if (!cursoId) {
      this.router.navigate(['/app/solicitudes-apertura']);
      return;
    }

    this.cursoId.set(cursoId);
    this.codigo.set(qp.get('codigo') ?? '');
    this.nombre.set(qp.get('nombre') ?? '');
    this.tipo.set(qp.get('tipo') === 'cambio_grupo' ? 'cambio_grupo' : 'nueva_apertura');
    this.referenciaId.set(qp.get('ref_id'));
    this.referenciaLabel.set(qp.get('ref_label'));
  }

  onFirmaSaved(base64: string): void {
    this.firmaBase64.set(base64);
  }

  enviar(): void {
    if (this.form.invalid || !this.firmaBase64()) {
      this.errorMessage.set('Por favor, completa el motivo y firma el documento antes de enviar.');
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set('');

    this.service.crearSolicitud({
      curso_id: this.cursoId(),
      tipo: this.tipo(),
      programacion_referencia_id: this.referenciaId(),
      motivo: this.form.value.motivo!,
      firma: this.firmaBase64()
    }).subscribe({
      next: () => {
        this.solicitudExitosa.set(true);
        setTimeout(() => this.router.navigate(['/app/solicitudes-apertura']), 2000);
      },
      error: (err) => {
        const errorMsg = err.error?.message ||
          (err.error?.errors ? Object.values(err.error.errors).flat().join('. ') : null) ||
          'Error al procesar la solicitud.';
        this.errorMessage.set(errorMsg);
        this.isSubmitting.set(false);
      }
    });
  }
}
