import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

/**
 * Motor paramétrico de Roles y Permisos — Fase 1 + Fase 2 (ver
 * docs/specs/rbac-roles-permisos-parametrico.md y
 * docs/specs/rbac-fase2-enforcement.md).
 *
 * Fase 2 conectó el catálogo a la autorización real: gate de pantalla
 * (roleGuard vía data.permission) y las acciones estáticas de backend
 * (CheckPermission). Los 6 controladores con lógica de workflow BPMN
 * (CreditoOrdinarioController, GestionCreditoController, etc.) quedan
 * fuera de alcance permanentemente — sus reglas de negocio no se tocan.
 */
@Component({
  selector: 'app-roles-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Gestión de Roles y Permisos</h1>
          <p>Catálogo paramétrico de roles y los permisos que se les puede asignar.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">add_moderator</span> Nuevo Rol
        </button>
      </header>

      <div class="card p-0 overflow-hidden shadow-sm">
        <table class="pro-table">
          <thead>
            <tr>
              <th>Rol</th>
              <th>Slug</th>
              <th>Tipo</th>
              <th>Permisos</th>
              <th>Usuarios</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let rol of roles">
              <td>
                <div class="user-cell">
                  <div class="avatar-sm">{{ rol.nombre.charAt(0).toUpperCase() }}</div>
                  <div>
                    <span class="name">{{ rol.nombre }}</span>
                    <div class="descripcion" *ngIf="rol.descripcion">{{ rol.descripcion }}</div>
                  </div>
                </div>
              </td>
              <td><code>{{ rol.slug }}</code></td>
              <td>
                <span class="pro-status" [ngClass]="rol.es_sistema ? 'rejected' : 'validated'">
                  {{ rol.es_sistema ? 'Sistema' : 'Personalizado' }}
                </span>
              </td>
              <td>{{ rol.permission_ids.length }}</td>
              <td>{{ rol.usuarios_asignados }}</td>
              <td class="text-right">
                <div class="actions">
                  <button class="btn-pro secondary sm icon-only" (click)="openModal(rol)" title="Editar">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button class="btn-pro danger sm icon-only" (click)="deleteRol(rol)" title="Eliminar"
                          [disabled]="rol.es_sistema">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr *ngIf="roles.length === 0">
              <td colspan="6" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                No hay roles registrados todavía.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  `,
  styles: [`
    .view-container { padding: 2.5rem 3rem; background: #F4F7FE; min-height: 100vh; }
    .view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .title-area { h1 { margin: 0; font-size: 1.5rem; color: var(--primary); } p { margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem; } }
    .p-0 { padding: 0 !important; }
    .overflow-hidden { overflow: hidden; }

    .user-cell { display: flex; align-items: center; gap: 12px; .avatar-sm { width: 32px; height: 32px; border-radius: 8px; background: var(--grad-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; } .name { font-weight: 600; color: var(--text-main); } .descripcion { font-size: 0.78rem; color: var(--text-muted); } }

    .pro-status { font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase; }
    .pro-status.validated { background: #E6FFFA; color: #2C7A7B; border: 1px solid #B2F5EA; }
    .pro-status.rejected { background: #EDF2F7; color: #4A5568; border: 1px solid #CBD5E0; }

    .actions { display: flex; justify-content: flex-end; gap: 8px; }
    .text-center { text-align: center; }
    code { background: #EDF2F7; padding: 2px 6px; border-radius: 4px; font-size: 0.82rem; }
  `]
})
export class RolesManagementComponent implements OnInit {
  roles: any[] = [];
  permisosPorModulo: { [modulo: string]: any[] } = {};
  apiUrl = `${environment.apiUrl}/roles`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadRoles();
    this.loadPermisos();
  }

  loadRoles() {
    this.http.get<any[]>(this.apiUrl).subscribe(data => this.roles = data);
  }

  loadPermisos() {
    this.http.get<{ [modulo: string]: any[] }>(`${environment.apiUrl}/permissions`)
      .subscribe(data => this.permisosPorModulo = data);
  }

  private matrizPermisosHtml(rol: any = null): string {
    const idsAsignados: number[] = rol?.permission_ids || [];

    return Object.keys(this.permisosPorModulo).map(modulo => {
      const items = this.permisosPorModulo[modulo].map(p => `
        <label style="display:flex; align-items:center; gap:6px; padding: 2px 0;">
          <input type="checkbox" class="swal-permiso" value="${p.id}" ${idsAsignados.includes(p.id) ? 'checked' : ''}>
          ${p.nombre}
        </label>
      `).join('');

      return `
        <div style="margin-bottom: 0.75rem;">
          <div style="font-weight:700; font-size:0.78rem; text-transform:uppercase; color:#64748B; margin-bottom:4px;">${modulo}</div>
          ${items}
        </div>
      `;
    }).join('');
  }

  async openModal(rol: any = null) {
    const isEdit = !!rol;
    const esSistema = !!rol?.es_sistema;

    const { value: formValues } = await Swal.fire({
      title: isEdit ? `Editar Rol: ${rol.nombre}` : 'Nuevo Rol',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Nombre</label>
            <input id="swal-nombre" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;" value="${rol?.nombre || ''}">
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">
              Slug ${esSistema ? '(rol del sistema, no editable)' : ''}
            </label>
            <input id="swal-slug" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;"
                   value="${rol?.slug || ''}" placeholder="ej: auditor_externo" ${esSistema ? 'disabled' : ''}>
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Descripción</label>
            <textarea id="swal-descripcion" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;" rows="2">${rol?.descripcion || ''}</textarea>
          </div>

          <div class="pro-input-group">
            <label style="display:block; margin-bottom:8px; font-weight:600;">Permisos</label>
            <div style="max-height: 280px; overflow-y: auto; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.85rem;">
              ${this.matrizPermisosHtml(rol)}
            </div>
          </div>
        </div>
      `,
      width: 560,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Guardar Cambios' : 'Crear Rol',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1A3B8B',
      cancelButtonColor: '#718096',
      customClass: { popup: 'modern-swal-popup' },
      preConfirm: async () => {
        const checkboxes = document.querySelectorAll('.swal-permiso:checked') as NodeListOf<HTMLInputElement>;
        const permissionIds = Array.from(checkboxes).map(cb => Number(cb.value));

        const formValues: any = {
          nombre: (document.getElementById('swal-nombre') as HTMLInputElement).value.trim(),
          descripcion: (document.getElementById('swal-descripcion') as HTMLTextAreaElement).value.trim(),
          permission_ids: permissionIds,
        };

        if (!esSistema) {
          formValues.slug = (document.getElementById('swal-slug') as HTMLInputElement).value.trim();
        }

        if (!formValues.nombre || (!isEdit && !formValues.slug)) {
          Swal.showValidationMessage('Nombre y slug son obligatorios.');
          return false;
        }

        const request = isEdit
          ? this.http.put(`${this.apiUrl}/${rol.id}`, formValues)
          : this.http.post(this.apiUrl, formValues);

        try {
          await firstValueFrom(request);
          return formValues;
        } catch (err: any) {
          Swal.showValidationMessage(err?.error?.message || 'No se pudo guardar el rol.');
          return false;
        }
      }
    });

    if (formValues) {
      this.loadRoles();
      Swal.fire('¡Éxito!', `Rol ${isEdit ? 'actualizado' : 'creado'} correctamente.`, 'success');
    }
  }

  deleteRol(rol: any) {
    if (rol.es_sistema) {
      return;
    }

    Swal.fire({
      title: '¿Confirmar eliminación?',
      text: `¿Eliminar permanentemente el rol "${rol.nombre}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E',
      cancelButtonColor: '#718096'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${rol.id}`).subscribe({
          next: () => {
            this.loadRoles();
            Swal.fire('Eliminado', 'El rol fue eliminado.', 'success');
          },
          error: (err) => {
            Swal.fire('No se puede eliminar', err.error?.message || 'No se pudo eliminar el rol.', 'error');
          }
        });
      }
    });
  }
}
