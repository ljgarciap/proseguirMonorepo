import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-visitas',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Registro de visita a cliente</h1>
          <p>Funcionalidad para el registro de las visitas realizadas a los clientes.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">add_comment</span> Nueva Visita
        </button>
      </header>

      <!-- Table Card with search filters in headers -->
      <div class="content-card mt-4">
        <div class="table-container">
          <table class="modern-table">
            <thead>
              <tr class="header-labels">
                <th>ID Visita</th>
                <th>Fecha</th>
                <th>Nombre o razón social del cliente</th>
                <th>Tipo de Persona</th>
                <th>Identificación</th>
                <th>Ciudad</th>
                <th class="text-right">Acciones</th>
              </tr>
              <tr class="header-filters">
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.id" (input)="applyFilters()" placeholder="ID..." class="table-filter-input" style="max-width: 80px;" />
                  </div>
                </td>
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="date" [(ngModel)]="filters.fecha" (change)="applyFilters()" class="table-filter-input" />
                  </div>
                </td>
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.cliente" (input)="applyFilters()" placeholder="Buscar cliente..." class="table-filter-input" />
                  </div>
                </td>
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.tipo_persona" (input)="applyFilters()" placeholder="Buscar tipo..." class="table-filter-input" />
                  </div>
                </td>
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.identificacion" (input)="applyFilters()" placeholder="Buscar ID..." class="table-filter-input" />
                  </div>
                </td>
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.ciudad" (input)="applyFilters()" placeholder="Buscar ciudad..." class="table-filter-input" />
                  </div>
                </td>
                <td></td>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let visit of paginatedVisitas">
                <td class="id-cell">#{{ visit.id }}</td>
                <td>{{ visit.fecha | date:'dd/MM/yyyy' }}</td>
                <td>
                  <div class="user-cell">
                    <span class="name">{{ visit.cliente?.nombre || '-' }}</span>
                  </div>
                </td>
                <td>{{ visit.cliente?.tipo_persona?.nombre || '-' }}</td>
                <td>
                  <div class="doc-cell">
                    <span class="doc-type">{{ visit.cliente?.document_type?.codigo || 'NIT' }}</span>
                    <span class="doc-num">{{ visit.cliente?.numero_documento || '-' }}</span>
                  </div>
                </td>
                <td>{{ visit.ciudad }}</td>
                <td class="text-right">
                  <div class="actions">
                    <button class="btn-pro secondary sm icon-only" (click)="openModal(visit)" title="Editar">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn-pro danger sm icon-only" (click)="deleteVisita(visit)" title="Eliminar">
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr *ngIf="visitas.length === 0 && !loading">
                <td colspan="7" class="text-center py-4">No se encontraron visitas registradas.</td>
              </tr>
              <tr *ngIf="loading">
                <td colspan="7" class="text-center py-4">Cargando información de visitas comerciales...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div class="pagination-footer mt-4" *ngIf="totalPages > 1">
          <button (click)="changePage(currentPage - 1)" [disabled]="currentPage === 1" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <span class="pagination-info">Página {{ currentPage }} de {{ totalPages }} (Total: {{ visitas.length }})</span>
          <button (click)="changePage(currentPage + 1)" [disabled]="currentPage === totalPages" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>
      </div>
    </div>

    <!-- VISIT FORM MODAL Overlay -->
    <div class="modal-backdrop" *ngIf="showModal">
      <div class="modal-card">
        <header class="modal-header">
          <div>
            <h3>{{ isEdit ? 'Editar Registro de Visita' : 'Registro de Visita a Cliente' }}</h3>
            <p class="modal-subtitle">Complete la información de la visita y registre la necesidad de crédito identificada.</p>
          </div>
          <button class="btn-close" (click)="closeModal()">
            <span class="material-symbols-outlined">close</span>
          </button>
        </header>

        <main class="modal-body">
          <form #visitForm="ngForm" class="pro-form">
            <!-- SECTION 1: INFORMACIÓN GENERAL -->
            <div class="form-section">
              <h4 class="section-title">Información general de la visita</h4>
              
              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Fecha de la visita <span class="required">*</span></label>
                  <input type="date" name="fecha" [(ngModel)]="form.fecha" required class="pro-input" />
                </div>
                
                <div class="pro-input-group">
                  <label>Ciudad <span class="required">*</span></label>
                  <input type="text" name="ciudad" [(ngModel)]="form.ciudad" required placeholder="Ciudad de la visita" class="pro-input" />
                </div>
              </div>

              <div class="form-row one-col">
                <div class="pro-input-group">
                  <label>Cliente <span class="required">*</span></label>
                  <select name="cliente_id" [(ngModel)]="form.cliente_id" required class="pro-input" [disabled]="isEdit">
                    <option value="" disabled selected>Buscar / seleccionar cliente activo...</option>
                    <option *ngFor="let c of activeClientes" [value]="c.id">
                      {{ c.nombre }} ({{ c.tipo_persona?.nombre || 'Tipo N/A' }} - {{ c.numero_documento }})
                    </option>
                  </select>
                </div>
              </div>

              <div class="form-row one-col">
                <div class="pro-input-group">
                  <label>Asistentes a la visita <span class="required">*</span></label>
                  <input type="text" name="asistentes" [(ngModel)]="form.asistentes" required placeholder="Nombres de asistentes internos y del cliente" class="pro-input" />
                </div>
              </div>

              <div class="form-row one-col">
                <div class="pro-input-group">
                  <label>Observaciones</label>
                  <textarea name="observaciones" [(ngModel)]="form.observaciones" rows="3" placeholder="Ingrese observaciones relevantes, acuerdos, compromisos o hallazgos comerciales..." class="pro-input textarea"></textarea>
                </div>
              </div>
            </div>

            <!-- SECTION 2: NECESIDAD DE CRÉDITO -->
            <div class="form-section">
              <h4 class="section-title">Necesidad de crédito</h4>
              
              <div class="pro-input-group">
                <label>¿Requiere crédito? <span class="required">*</span></label>
                <div class="radio-group mt-2">
                  <label class="radio-label">
                    <input type="radio" name="requiere_credito" [value]="true" [(ngModel)]="form.requiere_credito" />
                    <span>Sí</span>
                  </label>
                  <label class="radio-label">
                    <input type="radio" name="requiere_credito" [value]="false" [(ngModel)]="form.requiere_credito" />
                    <span>No</span>
                  </label>
                </div>
                <p class="help-text mt-1 text-muted">Nota: si se selecciona "No", la sección de Información del crédito se oculta.</p>
              </div>
            </div>

            <!-- SECTION 3: INFORMACIÓN DEL CRÉDITO SOLICITADO (CONDITIONAL) -->
            <div *ngIf="form.requiere_credito === true" class="form-section highlight-section">
              <h4 class="section-title text-primary">Información del crédito solicitado</h4>
              
              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Tipo de crédito <span class="required">*</span></label>
                  <select name="tipo_credito_id" [(ngModel)]="form.tipo_credito_id" required class="pro-input">
                    <option value="" disabled selected>Seleccione tipo de crédito...</option>
                    <option *ngFor="let tc of tipoCreditos" [value]="tc.id">{{ tc.nombre }}</option>
                  </select>
                </div>
                
                <div class="pro-input-group">
                  <label>Monto solicitado <span class="required">*</span></label>
                  <input type="number" name="monto_solicitado" [(ngModel)]="form.monto_solicitado" required min="0.01" placeholder="$ 0.00" class="pro-input" />
                </div>
              </div>

              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Plazo (Meses) <span class="required">*</span></label>
                  <input type="number" name="plazo" [(ngModel)]="form.plazo" required min="1" placeholder="Número de meses" class="pro-input" />
                </div>
                
                <div class="pro-input-group">
                  <label>Amortización <span class="required">*</span></label>
                  <select name="amortizacion_id" [(ngModel)]="form.amortizacion_id" required class="pro-input">
                    <option value="" disabled selected>Mensual / Trimestral / Otra...</option>
                    <option *ngFor="let am of amortizaciones" [value]="am.id">{{ am.nombre }}</option>
                  </select>
                </div>
              </div>

              <div class="form-row one-col">
                <div class="pro-input-group">
                  <label>¿Para qué requiere el recurso? <span class="required">*</span></label>
                  <textarea name="destino_recurso" [(ngModel)]="form.destino_recurso" required rows="2" placeholder="Describa el destino del recurso solicitado..." class="pro-input textarea"></textarea>
                </div>
              </div>

              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Garantía</label>
                  <input type="text" name="garantia" [(ngModel)]="form.garantia" placeholder="Tipo de garantía ofrecida" class="pro-input" />
                </div>
                
                <div class="pro-input-group">
                  <label>Fuente de pago <span class="required">*</span></label>
                  <input type="text" name="fuente_pago" [(ngModel)]="form.fuente_pago" required placeholder="Ingresos, flujo esperado u otra fuente" class="pro-input" />
                </div>
              </div>
            </div>
          </form>
        </main>

        <footer class="modal-footer">
          <button class="btn-pro secondary" (click)="closeModal()">Cancelar</button>
          <button class="btn-pro primary" [disabled]="!visitForm.valid" (click)="saveVisita()">Guardar Visita</button>
        </footer>
      </div>
    </div>
  `,
  styles: [`
    .view-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
    .view-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .title-area { h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: #1e293b; } p { margin: 4px 0 0 0; color: #64748b; font-size: 0.95rem; } }

    .content-card {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.4);
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
    }

    .modern-table {
      width: 100%;
      border-collapse: collapse;

      th {
        padding: 0.85rem 1rem;
        text-align: left;
        font-weight: 700;
        color: #475569;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .header-labels th {
        border-bottom: 1px solid rgba(226, 232, 240, 0.8);
      }

      .header-filters td {
        padding: 0.5rem 1rem 1rem 1rem;
        border-bottom: 2px solid rgba(226, 232, 240, 0.8);
      }

      td {
        padding: 1rem;
        color: #334155;
        border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        font-size: 0.95rem;
      }

      tr:hover td {
        background-color: rgba(241, 245, 249, 0.4);
      }
    }

    .id-cell {
      font-weight: 700;
      color: #1e40af;
    }

    .filter-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;

      .filter-icon {
        position: absolute;
        left: 8px;
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
      }

      .table-filter-input {
        width: 100%;
        padding: 6px 6px 6px 28px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.85rem;
        outline: none;
        background: white;
        transition: all 0.2s;

        &:focus {
          border-color: #3b82f6;
          box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
      }
    }

    .user-cell {
      .name { font-weight: 600; color: #1e293b; }
    }

    .doc-cell {
      display: flex;
      gap: 6px;
      align-items: center;
      .doc-type {
        background: #f1f5f9;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
      }
      .doc-num { font-weight: 500; }
    }

    .actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }

    .pagination-footer {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 1rem;
    }

    .btn-pagination {
      background: white;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      padding: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      transition: all 0.2s;

      &:hover:not(:disabled) { background: #f8fafc; }
      &:disabled { opacity: 0.5; cursor: not-allowed; }
    }

    .pagination-info { font-size: 0.85rem; color: #475569; }

    /* Modal Style overrides */
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(15, 23, 42, 0.3);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1100;
    }

    .modal-card {
      background: white;
      width: 90%;
      max-width: 800px;
      max-height: 90vh;
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(226, 232, 240, 0.8);
      animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      h3 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; }
      .modal-subtitle { margin: 2px 0 0 0; font-size: 0.85rem; color: #64748b; }
    }

    .btn-close {
      background: transparent;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      color: #64748b;
      &:hover { color: #0f172a; }
    }

    .modal-body {
      padding: 1.5rem;
      overflow-y: auto;
    }

    .modal-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }

    /* Form Styles */
    .pro-form {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .form-section {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      border-top: 1px solid #f1f5f9;
      padding-top: 1.25rem;

      &:first-child {
        border-top: none;
        padding-top: 0;
      }
    }

    .highlight-section {
      background-color: #f8fafc;
      padding: 1.25rem;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }

    .section-title {
      margin: 0 0 0.25rem 0;
      font-size: 0.95rem;
      font-weight: 700;
      color: #1e3a8a;
    }

    .form-row {
      display: grid;
      gap: 1rem;

      &.two-cols {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      }

      &.one-col {
        grid-template-columns: 1fr;
      }
    }

    .pro-input-group {
      display: flex;
      flex-direction: column;
      gap: 4px;

      label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
      }
    }

    .pro-input {
      padding: 10px 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 0.9rem;
      outline: none;
      color: #0f172a;
      background-color: #f8fafc;
      transition: all 0.2s;

      &:focus {
        border-color: #3b82f6;
        background-color: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
      }

      &.textarea {
        resize: vertical;
        font-family: inherit;
      }

      &:disabled {
        background-color: #e2e8f0;
        color: #64748b;
        cursor: not-allowed;
      }
    }

    .radio-group {
      display: flex;
      gap: 24px;
    }

    .radio-label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 600;
      color: #475569;

      input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
        cursor: pointer;
      }
    }

    .help-text {
      font-size: 0.75rem;
    }

    .required {
      color: #ef4444;
      font-weight: 700;
    }

    /* Buttons */
    .btn-pro {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      font-size: 0.9rem;
      font-weight: 600;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s;

      &.primary {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2);

        &:hover:not(:disabled) {
          background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }
      }

      &.secondary {
        background: #f1f5f9;
        color: #475569;

        &:hover:not(:disabled) {
          background: #e2e8f0;
        }
      }

      &.danger {
        background: #fee2e2;
        color: #dc2626;

        &:hover:not(:disabled) {
          background: #fca5a5;
        }
      }

      &.sm {
        padding: 6px 12px;
        font-size: 0.8rem;
      }

      &.icon-only {
        padding: 6px;
        border-radius: 6px;
        .material-symbols-outlined { font-size: 18px; }
      }

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    }
  `]
})
export class VisitasComponent implements OnInit {
  visitas: any[] = [];
  activeClientes: any[] = [];
  tipoCreditos: any[] = [];
  amortizaciones: any[] = [];
  loading = false;

  // Pagination & Filtering
  currentPage = 1;
  pageSize = 10;
  filters: any = {
    id: '',
    fecha: '',
    cliente: '',
    tipo_persona: '',
    identificacion: '',
    ciudad: ''
  };

  // Form Modal state
  showModal = false;
  isEdit = false;
  editingId: number | null = null;
  form: any = {
    fecha: '',
    ciudad: '',
    cliente_id: '',
    asistentes: '',
    observaciones: '',
    requiere_credito: false,
    
    // Credit fields
    tipo_credito_id: '',
    monto_solicitado: null,
    plazo: null,
    amortizacion_id: '',
    destino_recurso: '',
    garantia: '',
    fuente_pago: ''
  };

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadVisitas();
    this.loadActiveClientes();
    this.loadParameters();
  }

  loadVisitas() {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/visitas`).subscribe({
      next: (data) => {
        this.visitas = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudieron cargar las visitas comerciales.', 'error');
      }
    });
  }

  loadActiveClientes() {
    // Only load active clients for visits selection
    this.http.get<any[]>(`${environment.apiUrl}/clientes?activo=true`).subscribe({ next: data => this.activeClientes = data, error: () => {} });
  }

  loadParameters() {
    this.http.get<any[]>(`${environment.apiUrl}/parameters/tipo_creditos`).subscribe({ next: data => this.tipoCreditos = data, error: () => {} });
    this.http.get<any[]>(`${environment.apiUrl}/parameters/amortizaciones`).subscribe({ next: data => this.amortizaciones = data, error: () => {} });
  }

  applyFilters() {
    const params = new URLSearchParams();
    if (this.filters.id) params.append('id', this.filters.id);
    if (this.filters.fecha) params.append('fecha', this.filters.fecha);
    if (this.filters.cliente) params.append('cliente', this.filters.cliente);
    if (this.filters.tipo_persona) params.append('tipo_persona', this.filters.tipo_persona);
    if (this.filters.identificacion) params.append('identificacion', this.filters.identificacion);
    if (this.filters.ciudad) params.append('ciudad', this.filters.ciudad);

    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/visitas?${params.toString()}`).subscribe({
      next: (data) => {
        this.visitas = data;
        this.currentPage = 1;
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  get paginatedVisitas() {
    const startIndex = (this.currentPage - 1) * this.pageSize;
    return this.visitas.slice(startIndex, startIndex + this.pageSize);
  }

  get totalPages() {
    return Math.ceil(this.visitas.length / this.pageSize);
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  openModal(visit: any = null) {
    this.isEdit = !!visit;
    if (visit) {
      this.editingId = visit.id;
      this.form = { ...visit };
      // Format date for input tag (YYYY-MM-DD)
      if (visit.fecha) {
        this.form.fecha = new Date(visit.fecha).toISOString().split('T')[0];
      }
      this.form.cliente_id = visit.cliente_id || '';
      this.form.tipo_credito_id = visit.tipo_credito_id || '';
      this.form.amortizacion_id = visit.amortizacion_id || '';
      this.form.requiere_credito = !!visit.requiere_credito;
    } else {
      this.editingId = null;
      this.form = {
        fecha: new Date().toISOString().split('T')[0], // Default to today
        ciudad: '',
        cliente_id: '',
        asistentes: '',
        observaciones: '',
        requiere_credito: false,
        
        tipo_credito_id: '',
        monto_solicitado: null,
        plazo: null,
        amortizacion_id: '',
        destino_recurso: '',
        garantia: '',
        fuente_pago: ''
      };
    }
    this.showModal = true;
  }

  closeModal() {
    this.showModal = false;
    this.editingId = null;
  }

  saveVisita() {
    // If requiring credit, validate fields manually too (additional frontend safety)
    if (this.form.requiere_credito) {
      if (!this.form.tipo_credito_id || !this.form.monto_solicitado || !this.form.plazo || !this.form.amortizacion_id || !this.form.destino_recurso || !this.form.fuente_pago) {
        Swal.fire('Validación', 'Por favor complete todos los campos obligatorios de la necesidad de crédito.', 'warning');
        return;
      }
      if (this.form.monto_solicitado <= 0 || this.form.plazo <= 0) {
        Swal.fire('Validación', 'Monto y Plazo deben ser valores mayores a cero.', 'warning');
        return;
      }
    }

    const request = this.isEdit
      ? this.http.put(`${environment.apiUrl}/visitas/${this.editingId}`, this.form)
      : this.http.post(`${environment.apiUrl}/visitas`, this.form);

    request.subscribe({
      next: () => {
        Swal.fire('¡Éxito!', 'Registro de visita guardado correctamente.', 'success');
        this.closeModal();
        this.loadVisitas();
      },
      error: (err) => {
        const errorMsg = err.error?.message || 'Ocurrió un error al registrar la visita.';
        Swal.fire('Error', errorMsg, 'error');
      }
    });
  }

  deleteVisita(visit: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: `Se eliminará de forma permanente el registro de visita #${visit.id}.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ef4444'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/visitas/${visit.id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'La visita ha sido eliminada del sistema.', 'success');
            this.loadVisitas();
          },
          error: () => {
            Swal.fire('Error', 'No se pudo eliminar la visita.', 'error');
          }
        });
      }
    });
  }
}
