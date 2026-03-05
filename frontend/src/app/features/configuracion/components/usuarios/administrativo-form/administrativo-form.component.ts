import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { UsuarioService } from '@core/services/usuario.service';
import { RolService, Rol } from '@core/services/rol.service';
import { AppButtonComponent } from '@shared/button/button.component';

@Component({
  selector: 'app-administrativo-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AppButtonComponent],
  templateUrl: './administrativo-form.component.html'
})
export class AdministrativoFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private usuarioService = inject(UsuarioService);
  private rolService = inject(RolService);

  usuarioId = signal<string | null>(null);
  roles = signal<Rol[]>([]);
  loading = signal(false);
  saving = signal(false);
  mensaje = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);
  showPassword = signal(false);

  form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    username: ['', [Validators.required, Validators.maxLength(50), Validators.pattern(/^[a-zA-Z0-9_-]+$/)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    password: ['', [Validators.minLength(8)]],
    roles: [[] as string[]]
  });

  get isEditing(): boolean {
    return !!this.usuarioId();
  }

  ngOnInit(): void {
    this.cargarRoles();

    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.usuarioId.set(id);
      this.cargarUsuario(id);
    } else {
      // Requerir password al crear
      this.form.get('password')?.setValidators([Validators.required, Validators.minLength(8)]);
      this.form.get('password')?.updateValueAndValidity();
    }
  }

  cargarRoles(): void {
    this.rolService.getAll().subscribe({
      next: (roles) => {
        // Filtrar roles que solo pueden asignarse a administrativos
        const rolesAdmin = roles.filter(r => r.name !== 'estudiante');
        this.roles.set(rolesAdmin);
      },
      error: () => console.error('Error cargando roles')
    });
  }

  cargarUsuario(id: string): void {
    this.loading.set(true);
    this.usuarioService.getAdministrativoById(id).subscribe({
      next: (usuario) => {
        this.form.patchValue({
          name: usuario.name,
          username: usuario.username,
          email: usuario.email,
          roles: usuario.roles
        });
        this.loading.set(false);
      },
      error: () => {
        this.mostrarMensaje('error', 'Error al cargar el usuario');
        this.loading.set(false);
      }
    });
  }

  toggleRole(roleName: string): void {
    const currentRoles = this.form.get('roles')?.value || [];
    const index = currentRoles.indexOf(roleName);

    if (index === -1) {
      this.form.patchValue({ roles: [...currentRoles, roleName] });
    } else {
      this.form.patchValue({ roles: currentRoles.filter(r => r !== roleName) });
    }
  }

  isRoleSelected(roleName: string): boolean {
    return (this.form.get('roles')?.value || []).includes(roleName);
  }

  togglePasswordVisibility(): void {
    this.showPassword.update(v => !v);
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const formValue = this.form.value;

    // Preparar datos
    const data: any = {
      name: formValue.name,
      username: formValue.username,
      email: formValue.email,
      roles: formValue.roles
    };

    // Solo incluir password si se proporcionó
    if (formValue.password) {
      data.password = formValue.password;
    }

    const request$ = this.isEditing
      ? this.usuarioService.updateAdministrativo(this.usuarioId()!, data)
      : this.usuarioService.createAdministrativo(data);

    request$.subscribe({
      next: () => {
        this.mostrarMensaje('success', this.isEditing ? 'Usuario actualizado' : 'Usuario creado');
        setTimeout(() => this.router.navigate(['/app/configuracion/usuarios']), 1500);
      },
      error: (err) => {
        const errorMsg = err.error?.message || 'Error al guardar';
        this.mostrarMensaje('error', errorMsg);
        this.saving.set(false);
      }
    });
  }

  cancelar(): void {
    this.router.navigate(['/app/configuracion/usuarios']);
  }

  private mostrarMensaje(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }

  getRolColor(rolName: string): string {
    const colors: Record<string, string> = {
      'admin': 'indigo',
      'developer': 'purple',
      'secretaria': 'emerald',
      'decano': 'amber',
      'secretario academico': 'cyan'
    };
    return colors[rolName.toLowerCase()] || 'slate';
  }
}
