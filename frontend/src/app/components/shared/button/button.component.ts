import { Component, input, output, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-btn',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './button.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppButtonComponent {
  variant = input<'primary' | 'secondary' | 'danger' | 'success' | 'ghost'>('primary');
  loading = input<boolean>(false);
  disabled = input<boolean>(false);
  clicked = output<MouseEvent>();

  buttonClasses = computed(() => {
    const base = "inline-flex items-center justify-center px-5 py-2.5 rounded text-[10px] font-semibold uppercase tracking-widest transition-all active:scale-95 disabled:opacity-50 disabled:pointer-events-none ";
    const variants = {
      primary: "bg-indigo-600 text-white hover:bg-indigo-700",
      secondary: "bg-white border border-slate-200 text-slate-600 hover:bg-slate-50",
      danger: "bg-red-500 text-white hover:bg-red-600",
      success: "bg-emerald-500 text-white hover:bg-emerald-600",
      ghost: "bg-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-700"
    };
    return base + variants[this.variant()];
  });
}