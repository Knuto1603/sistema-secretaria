import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DownloadManagerService } from '../../services/download-manager.service';

@Component({
  selector: 'app-download-toast',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './download-toast.component.html',
})
export class DownloadToastComponent {
  dm = inject(DownloadManagerService);
}
