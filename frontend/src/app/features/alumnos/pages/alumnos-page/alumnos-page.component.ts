import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AlumnoListaComponent } from '../../components/alumno-lista/alumno-lista.component';
import { ImportacionesAlumnosComponent } from '../../components/importaciones-alumnos/importaciones-alumnos.component';

type TabActiva = 'lista' | 'importaciones';

@Component({
  selector: 'app-alumnos-page',
  standalone: true,
  imports: [CommonModule, AlumnoListaComponent, ImportacionesAlumnosComponent],
  templateUrl: './alumnos-page.component.html',
})
export class AlumnosPageComponent {
  tabActiva = signal<TabActiva>('lista');
}
