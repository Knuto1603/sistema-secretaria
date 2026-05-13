import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CursosDepartamentoService, CursoItem } from '../../services/cursos-departamento.service';
import { DepartamentoService, Departamento } from '../../services/departamento.service';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';

@Component({
  selector: 'app-cursos-departamento',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cursos-departamento.component.html',
})
export class CursosDepartamentoComponent implements OnInit {
  private svc   = inject(CursosDepartamentoService);
  private depSvc = inject(DepartamentoService);

  cursos         = signal<CursoItem[]>([]);
  departamentos  = signal<Departamento[]>([]);
  loading        = signal(false);
  asignando      = signal<string | null>(null);
  mensaje        = signal<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  filtroSinArea = false;
  filtroSearch  = '';
  filtroAreaId  = '';

  private search$ = new Subject<string>();

  ngOnInit(): void {
    this.depSvc.getDepartamentos().subscribe(d => this.departamentos.set(d));
    this.cargar();

    this.search$.pipe(debounceTime(300), distinctUntilChanged())
      .subscribe(() => this.cargar());
  }

  cargar(): void {
    this.loading.set(true);
    this.svc.getCursos({
      sinArea: this.filtroSinArea || undefined,
      search:  this.filtroSearch || undefined,
      areaId:  this.filtroAreaId || undefined,
    }).subscribe({
      next: c => { this.cursos.set(c); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  onSearchChange(val: string): void {
    this.filtroSearch = val;
    this.search$.next(val);
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
