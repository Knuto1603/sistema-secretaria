import { Component, inject } from '@angular/core';
import { Location } from '@angular/common';
import { ErrorModalService } from '../../services/error-modal.service';

@Component({
  selector: 'app-error-modal',
  standalone: true,
  templateUrl: './error-modal.component.html',
})
export class ErrorModalComponent {
  protected errorModal = inject(ErrorModalService);
  private location = inject(Location);

  accept(): void {
    const goBack = this.errorModal.config().goBack;
    this.errorModal.hide();
    if (goBack) {
      this.location.back();
    }
  }
}
