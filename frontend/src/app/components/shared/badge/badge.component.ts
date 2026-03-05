import { Component, input, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-badge',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './badge.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppBadgeComponent {
  color = input<'indigo' | 'emerald' | 'amber' | 'red' | 'slate' | 'cyan' | 'purple'>('slate');
  size = input<'sm' | 'md' | 'lg'>('md');

  badgeClasses = computed(() => {
    const sizes = {
      sm: "px-2 py-0.5 text-[9px]",
      md: "px-2.5 py-1 text-[10px]",
      lg: "px-3 py-1.5 text-xs"
    };
    const base = `inline-flex items-center rounded-lg font-black uppercase tracking-widest ${sizes[this.size()]} `;
    const colors: Record<string, string> = {
      indigo: "bg-indigo-50 text-indigo-700",
      emerald: "bg-emerald-50 text-emerald-700",
      amber: "bg-amber-50 text-amber-700",
      red: "bg-red-50 text-red-700",
      slate: "bg-slate-100 text-slate-600",
      cyan: "bg-cyan-50 text-cyan-700",
      purple: "bg-purple-50 text-purple-700"
    };
    return base + colors[this.color()];
  });

  dotClasses = computed(() => {
    const colors: Record<string, string> = {
      indigo: "bg-indigo-500",
      emerald: "bg-emerald-500",
      amber: "bg-amber-500",
      red: "bg-red-500",
      slate: "bg-slate-400",
      cyan: "bg-cyan-500",
      purple: "bg-purple-500"
    };
    return colors[this.color()];
  });
}