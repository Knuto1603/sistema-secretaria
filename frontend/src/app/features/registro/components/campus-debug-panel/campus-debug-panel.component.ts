import { Component, input, output } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface CampusDebugResult {
  actualizados: number;
  omitidos: number;
  detalle: { codigo: string; nombre: string; seccion: any; motivo: string }[];
  no_en_campus: { id: string; codigo: string; nombre: string; seccion: any; grupo: string; escuela: string }[];
}

@Component({
  selector: 'app-campus-debug-panel',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './campus-debug-panel.component.html'
})
export class CampusDebugPanelComponent {
  resultado = input.required<CampusDebugResult>();

  cerrar = output<void>();

  descargarCSV(): void {
    const r = this.resultado();
    const filas: string[] = ['Caso,Código,Nombre,Sección,Grupo,Escuela,Motivo'];

    for (const item of r.detalle) {
      filas.push(`"En Campus / sin programación en sistema","${item.codigo}","${item.nombre}","${item.seccion ?? ''}","","","${item.motivo}"`);
    }
    for (const item of r.no_en_campus) {
      filas.push(`"En sistema / no está en Campus","${item.codigo}","${item.nombre}","${item.seccion ?? ''}","${item.grupo}","${item.escuela}",""`);
    }

    if (filas.length <= 1) return;
    const blob = new Blob(['\ufeff' + filas.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'debug_campus.csv';
    a.click();
    URL.revokeObjectURL(url);
  }
}
