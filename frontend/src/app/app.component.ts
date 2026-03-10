import { Component, inject, OnInit } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ErrorModalComponent } from './core/components/error-modal/error-modal.component';
import { AuthService } from '@core/auth/services/auth.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, ErrorModalComponent],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent implements OnInit {
  title = 'frontend';
  private authService = inject(AuthService);

  ngOnInit(): void {
    if (this.authService.isAuthenticated()) {
      this.authService.refreshCurrentUser().subscribe();
    }
  }
}
