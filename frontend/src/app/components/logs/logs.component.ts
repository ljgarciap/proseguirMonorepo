import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-logs',
  standalone: true,
  imports: [CommonModule, FormsModule, DatePipe],
  template: `
    <div class="view-container">
      <div class="search-header">
        <div class="search-card">
          <span class="search-icon">🔍</span>
          <input 
            type="text" 
            [(ngModel)]="searchTerm" 
            (keyup.enter)="onSearch()"
            placeholder="Buscar en logs..." 
            class="search-input"
          />
        </div>
        
        <button (click)="loadLogs()" class="btn-action" [class.loading]="isLoading">
          <span class="icon">🔄</span>
          <span class="text">{{ isLoading ? 'Cargando...' : 'Actualizar' }}</span>
        </button>
      </div>

      <div class="content-card">
        <div class="table-container">
          <table class="modern-table">
            <thead>
              <tr>
                <th (click)="sortByColumn('id')" class="sortable">
                  <div class="th-content">
                    ID <span class="sort-icon" *ngIf="sortBy === 'id'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th (click)="sortByColumn('created_at')" class="sortable">
                  <div class="th-content">
                    FECHA/HORA <span class="sort-icon" *ngIf="sortBy === 'created_at'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th (click)="sortByColumn('categoria')" class="sortable">
                  <div class="th-content">
                    CATEGORÍA <span class="sort-icon" *ngIf="sortBy === 'categoria'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th (click)="sortByColumn('filename')" class="sortable">
                  <div class="th-content">
                    ARCHIVO <span class="sort-icon" *ngIf="sortBy === 'filename'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th (click)="sortByColumn('action')" class="sortable">
                  <div class="th-content">
                    ACCIÓN <span class="sort-icon" *ngIf="sortBy === 'action'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th (click)="sortByColumn('records_processed')" class="sortable text-right">
                  <div class="th-content j-end">
                    RÉCORDS <span class="sort-icon" *ngIf="sortBy === 'records_processed'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                  </div>
                </th>
                <th class="text-right">ACCIONES</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let log of logs">
                <td class="id-badge">#{{ log.id }}</td>
                <td class="timestamp">{{ log.created_at | date:'dd/MM/yyyy HH:mm' }}</td>
                <td>
                  <span class="modern-badge category-{{ log.categoria }}">
                    {{ log.categoria || 'N/A' | uppercase }}
                  </span>
                </td>
                <td class="filename">{{ log.filename || '-' }}</td>
                <td>
                  <span class="status-indicator" 
                        [class.success]="log.action.includes('Recibido') || log.action.includes('éxito')"
                        [class.error]="log.action.includes('Error') || log.action.includes('Fallo')">
                    {{ log.action }}
                  </span>
                </td>
                <td class="text-right font-medium">{{ log.records_processed }}</td>
                <td class="text-right actions-cell">
                  <button class="action-btn view-btn" (click)="openDetails(log)" title="Ver Detalles">
                    👁️
                  </button>
                  <button class="action-btn retry-btn" (click)="retryLog(log)" title="Reintentar">
                    🔄
                  </button>
                  <button *ngIf="['superadmin', 'gerente', 'operativo'].includes(userRole!) && log.filename" class="action-btn mass-delete-btn" (click)="deleteFileData(log)" title="Borrar datos de este archivo">
                    🔥
                  </button>
                  <button *ngIf="['superadmin', 'gerente', 'operativo'].includes(userRole!)" class="action-btn delete-btn" (click)="deleteLog(log)" title="Eliminar Log">
                    🗑️
                  </button>
                </td>
              </tr>
              <tr *ngIf="logs.length === 0 && !isLoading">
                <td colspan="7" class="empty-row">No se encontraron registros de auditoría.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="footer-pagination">
          <div class="pagination-info">
             {{ (currentPage - 1) * perPage + 1 }} - {{ math.min(currentPage * perPage, totalItems) }} de {{ totalItems }}
          </div>
          
          <div class="pagination-controls">
            <div class="per-page-group">
              <span class="label">Ítems por página:</span>
              <select [(ngModel)]="perPage" (change)="onPerPageChange()" class="compact-select">
                <option [ngValue]="5">5</option>
                <option [ngValue]="10">10</option>
                <option [ngValue]="25">25</option>
                <option [ngValue]="50">50</option>
                <option [ngValue]="100">100</option>
              </select>
            </div>
            
            <div class="nav-buttons">
              <button (click)="changePage(currentPage - 1)" [disabled]="currentPage === 1 || isLoading" class="btn-nav">‹</button>
              <button (click)="changePage(currentPage + 1)" [disabled]="currentPage === lastPage || isLoading" class="btn-nav">›</button>
            </div>
          </div>
        </div>
      
      <!-- Modal Detalles -->
      <div class="modal-overlay" *ngIf="selectedLog" (click)="closeDetails()">
        <div class="modal-content" (click)="$event.stopPropagation()">
          <div class="modal-header">
            <h3>Detalles del Log #{{ selectedLog.id }}</h3>
            <button class="close-btn" (click)="closeDetails()">×</button>
          </div>
          <div class="modal-body">
            <div class="detail-group">
              <label>Acción:</label>
              <div class="detail-value">{{ selectedLog.action }}</div>
            </div>
            <div class="detail-group">
              <label>Mensaje:</label>
              <div class="detail-value">{{ selectedLog.message }}</div>
            </div>
            
            <div class="detail-group" *ngIf="selectedLog.payload">
              <label>Payload (Datos recibidos):</label>
              <pre class="code-block">{{ formatJson(selectedLog.payload) }}</pre>
            </div>

            <div class="detail-group" *ngIf="selectedLog.error_details">
              <label class="text-danger">Detalles del Error técnico:</label>
              <pre class="code-block error-block">{{ selectedLog.error_details }}</pre>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" (click)="closeDetails()">Cerrar</button>
            <button *ngIf="canRetry(selectedLog)" class="btn-primary" (click)="retryLog(selectedLog); closeDetails()">
              Reintentar Operación
            </button>
          </div>
        </div>
      </div>

      <!-- Toast Notifications -->
      <div class="toast-container">
        <div *ngFor="let toast of toasts" class="toast" [class]="'toast-' + toast.type">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          <span class="toast-message">{{ toast.message }}</span>
          <button class="toast-close" (click)="removeToast(toast)">×</button>
        </div>
      </div>
    </div>

  `,
  styles: [`
    :host {
      display: block;
      height: 100%;
      background-color: #f8fafc;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .view-container {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 24px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    .search-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .search-card {
      background: white;
      border-radius: 8px;
      padding: 0 16px;
      display: flex;
      align-items: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      width: 100%;
      max-width: 400px;
      height: 48px;

      .search-icon { color: #94a3b8; margin-right: 12px; }
      .search-input { border: none; outline: none; width: 100%; font-size: 15px; }
    }

    .btn-action {
      background: #1e293b;
      color: white;
      border: none;
      border-radius: 8px;
      height: 40px;
      padding: 0 20px;
      font-weight: 500;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;

      &:hover { background: #0f172a; }
      &.loading .icon { animation: spin 1s linear infinite; }
    }

    .content-card {
      background: white;
      border-radius: 12px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      overflow: visible;
      flex: none;
    }

    .table-container { overflow-x: auto; }

    .modern-table {
      width: 100%;
      border-collapse: collapse;
      
      th {
        padding: 14px 24px;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        background: #fcfcfc;
        z-index: 10;
        cursor: pointer;

        .th-content { display: flex; align-items: center; gap: 8px; }
        .j-end { justify-content: flex-end; }
        .sort-icon { color: #3b82f6; }
      }

      td {
        padding: 16px 24px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 14px;
      }

      tr:hover td { background-color: #f8fafc; }
    }

    .modern-badge {
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
    }

    .category-cartera { background: #eff6ff; color: #1d4ed8; }
    .category-op { background: #f0fdf4; color: #15803d; }
    .category-pagos { background: #fff7ed; color: #9a3412; }
    .category-opf { background: #faf5ff; color: #7e22ce; }

    .status-indicator {
      font-weight: 600;
      &.success { color: #10b981; }
      &.error { color: #ef4444; }
    }

    .footer-pagination {
      padding: 16px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fcfcfc;
      border-top: 1px solid #f1f5f9;
      color: #64748b;
      font-size: 13px;
    }

    .pagination-controls { display: flex; align-items: center; gap: 32px; }
    .per-page-group { display: flex; align-items: center; gap: 12px; }
    .compact-select { border: none; background: none; font-weight: 600; cursor: pointer; color: #1e293b; }
    .nav-buttons { display: flex; gap: 8px; }
    .btn-nav { width: 32px; height: 32px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; }

    .id-badge { color: #94a3b8; font-weight: 500; width: 60px; }
    .timestamp { color: #64748b; width: 150px; }
    .filename { color: #0284c7; font-weight: 500; }
    .text-right { text-align: right; }
    .font-medium { font-weight: 500; }
    .empty-row { padding: 48px; text-align: center; color: #94a3b8; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    /* Toast Styles */
    .toast-container { position: fixed; bottom: 24px; right: 24px; display: flex; flex-direction: column; gap: 12px; z-index: 9999; }
    .toast { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); min-width: 300px; animation: slideIn 0.3s ease-out forwards; background: white; color: #1e293b; border-left: 4px solid #cbd5e1; }
    .toast-success { border-left-color: #10b981; }
    .toast-error { border-left-color: #ef4444; }
    .toast-message { flex: 1; font-size: 14px; font-weight: 500; }
    .toast-close { background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; }
    .toast-close:hover { color: #475569; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    .actions-cell { display: flex; gap: 8px; justify-content: flex-end; }
    .action-btn { background: none; border: none; font-size: 16px; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
    .action-btn:hover { background: #f1f5f9; }
    
    /* Modal Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); }
    .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 700px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: #1e293b; }
    .close-btn { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
    .modal-body { padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
    .detail-group label { display: block; font-weight: 600; font-size: 0.85rem; color: #64748b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
    .detail-value { font-size: 0.95rem; color: #1e293b; }
    .code-block { background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 0.85rem; overflow-x: auto; white-space: pre-wrap; color: #334155; }
    .error-block { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .text-danger { color: #ef4444 !important; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #fcfcfc; }
    .btn-secondary { background: white; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; color: #475569; font-weight: 500; cursor: pointer; }
    .btn-primary { background: #3b82f6; border: none; padding: 8px 16px; border-radius: 6px; color: white; font-weight: 500; cursor: pointer; }
  `]
})
export class LogsComponent implements OnInit {
  logs: any[] = [];
  isLoading = false;
  userRole: string | null = '';
  math = Math;

