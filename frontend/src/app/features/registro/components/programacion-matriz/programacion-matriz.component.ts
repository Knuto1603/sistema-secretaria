import { Component, input, computed, output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Programacion } from '../../services/programacion.service';

@Component({
  selector: 'app-programacion-matriz',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './programacion-matriz.component.html',
})
export class ProgramacionMatrizComponent {
  items    = input.required<Programacion[]>();
  loading  = input<boolean>(false);

  verDetalle = output<string>();

  // Grupos ordenados G1-G14
  grupos = computed(() => {
    const gs = [...new Set(this.items().map(p => p.grupo).filter(Boolean))];
    return gs.sort((a, b) => {
      const na = parseInt(a?.replace(/\D/g, '') ?? '0');
      const nb = parseInt(b?.replace(/\D/g, '') ?? '0');
      return na - nb;
    });
  });

  // Aulas únicas ordenadas
  aulas = computed(() => {
    const as = [...new Set(
      this.items()
        .map(p => this.aulaNombre(p))
        .filter(Boolean)
    )];
    return as.sort();
  });

  // Map: grupo -> aula -> Programacion[]
  matriz = computed(() => {
    const map = new Map<string, Map<string, Programacion[]>>();
    for (const item of this.items()) {
      const grupo = item.grupo;
      const aula  = this.aulaNombre(item);
      if (!grupo || !aula) continue;

      if (!map.has(grupo)) map.set(grupo, new Map());
      const aulaMap = map.get(grupo)!;
      if (!aulaMap.has(aula)) aulaMap.set(aula, []);
      aulaMap.get(aula)!.push(item);
    }
    return map;
  });

  getCeldas(grupo: string, aula: string): Programacion[] {
    return this.matriz().get(grupo)?.get(aula) ?? [];
  }

  aulaNombre(p: Programacion): string {
    return p.aula_nombre || p.aula || p.aula_rel?.nombre || '';
  }

  escuelasLabel(p: Programacion): string {
    if (!p.escuelas?.length) return '';
    return p.escuelas.map(e => e.nombre_corto ?? e.nombre).join(', ');
  }

  getCeldaColor(prog: Programacion): string {
    if (prog.esta_lleno) return 'bg-red-50 border-red-200';
    return 'bg-emerald-50 border-emerald-200';
  }

  trackByGrupo(_: number, grupo: string) { return grupo; }
  trackByAula(_: number, aula: string)   { return aula; }
  trackByProg(_: number, p: Programacion) { return p.id; }
}
