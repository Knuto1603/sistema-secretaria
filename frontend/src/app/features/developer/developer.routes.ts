import { Routes } from '@angular/router';
import { developerGuard } from './guards/developer.guard';

export const DEVELOPER_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./components/developer-home/developer-home.component').then(
        m => m.DeveloperHomeComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'health',
    loadComponent: () =>
      import('./components/health-panel/health-panel.component').then(
        m => m.HealthPanelComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'activity',
    loadComponent: () =>
      import('./components/activity-log/activity-log.component').then(
        m => m.ActivityLogComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'emails',
    loadComponent: () =>
      import('./components/email-viewer/email-viewer.component').then(
        m => m.EmailViewerComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'settings',
    loadComponent: () =>
      import('./components/system-settings/system-settings.component').then(
        m => m.SystemSettingsComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'maintenance',
    loadComponent: () =>
      import('./components/maintenance-tools/maintenance-tools.component').then(
        m => m.MaintenanceToolsComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'api-explorer',
    loadComponent: () =>
      import('./components/api-explorer/api-explorer.component').then(
        m => m.ApiExplorerComponent
      ),
    canActivate: [developerGuard],
  },
  {
    path: 'impersonation',
    loadComponent: () =>
      import('./components/impersonation/impersonation.component').then(
        m => m.ImpersonationComponent
      ),
    canActivate: [developerGuard],
  },
];
