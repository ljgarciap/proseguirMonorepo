import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-notificaciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Configuración de Notificaciones</h1>
          <p>Configuración de las notificaciones y plantillas de correo del sistema.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">add_alert</span> Nueva Notificación
        </button>
      </header>

      <div class="card p-0 overflow-hidden shadow-sm">
        <table class="pro-table">
          <thead>
            <tr>
              <th>Nombre de Notificación</th>
              <th>Mensaje / Contenido</th>
              <th>Activa</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let notif of notificaciones">
              <td style="font-weight: 600; color: var(--text-main);">
                <div class="notif-cell">
                  <span class="material-symbols-outlined" style="color: var(--secondary);">notifications</span>
                  {{ notif.nombre }}
                </div>
              </td>
              <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" [title]="notif.mensaje">
                {{ notif.mensaje }}
              </td>
              <td>
                <span class="pro-status cursor-pointer" 
                      [ngClass]="notif.activo ? 'validated' : 'rejected'" 
                      (click)="toggleActive(notif)"
                      [title]="notif.activo ? 'Click para desactivar' : 'Click para activar'">
                  {{ notif.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-right">
                <div class="actions">
                  <button class="btn-pro secondary sm icon-only" (click)="openModal(notif)" title="Editar">
                    <span class="material-symbols-outlined">edit</span>
                  </button>
                  <button class="btn-pro danger sm icon-only" (click)="deleteNotificacion(notif)" title="Eliminar">
                    <span class="material-symbols-outlined">delete</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr *ngIf="notificaciones.length === 0">
              <td colspan="4" class="text-center" style="padding: 2rem; color: var(--text-muted);">
                No hay notificaciones registradas en el sistema.
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
    
    .notif-cell { display: flex; align-items: center; gap: 8px; }
    .cursor-pointer { cursor: pointer; user-select: none; }
    
    .pro-status { font-size: 0.7rem; padding: 2px 8px; border-radius: 6px; font-weight: 800; text-transform: uppercase; transition: all 0.2s; }
    .pro-status.validated { background: #E6FFFA; color: #2C7A7B; border: 1px solid #B2F5EA; &:hover { background: #B2F5EA; } }
    .pro-status.rejected { background: #FFF5F5; color: #C53030; border: 1px solid #FED7D7; &:hover { background: #FED7D7; } }

    .actions { display: flex; justify-content: flex-end; gap: 8px; }
    .text-center { text-align: center; }
  `]
})
export class NotificacionesComponent implements OnInit {
  notificaciones: any[] = [];
  apiUrl = `${environment.apiUrl}/notificaciones`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadNotificaciones();
  }

  loadNotificaciones() {
    this.http.get<any[]>(this.apiUrl).subscribe({
      next: (data) => this.notificaciones = data,
      error: () => {}
    });
  }

  toggleActive(notif: any) {
    const updatedStatus = !notif.activo;
    this.http.put(`${this.apiUrl}/${notif.id}`, {
      nombre: notif.nombre,
      mensaje: notif.mensaje,
      activo: updatedStatus
    }).subscribe({
      next: () => {
        notif.activo = updatedStatus;
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: `Notificación ${updatedStatus ? 'activada' : 'desactivada'}`,
          showConfirmButton: false,
          timer: 2000
        });
      },
      error: (err) => {
        Swal.fire('Error', err.error.message || 'No se pudo actualizar el estado de la notificación.', 'error');
      }
    });
  }

  async openModal(notif: any = null) {
    const isEdit = !!notif;
    
    const { value: formValues } = await Swal.fire({
      title: isEdit ? 'Editar Notificación' : 'Nueva Notificación',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Nombre de Notificación</label>
            <input id="swal-nombre" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px;" value="${notif?.nombre || ''}" placeholder="Ej: Comprobante Recibido">
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Mensaje / Contenido de Notificación</label>
            <textarea id="swal-mensaje" class="pro-input" style="width:100%; height: 120px; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px; font-family: inherit; resize: vertical;" placeholder="Ej: Estimado usuario, su operación ha sido procesada con éxito...">${notif?.mensaje || ''}</textarea>
          </div>

          <div class="pro-input-group" style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px;">
            <input id="swal-activo" type="checkbox" style="width: 18px; height: 18px; cursor: pointer;" ${(!isEdit || notif?.activo) ? 'checked' : ''}>
            <label for="swal-activo" style="margin: 0; font-weight: 600; font-size: 0.85rem; color: #2D3748; cursor: pointer; user-select: none;">Activa</label>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Guardar Cambios' : 'Crear Notificación',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1A3B8B',
      cancelButtonColor: '#718096',
      customClass: { popup: 'modern-swal-popup' },
      preConfirm: () => {
        const nombreInput = document.getElementById('swal-nombre') as HTMLInputElement;
        const mensajeInput = document.getElementById('swal-mensaje') as HTMLTextAreaElement;
        const activoInput = document.getElementById('swal-activo') as HTMLInputElement;

        return {
          nombre: nombreInput.value.trim(),
          mensaje: mensajeInput.value.trim(),
          activo: activoInput.checked
        };
      }
    });

    if (formValues) {
      if (!formValues.nombre || !formValues.mensaje) {
        Swal.fire('Error', 'Todos los campos son obligatorios.', 'error');
        return;
      }

      const request = isEdit 
        ? this.http.put(`${this.apiUrl}/${notif.id}`, formValues)
        : this.http.post(this.apiUrl, formValues);

      request.subscribe({
        next: () => {
          this.loadNotificaciones();
          Swal.fire('¡Éxito!', `Notificación ${isEdit ? 'actualizada' : 'creada'} correctamente.`, 'success');
        },
        error: (err) => {
          Swal.fire('Error', err.error.message || 'No se pudo guardar la notificación.', 'error');
        }
      });
    }
  }

  deleteNotificacion(notif: any) {
    Swal.fire({
      title: '¿Confirmar eliminación?',
      text: `¿Estás seguro de que deseas eliminar permanentemente la notificación "${notif.nombre}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E',
      cancelButtonColor: '#718096'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${notif.id}`).subscribe({
          next: () => {
            this.loadNotificaciones();
            Swal.fire('Eliminado', 'La notificación ha sido borrada del sistema.', 'success');
          },
          error: (err) => {
            // Regla de Negocio: Mostrar advertencia si falla por estar asociada a destinatarios
            Swal.fire({
              title: 'No se puede eliminar',
              text: err.error.message || 'Esta notificación tiene destinatarios asociados y no se puede borrar.',
              icon: 'warning',
              confirmButtonColor: '#1A3B8B'
            });
          }
        });
      }
    });
  }
}
