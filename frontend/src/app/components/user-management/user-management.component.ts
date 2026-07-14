import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-user-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Gestión de Usuarios</h1>
          <p>Administración de accesos, roles y seguridad de la plataforma.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">person_add</span> Nuevo Usuario
        </button>
      </header>

      <!-- Search and Tabs Bar -->
      <div class="control-bar mt-4">
        <div class="tabs-container">
          <button class="tab-btn" [class.active]="activeTab === 'active'" (click)="setTab('active')">
            <span class="material-symbols-outlined">check_circle</span> Activos
          </button>
          <button class="tab-btn" [class.active]="activeTab === 'inactive'" (click)="setTab('inactive')">
            <span class="material-symbols-outlined">cancel</span> Inactivos
          </button>
        </div>

        <div class="search-wrapper">
          <span class="material-symbols-outlined search-icon">search</span>
          <input type="text" [(ngModel)]="searchTerm" (input)="onSearchChange()" 
                 placeholder="Buscar por nombre, documento o correo..." class="filter-input" />
        </div>
      </div>

      <!-- Users Table Card -->
      <div class="content-card mt-4">
        <div class="table-container">
          <table class="modern-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Email</th>
                <th>Rol</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let user of paginatedUsers">
                <td>
                  <div class="user-cell">
                    <div class="avatar-sm">{{ user.name.charAt(0) }}</div>
                    <span class="name">{{ user.name }}</span>
                  </div>
                </td>
                <td>
                  <div class="doc-cell">
                    <span class="doc-type">{{ user.document_type?.codigo || 'CC' }}</span>
                    <span class="doc-num">{{ user.numero_documento }}</span>
                  </div>
                </td>
                <td>{{ user.email || '-' }}</td>
                <td>
                  <div class="roles-badges">
                    <span *ngFor="let role of user.roles" class="pro-status" [ngClass]="role">
                      {{ role.split('_').join(' ') | titlecase }}
                    </span>
                  </div>
                </td>
                <td class="text-right">
                  <div class="actions" *ngIf="activeTab === 'active'; else inactiveActions">
                    <button class="btn-pro secondary sm icon-only" (click)="openModal(user)" title="Editar">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn-pro danger sm icon-only" (click)="deleteUser(user)" title="Desactivar">
                      <span class="material-symbols-outlined">person_off</span>
                    </button>
                  </div>
                  <ng-template #inactiveActions>
                    <div class="actions">
                      <button class="btn-pro success sm icon-only" (click)="restoreUser(user)" title="Reactivar">
                        <span class="material-symbols-outlined">settings_backup_restore</span>
                      </button>
                    </div>
                  </ng-template>
                </td>
              </tr>
              <tr *ngIf="users.length === 0">
                <td colspan="5" class="text-center py-4">No se encontraron usuarios en esta sección.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div class="pagination-footer mt-4" *ngIf="totalPages > 1">
          <button (click)="changePage(currentPage - 1)" [disabled]="currentPage === 1" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <span class="pagination-info">Página {{ currentPage }} de {{ totalPages }}</span>
          <button (click)="changePage(currentPage + 1)" [disabled]="currentPage === totalPages" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .view-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
    .view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .title-area { h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: #1a202c; } p { margin: 4px 0 0 0; color: #718096; font-size: 0.95rem; } }
    
    .control-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
      background: rgba(255, 255, 255, 0.4);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      padding: 0.75rem 1rem;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.02);
    }

    .tabs-container {
      display: flex;
      gap: 0.5rem;
    }

    .tab-btn {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1rem;
      border: none;
      background: transparent;
      border-radius: 8px;
      font-weight: 600;
      color: #718096;
      cursor: pointer;
      transition: all 0.2s ease;

      .material-symbols-outlined {
        font-size: 1.15rem;
      }

      &:hover {
        background: rgba(0, 0, 0, 0.03);
        color: #2d3748;
      }

      &.active {
        background: #ebf8ff;
        color: #2b6cb0;
      }
    }

    .search-wrapper {
      position: relative;
      flex: 1;
      max-width: 400px;
      min-width: 250px;

      .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #a0aec0;
        pointer-events: none;
      }

      .filter-input {
        width: 100%;
        padding: 0.5rem 0.5rem 0.5rem 2.2rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        color: #2d3748;
        transition: all 0.2s ease;

        &:focus {
          outline: none;
          border-color: #3182ce;
          box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }
      }
    }

    .content-card {
      background: rgba(255, 255, 255, 0.5);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    }

    .modern-table {
      width: 100%;
      border-collapse: collapse;

      th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #4a5568;
        border-bottom: 2px solid rgba(226, 232, 240, 0.8);
      }

      td {
        padding: 1rem;
        color: #2d3748;
        border-bottom: 1px solid rgba(226, 232, 240, 0.6);
      }

      tr:hover td {
        background-color: rgba(237, 242, 247, 0.3);
      }
    }

    .user-cell {
      display: flex;
      align-items: center;
      gap: 12px;

      .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
      }

      .name {
        font-weight: 600;
        color: #2d3748;
      }
    }

    .doc-cell {
      display: flex;
      flex-direction: column;

      .doc-type {
        font-size: 0.7rem;
        font-weight: 800;
        color: #3182ce;
        text-transform: uppercase;
      }

      .doc-num {
        font-size: 0.9rem;
        color: #2d3748;
      }
    }

    .roles-badges {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
    }

    .pro-status {
      font-size: 0.7rem;
      padding: 2px 6px;
      border-radius: 6px;
      font-weight: 700;
      text-transform: uppercase;

      &.superadmin { background: #ebf8ff; color: #2b6cb0; }
      &.gerente { background: #e6fffa; color: #319795; }
      &.operativo { background: #fffaf0; color: #dd6b20; }
      &.cliente { background: #faf5ff; color: #805ad5; }
      &.contable { background: #ebf4ff; color: #5a67d8; }
      &.coordinador_comercial { background: #f0fdf4; color: #166534; }
      &.oficial_cumplimiento { background: #ecfdf5; color: #065f46; }
      &.comite_credito { background: #fff5f5; color: #c53030; }
      &.tesoreria { background: #fffbeb; color: #b7791f; }
    }

    .actions {
      display: flex;
      justify-content: flex-end;
      gap: 6px;
    }

    .btn-pro {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 1.1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;

      &.primary {
        background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
        color: white;
        &:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(49, 130, 206, 0.2); }
      }

      &.secondary {
        background: #edf2f7;
        color: #4a5568;
        &:hover { background: #e2e8f0; }
      }

      &.danger {
        background: #fff5f5;
        color: #e53e3e;
        &:hover { background: #fed7d7; }
      }

      &.success {
        background: #f0fdf4;
        color: #166534;
        &:hover { background: #dcfce7; }
      }

      &.sm {
        padding: 0.35rem 0.7rem;
        font-size: 0.8rem;
      }

      &.icon-only {
        padding: 0.4rem;
        border-radius: 6px;
        .material-symbols-outlined { font-size: 1.15rem; }
      }
    }

    .pagination-footer {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 1rem;
    }

    .btn-pagination {
      background: rgba(255, 255, 255, 0.8);
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      transition: all 0.2s;

      &:hover:not(:disabled) { background: #edf2f7; }
      &:disabled { opacity: 0.5; cursor: not-allowed; }
    }

    .pagination-info {
      font-size: 0.9rem;
      color: #4a5568;
    }
  `]
})
export class UserManagementComponent implements OnInit {
  users: any[] = [];
  documentTypes: any[] = [];
  apiUrl = `${environment.apiUrl}/users`;

  // Tab & search controls
  activeTab: 'active' | 'inactive' = 'active';
  searchTerm: string = '';
  currentPage: number = 1;
  pageSize: number = 10;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadUsers();
    this.loadDocumentTypes();
  }

  loadUsers() {
    this.http.get<any[]>(`${this.apiUrl}?status=${this.activeTab}&search=${this.searchTerm}`).subscribe(data => {
      this.users = data;
      this.currentPage = 1;
    });
  }

  loadDocumentTypes() {
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe(data => this.documentTypes = data);
  }

  setTab(tab: 'active' | 'inactive') {
    this.activeTab = tab;
    this.loadUsers();
  }

  onSearchChange() {
    this.loadUsers();
  }

  get paginatedUsers() {
    const startIndex = (this.currentPage - 1) * this.pageSize;
    return this.users.slice(startIndex, startIndex + this.pageSize);
  }

  get totalPages() {
    return Math.ceil(this.users.length / this.pageSize);
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  async openModal(user: any = null) {
    const isEdit = !!user;
    
    // Build options for document types
    const docOptions = this.documentTypes.map(t => 
      `<option value="${t.id}" ${user?.tipo_documento_id === t.id ? 'selected' : ''}>${t.codigo} - ${t.nombre}</option>`
    ).join('');

    const { value: formValues } = await Swal.fire({
      title: isEdit ? 'Editar Usuario' : 'Nuevo Usuario',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 15px; margin-bottom: 1rem;">
             <div class="pro-input-group">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Tipo Docto.</label>
                <select id="swal-type" class="pro-input" style="width:100%">
                   ${docOptions}
                 </select>
             </div>
             <div class="pro-input-group">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Número Documento</label>
                <input id="swal-doc" class="pro-input" style="width:100%" value="${user?.numero_documento || ''}" 
                       placeholder="Ej: 123456" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
             </div>
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Nombre Completo</label>
            <input id="swal-name" class="pro-input" style="width:100%" value="${user?.name || ''}">
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Correo Electrónico (Opcional)</label>
            <input id="swal-email" type="email" class="pro-input" style="width:100%" value="${user?.email || ''}">
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1rem;">
             <div class="pro-input-group">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Contraseña ${isEdit ? '(Opcional)' : ''}</label>
                <input id="swal-pass" type="password" class="pro-input" style="width:100%" placeholder="${isEdit ? 'Mantener actual' : 'Mín. 8 caracteres'}">
             </div>
             <div class="pro-input-group">
                <label style="display:block; margin-bottom:10px; font-weight:600;">Roles / Perfiles</label>
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-size: 0.82rem;">
                   <label><input type="checkbox" class="swal-role" value="operativo" ${user?.roles?.includes('operativo') ? 'checked' : ''}> Dir. Administrativa</label>
                   <label><input type="checkbox" class="swal-role" value="gerente" ${user?.roles?.includes('gerente') ? 'checked' : ''}> Gerencia</label>
                   <label><input type="checkbox" class="swal-role" value="superadmin" ${user?.roles?.includes('superadmin') ? 'checked' : ''}> Admin</label>
                   <label><input type="checkbox" class="swal-role" value="cliente" ${user?.roles?.includes('cliente') ? 'checked' : ''}> Cliente</label>
                   <label><input type="checkbox" class="swal-role" value="contable" ${user?.roles?.includes('contable') ? 'checked' : ''}> Contable</label>
                   <label><input type="checkbox" class="swal-role" value="coordinador_comercial" ${user?.roles?.includes('coordinador_comercial') ? 'checked' : ''}> Comercial</label>
                   <label><input type="checkbox" class="swal-role" value="oficial_cumplimiento" ${user?.roles?.includes('oficial_cumplimiento') ? 'checked' : ''}> Oficial Cumpl.</label>
                   <label><input type="checkbox" class="swal-role" value="comite_credito" ${user?.roles?.includes('comite_credito') ? 'checked' : ''}> Comité Crédito</label>
                   <label><input type="checkbox" class="swal-role" value="tesoreria" ${user?.roles?.includes('tesoreria') ? 'checked' : ''}> Tesorería</label>
                   <label><input type="checkbox" class="swal-role" value="ingeniero" ${user?.roles?.includes('ingeniero') ? 'checked' : ''}> Ingeniero</label>
                 </div>
             </div>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Actualizar' : 'Crear Usuario',
      customClass: { popup: 'modern-swal-popup' },
      preConfirm: async () => {
        const checkboxes = document.querySelectorAll('.swal-role:checked') as NodeListOf<HTMLInputElement>;
        const roles = Array.from(checkboxes).map(cb => cb.value);

        const formValues = {
          tipo_documento_id: (document.getElementById('swal-type') as HTMLSelectElement).value,
          numero_documento: (document.getElementById('swal-doc') as HTMLInputElement).value,
          name: (document.getElementById('swal-name') as HTMLInputElement).value,
          email: (document.getElementById('swal-email') as HTMLInputElement).value,
          password: (document.getElementById('swal-pass') as HTMLInputElement).value,
          roles: roles
        };

        if (!formValues.name || !formValues.numero_documento || (!isEdit && !formValues.password)) {
          Swal.showValidationMessage('Por favor completa los campos obligatorios (Documento, Nombre, Contraseña).');
          return false;
        }

        const request = isEdit
          ? this.http.put(`${this.apiUrl}/${user.id}`, formValues)
          : this.http.post(this.apiUrl, formValues);

        try {
          await firstValueFrom(request);
          return formValues;
        } catch (err: any) {
          Swal.showValidationMessage(err.error?.message || 'Ocurrió un error al procesar el usuario.');
          return false;
        }
      }
    });

    if (formValues) {
      this.loadUsers();
      Swal.fire('¡Éxito!', `Usuario ${isEdit ? 'actualizado' : 'creado'} correctamente.`, 'success');
    }
  }

  deleteUser(user: any) {
    Swal.fire({
      title: '¿Desactivar usuario?',
      text: `El usuario ${user.name} perderá acceso pero se conservará en el historial.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, desactivar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${user.id}`).subscribe({
          next: () => {
            this.loadUsers();
            Swal.fire('Desactivado', 'El usuario ha sido desactivado correctamente.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo desactivar el usuario.', 'error');
          }
        });
      }
    });
  }

  restoreUser(user: any) {
    Swal.fire({
      title: '¿Reactivar usuario?',
      text: `El usuario ${user.name} recuperará todos sus accesos.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, reactivar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#38A169'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.post(`${this.apiUrl}/${user.id}/restore`, {}).subscribe({
          next: () => {
            this.loadUsers();
            Swal.fire('Reactivado', 'El usuario ha sido reactivado correctamente.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo reactivar el usuario.', 'error');
          }
        });
      }
    });
  }
}
