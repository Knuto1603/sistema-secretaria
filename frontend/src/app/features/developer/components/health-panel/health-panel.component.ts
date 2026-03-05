import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { DeveloperService } from '../../services/developer.service';
import { HealthStatus } from '../../models/developer.models';

@Component({
  selector: 'app-health-panel',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './health-panel.component.html',
})
export class HealthPanelComponent implements OnInit {
  private devService = inject(DeveloperService);

  health = signal<HealthStatus | null>(null);
  loading = signal(false);
  error = signal<string | null>(null);

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set(null);
    this.devService.getHealth().subscribe({
      next: data => { this.health.set(data); this.loading.set(false); },
      error: () => { this.error.set('Error al obtener el estado del servidor'); this.loading.set(false); },
    });
  }
}
