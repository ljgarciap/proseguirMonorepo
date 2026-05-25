import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-asignaciones',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Asignación de Notificaciones</h1>
          <p>Asociación masiva de destinatarios a notificaciones automáticas del sistema.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">assignment_ind</span> Asignar Notificación
        </button>
      </header>

      <div class="card p-0 overflow-hidden shadow-sm">
        <table class="pro-table">
          <thead>
            <tr>
              <th style="width: 120px;">ID Notif.</th>
              <th>Nombre de Notificación</th>
              <th>Destinatarios Asignados</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let asig of asignaciones">
              <td>
                <span class="pro-badge info bold">#{{ asig.id }}</span>
              </td>
              <td style="font-weight: 600; color: var(--text-main);">
                {{ asig.nombre }}
              </td>
              <td>
                <span class="pro-status" 
                      [ngClass]="asig.destinatarios_count > 0 ? 'validated' : 'pending'">
                  {{ asig.destinatarios_count }} Destinatario(s)
                </span>
              </td>
              <td class="text-right">
                <div class="actions">
                  <button class="btn-pro secondary sm icon-only" (click)="openModal(asig)" title="Configurar / Editar Asignación">
                    <span class="material-symbols-outlined">settings</span>
                  </button>
                  <button class="btn-pro danger sm icon-only" 
                          [disabled]="asig.destinatarios_count === 0"
                          (click)="deleteAsignacion(asig)" 
                          title="Limpiar Asignación">
                    <span class="material-symbols-outlined">delete_sweep</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr *ngIf="asignaciones.length === 0">
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

    .actions { display: flex; justify-content: flex-end; gap: 8px; }
    .text-center { text-align: center; }
    .bold { font-weight: 700; }
  `]
})
export class AsignacionesComponent implements OnInit {
  asignaciones: any[] = [];
  activeNotifications: any[] = [];
  apiUrl = `${environment.apiUrl}/asignaciones`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadAsignaciones();
    this.loadActiveNotifications();
  }

  loadAsignaciones() {
    this.http.get<any[]>(this.apiUrl).subscribe({
      next: (data) => this.asignaciones = data,
      error: () => {}
    });
  }

  loadActiveNotifications() {
    this.http.get<any[]>(`${environment.apiUrl}/notificaciones`).subscribe({
      next: (data) => {
        // Regla de Negocio: Solo notificaciones activas pueden asociarse
        this.activeNotifications = data.filter(n => n.activo);
      },
      error: () => {}
    });
  }

  async openModal(selectedAsig: any = null) {
    const isEdit = !!selectedAsig;
    const token = localStorage.getItem('auth_token');

    // Build notification dropdown options
    let selectOptions = '';
    if (isEdit) {
      // In edit mode, only show the selected notification (disabled)
      selectOptions = `<option value="${selectedAsig.id}" selected>${selectedAsig.nombre}</option>`;
    } else {
      // In create mode, list all active notifications
      selectOptions = `<option value="">-- Selecciona una Notificación Activa --</option>` + 
        this.activeNotifications.map(n => `<option value="${n.id}">${n.nombre}</option>`).join('');
    }

    const { value: formValues } = await Swal.fire({
      title: isEdit ? 'Configurar Asignación' : 'Nueva Asignación de Notificación',
      html: `
        <div class="swal-form" style="text-align: left; font-family: inherit;">
          <div class="pro-input-group" style="margin-bottom: 1.5rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Notificación</label>
            <select id="swal-notif" class="pro-input" style="width:100%; padding: 0.75rem 1rem; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 500;" ${isEdit ? 'disabled' : ''}>
              ${selectOptions}
            </select>
          </div>

          <div style="display: flex; gap: 15px; align-items: center; justify-content: space-between;">
             <!-- Available Recipients List -->
             <div class="pro-input-group" style="flex: 1; margin: 0;">
                <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Destinatarios Disponibles</label>
                <select id="swal-disponibles" multiple style="width: 100%; height: 200px; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px; outline: none; font-size: 0.85rem;">
                </select>
             </div>

             <!-- Dual List Action Buttons -->
             <div style="display: flex; flex-direction: column; gap: 10px;">
                <button id="swal-btn-add" class="btn-pro primary sm icon-only" type="button" title="Asociar Seleccionados" style="padding: 6px 12px;">
                  <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
                </button>
                <button id="swal-btn-remove" class="btn-pro secondary sm icon-only" type="button" title="Remover Seleccionados" style="padding: 6px 12px; border-color: #E2E8F0;">
                  <span class="material-symbols-outlined" style="font-size: 18px;">arrow_back</span>
                </button>
             </div>

             <!-- Assigned Recipients List -->
             <div class="pro-input-group" style="flex: 1; margin: 0;">
                <label style="display:block; margin-bottom:5px; font-weight:600; font-size: 0.85rem; color: #2D3748;">Destinatarios Asignados</label>
                <select id="swal-asignados" multiple style="width: 100%; height: 200px; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px; outline: none; font-size: 0.85rem;">
                </select>
             </div>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar Asignaciones',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1A3B8B',
      cancelButtonColor: '#718096',
      width: '650px',
      customClass: { popup: 'modern-swal-popup' },
      didOpen: () => {
        const notifSelect = document.getElementById('swal-notif') as HTMLSelectElement;
        const listDisponibles = document.getElementById('swal-disponibles') as HTMLSelectElement;
        const listAsignados = document.getElementById('swal-asignados') as HTMLSelectElement;
        const btnAdd = document.getElementById('swal-btn-add') as HTMLButtonElement;
        const btnRemove = document.getElementById('swal-btn-remove') as HTMLButtonElement;

        const updateLists = (notifId: string) => {
          if (!notifId) {
            listDisponibles.innerHTML = '';
            listAsignados.innerHTML = '';
            return;
          }

          fetch(`${environment.apiUrl}/asignaciones/${notifId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
          })
          .then(res => res.json())
          .then(data => {
            const assignedIds = new Set(data.asignados.map((d: any) => d.id));
            
            // Available active recipients are active ones not in assigned
            const available = data.activos.filter((d: any) => !assignedIds.has(d.id));

            // Populate disponibles select
            listDisponibles.innerHTML = available.map((d: any) => 
              `<option value="${d.id}">${d.nombre} (${d.email})</option>`
            ).join('');

            // Populate asignados select
            listAsignados.innerHTML = data.asignados.map((d: any) => 
              `<option value="${d.id}">${d.nombre} (${d.email})</option>`
            ).join('');
          });
        };

        if (notifSelect.value) {
          updateLists(notifSelect.value);
        }

        notifSelect.addEventListener('change', (e) => {
          const val = (e.target as HTMLSelectElement).value;
          updateLists(val);
        });

        btnAdd.addEventListener('click', () => {
          const selected = Array.from(listDisponibles.selectedOptions);
          selected.forEach(opt => {
            listAsignados.appendChild(opt);
          });
        });

        btnRemove.addEventListener('click', () => {
          const selected = Array.from(listAsignados.selectedOptions);
          selected.forEach(opt => {
            listDisponibles.appendChild(opt);
          });
        });
      },
      preConfirm: () => {
        const notifSelect = document.getElementById('swal-notif') as HTMLSelectElement;
        const listAsignados = document.getElementById('swal-asignados') as HTMLSelectElement;
        
        if (!notifSelect.value) {
          Swal.showValidationMessage('Debes seleccionar una notificación.');
          return false;
        }

        const options = Array.from(listAsignados.options) as HTMLOptionElement[];
        const ids = options.map(opt => parseInt(opt.value));

        return {
          notificacion_id: parseInt(notifSelect.value),
          destinatario_ids: ids
        };
      }
    });

    if (formValues) {
      // Criterio de Aceptación: Confirmación intermedia antes de guardar
      Swal.fire({
        title: '¿Confirmar asignación?',
        text: '¿Está seguro de que desea guardar la asignación realizada?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1A3B8B',
        cancelButtonColor: '#718096'
      }).then((confirmRes) => {
        if (confirmRes.isConfirmed) {
          this.http.post(this.apiUrl, formValues).subscribe({
            next: () => {
              this.loadAsignaciones();
              Swal.fire('¡Éxito!', 'La asignación de notificaciones ha sido guardada correctamente.', 'success');
            },
            error: (err) => {
              Swal.fire('Error', err.error.message || 'No se pudo guardar la asignación.', 'error');
            }
          });
        }
      });
    }
  }

  deleteAsignacion(asig: any) {
    Swal.fire({
      title: '¿Confirmar limpieza?',
      text: `¿Estás seguro de que deseas remover a todos los destinatarios asignados a la notificación "${asig.nombre}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, remover todos',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E',
      cancelButtonColor: '#718096'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${asig.id}`).subscribe({
          next: () => {
            this.loadAsignaciones();
            Swal.fire('Completado', 'Se han removido todas las asociaciones para esta notificación.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo limpiar la asignación.', 'error');
          }
        });
      }
    });
  }
}
