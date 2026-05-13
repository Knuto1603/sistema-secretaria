import { Component, inject, OnInit, signal, DestroyRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CursosDepartamentoService, CursoItem } from '../../services/cursos-departamento.service';
import { DepartamentoService, Departamento } from '../../services/departamento.service';
import { Subject } from 'rxjs';
import { debounceTime, switchMap } from 'rxjs/operators';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

@Component({
  selector: 'app-cursos-departamento',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cursos-departamento.component.html',
})
export class CursosDepartamentoComponent implements OnInit {
  private svc        = inject(CursosDepartamentoService);
  private depSvc     = inject(DepartamentoService);
  private destroyRef = inject(DestroyRef);

  cursos        = signal<CursoItem[]>([]);
  departamentos = signal<Departamento[]>([]);
  loading       = signal(false);
  asignando     = signal<string | null>(null);
  mensaje       = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  filtroSinArea = signal(false);
  filtroSearch  = signal('');
  filtroAreaId  = signal('');

  private reload$ = new Subject<void>();

  ngOnInit(): void {
    this.depSvc.getDepartamentos().subscribe(d => this.departamentos.set(d));

    this.reload$.pipe(
      debounceTime(200),
      switchMap(() => {
        this.loading.set(true);
        return this.svc.getCursos({
          sinArea: this.filtroSinArea() || undefined,
          search:  this.filtroSearch()  || undefined,
          areaId:  this.filtroAreaId()  || undefined,
        });
      }),
      takeUntilDestroyed(this.destroyRef)
    ).subscribe({
      next: (c: CursoItem[]) => { this.cursos.set(c); this.loading.set(false); },
      error: ()              => this.loading.set(false),
    });

    this.reload$.next();
  }

  cargar(): void {
    this.reload$.next();
  }

  onSearchChange(val: string): void {
    this.filtroSearch.set(val);
    this.reload$.next();
  }

  asignar(curso: CursoItem, areaId: string): void {
    this.asignando.set(curso.id);
    this.svc.asignarArea(curso.id, areaId || null).subscribe({
      next: updated => {
        this.cursos.update(list => list.map(c => c.id === updated.id ? updated : c));
        this.asignando.set(null);
        this.mostrar('success', `"${updated.nombre}" asignado a ${updated.area?.nombre ?? 'Sin área'}.`);
      },
      error: err => {
        this.asignando.set(null);
        this.mostrar('error', err.error?.message || 'Error al asignar.');
      },
    });
  }

  private mostrar(tipo: 'success' | 'error', texto: string): void {
    this.mensaje.set({ tipo, texto });
    setTimeout(() => this.mensaje.set(null), 4000);
  }
}
