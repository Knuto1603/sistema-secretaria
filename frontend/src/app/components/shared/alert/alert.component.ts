import { Component, input, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-alert',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './alert.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppAlertComponent {
  type = input<'success' | 'error' | 'info'>('info');
  message = input.required<string>();

  alertClasses = computed(() => {
    const base = "p-4 rounded-md mb-4 border transition-all animate-in fade-in slide-in-from-top-4 ";
    const types = {
      success: "bg-white border-slate-200 border-l-4 border-l-emerald-500 text-slate-700",
      error:   "bg-white border-slate-200 border-l-4 border-l-red-500 text-slate-700",
      info:    "bg-white border-slate-200 border-l-4 border-l-indigo-500 text-slate-700"
    };
    return base + types[this.type()];
  });
}