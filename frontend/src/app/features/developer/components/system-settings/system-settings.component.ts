import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DeveloperService } from '../../services/developer.service';
import { SystemSetting } from '../../models/developer.models';

interface SettingEdit {
  key: string;
  editValue: string;
  saving: boolean;
  saved: boolean;
}

@Component({
  selector: 'app-system-settings',
  standalone: true,
  imports: [CommonModule, RouterLink, FormsModule],
  templateUrl: './system-settings.component.html',
})
export class SystemSettingsComponent implements OnInit {
  private devService = inject(DeveloperService);

  settings = signal<SystemSetting[]>([]);
  loading = signal(false);
  editing = signal<Record<string, SettingEdit>>({});

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.devService.getSettings().subscribe({
      next: data => { this.settings.set(data); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  get grupos(): string[] {
    return [...new Set(this.settings().map(s => s.grupo))];
  }

  settingsByGrupo(grupo: string): SystemSetting[] {
    return this.settings().filter(s => s.grupo === grupo);
  }

  startEdit(setting: SystemSetting): void {
    this.editing.update(state => ({
      ...state,
      [setting.key]: { key: setting.key, editValue: setting.value ?? '', saving: false, saved: false }
    }));
  }

  cancelEdit(key: string): void {
    this.editing.update(state => {
      const next = { ...state };
      delete next[key];
      return next;
    });
  }

  save(key: string): void {
    const editState = this.editing()[key];
    if (!editState) return;

    this.editing.update(state => ({ ...state, [key]: { ...state[key], saving: true } }));

    this.devService.updateSetting(key, editState.editValue).subscribe({
      next: updated => {
        this.settings.update(items => items.map(s => s.key === key ? updated : s));
        this.editing.update(state => ({ ...state, [key]: { ...state[key], saving: false, saved: true } }));
        setTimeout(() => this.cancelEdit(key), 1200);
      },
      error: () => {
        this.editing.update(state => ({ ...state, [key]: { ...state[key], saving: false } }));
      },
    });
  }

  isEditing(key: string): boolean {
    return key in this.editing();
  }

  getEdit(key: string): SettingEdit | null {
    return this.editing()[key] ?? null;
  }
}