  // Parámetros de tabla
  searchTerm: string = '';
  sortBy: string = 'id';
  sortDir: 'desc' | 'asc' = 'desc';
  currentPage: number = 1;
  lastPage: number = 1;
  totalItems: number = 0;
  perPage: number = 5;

  private apiUrl = `${environment.apiUrl}/logs`;

  constructor(private http: HttpClient) { }

  ngOnInit() {
    this.userRole = localStorage.getItem('active_role');
    this.loadLogs();
  }

  loadLogs() {
    this.isLoading = true;

    // Construir URL con parámetros
    const url = new URL(this.apiUrl);
    if (this.searchTerm) url.searchParams.append('search', this.searchTerm);
    if (this.sortBy) url.searchParams.append('sortBy', this.sortBy);
    if (this.sortDir) url.searchParams.append('sortDir', this.sortDir);
    url.searchParams.append('page', this.currentPage.toString());
    url.searchParams.append('perPage', this.perPage.toString());

    this.http.get<any>(url.toString()).subscribe({
      next: (response) => {
        // Paginación Laravel: response.data contiene los array
        this.logs = response.data || response;
        this.currentPage = response.current_page || 1;
        this.lastPage = response.last_page || 1;
        this.totalItems = response.total || 0;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error fetching logs', error);
        this.logs = [];
        this.isLoading = false;
      }
    });
  }

