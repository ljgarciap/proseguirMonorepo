import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-destinatarios',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Configuración de Destinatarios</h1>
          <p>Registro de destinatarios para envío de notificaciones automáticas.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">person_add</span> Nuevo Destinatario
        </button>
      </header>

      <div class="card p-0 overflow-hidden shadow-sm">
        <table class="pro-table">
          <thead>
            <tr>
              <th>Nombre Completo</th>
              <th>Correo Electrónico</th>
              <th>Fecha de Creación</th>
              <th>Última Modificación</th>
              <th>Activo</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let dest of destinatarios">
              <td>
                <div class="user-cell">
                  <div class="avatar-sm">{{ dest.nombre.charAt(0).toUpperCase() }}</div>
                  <span class="name">{{ dest.nombre }}</span>
                </div>
              </td>
              <td>{{ dest.email }}</td>
              <td class="timestamp">{{ dest.created_at | date:'dd/MM/yyyy HH:mm' }}</td>
              <td class="timestamp">{{ dest.updated_at | date:'dd/MM/yyyy HH:mm' }}</td>
              <td>
                <span class="pro-status cursor-pointer" 
                      [ngClass]="dest.activo ? 'validated' : 'rejected'" 
                      (click)="toggleActive(dest)"
                      [title]="dest.activo ? 'Click para desactivar' : 'Click para activar'">
                  {{ dest.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="text-right">
                <div class="actions">
                  <button class="btn-pro secondary sm icon-only" (click)="openModal(dest)" title="Editar">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button class="btn-pro danger sm icon-only" (click)="deleteDestinatario(dest)" title="Eliminar">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr *ngIf="destinatarios.length === 0">
              <td colspan="6" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                No hay destinatarios registrados en el sistema.
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
    
    .user-cell { display: flex; align-items: center; gap: 12px; .avatar-sm { width: 32px; height: 32px; border-radius: 8px; background: var(--grad-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; } .name { font-weight: 600; color: var(--text-main); } }
    .cursor-pointer { cursor: pointer; user-select: none; }
    
    .pro-status { font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase; transition: all 0.2s; }
    .pro-status.validated { background: #E6FFFA; color: #2C7A7B; border: 1px solid #B2F5EA; &:hover { background: #B2F5EA; } }
    .pro-status.rejected { background: #FFF5F5; color: #C53030; border: 1px solid #FED7D7; &:hover { background: #FED7D7; } }
    
    .timestamp { color: #64748B; font-size: 0.85rem; }

    .actions { display: flex; justify-content: flex-end; gap: 8px; }
    .text-center { text-align: center; }
  `]
})
export class DestinatariosComponent implements OnInit {
  destinatarios: any[] = [];
  apiUrl = `${environment.apiUrl}/destinatarios`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadDestinatarios();
  }

  loadDestinatarios() {
    this.http.get<any[]>(this.apiUrl).subscribe({
      next: (data) => this.destinatarios = data,
      error: () => {}
    });
  }

  toggleActive(dest: any) {
    const updatedStatus = !dest.activo;
    this.http.put(`${this.apiUrl}/${dest.id}`, {
      nombre: dest.nombre,
      email: dest.email,
      activo: updatedStatus
    }).subscribe({
      next: () => {
        dest.activo = updatedStatus;
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: `Destinatario ${updatedStatus ? 'activado' : 'desactivado'}`,
          showConfirmButton: false,
          timer: 2000
        });
      },
      error: (err) => {
        Swal.fire('Error', err.error.message || 'No se pudo actualizar el estado del destinatario.', 'error');
      }
    });
  }

  async openModal(dest: any = null) {
    const isEdit = !!dest;
    
    const { value: formValues } = await Swal.fire({
      title: isEdit ? 'Editar Destinatario' : 'Nuevo Destinatario',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Nombre Completo</label>
            <input id="swal-nombre" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;" value="${dest?.nombre || ''}" placeholder="Ej: Juan Pérez">
          </div>

          <div class="pro-input-group" style="margin-bottom: 1.5rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Correo Electrónico</label>
            <input id="swal-email" type="email" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;" value="${dest?.email || ''}" placeholder="Ej: juan.perez@proseguir.com">
          </div>

          <div class="pro-input-group" style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;">
            <input id="swal-activo" type="checkbox" style="width: 18px; height: 18px; cursor: pointer;" ${(!isEdit || dest?.activo) ? 'checked' : ''}>
            <label for="swal-activo" style="margin: 0; font-weight: 600; font-size: 0.85rem; color: #2D3748; cursor: pointer; user-select: none;">Activo</label>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Guardar Cambios' : 'Crear Destinatario',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1A3B8B',
      cancelButtonColor: '#718096',
      customClass: { popup: 'modern-swal-popup' },
      preConfirm: () => {
        const nombreInput = document.getElementById('swal-nombre') as HTMLInputElement;
        const emailInput = document.getElementById('swal-email') as HTMLInputElement;
        const activoInput = document.getElementById('swal-activo') as HTMLInputElement;

        return {
          nombre: nombreInput.value.trim(),
          email: emailInput.value.trim(),
          activo: activoInput.checked
        };
      }
    });

    if (formValues) {
      if (!formValues.nombre || !formValues.email) {
        Swal.fire('Error', 'Por favor completa todos los campos del destinatario.', 'error');
        return;
      }

      // Format check (email format)
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formValues.email)) {
        Swal.fire('Formato Inválido', 'El formato del correo electrónico no es válido.', 'error');
        return;
      }

      const request = isEdit 
        ? this.http.put(`${this.apiUrl}/${dest.id}`, formValues)
        : this.http.post(this.apiUrl, formValues);

      request.subscribe({
        next: () => {
          this.loadDestinatarios();
          Swal.fire('¡Éxito!', `Destinatario ${isEdit ? 'actualizado' : 'creado'} correctamente.`, 'success');
        },
        error: (err) => {
          Swal.fire('Error', err.error.message || 'No se pudo guardar el destinatario. Asegúrate de que no esté duplicado.', 'error');
        }
      });
    }
  }

  deleteDestinatario(dest: any) {
    Swal.fire({
      title: '¿Confirmar eliminación?',
      text: `¿Estás seguro de que deseas eliminar permanentemente al destinatario "${dest.nombre}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E',
      cancelButtonColor: '#718096'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${dest.id}`).subscribe({
          next: () => {
            this.loadDestinatarios();
            Swal.fire('Eliminado', 'El destinatario ha sido borrado del sistema.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo eliminar el destinatario.', 'error');
          }
        });
      }
    });
  }
}
