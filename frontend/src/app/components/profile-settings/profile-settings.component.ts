import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-profile-settings',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="settings-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Mi Perfil</h1>
          <p>Gestiona tu información personal y seguridad de la cuenta.</p>
        </div>
      </header>

      <div class="settings-grid">
        <!-- User Info Card -->
        <div class="card profile-info-card">
          <div class="card-header">
            <h3>Información Personal</h3>
          </div>
          <div class="card-body">
            <div class="user-avatar-large">
               {{ authService.getActiveRole()?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="info-group">
              <label>Nombre Completo</label>
              <div class="value">{{ user?.name }}</div>
            </div>
            <div class="info-group">
              <label>Correo Electrónico</label>
              <div class="value">{{ user?.email }}</div>
            </div>
            <div class="info-group">
              <label>Rol Asignado</label>
              <div class="value badge-role">{{ authService.getActiveRole() | titlecase }}</div>
            </div>
          </div>
        </div>

        <!-- Password Change Card -->
        <div class="card password-card">
          <div class="card-header">
            <h3>Cambiar Contraseña</h3>
          </div>
          <form (ngSubmit)="onChangePassword()" #pwdForm="ngForm" class="card-body">
            <div class="pro-input-group">
              <label>Contraseña Actual</label>
              <input type="password" [(ngModel)]="pwdData.current_password" name="current_password" required class="pro-input">
            </div>
            <div class="pro-input-group">
              <label>Nueva Contraseña</label>
              <input type="password" [(ngModel)]="pwdData.new_password" name="new_password" required minlength="8" class="pro-input">
            </div>
            <div class="pro-input-group">
              <label>Confirmar Nueva Contraseña</label>
              <input type="password" [(ngModel)]="pwdData.new_password_confirmation" name="new_password_confirmation" required class="pro-input">
            </div>

            <div class="status-msg success" *ngIf="successMessage">
              <span class="material-symbols-outlined">check_circle</span>
              {{ successMessage }}
            </div>
            <div class="status-msg error" *ngIf="errorMessage">
              <span class="material-symbols-outlined">error</span>
              {{ errorMessage }}
            </div>

            <button type="submit" class="btn-pro primary" [disabled]="isLoading || !pwdForm.valid || pwdData.new_password !== pwdData.new_password_confirmation">
              <span class="material-symbols-outlined">key</span>
              {{ isLoading ? 'Actualizando...' : 'Actualizar Contraseña' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .settings-container { /* Padding handled by global viewport */ }
    .settings-grid {
      display: grid;
      grid-template-columns: 1fr 1.5fr;
      gap: 2rem;
      margin-top: 1rem;
    }

    .profile-info-card {
      .card-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.5rem;
        padding: 2.5rem 2rem;
      }
    }

    .user-avatar-large {
      width: 110px;
      height: 110px;
      background: var(--grad-primary);
      color: white;
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3.5rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-lg);
    }

    .info-group {
      width: 100%;
      label { display: block; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 6px; letter-spacing: 0.5px; }
      .value { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
      .badge-role {
        display: inline-block;
        background: #EBF4FF;
        color: var(--primary);
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
      }
    }

    .password-card {
      .card-body {
        padding: 2.5rem;
      }
      .btn-pro {
        margin-top: 1rem;
        width: 100%;
      }
    }

    .status-msg {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 1rem;
      &.success { background: #F0FFF4; color: #38A169; border: 1px solid #C6F6D5; }
      &.error { background: #FFF5F5; color: #E53E3E; border: 1px solid #FED7D7; }
    }
  `]
})
export class ProfileSettingsComponent implements OnInit {
  user: any;
  pwdData = {
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  };
  isLoading = false;
  successMessage = '';
  errorMessage = '';

  constructor(public authService: AuthService, private http: HttpClient) {}

  ngOnInit(): void {
    this.user = this.authService.getUser();
  }

  onChangePassword(): void {
    this.isLoading = true;
    this.successMessage = '';
    this.errorMessage = '';

    this.http.post(`${environment.apiUrl}/change-password`, {
        current_password: this.pwdData.current_password,
        new_password: this.pwdData.new_password,
        new_password_confirmation: this.pwdData.new_password_confirmation
    }).subscribe({
      next: () => {
        this.isLoading = false;
        this.successMessage = 'Contraseña actualizada con éxito.';
        this.pwdData = { current_password: '', new_password: '', new_password_confirmation: '' };
      },
      error: (err) => {
        this.isLoading = false;
        this.errorMessage = err.error?.message || 'Error al actualizar la contraseña.';
      }
    });
  }
}
