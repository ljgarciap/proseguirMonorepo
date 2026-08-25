import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';

/**
 * SCRUM-246 — Log de actividad de usuarios (solo superadmin, ver
 * roleGuard en app.routes.ts). Distinta de /logs (LogsComponent), que es
 * el pipeline de OCR — esta pantalla es "quién hizo qué" a nivel de
 * negocio (login, firmas, y lo que se vaya conectando a futuro).
 */
@Component({
  selector: 'app-activity-logs',
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
            placeholder="Buscar por usuario, IP, descripción..."
            class="search-input"
          />
        </div>

        <select [(ngModel)]="accionFiltro" (change)="onFiltroChange()" class="filter-select">
          <option value="">Todas las acciones</option>
          <option *ngFor="let a of accionesDisponibles" [value]="a">{{ a }}</option>
        </select>

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
                <th (click)="sortByColumn('created_at')" class="sortable">
                  FECHA/HORA <span *ngIf="sortBy === 'created_at'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th>USUARIO</th>
                <th (click)="sortByColumn('accion')" class="sortable">
                  ACCIÓN <span *ngIf="sortBy === 'accion'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                </th>
                <th>DESCRIPCIÓN</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let log of logs">
                <td class="timestamp">{{ log.created_at | date:'dd/MM/yyyy HH:mm:ss' }}</td>
                <td>{{ log.nombre_usuario || '(sin usuario)' }}</td>
                <td><span class="accion-badge">{{ log.accion }}</span></td>
                <td class="descripcion" [title]="log.descripcion">{{ log.descripcion }}</td>
                <td class="ip">{{ log.direccion_ip || '-' }}</td>
              </tr>
              <tr *ngIf="logs.length === 0 && !isLoading">
                <td colspan="5" class="empty-row">No se encontraron registros de actividad.</td>
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
                <option [ngValue]="15">15</option>
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
      </div>
    </div>
  `,
  styles: [`
    :host { display: block; height: 100%; background-color: #f8fafc; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .view-container { padding: 24px; display: flex; flex-direction: column; gap: 24px; min-height: 100vh; box-sizing: border-box; }
    .search-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .search-card {
      background: white; border-radius: 8px; padding: 0 16px; display: flex; align-items: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; width: 100%; max-width: 400px; height: 48px;
    }
    .search-icon { color: #94a3b8; margin-right: 12px; }
    .search-input { border: none; outline: none; width: 100%; font-size: 15px; }
    .filter-select {
      height: 48px; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 12px; background: white; font-size: 14px; color: #334155;
    }
    .btn-action {
      background: #1e293b; color: white; border: none; border-radius: 8px; height: 40px; padding: 0 20px;
      font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;
    }
    .btn-action:hover { background: #0f172a; }
    .btn-action.loading .icon { animation: spin 1s linear infinite; }
    .content-card { background: white; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
    .table-container { overflow-x: auto; }
    .modern-table { width: 100%; border-collapse: collapse; }
    .modern-table th { padding: 14px 24px; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; text-align: left; }
    .modern-table th.sortable { cursor: pointer; user-select: none; }
    .modern-table td { padding: 14px 24px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
    .timestamp { white-space: nowrap; color: #64748b; }
    .accion-badge {
      background: #eef2ff; color: #4338ca; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; white-space: nowrap;
    }
    .descripcion { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ip { font-family: monospace; color: #64748b; }
    .empty-row { text-align: center; color: #94a3b8; padding: 32px; }
    .footer-pagination { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 12px; }
    .pagination-info { font-size: 13px; color: #64748b; }
    .pagination-controls { display: flex; align-items: center; gap: 16px; }
    .per-page-group { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
    .compact-select { border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px; }
    .nav-buttons { display: flex; gap: 4px; }
    .btn-nav { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; }
    .btn-nav:disabled { opacity: 0.4; cursor: not-allowed; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
  `]
})
export class ActivityLogsComponent implements OnInit {
  logs: any[] = [];
  accionesDisponibles: string[] = [];
  isLoading = false;
  math = Math;

  searchTerm = '';
  accionFiltro = '';
  sortBy = 'created_at';
  sortDir: 'desc' | 'asc' = 'desc';
  currentPage = 1;
  lastPage = 1;
  totalItems = 0;
  perPage = 15;

  private apiUrl = `${environment.apiUrl}/activity-logs`;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadLogs();
    this.loadAcciones();
  }

  loadLogs() {
    this.isLoading = true;

    const url = new URL(this.apiUrl, window.location.origin);
    if (this.searchTerm) url.searchParams.append('search', this.searchTerm);
    if (this.accionFiltro) url.searchParams.append('accion', this.accionFiltro);
    url.searchParams.append('sortBy', this.sortBy);
    url.searchParams.append('sortDir', this.sortDir);
    url.searchParams.append('page', this.currentPage.toString());
    url.searchParams.append('perPage', this.perPage.toString());

    this.http.get<any>(url.toString()).subscribe({
      next: (response) => {
        this.logs = response.data || [];
        this.currentPage = response.current_page || 1;
        this.lastPage = response.last_page || 1;
        this.totalItems = response.total || 0;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error fetching activity logs', error);
        this.logs = [];
        this.isLoading = false;
      }
    });
  }

  loadAcciones() {
    this.http.get<string[]>(`${this.apiUrl}/acciones`).subscribe({
      next: (acciones) => (this.accionesDisponibles = acciones),
      error: () => (this.accionesDisponibles = []),
    });
  }

  onSearch() {
    this.currentPage = 1;
    this.loadLogs();
  }

  onFiltroChange() {
    this.currentPage = 1;
    this.loadLogs();
  }

  onPerPageChange() {
    this.currentPage = 1;
    this.loadLogs();
  }

  sortByColumn(column: string) {
    if (this.sortBy === column) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = column;
      this.sortDir = 'desc';
    }
    this.loadLogs();
  }

  changePage(page: number) {
    if (page < 1 || page > this.lastPage) return;
    this.currentPage = page;
    this.loadLogs();
  }
}
