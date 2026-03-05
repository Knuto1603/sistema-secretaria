import { CursoService } from './../../services/curso.service';
import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { FormBuilder, ReactiveFormsModule, Validators, FormsModule } from '@angular/forms';
import { switchMap } from 'rxjs/operators';

import { SolicitudService } from '../../services/solicitud.service';
import { Programacion, ProgramacionService } from './../../../registro/services/programacion.service';

// Importación de componentes de la librería UI creada
import { AppButtonComponent } from '../../../../components/shared/button/button.component';
import { AppAlertComponent } from '../../../../components/shared/alert/alert.component';
import { AppSignaturePadComponent } from '../../../../components/shared/signature-pad/signature-pad.component';

@Component({
  selector: 'app-solicitud-form',
  standalone: true,
  imports: [
    CommonModule, 
    FormsModule, 
    ReactiveFormsModule,
    AppButtonComponent,
    AppAlertComponent,
    AppSignaturePadComponent
  ],
  templateUrl: './solicitud-form.component.html'
})
export class SolicitudFormComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private fb = inject(FormBuilder);
  private solicitudService = inject(SolicitudService);
  private ProgramacionService = inject(ProgramacionService);
  private CursoService = inject(CursoService);

  // Estado del componente
  // Cambiamos 'cursoSeleccionado' de input() a un signal interno
  programacionInfo = signal<Programacion | null>(null);
  cursoInfo = signal<any | null>(null);
  loading = signal(false);
  isSubmitting = signal(false);
  successMessage = signal('');
  errorMessage = signal('');
  firmaBase64 = signal('');

  solicitudForm = this.fb.group({
    motivo: ['', [Validators.required, Validators.minLength(20)]],
    archivo_sustento: [null]
  });

ngOnInit(): void {
  const id = this.route.snapshot.paramMap.get('id');

  if (!id) {
    this.router.navigate(['/programacion']);
    return;
  }

  this.ProgramacionService.getDetalleProgramacion(id).pipe(
    switchMap(programacion => {
      this.programacionInfo.set(programacion);
      if (!programacion.curso?.id) {
        throw new Error('No se encontró información del curso');
      }
      return this.CursoService.obtenerDetalleCurso(programacion.curso.id);
    })
  ).subscribe({
    next: (curso) => {
      this.cursoInfo.set(curso);
    },
    error: (err) => {
      this.errorMessage.set('Error al cargar los datos. Verifica la conexión.');
      console.error(err);
    }
  });
}

  onFirmaSaved(base64: string) {
    this.firmaBase64.set(base64);
  }

  onFileChange(event: any) {
    const file = event.target.files[0];
    if (file) {
      this.solicitudForm.patchValue({ archivo_sustento: file });
    }
  }

  enviarSolicitud() {

    if (this.solicitudForm.invalid || !this.firmaBase64()) {
      this.errorMessage.set('Por favor, completa el motivo y firma el documento antes de enviar.');
      return;
    }

    this.isSubmitting.set(true);
    this.errorMessage.set('');

    const payload = {
      programacion_id: this.programacionInfo()?.id!,
      motivo: this.solicitudForm.value.motivo!,
      firma: this.firmaBase64(),
      archivo_sustento: this.solicitudForm.value.archivo_sustento as any | undefined
    };

    this.solicitudService.crearSolicitud(payload).subscribe({
      next: () => {
        this.successMessage.set('Solicitud enviada con éxito. Redirigiendo...');
        setTimeout(() => this.router.navigate(['/app/solicitudes/list']), 2000);
      },
      error: (err) => {
        // Manejar diferentes formatos de error del backend
        const errorMsg = err.error?.message ||
                         err.error?.error ||
                         (err.error?.errors ? Object.values(err.error.errors).flat().join('. ') : null) ||
                         'Error al procesar la solicitud.';
        this.errorMessage.set(errorMsg);
        this.isSubmitting.set(false);
        console.error('Error al crear solicitud:', err);
      }
    });
  }
}