  onSearch() {
    this.currentPage = 1;
    this.loadLogs();
  }

  onPerPageChange() {
    this.currentPage = 1;
    this.loadLogs();
  }

  sortByColumn(col: string) {
    if (this.sortBy === col) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = col;
      this.sortDir = 'asc';
    }
    this.loadLogs();
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.lastPage) {
      this.currentPage = page;
      this.loadLogs();
    }
  }

  selectedLog: any = null;

  openDetails(log: any) {
    this.selectedLog = log;
  }

  closeDetails() {
    this.selectedLog = null;
  }

  canRetry(log: any): boolean {
    return log && (log.action.includes('Error') || log.action.includes('Fallo')) && !!log.payload;
  }

  formatJson(jsonStr: string): string {
    try {
      const parsed = JSON.parse(jsonStr);
      return JSON.stringify(parsed, null, 2);
    } catch (e) {
      return jsonStr;
    }
  }

  // Sistema de Toasts
  toasts: { id: number, message: string, type: 'success' | 'error' }[] = [];
  toastIdCounter = 0;

  showToast(message: string, type: 'success' | 'error' = 'success') {
    const id = this.toastIdCounter++;
    const toast = { id, message, type };
    this.toasts.push(toast);
    setTimeout(() => this.removeToast(toast), 5000); // Auto close after 5s
  }

  removeToast(toast: any) {
    this.toasts = this.toasts.filter(t => t.id !== toast.id);
  }

  retryLog(log: any) {
    Swal.fire({
      title: '¿Reintentar procesamiento?',
      text: `Se volverá a procesar el payload del log #${log.id}.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3b82f6',
      cancelButtonColor: '#94a3b8',
      confirmButtonText: 'Sí, reintentar',
      cancelButtonText: 'Cancelar',
      timerProgressBar: true
    }).then((result) => {
      if (result.isConfirmed) {
        this.isLoading = true;
        this.http.post(`${this.apiUrl}/${log.id}/retry`, {}).subscribe({
          next: (res: any) => {
            this.showToast(res.message || 'Log reintentado con éxito', 'success');
            this.loadLogs(); // Refrescar tabla
          },
          error: (err) => {
            console.error(err);
            this.showToast(err.error?.message || 'Hubo un error al reintentar el log', 'error');
            this.loadLogs();
          }
        });
      }
    });
  }

  deleteLog(log: any) {
    Swal.fire({
      title: '¿Eliminar registro?',
      text: `Se borrará el log #${log.id} permanentemente.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#94a3b8',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${this.apiUrl}/${log.id}`).subscribe({
          next: (res: any) => {
            this.showToast(res.message || 'Log eliminado con éxito', 'success');
            this.loadLogs();
          },
          error: (err) => {
            console.error(err);
            this.showToast(err.error?.message || 'Error al eliminar el log', 'error');
          }
        });
      }
    });
  }

  deleteFileData(log: any) {
    Swal.fire({
      title: '¿Borrar TODOS los datos de este archivo?',
      text: `Esta acción eliminará todos los registros de cartera, factoring, etc., asociados al archivo "${log.filename}". Esta acción no se puede deshacer.`,
      icon: 'error',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, BORRAR TODO',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.isLoading = true;
        // First find the upload ID by filename
        this.http.get<any>(`${environment.apiUrl}/uploads?search=${log.filename}`).subscribe({
          next: (res) => {
            const upload = res.data ? res.data.find((u: any) => u.filename === log.filename) : null;
            if (!upload) {
              this.showToast('No se encontró el registro del archivo para este log', 'error');
              this.isLoading = false;
              return;
            }

            // Now delete by upload ID
            this.http.delete(`${environment.apiUrl}/history/by-upload/${upload.id}`).subscribe({
              next: (delRes: any) => {
                Swal.fire('¡Limpieza Completada!', `${delRes.deleted_records} registros fueron eliminados exitosamente.`, 'success');
                this.isLoading = false;
              },
              error: (err) => {
                console.error(err);
                this.showToast('Error al intentar el borrado masivo', 'error');
                this.isLoading = false;
              }
            });
          },
          error: (err) => {
            console.error(err);
            this.showToast('Error al buscar el archivo', 'error');
            this.isLoading = false;
          }
        });
      }
    });
  }
}
