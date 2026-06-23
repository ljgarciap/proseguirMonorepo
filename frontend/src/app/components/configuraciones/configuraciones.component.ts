import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

interface Configuracion {
  id: number;
  clave: string;
  valor: string | null;
  descripcion: string;
  grupo: string;
  es_secreto: boolean;
  tiene_valor: boolean;
  updated_by: string | null;
  updated_at: string | null;
  // UI state
  editando?: boolean;
  valorTemporal?: string;
  guardando?: boolean;
  guardado?: boolean;
}

const GRUPO_LABELS: Record<string, string> = {
  ia_providers: 'Proveedores IA',
  email: 'Correo Electrónico',
  integraciones: 'Integraciones',
  general: 'General',
};

@Component({
  selector: 'app-configuraciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <div>
        <h2>Configuraciones del Sistema</h2>
        <p>Gestión de claves de API y parámetros de servicios externos. Los valores secretos nunca se muestran.</p>
      </div>
    </div>

    <div class="loading-state" *ngIf="cargando">
      <span class="material-symbols-outlined spin">progress_activity</span>
      <p>Cargando configuraciones...</p>
    </div>

    <div *ngIf="!cargando">
      <div class="grupo-section" *ngFor="let grupo of grupos">
        <div class="grupo-header">
          <span class="material-symbols-outlined">{{ getGrupoIcon(grupo) }}</span>
          <h3>{{ getGrupoLabel(grupo) }}</h3>
          <span class="grupo-count">{{ getGrupoItems(grupo).length }} parámetros</span>
        </div>

        <div class="config-table">
          <div class="config-row header-row">
            <span>Descripción</span>
            <span>Clave</span>
            <span>Valor</span>
            <span>Última modificación</span>
            <span>Acción</span>
          </div>

          <div class="config-row" *ngFor="let cfg of getGrupoItems(grupo)" [class.editing]="cfg.editando">
            <div class="col-desc">
              <span class="desc-text">{{ cfg.descripcion }}</span>
              <span class="badge-secreto" *ngIf="cfg.es_secreto">
                <span class="material-symbols-outlined">lock</span> Secreto
              </span>
            </div>

            <div class="col-clave">
              <code>{{ cfg.clave }}</code>
            </div>

            <div class="col-valor">
              <ng-container *ngIf="!cfg.editando">
                <ng-container *ngIf="cfg.es_secreto">
                  <div class="secret-display" *ngIf="cfg.tiene_valor">
                    <span class="dots">••••••••••••</span>
                    <span class="badge-set">
                      <span class="material-symbols-outlined">check_circle</span> Configurado
                    </span>
                  </div>
                  <span class="empty-badge" *ngIf="!cfg.tiene_valor">Sin configurar</span>
                </ng-container>
                <ng-container *ngIf="!cfg.es_secreto">
                  <span class="valor-text" *ngIf="cfg.tiene_valor">{{ cfg.valor }}</span>
                  <span class="empty-badge" *ngIf="!cfg.tiene_valor">Sin configurar</span>
                </ng-container>
              </ng-container>

              <ng-container *ngIf="cfg.editando">
                <input
                  class="input-valor"
                  [type]="cfg.es_secreto ? 'password' : 'text'"
                  [(ngModel)]="cfg.valorTemporal"
                  [placeholder]="cfg.es_secreto ? 'Ingresa el nuevo valor secreto' : 'Ingresa el valor'"
                  autofocus
                />
              </ng-container>
            </div>

            <div class="col-meta">
              <span class="meta-user" *ngIf="cfg.updated_by">{{ cfg.updated_by }}</span>
              <span class="meta-date" *ngIf="cfg.updated_at">{{ cfg.updated_at | date:'dd/MM/yyyy HH:mm' }}</span>
              <span class="empty-badge" *ngIf="!cfg.updated_by">Sin cambios</span>
            </div>

            <div class="col-action">
              <ng-container *ngIf="!cfg.editando">
                <button class="btn-edit" (click)="iniciarEdicion(cfg)">
                  <span class="material-symbols-outlined">edit</span>
                  Editar
                </button>
              </ng-container>
              <ng-container *ngIf="cfg.editando">
                <button class="btn-save" (click)="guardar(cfg)" [disabled]="cfg.guardando">
                  <span class="material-symbols-outlined" *ngIf="!cfg.guardando">save</span>
                  <span class="material-symbols-outlined spin" *ngIf="cfg.guardando">progress_activity</span>
                  {{ cfg.guardando ? 'Guardando...' : 'Guardar' }}
                </button>
                <button class="btn-cancel" (click)="cancelarEdicion(cfg)" [disabled]="cfg.guardando">
                  <span class="material-symbols-outlined">close</span>
                </button>
              </ng-container>
              <span class="saved-badge" *ngIf="cfg.guardado && !cfg.editando">
                <span class="material-symbols-outlined">check_circle</span> Guardado
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .page-header { margin-bottom: 2rem;
      h2 { margin: 0 0 0.25rem; font-size: 1.5rem; color: #1A202C; }
      p { margin: 0; color: #718096; font-size: 0.9rem; }
    }

    .loading-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 4rem; color: #718096;
      .material-symbols-outlined { font-size: 48px; color: #A0AEC0; }
    }

    .grupo-section { margin-bottom: 2rem; background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden; }

    .grupo-header { display: flex; align-items: center; gap: 12px; padding: 1.25rem 1.5rem; border-bottom: 1px solid #EDF2F7; background: #FAFBFC;
      .material-symbols-outlined { font-size: 22px; color: var(--primary); }
      h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #2D3748; }
      .grupo-count { margin-left: auto; font-size: 0.75rem; color: #A0AEC0; background: #EDF2F7; padding: 2px 10px; border-radius: 12px; font-weight: 600; }
    }

    .config-table { width: 100%; }

    .config-row { display: grid; grid-template-columns: 2fr 1.5fr 2fr 1.5fr 1fr; gap: 1rem; padding: 1rem 1.5rem; align-items: center; border-bottom: 1px solid #EDF2F7;
      &:last-child { border-bottom: none; }
      &.header-row { font-size: 0.7rem; font-weight: 700; color: #A0AEC0; text-transform: uppercase; letter-spacing: 0.5px; background: #F8FAFC; padding: 0.6rem 1.5rem; }
      &.editing { background: #FFFBEB; }
    }

    .col-desc { display: flex; flex-direction: column; gap: 4px;
      .desc-text { font-size: 0.9rem; font-weight: 500; color: #2D3748; }
      .badge-secreto { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 600; color: #744210; background: #FEFCBF; padding: 2px 8px; border-radius: 6px; width: fit-content;
        .material-symbols-outlined { font-size: 12px; }
      }
    }

    .col-clave code { font-size: 0.78rem; background: #EDF2F7; color: #4A5568; padding: 3px 8px; border-radius: 6px; font-family: monospace; word-break: break-all; }

    .col-valor {
      .secret-display { display: flex; align-items: center; gap: 10px;
        .dots { font-size: 1.1rem; color: #A0AEC0; letter-spacing: 2px; }
        .badge-set { display: flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 600; color: #38A169;
          .material-symbols-outlined { font-size: 14px; }
        }
      }
      .valor-text { font-size: 0.85rem; color: #4A5568; word-break: break-all; }
      .empty-badge { font-size: 0.75rem; color: #FC8181; background: #FFF5F5; padding: 3px 8px; border-radius: 6px; font-weight: 600; }
      .input-valor { width: 100%; padding: 8px 12px; border: 2px solid var(--primary); border-radius: 8px; font-size: 0.85rem; outline: none; background: white; }
    }

    .col-meta { display: flex; flex-direction: column; gap: 2px;
      .meta-user { font-size: 0.8rem; font-weight: 600; color: #4A5568; }
      .meta-date { font-size: 0.75rem; color: #A0AEC0; }
    }

    .col-action { display: flex; align-items: center; gap: 8px; }

    .btn-edit { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: #EBF8FF; color: #2B6CB0; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
      .material-symbols-outlined { font-size: 16px; }
      &:hover { background: #BEE3F8; }
    }

    .btn-save { display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: #48BB78; color: white; border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
      .material-symbols-outlined { font-size: 16px; }
      &:hover:not(:disabled) { background: #38A169; }
      &:disabled { opacity: 0.6; cursor: not-allowed; }
    }

    .btn-cancel { display: flex; align-items: center; padding: 6px; background: #EDF2F7; color: #718096; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s;
      .material-symbols-outlined { font-size: 18px; }
      &:hover:not(:disabled) { background: #E2E8F0; }
      &:disabled { opacity: 0.6; cursor: not-allowed; }
    }

    .saved-badge { display: flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 600; color: #38A169;
      .material-symbols-outlined { font-size: 16px; }
    }

    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  `]
})
export class ConfiguracionesComponent implements OnInit {
  configs: Configuracion[] = [];
  grupos: string[] = [];
  cargando = true;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.cargar();
  }

  cargar() {
    this.cargando = true;
    this.http.get<Configuracion[]>(`${environment.apiUrl}/configuraciones`).subscribe({
      next: (data) => {
        this.configs = data.map(c => ({ ...c, editando: false, valorTemporal: '', guardando: false, guardado: false }));
        this.grupos = [...new Set(this.configs.map(c => c.grupo))];
        this.cargando = false;
      },
      error: () => { this.cargando = false; }
    });
  }

  getGrupoItems(grupo: string): Configuracion[] {
    return this.configs.filter(c => c.grupo === grupo);
  }

  getGrupoLabel(grupo: string): string {
    return GRUPO_LABELS[grupo] ?? grupo;
  }

  getGrupoIcon(grupo: string): string {
    const icons: Record<string, string> = {
      ia_providers: 'auto_awesome',
      email: 'mail',
      integraciones: 'hub',
      general: 'settings',
    };
    return icons[grupo] ?? 'settings';
  }

  iniciarEdicion(cfg: Configuracion) {
    this.configs.forEach(c => { if (c.editando) this.cancelarEdicion(c); });
    cfg.editando = true;
    cfg.valorTemporal = cfg.es_secreto ? '' : (cfg.valor ?? '');
    cfg.guardado = false;
  }

  cancelarEdicion(cfg: Configuracion) {
    cfg.editando = false;
    cfg.valorTemporal = '';
  }

  guardar(cfg: Configuracion) {
    if (cfg.guardando) return;
    cfg.guardando = true;

    this.http.put<any>(`${environment.apiUrl}/configuraciones/${cfg.id}`, { valor: cfg.valorTemporal }).subscribe({
      next: (res) => {
        cfg.guardando = false;
        cfg.editando = false;
        cfg.tiene_valor = res.tiene_valor;
        cfg.updated_by = res.updated_by;
        cfg.updated_at = res.updated_at;
        cfg.valor = cfg.es_secreto ? null : cfg.valorTemporal ?? null;
        cfg.guardado = true;
        setTimeout(() => { cfg.guardado = false; }, 3000);
      },
      error: () => {
        cfg.guardando = false;
      }
    });
  }
}
