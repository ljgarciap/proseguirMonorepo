import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-operator-validation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Gestión de Validaciones</h1>
          <p>Auditoría y control de flujo de aprobación para cargues de clientes.</p>
        </div>
      </header>

      <!-- Filters Toolbar -->
      <div class="filters-toolbar card">
        <div class="search-group">
          <span class="material-symbols-outlined">search</span>
          <input type="text" [(ngModel)]="searchTerm" (keyup.enter)="onSearch()" placeholder="Buscar por cliente o archivo..." class="pro-input">
        </div>
        
        <div class="filter-controls">
          <div class="pro-input-group horizontal">
            <label>Estado:</label>
            <select [(ngModel)]="statusFilter" (change)="onFilterChange()" class="pro-input select-sm">
              <option value="todos">Todos los estados</option>
              <option value="pendiente">Pendiente</option>
              <option value="validado">Validado</option>
              <option value="aprobado">Aprobado</option>
              <option value="rechazado">Rechazado</option>
            </select>
          </div>
          
          <button class="btn-pro primary sm" (click)="loadUploads()">
             <span class="material-symbols-outlined">refresh</span>
          </button>
        </div>
      </div>

      <div class="content-body">
        <div class="table-section-header">
           <span class="material-symbols-outlined">list_alt</span>
           <h3>Documentos en Cola de Procesamiento</h3>
        </div>

        <div class="card p-0 overflow-hidden shadow-sm">
          <table class="pro-table">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Documento / Archivo</th>
                <th>Fecha de Carga</th>
                <th>Estado Actual</th>
                <th class="text-right">Acciones Disponibles</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let upload of uploads">
                <td>
                   <div class="client-cell">
                      <div class="avatar-sm">
                        <span class="material-symbols-outlined">person</span>
                      </div>
                      <span class="client-name">{{ upload.user?.name }}</span>
                   </div>
                </td>
                <td>
                   <div class="doc-cell">
                      <span class="material-symbols-outlined file-icon">description</span>
                      <span class="bold">{{ upload.original_name }}</span>
                   </div>
                </td>
                <td>{{ upload.created_at | date:'mediumDate' }}</td>
                <td>
                  <span class="pro-status" [ngClass]="{
                    'pending': upload.status === 'pendiente',
                    'validated': upload.status === 'validado',
                    'approved': upload.status === 'aprobado',
                    'rejected': upload.status === 'rechazado'
                  }">{{ upload.status }}</span>
                </td>
                <td class="text-right">
                  <div class="actions-group">
                    <!-- Operativo Actions -->
                    <ng-container *ngIf="userRole === 'operativo' || userRole === 'superadmin'">
                      <button *ngIf="upload.status === 'pendiente'" class="btn-pro secondary sm" (click)="handleAction(upload, 'validar')">
                        <span class="material-symbols-outlined">rule</span> Validar
                      </button>
                      <button *ngIf="upload.status === 'pendiente'" class="btn-pro danger sm" (click)="handleAction(upload, 'rechazar')">
                        <span class="material-symbols-outlined">block</span> Rechazar
                      </button>
                    </ng-container>
  
                    <!-- Gerente Actions -->
                    <ng-container *ngIf="userRole === 'gerente' || userRole === 'superadmin'">
                      <button *ngIf="upload.status === 'validado'" class="btn-pro primary sm" (click)="finalize(upload, 'aprobar')">
                        <span class="material-symbols-outlined">verified</span> Aprobar
                      </button>
                      <button *ngIf="upload.status === 'validado'" class="btn-pro danger sm" (click)="finalize(upload, 'rechazar')">
                        <span class="material-symbols-outlined">block</span> Rechazar
                      </button>
                    </ng-container>
  
                    <!-- Common Actions -->
                    <button class="btn-pro secondary sm icon-only" (click)="preview(upload)" title="Previsualizar">
                      <span class="material-symbols-outlined">visibility</span>
                    </button>
                    <button class="btn-pro secondary sm icon-only" (click)="download(upload)" title="Descargar Archivo">
                      <span class="material-symbols-outlined">download</span>
                    </button>
                    <button *ngIf="(userRole === 'cliente' && upload.status === 'pendiente') || userRole === 'superadmin'" 
                            class="btn-pro danger sm icon-only" (click)="deleteUpload(upload)" title="Eliminar Archivo">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
  
                    <div class="status-indicator success" *ngIf="upload.status === 'aprobado'">
                      <span class="material-symbols-outlined">check_circle</span>
                      <span>Completado</span>
                    </div>
                  </div>
                </td>
              </tr>
              <tr *ngIf="uploads.length === 0">
                <td colspan="5" class="empty-row">
                  <span class="material-symbols-outlined">inbox</span>
                  <p>No hay documentos que coincidan con los criterios.</p>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination Footer -->
          <footer class="pro-pagination-footer" *ngIf="totalItems > 0">
            <div class="info">
              Mostrando <strong>{{ (currentPage - 1) * perPage + 1 }} - {{ math.min(currentPage * perPage, totalItems) }}</strong> de <strong>{{ totalItems }}</strong> registros
            </div>
            <div class="pagination-actions">
              <div class="per-page-selector">
                <label>Mostrar:</label>
                <select [(ngModel)]="perPage" (change)="onPerPageChange()" class="compact-select">
                  <option [ngValue]="5">5</option>
                  <option [ngValue]="10">10</option>
                  <option [ngValue]="25">25</option>
                  <option [ngValue]="50">50</option>
                </select>
              </div>
              <div class="controls">
                <button class="btn-nav" [disabled]="currentPage === 1" (click)="changePage(currentPage - 1)">
                  <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <span class="current">Página {{ currentPage }} de {{ lastPage }}</span>
                <button class="btn-nav" [disabled]="currentPage === lastPage" (click)="changePage(currentPage + 1)">
                  <span class="material-symbols-outlined">chevron_right</span>
                </button>
              </div>
            </div>
          </footer>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .view-container { display: flex; flex-direction: column; min-height: 100vh; background: #F4F7FE; padding: 2.5rem 3rem; }
    .content-body { flex-grow: 1; margin-top: 2rem; }
    .p-0 { padding: 0 !important; }
    .overflow-hidden { overflow: hidden; }
    
    .view-header { margin-bottom: 2rem; }

    .filters-toolbar {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1.25rem 1.5rem; gap: 2rem; background: white; border-radius: 16px;
      
      .search-group {
        flex-grow: 1; position: relative;
        .material-symbols-outlined { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #A0AEC0; }
        input { padding-left: 44px; width: 100%; max-width: 450px; height: 45px; border-radius: 12px; }
      }

      .filter-controls {
        display: flex; align-items: center; gap: 1.5rem;
        .horizontal { flex-direction: row; align-items: center; gap: 10px; margin-bottom: 0; label { margin-bottom: 0; white-space: nowrap; font-weight: 600; color: #4A5568; } }
        .select-sm { width: 180px; padding: 8px 12px; border-radius: 10px; }
      }
    }

    .table-section-header {
      display: flex; align-items: center; gap: 12px; margin-bottom: 1.25rem; padding-left: 4px;
      .material-symbols-outlined { color: var(--primary); font-size: 24px; }
      h3 { margin: 0; font-size: 1.1rem; color: #2D3748; font-weight: 700; letter-spacing: -0.02em; }
    }

    .client-cell {
      display: flex; align-items: center; gap: 12px;
      .avatar-sm { width: 32px; height: 32px; border-radius: 50%; background: #EDF2F7; display: flex; align-items: center; justify-content: center; .material-symbols-outlined { color: #718096; font-size: 18px; } }
      .client-name { font-weight: 500; color: #4A5568; }
    }

    .doc-cell {
      display: flex; align-items: center; gap: 10px;
      .file-icon { color: #E2E8F0; font-size: 20px; }
      .bold { color: var(--text-main); font-weight: 600; }
    }

    .actions-group { display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center; }

    .empty-row {
      text-align: center; padding: 5rem !important; color: #A0AEC0;
      .material-symbols-outlined { font-size: 56px; margin-bottom: 1.25rem; opacity: 0.4; }
      p { margin: 0; font-weight: 500; font-size: 1.1rem; }
    }

    .status-indicator {
      display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700;
      &.success { color: var(--secondary); }
      .material-symbols-outlined { font-size: 18px; }
    }

    .pro-pagination-footer {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1.25rem 1.5rem; background: #F8FAFC; border-top: 1px solid #EDF2F7;
      font-size: 0.85rem; color: #718096;
      
      strong { color: #2D3748; }

      .pagination-actions {
        display: flex; align-items: center; gap: 2.5rem;
        
        .per-page-selector {
           display: flex; align-items: center; gap: 10px;
           label { margin: 0; font-weight: 600; color: #718096; }
           .compact-select { border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; background: white; font-weight: 700; color: var(--primary); cursor: pointer; outline: none; &:hover { border-color: var(--primary); } }
        }

        .controls {
          display: flex; align-items: center; gap: 1rem;
          .btn-nav {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid #E2E8F0;
            background: white; color: #718096; cursor: pointer; transition: all 0.2s;
            &:disabled { opacity: 0.4; cursor: not-allowed; }
            &:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); background: #F0F4FF; }
          }
          .current { font-weight: 700; color: #2D3748; }
        }
      }
    }

    .shadow-sm { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
  `]
})
export class OperatorValidationComponent implements OnInit {
  uploads: any[] = [];
  userRole: string | null = '';
  math = Math;

  // Pagination & Filters
  searchTerm: string = '';
  statusFilter: string = 'todos';
  currentPage: number = 1;
  lastPage: number = 1;
  totalItems: number = 0;
  perPage: number = 10;

  constructor(private http: HttpClient, private authService: AuthService) {}

  ngOnInit(): void {
    this.userRole = this.authService.getActiveRole();
    this.loadUploads();
  }

  loadUploads(): void {
    let url = `${environment.apiUrl}/uploads?page=${this.currentPage}&perPage=${this.perPage}`;
    if (this.searchTerm) url += `&search=${this.searchTerm}`;
    if (this.statusFilter !== 'todos') url += `&status=${this.statusFilter}`;

    this.http.get<any>(url).subscribe(response => {
      this.uploads = response.data || [];
      this.currentPage = response.current_page || 1;
      this.lastPage = response.last_page || 1;
      this.totalItems = response.total || 0;
    });
  }

  onSearch(): void {
    this.currentPage = 1;
    this.loadUploads();
  }

  onFilterChange(): void {
    this.currentPage = 1;
    this.loadUploads();
  }

  onPerPageChange(): void {
    this.currentPage = 1;
    this.loadUploads();
  }

  changePage(page: number): void {
    if (page >= 1 && page <= this.lastPage) {
      this.currentPage = page;
      this.loadUploads();
    }
  }

  async handleAction(upload: any, action: 'validar' | 'rechazar') {
    const { value: observations, isConfirmed } = await Swal.fire({
      title: action === 'validar' ? 'Confirmar Validación' : 'Registrar Rechazo',
      text: `¿Desea ${action} el archivo "${upload.original_name}"?`,
      input: 'textarea',
      inputLabel: 'Observaciones (opcional)',
      inputPlaceholder: 'Escriba sus comentarios aquí...',
      showCancelButton: true,
      confirmButtonText: action === 'validar' ? 'Sí, Validar' : 'Sí, Rechazar',
      confirmButtonColor: action === 'validar' ? '#38A169' : '#E53E3E',
      cancelButtonText: 'Cancelar'
    });

    if (isConfirmed) {
      this.http.post(`${environment.apiUrl}/uploads/${upload.id}/validate`, {
        action,
        observations: observations || ''
      }).subscribe(() => {
        this.loadUploads();
        Swal.fire('¡Éxito!', `El archivo ha sido ${action === 'validar' ? 'validado' : 'rechazado'}.`, 'success');
      });
    }
  }

  async finalize(upload: any, action: 'aprobar' | 'rechazar') {
    const { value: observations, isConfirmed } = await Swal.fire({
      title: action === 'aprobar' ? 'Aprobación Definitiva' : 'Rechazo Gerencial',
      text: `¿Está seguro de ${action} el archivo "${upload.original_name}"?`,
      input: 'textarea',
      inputLabel: 'Observaciones finales',
      inputPlaceholder: 'Comentarios para el cliente...',
      showCancelButton: true,
      confirmButtonText: action === 'aprobar' ? 'Sí, Aprobar' : 'Sí, Rechazar',
      confirmButtonColor: action === 'aprobar' ? '#1A3B8B' : '#E53E3E',
      cancelButtonText: 'Cancelar'
    });

    if (isConfirmed) {
      this.http.post(`${environment.apiUrl}/uploads/${upload.id}/approve`, {
        action,
        observations: observations || ''
      }).subscribe(() => {
        this.loadUploads();
        Swal.fire('¡Procesado!', `La documentación ha sido ${action === 'aprobar' ? 'aprobada' : 'rechazada'} exitosamente.`, 'success');
      });
    }
  }

  download(upload: any): void {
    const url = `${environment.apiUrl}/uploads/${upload.id}/download`;
    this.http.get(url, { responseType: 'blob' }).subscribe((blob: Blob) => {
      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.download = upload.original_name;
      link.click();
      window.URL.revokeObjectURL(downloadUrl);
    });
  }

  preview(upload: any): void {
    const url = `${environment.apiUrl}/uploads/${upload.id}/download`;
    const isImage = upload.original_name.toLowerCase().match(/\.(jpg|jpeg|png|gif|webp)$/);
    const isPdf = upload.original_name.toLowerCase().endsWith('.pdf');

    Swal.fire({
      title: 'Cargando previsualización...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.http.get(url, { responseType: 'blob' }).subscribe({
      next: (blob: Blob) => {
        const fileUrl = window.URL.createObjectURL(blob);
        let htmlContent = '';

        if (isImage) {
          htmlContent = `<img src="${fileUrl}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">`;
        } else if (isPdf) {
          htmlContent = `<iframe src="${fileUrl}" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>`;
        } else {
          htmlContent = `<div style="padding: 2rem; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 48px; color: #A0AEC0;">insert_drive_file</span>
            <p style="margin-top: 1rem;">Previsualización no disponible para este tipo de archivo.</p>
            <p style="font-size: 0.85rem; color: #718096;">${upload.original_name}</p>
          </div>`;
        }

        Swal.fire({
          title: upload.original_name,
          html: htmlContent,
          width: isImage ? 'auto' : '80%',
          showCloseButton: true,
          showConfirmButton: false,
          customClass: {
            popup: 'preview-modal-popup'
          },
          didClose: () => {
            window.URL.revokeObjectURL(fileUrl);
          }
        });
      }
    });
  }

  async deleteUpload(upload: any) {
    const { isConfirmed } = await Swal.fire({
      title: '¿Estás seguro?',
      text: `Se eliminará el archivo "${upload.original_name}" permanentemente.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E'
    });

    if (isConfirmed) {
      this.http.delete(`${environment.apiUrl}/uploads/${upload.id}`).subscribe({
        next: () => {
          this.loadUploads();
          Swal.fire('Eliminado', 'El archivo ha sido borrado.', 'success');
        },
        error: (err) => {
          console.error('Error deleting upload:', err);
          Swal.fire('Error', 'No se pudo eliminar el archivo.', 'error');
        }
      });
    }
  }
}
