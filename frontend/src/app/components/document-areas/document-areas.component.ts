import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

interface DocumentArea {
  id: number;
  nombre: string;
  codigo: string;
  activo: boolean;
  // UI state
  editando?: boolean;
  nombreTemporal?: string;
  codigoTemporal?: string;
  guardando?: boolean;
}

@Component({
  selector: 'app-document-areas',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page-header">
      <div>
        <h2>Áreas de Aprobación</h2>
        <p>Catálogo de áreas disponibles para armar la ruta de aprobación en la Bandeja Interna. El código debe coincidir con un rol real de usuario (ej: contable, gerente, operativo) para que ese paso sea gestionable.</p>
      </div>
      <button class="btn-add" (click)="iniciarCreacion()" *ngIf="!creando">
        <span class="material-symbols-outlined">add_circle</span> Nueva Área
      </button>
    </div>

    <div class="loading-state" *ngIf="cargando">
      <span class="material-symbols-outlined spin">progress_activity</span>
      <p>Cargando áreas...</p>
    </div>

    <div class="empty-state" *ngIf="!cargando && areas.length === 0 && !creando">
      <span class="material-symbols-outlined">domain_disabled</span>
      <p>No hay áreas registradas todavía.</p>
      <small>Sin áreas, nadie va a poder crear un envío nuevo en la Bandeja Interna.</small>
    </div>

    <div class="areas-table" *ngIf="!cargando && (areas.length > 0 || creando)">
      <div class="area-row header-row">
        <span>Nombre</span>
        <span>Código</span>
        <span>Estado</span>
        <span>Acción</span>
      </div>

      <div class="area-row" *ngIf="creando" [class.editing]="true">
        <div class="col-nombre">
          <input class="input-field" [(ngModel)]="nuevaArea.nombre" placeholder="Ej: Auditoría" autofocus>
        </div>
        <div class="col-codigo">
          <input class="input-field" [(ngModel)]="nuevaArea.codigo" placeholder="ej: auditoria">
        </div>
        <div class="col-estado">
          <span class="badge-activo">Activo</span>
        </div>
        <div class="col-action">
          <button class="btn-save" (click)="guardarNueva()" [disabled]="guardandoNueva">
            <span class="material-symbols-outlined" *ngIf="!guardandoNueva">save</span>
            <span class="material-symbols-outlined spin" *ngIf="guardandoNueva">progress_activity</span>
          </button>
          <button class="btn-cancel" (click)="cancelarCreacion()" [disabled]="guardandoNueva">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
      </div>

      <div class="area-row" *ngFor="let area of areas" [class.editing]="area.editando" [class.inactive]="!area.activo">
        <div class="col-nombre">
          <span *ngIf="!area.editando" class="nombre-text">{{ area.nombre }}</span>
          <input *ngIf="area.editando" class="input-field" [(ngModel)]="area.nombreTemporal">
        </div>
        <div class="col-codigo">
          <code *ngIf="!area.editando">{{ area.codigo }}</code>
          <input *ngIf="area.editando" class="input-field" [(ngModel)]="area.codigoTemporal">
        </div>
        <div class="col-estado">
          <span class="badge-activo" *ngIf="area.activo">Activo</span>
          <span class="badge-inactivo" *ngIf="!area.activo">Inactivo</span>
        </div>
        <div class="col-action">
          <ng-container *ngIf="!area.editando">
            <button class="btn-edit" (click)="iniciarEdicion(area)" title="Editar">
              <span class="material-symbols-outlined">edit</span>
            </button>
            <button class="btn-toggle" (click)="toggleActivo(area)" [title]="area.activo ? 'Desactivar' : 'Activar'">
              <span class="material-symbols-outlined">{{ area.activo ? 'toggle_on' : 'toggle_off' }}</span>
            </button>
            <button class="btn-delete" (click)="eliminar(area)" title="Eliminar">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </ng-container>
          <ng-container *ngIf="area.editando">
            <button class="btn-save" (click)="guardarEdicion(area)" [disabled]="area.guardando">
              <span class="material-symbols-outlined" *ngIf="!area.guardando">save</span>
              <span class="material-symbols-outlined spin" *ngIf="area.guardando">progress_activity</span>
            </button>
            <button class="btn-cancel" (click)="cancelarEdicion(area)" [disabled]="area.guardando">
              <span class="material-symbols-outlined">close</span>
            </button>
          </ng-container>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; gap: 1rem;
      h2 { margin: 0 0 0.25rem; font-size: 1.5rem; color: #1A202C; }
      p { margin: 0; color: #718096; font-size: 0.85rem; max-width: 640px; }
    }

    .btn-add { display: flex; align-items: center; gap: 6px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap;
      .material-symbols-outlined { font-size: 18px; }
      &:hover { opacity: 0.9; }
    }

    .loading-state, .empty-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 4rem; color: #718096; text-align: center;
      .material-symbols-outlined { font-size: 48px; color: #A0AEC0; }
      p { margin: 0; font-size: 1rem; }
      small { font-size: 0.8rem; color: #A0AEC0; }
    }

    .areas-table { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden; }

    .area-row { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1.2fr; gap: 1rem; padding: 1rem 1.5rem; align-items: center; border-bottom: 1px solid #EDF2F7;
      &:last-child { border-bottom: none; }
      &.header-row { font-size: 0.7rem; font-weight: 700; color: #A0AEC0; text-transform: uppercase; letter-spacing: 0.5px; background: #F8FAFC; padding: 0.6rem 1.5rem; }
      &.editing { background: #FFFBEB; }
      &.inactive { opacity: 0.6; }
    }

    .nombre-text { font-size: 0.9rem; font-weight: 500; color: #2D3748; }
    .col-codigo code { font-size: 0.78rem; background: #EDF2F7; color: #4A5568; padding: 3px 8px; border-radius: 6px; font-family: monospace; }

    .badge-activo { font-size: 0.75rem; font-weight: 600; color: #38A169; background: #F0FFF4; padding: 3px 10px; border-radius: 12px; }
    .badge-inactivo { font-size: 0.75rem; font-weight: 600; color: #A0AEC0; background: #F7FAFC; padding: 3px 10px; border-radius: 12px; }

    .input-field { width: 100%; padding: 6px 10px; border: 2px solid var(--primary); border-radius: 8px; font-size: 0.85rem; outline: none; background: white; }

    .col-action { display: flex; align-items: center; gap: 6px; }

    .btn-edit, .btn-toggle, .btn-save, .btn-cancel, .btn-delete { display: flex; align-items: center; padding: 6px; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s;
      .material-symbols-outlined { font-size: 18px; }
      &:disabled { opacity: 0.6; cursor: not-allowed; }
    }
    .btn-edit { background: #EBF8FF; color: #2B6CB0; &:hover { background: #BEE3F8; } }
    .btn-toggle { background: #F7FAFC; color: #4A5568; &:hover { background: #EDF2F7; } }
    .btn-save { background: #48BB78; color: white; &:hover:not(:disabled) { background: #38A169; } }
    .btn-cancel { background: #EDF2F7; color: #718096; &:hover:not(:disabled) { background: #E2E8F0; } }
    .btn-delete { background: #FFF5F5; color: #E53E3E; &:hover { background: #FED7D7; } }

    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  `]
})
export class DocumentAreasComponent implements OnInit {
  areas: DocumentArea[] = [];
  cargando = true;

  creando = false;
  guardandoNueva = false;
  nuevaArea = { nombre: '', codigo: '' };

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.cargar();
  }

  cargar() {
    this.cargando = true;
    this.http.get<DocumentArea[]>(`${environment.apiUrl}/document-areas`).subscribe({
      next: (data) => {
        this.areas = data.map(a => ({ ...a, editando: false, guardando: false }));
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        Swal.fire('Error', 'No se pudieron cargar las áreas.', 'error');
      }
    });
  }

  iniciarCreacion() {
    this.creando = true;
    this.nuevaArea = { nombre: '', codigo: '' };
  }

  cancelarCreacion() {
    this.creando = false;
  }

  guardarNueva() {
    if (!this.nuevaArea.nombre.trim() || !this.nuevaArea.codigo.trim()) {
      Swal.fire('Faltan datos', 'Nombre y código son obligatorios.', 'warning');
      return;
    }

    this.guardandoNueva = true;
    this.http.post<DocumentArea>(`${environment.apiUrl}/document-areas`, this.nuevaArea).subscribe({
      next: () => {
        this.guardandoNueva = false;
        this.creando = false;
        this.cargar();
        Swal.fire('¡Creada!', 'El área fue agregada al catálogo.', 'success');
      },
      error: (err) => {
        this.guardandoNueva = false;
        Swal.fire('Error', err.error?.message || 'No se pudo crear el área.', 'error');
      }
    });
  }

  iniciarEdicion(area: DocumentArea) {
    this.areas.forEach(a => { if (a.editando) a.editando = false; });
    area.editando = true;
    area.nombreTemporal = area.nombre;
    area.codigoTemporal = area.codigo;
  }

  cancelarEdicion(area: DocumentArea) {
    area.editando = false;
  }

  guardarEdicion(area: DocumentArea) {
    area.guardando = true;
    this.http.put<DocumentArea>(`${environment.apiUrl}/document-areas/${area.id}`, {
      nombre: area.nombreTemporal,
      codigo: area.codigoTemporal,
    }).subscribe({
      next: (res) => {
        area.guardando = false;
        area.editando = false;
        area.nombre = res.nombre;
        area.codigo = res.codigo;
      },
      error: (err) => {
        area.guardando = false;
        Swal.fire('Error', err.error?.message || 'No se pudo actualizar el área.', 'error');
      }
    });
  }

  toggleActivo(area: DocumentArea) {
    this.http.put<DocumentArea>(`${environment.apiUrl}/document-areas/${area.id}`, {
      activo: !area.activo,
    }).subscribe({
      next: (res) => {
        area.activo = res.activo;
      },
      error: () => {
        Swal.fire('Error', 'No se pudo cambiar el estado del área.', 'error');
      }
    });
  }

  eliminar(area: DocumentArea) {
    Swal.fire({
      title: '¿Eliminar área?',
      text: `Si "${area.nombre}" ya tiene pasos de aprobación asociados (histórico), se desactivará en lugar de eliminarse para no perder trazabilidad.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, continuar',
      confirmButtonColor: '#e53e3e'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete<any>(`${environment.apiUrl}/document-areas/${area.id}`).subscribe({
          next: (res) => {
            Swal.fire(res.area ? 'Desactivada' : 'Eliminada', res.message, 'success');
            this.cargar();
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'No se pudo eliminar el área.', 'error');
          }
        });
      }
    });
  }
}
