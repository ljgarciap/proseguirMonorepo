import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-client-upload',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="pro-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Mis Documentos</h1>
          <p>Gestión y carga de archivos para validación operativa.</p>
        </div>
        <div class="actions">
          <div class="search-box-inline">
            <span class="material-symbols-outlined">search</span>
            <input type="text" [(ngModel)]="searchText" placeholder="Buscar por nombre de archivo..." (ngModelChange)="onSearch()">
          </div>
          <button class="btn-pro primary" (click)="fileInput.click()" [disabled]="isUploading">
            <span class="material-symbols-outlined">{{ isUploading ? 'sync' : 'upload_file' }}</span>
            {{ isUploading ? 'Subiendo...' : 'Cargar Nuevo Archivo' }}
          </button>
          <input type="file" #fileInput (change)="onFileSelected($event)" hidden>
        </div>
      </header>

      <div class="upload-feedback-card card" *ngIf="selectedFile">
        <div class="file-info">
          <span class="material-symbols-outlined">description</span>
          <div class="details">
            <span class="filename">{{ selectedFile.name }}</span>
            <span class="filesize">{{ (selectedFile.size / 1024 / 1024) | number:'1.1-2' }} MB</span>
          </div>
        </div>
        <div class="action-btns">
          <button class="btn-pro secondary" (click)="selectedFile = null">Cancelar</button>
          <button class="btn-pro primary" (click)="uploadFile()">Confirmar Envío</button>
        </div>
      </div>

      <!-- ACTIVE DOCUMENT REQUEST CHECKLIST -->
      <div class="active-request-card card mb-4 mt-2" *ngIf="activeRequest">
        <h3 class="card-title text-primary">
          <span class="material-symbols-outlined">playlist_add_check</span> Soportes Requeridos por Proseguir
        </h3>
        <p class="card-subtitle">A continuación se listan los documentos solicitados. Aquellos que posean plantilla descargable deben ser completados y firmados antes de cargarse en formato PDF.</p>
        
        <div class="items-list-vertical mt-3">
          <div class="item-row" *ngFor="let item of activeRequest.items" [class.approved-row]="item.estado === 'aprobado'">
            <div class="item-header-info">
              <span class="material-symbols-outlined icon-doc">description</span>
              <div class="title-details">
                <span class="item-title">{{ item.requirement?.nombre }}</span>
                <span class="item-desc" *ngIf="item.requirement?.descripcion">{{ item.requirement?.descripcion }}</span>
                
                <!-- Template box if requirement provides one -->
                <div class="template-box-inline mt-1" *ngIf="item.requirement?.tiene_plantilla">
                  <span class="material-symbols-outlined">download_for_offline</span>
                  <span>Formato Proseguir: </span>
                  <button type="button" class="btn-link-download" (click)="downloadTemplate(item.requirement.id, item.requirement.plantilla_nombre)">
                    Descargar ({{ item.requirement.plantilla_nombre }})
                  </button>
                </div>
              </div>
            </div>
            
            <div class="item-status-block">
              <span class="status-indicator-badge" [ngClass]="item.estado">
                <span class="status-dot"></span>
                {{ item.estado | uppercase }}
              </span>

              <!-- Upload action if not approved -->
              <ng-container *ngIf="item.estado !== 'aprobado'">
                <button type="button" class="btn-pro secondary sm upload-item-btn" (click)="itemFileInput.click()">
                  <span class="material-symbols-outlined">upload</span> Cargar Sopo.
                </button>
                <input type="file" #itemFileInput (change)="onFileSelectedForItem($event, item.id)" hidden>
              </ng-container>

              <span *ngIf="item.estado === 'aprobado'" class="completado-text">
                <span class="material-symbols-outlined">task_alt</span> Completado
              </span>
            </div>

            <!-- Observations if rejected -->
            <div class="item-observations" *ngIf="item.estado === 'rechazado' && item.observaciones">
              <span class="material-symbols-outlined text-danger">error</span>
              <p>Motivo Rechazo: {{ item.observaciones }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="history-section mt-4">
        <div class="section-header">
          <h3>Historial de Cargas</h3>
        </div>
        
        <div class="pro-table-wrapper card p-0 overflow-hidden">
          <table class="pro-table">
            <thead>
              <tr>
                <th>Archivo</th>
                <th>Fecha de Carga</th>
                <th>Estado</th>
                <th>Observaciones Operativas</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let upload of uploads">
                <td class="bold">
                  <div class="file-cell">
                    <span class="material-symbols-outlined">draft</span>
                    {{ upload.original_name }}
                  </div>
                </td>
                <td>{{ upload.created_at | date:'medium' }}</td>
                <td>
                  <span class="pro-status" [ngClass]="{
                    'pending': upload.status === 'pendiente',
                    'validated': upload.status === 'validado',
                    'approved': upload.status === 'aprobado',
                    'rejected': upload.status === 'rechazado'
                  }">{{ upload.status }}</span>
                </td>
                <td class="text-muted">{{ upload.observations || 'Sin observaciones aún' }}</td>
                <td class="text-center">
                  <div class="actions-cell-inline">
                    <button class="btn-view" (click)="viewFile(upload.id, upload.original_name)" title="Visualizar documento">
                      <span class="material-symbols-outlined">visibility</span>
                    </button>
                    <button *ngIf="upload.status === 'pendiente'" 
                            class="btn-delete" 
                            (click)="deleteUpload(upload.id)"
                            title="Eliminar carga">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                    <span *ngIf="upload.status !== 'pendiente'" class="text-muted" title="Ya está siendo procesado">
                      <span class="material-symbols-outlined disabled-icon">lock</span>
                    </span>
                  </div>
                </td>
              </tr>
              <tr *ngIf="uploads.length === 0">
                <td colspan="5" class="empty-state">
                  <span class="material-symbols-outlined">inventory_2</span>
                  <p>No se han encontrado registros de carga.</p>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination Footer -->
          <footer class="pro-pagination-footer" *ngIf="totalItems > 0">
            <div class="info">
              Mostrando {{ (currentPage - 1) * perPage + 1 }} - {{ math.min(currentPage * perPage, totalItems) }} de {{ totalItems }}
            </div>
            <div class="pagination-actions">
              <div class="per-page-selector">
                <label>Mostrar:</label>
                <select [(ngModel)]="perPage" (change)="onPerPageChange()" class="compact-select">
                  <option [ngValue]="5">5</option>
                  <option [ngValue]="10">10</option>
                  <option [ngValue]="25">25</option>
                </select>
              </div>
              <div class="controls">
                <button class="btn-nav" [disabled]="currentPage === 1" (click)="changePage(currentPage - 1)">
                  <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <span class="current">Página {{ currentPage }}</span>
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
    .pro-container { max-width: 1200px; margin: 0 auto; padding: 2.5rem 0; }
    .p-0 { padding: 0 !important; }
    .overflow-hidden { overflow: hidden; }
    .mb-4 { margin-bottom: 1.5rem; }
    
    .view-header {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;
      h1 { margin: 0 0 0.5rem 0; color: var(--primary); font-size: 1.8rem; }
      p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
    }

    .actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .upload-feedback-card {
      display: flex; justify-content: space-between; align-items: center;
      background: #EBF4FF; border-color: #BEE3F8; margin-bottom: 2rem;
      animation: slideIn 0.3s ease-out;
      .file-info {
        display: flex; align-items: center; gap: 1rem;
        .material-symbols-outlined { font-size: 32px; color: var(--primary); }
        .details { display: flex; flex-direction: column; .filename { font-weight: 700; color: var(--primary); } .filesize { font-size: 0.8rem; color: var(--secondary); } }
      }
      .action-btns { display: flex; gap: 1rem; }
    }

    .search-box-inline {
      display: flex; align-items: center; background: white; border: 1px solid #E2E8F0;
      border-radius: 10px; padding: 0.5rem 1rem; gap: 0.5rem; width: 300px;
      box-shadow: var(--shadow-sm);
      span { font-size: 20px; color: #94A3B8; }
      input { border: none; outline: none; width: 100%; font-size: 0.9rem; &::placeholder { color: #CBD5E1; } }
    }

    .file-cell { display: flex; align-items: center; gap: 10px; .material-symbols-outlined { color: var(--secondary); font-size: 18px; } }

    .empty-state {
      padding: 4rem 0 !important; text-align: center; color: var(--text-muted);
      .material-symbols-outlined { font-size: 48px; margin-bottom: 1rem; opacity: 0.3; }
      p { margin: 0; font-weight: 500; }
    }

    .pro-pagination-footer {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1rem 1.5rem; background: #F8FAFC; border-top: 1px solid #E2E8F0;
      font-size: 0.85rem; color: #64748B;
      
      .pagination-actions {
        display: flex; align-items: center; gap: 2.5rem;
        .per-page-selector {
           display: flex; align-items: center; gap: 8px;
           label { margin: 0; font-weight: 600; }
           .compact-select { border: 1px solid #E2E8F0; border-radius: 4px; padding: 2px 4px; background: white; font-weight: 700; color: var(--primary); }
        }
        .controls {
          display: flex; align-items: center; gap: 1rem;
          .btn-nav {
            display: flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 8px; border: 1px solid #E2E8F0;
            background: white; color: #64748B; cursor: pointer;
            &:disabled { opacity: 0.5; cursor: not-allowed; }
          }
          .current { font-weight: 600; color: var(--text-main); }
        }
      }
    }

    @keyframes slideIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .mt-4 { margin-top: 2rem; }
    .mt-3 { margin-top: 1rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-1 { margin-top: 0.25rem; }
    .bold { font-weight: 600; }
    .text-muted { color: var(--text-muted); font-style: italic; font-size: 0.85rem; }
    .text-center { text-align: center; }
    .text-primary { color: #1e3a8a; }
    
    .actions-cell-inline {
      display: flex; gap: 8px; justify-content: center; align-items: center;
    }

    .btn-view {
      background: none; border: none; color: #1A3B8B; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.2s;
      &:hover { background: #EBF4FF; transform: scale(1.1); }
      .material-symbols-outlined { font-size: 20px; }
    }

    .btn-delete {
      background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.2s;
      &:hover { background: #fee2e2; transform: scale(1.1); }
      .material-symbols-outlined { font-size: 20px; }
    }

    .disabled-icon {
      font-size: 18px; color: #cbd5e1; cursor: help;
    }

    /* Checklist specific styles */
    .active-request-card {
      border: 1px solid rgba(59, 130, 246, 0.4);
      background: linear-gradient(180deg, #ffffff 0%, #f0f7ff 100%);
      padding: 1.5rem;
      border-radius: 16px;
      box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.05);
    }
    
    .card-title {
      font-size: 1.25rem;
      font-weight: 700;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .card-subtitle {
      font-size: 0.875rem;
      color: #64748b;
      margin: 4px 0 0 0;
    }

    .items-list-vertical {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .item-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      padding: 1rem;
      border-radius: 12px;
      background: white;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
    }

    .item-row:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .approved-row {
      border-color: #bbf7d0;
      background-color: #f6fdf9;
    }

    .item-header-info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      min-width: 280px;
    }

    .icon-doc {
      font-size: 24px;
      color: #3b82f6;
    }

    .title-details {
      display: flex;
      flex-direction: column;
    }

    .item-title {
      font-weight: 700;
      color: #1e293b;
    }

    .item-desc {
      font-size: 0.8rem;
      color: #64748b;
    }

    .template-box-inline {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.8rem;
      color: #1e3a8a;
      .material-symbols-outlined {
        font-size: 16px;
        color: #1d4ed8;
      }
    }

    .btn-link-download {
      background: none;
      border: none;
      padding: 0;
      color: #1d4ed8;
      font-weight: 700;
      text-decoration: underline;
      cursor: pointer;
      font-size: 0.8rem;
    }

    .item-status-block {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .status-indicator-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      background: #f1f5f9;
      color: #475569;
    }

    .status-indicator-badge.pendiente {
      background: #fef3c7;
      color: #d97706;
      .status-dot { background: #f59e0b; }
    }

    .status-indicator-badge.subido {
      background: #e0f2fe;
      color: #0369a1;
      .status-dot { background: #0ea5e9; }
    }

    .status-indicator-badge.validado {
      background: #e0e7ff;
      color: #4338ca;
      .status-dot { background: #6366f1; }
    }

    .status-indicator-badge.aprobado {
      background: #dcfce7;
      color: #15803d;
      .status-dot { background: #22c55e; }
    }

    .status-indicator-badge.rechazado {
      background: #fee2e2;
      color: #b91c1c;
      .status-dot { background: #ef4444; }
    }

    .status-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #94a3b8;
    }

    .completado-text {
      color: #16a34a;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.9rem;
      .material-symbols-outlined { font-size: 18px; }
    }

    .item-observations {
      width: 100%;
      margin-top: 8px;
      padding: 8px 12px;
      border-radius: 8px;
      background: #fff5f5;
      border: 1px solid #fed7d7;
      display: flex;
      align-items: center;
      gap: 8px;
      p { margin: 0; font-size: 0.8rem; color: #c53030; font-weight: 500; }
      .material-symbols-outlined { font-size: 16px; color: #e53e3e; }
    }
  `]
})
export class ClientUploadComponent implements OnInit {
  selectedFile: File | null = null;
  uploads: any[] = [];
  isUploading = false;
  math = Math;
  
  // Active document request checklist
  activeRequest: any = null;

  // Pagination
  currentPage: number = 1;
  lastPage: number = 1;
  totalItems: number = 0;
  perPage: number = 10;
  searchText: string = '';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadActiveRequest();
    this.loadUploads();
  }

  loadActiveRequest(): void {
    this.http.get<any>(`${environment.apiUrl}/document-requests/active`).subscribe(response => {
      this.activeRequest = response;
    });
  }

  loadUploads(): void {
    let url = `${environment.apiUrl}/uploads?page=${this.currentPage}&perPage=${this.perPage}`;
    if (this.searchText) {
      url += `&search=${encodeURIComponent(this.searchText)}`;
    }
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

  onFileSelected(event: any): void {
    this.selectedFile = event.target.files[0];
  }

  uploadFile(): void {
    if (!this.selectedFile) return;

    this.isUploading = true;
    const formData = new FormData();
    formData.append('file', this.selectedFile);
    
    const activeRole = localStorage.getItem('active_role') || 'cliente';
    formData.append('active_role', activeRole);

    this.http.post(`${environment.apiUrl}/uploads`, formData).subscribe({
      next: () => {
        this.selectedFile = null;
        this.isUploading = false;
        this.currentPage = 1;
        this.loadUploads();
        
        Swal.fire({
          icon: 'success',
          title: '¡Carga Exitosa!',
          text: 'Tu archivo ha sido enviado a validación operativa.',
          confirmButtonColor: '#1A3B8B'
        });
      },
      error: (err) => {
        this.isUploading = false;
        Swal.fire({
          icon: 'error',
          title: 'Error de Carga',
          text: err.error?.message || 'No se pudo procesar el archivo. Intente nuevamente.',
          confirmButtonColor: '#1A3B8B'
        });
      }
    });
  }

  onFileSelectedForItem(event: any, itemId: number): void {
    const file = event.target.files[0];
    if (!file) return;

    if (file.type !== 'application/pdf') {
      Swal.fire('Formato Inválido', 'Solo se permite subir archivos en formato PDF.', 'warning');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_request_item_id', itemId.toString());
    formData.append('active_role', 'cliente');

    Swal.fire({
      title: 'Subiendo Documento...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.http.post(`${environment.apiUrl}/uploads`, formData).subscribe({
      next: () => {
        Swal.fire('Carga Exitosa', 'El documento ha sido cargado para validación operativa.', 'success');
        this.loadActiveRequest();
        this.loadUploads();
      },
      error: (err) => {
        Swal.fire('Error', err.error.message || 'No se pudo subir el archivo.', 'error');
      }
    });
  }

  downloadTemplate(reqId: number, originalName: string) {
    this.http.get(`${environment.apiUrl}/document-requirements/${reqId}/download-template`, {
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = originalName || 'plantilla.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el formato de plantilla.', 'error');
      }
    });
  }

  deleteUpload(id: number): void {
    Swal.fire({
      title: '¿Estás seguro?',
      text: "Esta acción eliminará el archivo permanentemente.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/uploads/${id}`).subscribe({
          next: () => {
            this.loadActiveRequest();
            this.loadUploads();
            Swal.fire('¡Eliminado!', 'El archivo ha sido borrado.', 'success');
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'No se pudo eliminar el archivo.', 'error');
          }
        });
      }
    });
  }

  viewFile(id: number, originalName: string): void {
    this.http.get(`${environment.apiUrl}/uploads/${id}/download`, {
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const fileType = blob.type;
        const url = window.URL.createObjectURL(blob);
        
        if (fileType === 'application/pdf' || fileType.startsWith('image/')) {
          window.open(url, '_blank');
        } else {
          const a = document.createElement('a');
          a.href = url;
          a.download = originalName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }
      },
      error: (err) => {
        Swal.fire('Error', 'No se pudo cargar el archivo para visualización.', 'error');
      }
    });
  }
}
