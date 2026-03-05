import { Component, signal, Type } from '@angular/core';
import { CommonModule, NgComponentOutlet } from '@angular/common';
import { Router } from '@angular/router';
import { AdministrativosListaComponent } from '../administrativos-lista/administrativos-lista.component';
import { EstudiantesListaComponent } from '../estudiantes-lista/estudiantes-lista.component';
import { RolesListaComponent } from '../roles-lista/roles-lista.component';

type Tab = 'administrativos' | 'estudiantes' | 'roles';

@Component({
  selector: 'app-usuarios-home',
  standalone: true,
  imports: [CommonModule, NgComponentOutlet],
  templateUrl: './usuarios-home.component.html'
})
export class UsuariosHomeComponent {
  constructor(private router: Router) {}

  activeTab = signal<Tab>('administrativos');

  tabs: { id: Tab; label: string; icon: string }[] = [
    {
      id: 'administrativos',
      label: 'Administrativos',
      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
    },
    {
      id: 'estudiantes',
      label: 'Estudiantes',
      icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222'
    },
    {
      id: 'roles',
      label: 'Roles',
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
    }
  ];

  get currentComponent(): Type<unknown> {
    switch (this.activeTab()) {
      case 'administrativos':
        return AdministrativosListaComponent;
      case 'estudiantes':
        return EstudiantesListaComponent;
      case 'roles':
        return RolesListaComponent;
    }
  }

  setActiveTab(tab: Tab): void {
    this.activeTab.set(tab);
  }

  volver(): void {
    this.router.navigate(['/app/configuracion']);
  }
}
