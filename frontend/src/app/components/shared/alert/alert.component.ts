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
    const base = "p-4 rounded-2xl mb-4 border transition-all animate-in fade-in slide-in-from-top-4 ";
    const types = {
      success: "bg-emerald-50 border-emerald-100 text-emerald-800",
      error: "bg-red-50 border-red-100 text-red-800",
      info: "bg-indigo-50 border-indigo-100 text-indigo-800"
    };
    return base + types[this.type()];
  });
}