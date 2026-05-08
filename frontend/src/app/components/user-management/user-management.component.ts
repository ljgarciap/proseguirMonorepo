import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
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

      <div class="card p-0 overflow-hidden shadow-sm">
        <table class="pro-table">
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
            <tr *ngFor="let user of users">
              <td>
                <div class="user-cell">
                  <div class="avatar-sm">{{ user.name.charAt(0) }}</div>
                  <span class="name">{{ user.name }}</span>
                </div>
              </td>
              <td>
                <div class="doc-cell">
                  <span class="doc-type">{{ user.document_type?.codigo }}</span>
                  <span class="doc-num">{{ user.numero_documento }}</span>
                </div>
              </td>
              <td>{{ user.email || '-' }}</td>
              <td>
                <div class="roles-badges" style="display: flex; gap: 4px; flex-wrap: wrap;">
                  <span *ngFor="let role of user.roles" class="pro-status" [ngClass]="role">
                    {{ role | titlecase }}
                  </span>
                </div>
              </td>
              <td class="text-right">
                <div class="actions">
                  <button class="btn-pro secondary sm icon-only" (click)="openModal(user)" title="Editar">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button class="btn-pro danger sm icon-only" (click)="deleteUser(user)" title="Eliminar">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- User Modal (Custom Implementation with Swal or simple template) -->
      <!-- For simplicity and beauty, we'll use a custom Swal content for the form -->
    </div>
  `,
  styles: [`
    .view-container { padding: 2.5rem 3rem; background: #F4F7FE; min-height: 100vh; }
    .view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .title-area { h1 { margin: 0; font-size: 1.5rem; color: var(--primary); } p { margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.9rem; } }
    .p-0 { padding: 0 !important; }
    .overflow-hidden { overflow: hidden; }
    
    .user-cell { display: flex; align-items: center; gap: 12px; .avatar-sm { width: 32px; height: 32px; border-radius: 8px; background: var(--grad-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; } .name { font-weight: 600; color: var(--text-main); } }
    .roles-badges { display: flex; gap: 6px; flex-wrap: wrap; }
    .pro-status { font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
    .pro-status.superadmin { background: #EBF4FF; color: var(--primary); }
    .pro-status.gerente { background: #E0F2F1; color: var(--secondary); }
    .pro-status.operativo { background: #FFF7ED; color: var(--warning); }
    .pro-status.cliente { background: #F3E5F5; color: #9C27B0; }

    .actions { display: flex; justify-content: flex-end; gap: 8px; }
    .doc-cell { display: flex; flex-direction: column; .doc-type { font-size: 0.7rem; font-weight: 800; color: var(--primary); } .doc-num { font-size: 0.9rem; color: var(--text-main); } }
  `]
})
export class UserManagementComponent implements OnInit {
  users: any[] = [];
  documentTypes: any[] = [];
  apiUrl = `${environment.apiUrl}/users`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadUsers();
    this.loadDocumentTypes();
  }

  loadUsers() {
    this.http.get<any[]>(this.apiUrl).subscribe(data => this.users = data);
  }

  loadDocumentTypes() {
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe(data => this.documentTypes = data);
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
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-size: 0.85rem;">
                   <label><input type="checkbox" class="swal-role" value="operativo" ${user?.roles?.includes('operativo') ? 'checked' : ''}> Operativo</label>
                   <label><input type="checkbox" class="swal-role" value="gerente" ${user?.roles?.includes('gerente') ? 'checked' : ''}> Gerente</label>
                   <label><input type="checkbox" class="swal-role" value="superadmin" ${user?.roles?.includes('superadmin') ? 'checked' : ''}> Admin</label>
                   <label><input type="checkbox" class="swal-role" value="cliente" ${user?.roles?.includes('cliente') ? 'checked' : ''}> Cliente</label>
                </div>
             </div>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Actualizar' : 'Crear Usuario',
      customClass: { popup: 'modern-swal-popup' },
      preConfirm: () => {
        const checkboxes = document.querySelectorAll('.swal-role:checked') as NodeListOf<HTMLInputElement>;
        const roles = Array.from(checkboxes).map(cb => cb.value);

        return {
          tipo_documento_id: (document.getElementById('swal-type') as HTMLSelectElement).value,
          numero_documento: (document.getElementById('swal-doc') as HTMLInputElement).value,
          name: (document.getElementById('swal-name') as HTMLInputElement).value,
          email: (document.getElementById('swal-email') as HTMLInputElement).value,
          password: (document.getElementById('swal-pass') as HTMLInputElement).value,
          roles: roles
        };
      }
    });

    if (formValues) {
      if (!formValues.name || !formValues.numero_documento || (!isEdit && !formValues.password)) {
        Swal.fire('Error', 'Por favor completa los campos obligatorios (Documento, Nombre, Contraseña).', 'error');
        return;
      }

      const request = isEdit 
        ? this.http.put(`${this.apiUrl}/${user.id}`, formValues)
        : this.http.post(this.apiUrl, formValues);

      request.subscribe({
        next: () => {
          this.loadUsers();
          Swal.fire('¡Éxito!', `Usuario ${isEdit ? 'actualizado' : 'creado'} correctamente.`, 'success');
        },
        error: (err) => {
          Swal.fire('Error', err.error.message || 'Ocurrió un error al procesar el usuario.', 'error');
        }
      });
    }
  }

  deleteUser(user: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: `Eliminarás permanentemente al usuario ${user.name}.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${user.id}`).subscribe({
          next: () => {
            this.loadUsers();
            Swal.fire('Eliminado', 'El usuario ha sido borrado.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo eliminar el usuario.', 'error');
          }
        });
      }
    });
  }
}
