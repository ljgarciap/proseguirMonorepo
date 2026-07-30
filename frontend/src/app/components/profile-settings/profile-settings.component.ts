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
          <form (ngSubmit)="onSaveProfile()" #profileForm="ngForm" class="card-body">
            <div class="user-avatar-large">
               {{ authService.getActiveRole()?.charAt(0)?.toUpperCase() }}
            </div>

            <div class="pro-input-group full-width">
              <label>Nombre Completo</label>
              <input type="text" [(ngModel)]="profileData.name" name="name" required minlength="2" maxlength="255" class="pro-input">
            </div>

            <div class="info-group">
              <label>Correo Electrónico</label>
              <div class="value">{{ user?.email }}</div>
            </div>
            <div class="info-group">
              <label>Rol Asignado</label>
              <div class="value badge-role">{{ authService.getActiveRole() | titlecase }}</div>
            </div>

            <div class="pro-input-group full-width">
              <label>Duración de la Sesión</label>
              <select [(ngModel)]="profileData.session_duration_minutes" name="session_duration_minutes" class="pro-input">
                <option *ngFor="let opt of sessionDurationOptions" [ngValue]="opt.value">{{ opt.label }}</option>
              </select>
              <small class="field-hint">Aplica desde el próximo inicio de sesión.</small>
            </div>

            <div class="status-msg success" *ngIf="profileSuccessMessage">
              <span class="material-symbols-outlined">check_circle</span>
              {{ profileSuccessMessage }}
            </div>
            <div class="status-msg error" *ngIf="profileErrorMessage">
              <span class="material-symbols-outlined">error</span>
              {{ profileErrorMessage }}
            </div>

            <button type="submit" class="btn-pro primary" [disabled]="isProfileLoading || !profileForm.valid">
              <span class="material-symbols-outlined">save</span>
              {{ isProfileLoading ? 'Guardando...' : 'Guardar Cambios' }}
            </button>
          </form>
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

    .pro-input-group.full-width {
      width: 100%;
      text-align: left;
      label { display: block; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 6px; letter-spacing: 0.5px; }
    }

    .field-hint {
      display: block;
      margin-top: 6px;
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .profile-info-card .btn-pro {
      margin-top: 0.5rem;
      width: 100%;
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

  // SCRUM-161: lista cerrada de opciones válidas — debe reflejar
  // exactamente config('auth.session_duration_options') en el backend.
  // Server-side es la fuente de verdad (AuthController::updateProfile valida
  // contra la config), esta lista es solo para render del selector.
  sessionDurationOptions = [
    { value: 30, label: '30 minutos' },
    { value: 60, label: '1 hora' },
    { value: 240, label: '4 horas' },
    { value: 480, label: '8 horas' },
    { value: 1440, label: '24 horas' },
  ];

  profileData = {
    name: '',
    session_duration_minutes: 480,
  };
  isProfileLoading = false;
  profileSuccessMessage = '';
  profileErrorMessage = '';

  constructor(public authService: AuthService, private http: HttpClient) {}

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.profileData.name = this.user?.name ?? '';
    this.profileData.session_duration_minutes = this.user?.session_duration_minutes ?? 480;
  }

  onSaveProfile(): void {
    this.isProfileLoading = true;
    this.profileSuccessMessage = '';
    this.profileErrorMessage = '';

    this.http.patch<{ message: string; user: any }>(`${environment.apiUrl}/profile`, {
      name: this.profileData.name,
      session_duration_minutes: this.profileData.session_duration_minutes,
    }).subscribe({
      next: (res) => {
        this.isProfileLoading = false;
        this.profileSuccessMessage = 'Perfil actualizado con éxito.';
        this.user = res.user;
        this.authService.setUser(res.user);
      },
      error: (err) => {
        this.isProfileLoading = false;
        this.profileErrorMessage = err.error?.message || 'Error al actualizar el perfil.';
      }
    });
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
