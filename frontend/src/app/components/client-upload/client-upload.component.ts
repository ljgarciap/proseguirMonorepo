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
              </tr>
              <tr *ngIf="uploads.length === 0">
                <td colspan="4" class="empty-state">
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
    
    .view-header {
      display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2.5rem;
      h1 { margin: 0 0 0.5rem 0; color: var(--primary); font-size: 1.8rem; }
      p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
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
    .bold { font-weight: 600; }
    .text-muted { color: var(--text-muted); font-style: italic; font-size: 0.85rem; }
  `]
})
export class ClientUploadComponent implements OnInit {
  selectedFile: File | null = null;
  uploads: any[] = [];
  isUploading = false;
  math = Math;

  // Pagination
  currentPage: number = 1;
  lastPage: number = 1;
  totalItems: number = 0;
  perPage: number = 10;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadUploads();
  }

  loadUploads(): void {
    const url = `${environment.apiUrl}/uploads?page=${this.currentPage}&perPage=${this.perPage}`;
    this.http.get<any>(url).subscribe(response => {
      this.uploads = response.data || [];
      this.currentPage = response.current_page || 1;
      this.lastPage = response.last_page || 1;
      this.totalItems = response.total || 0;
    });
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
}
