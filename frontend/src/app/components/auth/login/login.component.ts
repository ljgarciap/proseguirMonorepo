import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { AuthService } from '../../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="login-wrapper">
      <div class="login-card card">
        <div class="login-header">
          <img src="assets/logopsl.png" alt="Proseguir" class="login-logo">
          <h2>Acceso al Sistema</h2>
          <p>Ingresa tus credenciales para continuar.</p>
        </div>

        <form (ngSubmit)="onLogin()" #loginForm="ngForm" class="login-form" *ngIf="!showRoleSelector">
          <div class="pro-input-group">
            <label>Número de Documento</label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined">badge</span>
              <input type="text" [(ngModel)]="credentials.numero_documento" name="numero_documento" required 
                     placeholder="Ingresa tu documento" class="pro-input" inputmode="numeric" pattern="[0-9]*"
                     (keypress)="onlyNumbers($event)">
            </div>
          </div>

          <div class="pro-input-group">
            <label>Contraseña</label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined">lock</span>
              <input type="password" [(ngModel)]="credentials.password" name="password" required placeholder="••••••••" class="pro-input">
            </div>
          </div>

            <div class="expired-msg" *ngIf="sessionExpired">
            <span class="material-symbols-outlined">timer_off</span>
            Tu sesión expiró. Por favor inicia sesión de nuevo.
          </div>

          <div class="error-msg" *ngIf="errorMessage">
             <span class="material-symbols-outlined">error</span>
             {{ errorMessage }}
          </div>

          <button type="submit" class="btn-pro primary full-width" [disabled]="isLoading || !loginForm.form.valid">
            <span class="material-symbols-outlined" *ngIf="!isLoading">login</span>
            {{ isLoading ? 'Autenticando...' : 'Iniciar Sesión' }}
          </button>
        </form>

        <div class="role-selector" *ngIf="showRoleSelector">
          <h3>Selecciona un Perfil</h3>
          <p>Tu usuario tiene múltiples roles asignados.</p>
          <div class="role-list">
             <button *ngFor="let role of availableRoles" (click)="selectRole(role)" class="btn-role">
                <span class="material-symbols-outlined">person_pin</span>
                {{ role.split('_').join(' ') | titlecase }}
                <span class="material-symbols-outlined chevron">chevron_right</span>
             </button>
          </div>
        </div>

        <div class="login-footer">
          <p>&copy; 2026 Proseguir - Todos los derechos reservados.</p>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .login-wrapper {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: var(--bg-app);
      background-image: radial-gradient(circle at 20% 20%, rgba(14, 118, 188, 0.05) 0%, transparent 40%),
                        radial-gradient(circle at 80% 80%, rgba(26, 59, 139, 0.05) 0%, transparent 40%);
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      padding: 3rem 2.5rem;
      border-radius: 24px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      border: none;
      background: white;
    }

    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
      .login-logo { max-width: 180px; height: auto; margin-bottom: 1.5rem; }
      h2 { color: var(--primary); margin: 0; font-size: 1.5rem; }
      p { color: var(--text-muted); font-size: 0.9rem; margin-top: 8px; }
    }

    .login-form {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .input-with-icon {
      position: relative;
      display: flex;
      align-items: center;
      .material-symbols-outlined {
        position: absolute;
        left: 12px;
        color: #A0AEC0;
        font-size: 20px;
      }
      .pro-input {
        padding-left: 40px;
        width: 100%;
      }
    }

    .full-width { width: 100%; }

    .expired-msg {
      display: flex; align-items: center; gap: 8px;
      background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A;
      border-radius: 10px; padding: 10px 14px; font-size: 0.85rem; font-weight: 500;
      .material-symbols-outlined { font-size: 18px; flex-shrink: 0; }
    }

    .error-msg {
      background: #FFF5F5;
      color: var(--danger);
      padding: 10px 12px;
      border-radius: 8px;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 8px;
      border: 1px solid #FED7D7;
      .material-symbols-outlined { font-size: 18px; }
    }

    .login-footer {
      margin-top: 3rem;
      text-align: center;
      p { font-size: 0.75rem; color: #A0AEC0; }
    }

    .role-selector {
      text-align: center;
      h3 { color: var(--primary); margin-bottom: 8px; }
      p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; }
    }
    .role-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .btn-role {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 1rem;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      font-weight: 600;
      color: var(--text-main);
      cursor: pointer;
      transition: all 0.2s;
      text-align: left;
      &:hover { border-color: var(--primary); background: #EBF4FF; color: var(--primary); }
      .chevron { margin-left: auto; font-size: 18px; color: #A0AEC0; }
    }
  `]
})
export class LoginComponent implements OnInit {
  credentials = { numero_documento: '', password: '' };
  isLoading = false;
  errorMessage = '';
  sessionExpired = false;
  showRoleSelector = false;
  availableRoles: string[] = [];

  constructor(
    private http: HttpClient,
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.sessionExpired = this.route.snapshot.queryParamMap.get('expired') === '1';
  }

  onLogin(): void {
    this.isLoading = true;
    this.errorMessage = '';

    this.http.post<any>(`${environment.apiUrl}/login`, this.credentials).subscribe({
      next: (response) => {
        this.authService.login(response.token, response.user, response.roles);
        
        if (response.roles && response.roles.length > 1) {
          this.availableRoles = response.roles;
          this.showRoleSelector = true;
          this.isLoading = false;
        } else {
          this.router.navigate(['/']);
        }
      },
      error: (err) => {
        this.isLoading = false;
        this.errorMessage = err.error?.message || 'Error de conexión con el servidor.';
      }
    });
  }

  selectRole(role: string): void {
    this.authService.setActiveRole(role);
    this.router.navigate(['/']);
  }

  onlyNumbers(event: any): boolean {
    const charCode = (event.which) ? event.which : event.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
      return false;
    }
    return true;
  }
}
