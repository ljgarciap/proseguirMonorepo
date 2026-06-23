import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-clientes',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="view-container">
      <header class="view-header">
        <div class="title-area">
          <h1>Registro de Clientes</h1>
          <p>Funcionalidad para el registro de clientes.</p>
        </div>
        <button class="btn-pro primary" (click)="openModal()">
          <span class="material-symbols-outlined">person_add</span> Nuevo Cliente
        </button>
      </header>

      <!-- Search Filters Row (Column search inputs) -->
      <div class="content-card mt-4">
        <div class="table-container">
          <table class="modern-table">
            <thead>
              <tr class="header-labels">
                <th>Nombre o razón social</th>
                <th>Tipo de Persona</th>
                <th>Identificación</th>
                <th>Activo</th>
                <th class="text-right">Acciones</th>
              </tr>
              <tr class="header-filters">
                <td>
                  <div class="filter-input-wrapper">
                    <span class="material-symbols-outlined filter-icon">search</span>
                    <input type="text" [(ngModel)]="filters.nombre" (input)="applyFilters()" placeholder="Buscar nombre..." class="table-filter-input" />
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
                  <select [(ngModel)]="filters.activo" (change)="applyFilters()" class="table-filter-select">
                    <option value="">Todos</option>
                    <option value="true">Activo</option>
                    <option value="false">Inactivo</option>
                  </select>
                </td>
                <td></td>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let client of paginatedClientes">
                <td>
                  <div class="user-cell">
                    <div class="avatar-sm">{{ client.nombre.charAt(0) }}</div>
                    <span class="name">{{ client.nombre }}</span>
                  </div>
                </td>
                <td>{{ client.tipo_persona?.nombre || '-' }}</td>
                <td>
                  <div class="doc-cell">
                    <span class="doc-type">{{ client.document_type?.codigo || 'NIT/CC' }}</span>
                    <span class="doc-num">{{ client.numero_documento }}</span>
                  </div>
                </td>
                <td>
                  <span class="status-badge" [class.active]="client.activo" [class.inactive]="!client.activo">
                    {{ client.activo ? 'Sí' : 'No' }}
                  </span>
                </td>
                <td class="text-right">
                  <div class="actions">
                    <button class="btn-pro secondary sm icon-only" (click)="openModal(client)" title="Editar">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button *ngIf="client.activo" class="btn-pro danger sm icon-only" (click)="deactivateCliente(client)" title="Desactivar">
                      <span class="material-symbols-outlined">person_off</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr *ngIf="clientes.length === 0 && !loading">
                <td colspan="5" class="text-center py-4">No se encontraron clientes registrados.</td>
              </tr>
              <tr *ngIf="loading">
                <td colspan="5" class="text-center py-4">Cargando información de clientes...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div class="pagination-footer mt-4" *ngIf="totalPages > 1">
          <button (click)="changePage(currentPage - 1)" [disabled]="currentPage === 1" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <span class="pagination-info">Página {{ currentPage }} de {{ totalPages }} (Total: {{ clientes.length }})</span>
          <button (click)="changePage(currentPage + 1)" [disabled]="currentPage === totalPages" class="btn-pagination">
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </div>
      </div>
    </div>

    <!-- CLIENT FORM MODAL Overlay -->
    <div class="modal-backdrop" *ngIf="showModal">
      <div class="modal-card">
        <header class="modal-header">
          <h3>{{ isEdit ? 'Editar Información del Cliente' : 'Registro de Cliente' }}</h3>
          <button class="btn-close" (click)="closeModal()">
            <span class="material-symbols-outlined">close</span>
          </button>
        </header>

        <main class="modal-body">
          <form #clientForm="ngForm" class="pro-form">
            <!-- COMMON TOP SECTION -->
            <div class="form-row three-cols">
              <div class="pro-input-group">
                <label>Tipo de Persona <span class="required">*</span></label>
                <select name="tipo_persona_id" [(ngModel)]="form.tipo_persona_id" (change)="onTipoPersonaChange()" required class="pro-input" [disabled]="isEdit && hasOriginalTipoPersona" [class.is-invalid]="errors['tipo_persona_id']">
                  <option value="" disabled selected>Seleccione...</option>
                  <option *ngFor="let tp of tipoPersonas" [value]="tp.id">{{ tp.nombre }}</option>
                </select>
              </div>

              <div class="pro-input-group">
                <label>Tipo de Documento <span class="required">*</span></label>
                <select name="tipo_documento_id" [(ngModel)]="form.tipo_documento_id" required class="pro-input" [class.is-invalid]="errors['tipo_documento_id']">
                  <option value="" disabled selected>Seleccione...</option>
                  <option *ngFor="let dt of documentTypes" [value]="dt.id">{{ dt.codigo }} - {{ dt.nombre }}</option>
                </select>
              </div>

              <div class="pro-input-group">
                <label>Número Documento <span class="required">*</span></label>
                <input type="text" name="numero_documento" [(ngModel)]="form.numero_documento" required placeholder="Ej. 123456789" class="pro-input" [class.is-invalid]="errors['numero_documento']" />
              </div>
            </div>

            <!-- DYNAMIC: PERSONA NATURAL SECTION -->
            <div *ngIf="getSelectedTipoPersonaCodigo() === 'NATURAL'" class="form-section">
              <h4 class="section-title">Información Persona Natural</h4>
              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Nombres <span class="required">*</span></label>
                  <input type="text" name="nombres" [(ngModel)]="form.nombres" required placeholder="Nombres" class="pro-input" [class.is-invalid]="errors['nombres']" />
                </div>
                <div class="pro-input-group">
                  <label>Primer Apellido <span class="required">*</span></label>
                  <input type="text" name="primer_apellido" [(ngModel)]="form.primer_apellido" required placeholder="Primer Apellido" class="pro-input" [class.is-invalid]="errors['primer_apellido']" />
                </div>
                <div class="pro-input-group">
                  <label>Segundo Apellido</label>
                  <input type="text" name="segundo_apellido" [(ngModel)]="form.segundo_apellido" placeholder="Segundo Apellido" class="pro-input" [class.is-invalid]="errors['segundo_apellido']" />
                </div>
              </div>

              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Correo Electrónico</label>
                  <input type="email" name="correo_electronico" [(ngModel)]="form.correo_electronico" email placeholder="correo@ejemplo.com" class="pro-input" [class.is-invalid]="errors['correo_electronico']" />
                </div>
                <div class="pro-input-group">
                  <label>Ocupación</label>
                  <input type="text" name="ocupacion" [(ngModel)]="form.ocupacion" placeholder="Ocupación" class="pro-input" [class.is-invalid]="errors['ocupacion']" />
                </div>
                <div class="pro-input-group">
                  <label>Teléfono</label>
                  <input type="text" name="telefono" [(ngModel)]="form.telefono" placeholder="Teléfono" class="pro-input" [class.is-invalid]="errors['telefono']" />
                </div>
              </div>
            </div>

            <!-- DYNAMIC: PERSONA JURÍDICA SECTION -->
            <div *ngIf="getSelectedTipoPersonaCodigo() === 'JURIDICA'" class="form-section">
              <h4 class="section-title">Información Persona Jurídica</h4>
              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Nombre o Razón Social <span class="required">*</span></label>
                  <input type="text" name="nombre_razon_social" [(ngModel)]="form.nombre_razon_social" required placeholder="Razón Social" class="pro-input" [class.is-invalid]="errors['nombre_razon_social']" />
                </div>
                <div class="pro-input-group">
                  <label>Tipo de Empresa</label>
                  <input type="text" name="tipo_empresa" [(ngModel)]="form.tipo_empresa" placeholder="Ej. S.A.S, Limitada" class="pro-input" [class.is-invalid]="errors['tipo_empresa']" />
                </div>
                <div class="pro-input-group">
                  <label>Actividad Económica</label>
                  <input type="text" name="actividad_economica" [(ngModel)]="form.actividad_economica" placeholder="Actividad Económica" class="pro-input" [class.is-invalid]="errors['actividad_economica']" />
                </div>
              </div>

              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Correo Electrónico Empresarial</label>
                  <input type="email" name="correo_electronico_empresarial" [(ngModel)]="form.correo_electronico_empresarial" email placeholder="correo@empresa.com" class="pro-input" [class.is-invalid]="errors['correo_electronico_empresarial']" />
                </div>
                <div class="pro-input-group">
                  <label>Página Web</label>
                  <input type="text" name="pagina_web" [(ngModel)]="form.pagina_web" placeholder="www.empresa.com" class="pro-input" [class.is-invalid]="errors['pagina_web']" />
                </div>
                <div class="pro-input-group">
                  <label>Teléfono</label>
                  <input type="text" name="telefono" [(ngModel)]="form.telefono" placeholder="Teléfono" class="pro-input" [class.is-invalid]="errors['telefono']" />
                </div>
              </div>

              <!-- REPRESENTANTE LEGAL SECTION -->
              <h4 class="section-title mt-4 text-primary">Información del Representante Legal</h4>
              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Tipo de Documento <span class="required">*</span></label>
                  <select name="rep_tipo_documento_id" [(ngModel)]="form.rep_tipo_documento_id" required class="pro-input" [class.is-invalid]="errors['rep_tipo_documento_id']">
                    <option value="" disabled selected>Seleccione...</option>
                    <option *ngFor="let dt of documentTypes" [value]="dt.id">{{ dt.codigo }} - {{ dt.nombre }}</option>
                  </select>
                </div>
                <div class="pro-input-group">
                  <label>Número Documento <span class="required">*</span></label>
                  <input type="text" name="rep_numero_documento" [(ngModel)]="form.rep_numero_documento" required placeholder="Número Documento" class="pro-input" [class.is-invalid]="errors['rep_numero_documento']" />
                </div>
                <div class="pro-input-group">
                  <label>Teléfono</label>
                  <input type="text" name="rep_telefono" [(ngModel)]="form.rep_telefono" placeholder="Teléfono Rep. Legal" class="pro-input" [class.is-invalid]="errors['rep_telefono']" />
                </div>
              </div>

              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>Nombres <span class="required">*</span></label>
                  <input type="text" name="rep_nombres" [(ngModel)]="form.rep_nombres" required placeholder="Nombres" class="pro-input" [class.is-invalid]="errors['rep_nombres']" />
                </div>
                <div class="pro-input-group">
                  <label>Primer Apellido <span class="required">*</span></label>
                  <input type="text" name="rep_primer_apellido" [(ngModel)]="form.rep_primer_apellido" required placeholder="Primer Apellido" class="pro-input" [class.is-invalid]="errors['rep_primer_apellido']" />
                </div>
                <div class="pro-input-group">
                  <label>Segundo Apellido</label>
                  <input type="text" name="rep_segundo_apellido" [(ngModel)]="form.rep_segundo_apellido" placeholder="Segundo Apellido" class="pro-input" [class.is-invalid]="errors['rep_segundo_apellido']" />
                </div>
              </div>

              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Cargo</label>
                  <input type="text" name="rep_cargo" [(ngModel)]="form.rep_cargo" placeholder="Cargo" class="pro-input" [class.is-invalid]="errors['rep_cargo']" />
                </div>
                <div class="pro-input-group">
                  <label>Correo Electrónico</label>
                  <input type="email" name="rep_correo_electronico" [(ngModel)]="form.rep_correo_electronico" email placeholder="correo@replegal.com" class="pro-input" [class.is-invalid]="errors['rep_correo_electronico']" />
                </div>
              </div>
            </div>

            <!-- GEOGRAPHIC AND ADDRESS (COMMON SECTION) -->
            <div *ngIf="getSelectedTipoPersonaCodigo()" class="form-section">
              <h4 class="section-title">Ubicación y Dirección</h4>
              <div class="form-row three-cols">
                <div class="pro-input-group">
                  <label>País <span class="required">*</span></label>
                  <input type="text" name="pais" [(ngModel)]="form.pais" required placeholder="País" class="pro-input" [class.is-invalid]="errors['pais']" />
                </div>
                <div class="pro-input-group">
                  <label>Departamento <span class="required">*</span></label>
                  <input type="text" name="departamento" [(ngModel)]="form.departamento" required placeholder="Departamento" class="pro-input" [class.is-invalid]="errors['departamento']" />
                </div>
                <div class="pro-input-group">
                  <label>Ciudad <span class="required">*</span></label>
                  <input type="text" name="ciudad" [(ngModel)]="form.ciudad" required placeholder="Ciudad" class="pro-input" [class.is-invalid]="errors['ciudad']" />
                </div>
              </div>

              <div class="form-row two-cols">
                <div class="pro-input-group">
                  <label>Dirección</label>
                  <input type="text" name="direccion" [(ngModel)]="form.direccion" placeholder="Dirección Completa" class="pro-input" [class.is-invalid]="errors['direccion']" />
                </div>
                <div class="pro-input-group checkbox-align">
                  <label class="pro-checkbox-label">
                    <input type="checkbox" name="activo" [(ngModel)]="form.activo" />
                    <span>Activo</span>
                  </label>
                </div>
              </div>
            </div>
          </form>
        </main>

        <footer class="modal-footer">
          <button class="btn-pro secondary" (click)="closeModal()">Cancelar</button>
          <button class="btn-pro primary" [disabled]="!clientForm.valid" (click)="saveCliente()">Guardar</button>
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

    .table-filter-select {
      width: 100%;
      padding: 6px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 0.85rem;
      outline: none;
      background: white;
      transition: all 0.2s;

      &:focus {
        border-color: #3b82f6;
      }
    }

    .user-cell {
      display: flex;
      align-items: center;
      gap: 12px;

      .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
      }

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

    .status-badge {
      font-size: 0.75rem;
      font-weight: 700;
      padding: 4px 8px;
      border-radius: 6px;
      display: inline-block;

      &.active {
        background: #dcfce7;
        color: #15803d;
      }

      &.inactive {
        background: #fee2e2;
        color: #b91c1c;
      }
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

    /* Modal Styling */
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
      z-index: 1040;
    }

    .modal-card {
      background: white;
      width: 90%;
      max-width: 850px;
      max-height: 90vh;
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(226, 232, 240, 0.8);
      animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalSlideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      h3 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; }
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

      &.three-cols {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }

      &.two-cols {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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

      &:disabled {
        background-color: #e2e8f0;
        color: #64748b;
        cursor: not-allowed;
      }

      &.is-invalid {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        background-color: #fef2f2 !important;
      }
    }

    .required {
      color: #ef4444;
      font-weight: 700;
    }

    .checkbox-align {
      justify-content: center;
      padding-top: 1.5rem;
    }

    .pro-checkbox-label {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 600;
      color: #475569;

      input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
        cursor: pointer;
      }
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
export class ClientesComponent implements OnInit {
  clientes: any[] = [];
  tipoPersonas: any[] = [];
  documentTypes: any[] = [];
  loading = false;

  // Pagination & Filtering
  currentPage = 1;
  pageSize = 10;
  filters = {
    nombre: '',
    tipo_persona: '',
    identificacion: '',
    activo: ''
  };

  hasOriginalTipoPersona = false;
  // Form Modal state
  showModal = false;
  isEdit = false;
  editingId: number | null = null;
  errors: any = {};
  form: any = {
    tipo_persona_id: '',
    tipo_documento_id: '',
    numero_documento: '',
    nombres: '',
    primer_apellido: '',
    segundo_apellido: '',
    correo_electronico: '',
    ocupacion: '',
    telefono: '',
    pais: '',
    departamento: '',
    ciudad: '',
    direccion: '',
    nombre_razon_social: '',
    tipo_empresa: '',
    correo_electronico_empresarial: '',
    pagina_web: '',
    actividad_economica: '',
    rep_tipo_documento_id: '',
    rep_numero_documento: '',
    rep_nombres: '',
    rep_primer_apellido: '',
    rep_segundo_apellido: '',
    rep_cargo: '',
    rep_correo_electronico: '',
    rep_telefono: '',
    activo: true
  };

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadClientes();
    this.loadParameters();
  }

  loadClientes() {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/clientes`).subscribe({
      next: (data) => {
        this.clientes = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.fireAlert('Error', 'No se pudieron cargar los clientes.', 'error');
      }
    });
  }

  loadParameters() {
    this.http.get<any[]>(`${environment.apiUrl}/parameters/tipo_personas`).subscribe(data => this.tipoPersonas = data);
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe(data => this.documentTypes = data);
  }

  applyFilters() {
    const params = new URLSearchParams();
    if (this.filters.nombre) params.append('nombre', this.filters.nombre);
    if (this.filters.tipo_persona) params.append('tipo_persona', this.filters.tipo_persona);
    if (this.filters.identificacion) params.append('identificacion', this.filters.identificacion);
    if (this.filters.activo) params.append('activo', this.filters.activo);

    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/clientes?${params.toString()}`).subscribe({
      next: (data) => {
        this.clientes = data;
        this.currentPage = 1;
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  get paginatedClientes() {
    const startIndex = (this.currentPage - 1) * this.pageSize;
    return this.clientes.slice(startIndex, startIndex + this.pageSize);
  }

  get totalPages() {
    return Math.ceil(this.clientes.length / this.pageSize);
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  getSelectedTipoPersonaCodigo(): string {
    const selected = this.tipoPersonas.find(tp => tp.id == this.form.tipo_persona_id);
    return selected ? selected.codigo.toUpperCase() : '';
  }

  onTipoPersonaChange() {
    // Reset type specific fields when type changes
    this.form.nombres = '';
    this.form.primer_apellido = '';
    this.form.segundo_apellido = '';
    this.form.correo_electronico = '';
    this.form.ocupacion = '';
    this.form.nombre_razon_social = '';
    this.form.tipo_empresa = '';
    this.form.correo_electronico_empresarial = '';
    this.form.pagina_web = '';
    this.form.actividad_economica = '';
    this.form.rep_tipo_documento_id = '';
    this.form.rep_numero_documento = '';
    this.form.rep_nombres = '';
    this.form.rep_primer_apellido = '';
    this.form.rep_segundo_apellido = '';
    this.form.rep_cargo = '';
    this.form.rep_correo_electronico = '';
    this.form.rep_telefono = '';

    // Auto-migrate legacy name when selecting person type
    const code = this.getSelectedTipoPersonaCodigo();
    if (this.form.nombre) {
      if (code === 'JURIDICA') {
        this.form.nombre_razon_social = this.form.nombre;
      } else if (code === 'NATURAL') {
        const parts = this.form.nombre.trim().split(/\s+/);
        if (parts.length >= 4) {
          this.form.nombres = parts.slice(0, 2).join(' ');
          this.form.primer_apellido = parts[2];
          this.form.segundo_apellido = parts.slice(3).join(' ');
        } else if (parts.length === 3) {
          this.form.nombres = parts[0];
          this.form.primer_apellido = parts[1];
          this.form.segundo_apellido = parts[2];
        } else if (parts.length === 2) {
          this.form.nombres = parts[0];
          this.form.primer_apellido = parts[1];
        } else {
          this.form.nombres = this.form.nombre;
        }
      }
    }
  }

  openModal(client: any = null) {
    this.isEdit = !!client;
    this.errors = {};
    if (client) {
      this.editingId = client.id;
      this.form = { ...client };
      // Convert relations references to foreign keys integers
      this.form.tipo_persona_id = client.tipo_persona_id || '';
      this.hasOriginalTipoPersona = !!client.tipo_persona_id;
      this.form.tipo_documento_id = client.tipo_documento_id || '';
      this.form.rep_tipo_documento_id = client.rep_tipo_documento_id || '';
      this.form.activo = !!client.activo;

      // Fallback for legacy database records
      if (!this.form.numero_documento && client.identificacion) {
        this.form.numero_documento = client.identificacion;
      }
      // Set defaults for address fields if missing
      if (!this.form.pais) this.form.pais = 'Colombia';
    } else {
      this.editingId = null;
      this.hasOriginalTipoPersona = false;
      this.form = {
        tipo_persona_id: '',
        tipo_documento_id: '',
        numero_documento: '',
        nombres: '',
        primer_apellido: '',
        segundo_apellido: '',
        correo_electronico: '',
        ocupacion: '',
        telefono: '',
        pais: 'Colombia', // Sensible default
        departamento: '',
        ciudad: '',
        direccion: '',
        nombre_razon_social: '',
        tipo_empresa: '',
        correo_electronico_empresarial: '',
        pagina_web: '',
        actividad_economica: '',
        rep_tipo_documento_id: '',
        rep_numero_documento: '',
        rep_nombres: '',
        rep_primer_apellido: '',
        rep_segundo_apellido: '',
        rep_cargo: '',
        rep_correo_electronico: '',
        rep_telefono: '',
        activo: true
      };
    }
    this.showModal = true;
  }

  closeModal() {
    this.showModal = false;
    this.editingId = null;
    this.errors = {};
  }

  saveCliente() {
    this.errors = {};
    const codigo = this.getSelectedTipoPersonaCodigo();
    if (codigo === 'NATURAL' && this.form.correo_electronico) {
      if (!this.isValidEmail(this.form.correo_electronico)) {
        this.errors['correo_electronico'] = true;
        this.fireAlert('Validación', 'El formato del correo electrónico no es válido.', 'warning');
        return;
      }
    }
    if (codigo === 'JURIDICA') {
      if (this.form.correo_electronico_empresarial && !this.isValidEmail(this.form.correo_electronico_empresarial)) {
        this.errors['correo_electronico_empresarial'] = true;
        this.fireAlert('Validación', 'El formato del correo empresarial no es válido.', 'warning');
        return;
      }
      if (this.form.rep_correo_electronico && !this.isValidEmail(this.form.rep_correo_electronico)) {
        this.errors['rep_correo_electronico'] = true;
        this.fireAlert('Validación', 'El formato del correo del representante legal no es válido.', 'warning');
        return;
      }
    }

    const request = this.isEdit 
      ? this.http.put(`${environment.apiUrl}/clientes/${this.editingId}`, this.form)
      : this.http.post(`${environment.apiUrl}/clientes`, this.form);

    request.subscribe({
      next: () => {
        this.closeModal();
        this.fireAlert('¡Éxito!', 'Cliente guardado correctamente.', 'success');
        this.loadClientes();
      },
      error: (err) => {
        if (err.status === 422 && err.error?.errors) {
          const validationErrors = err.error.errors;
          for (const key in validationErrors) {
            this.errors[key] = true;
          }
          const fieldNames: { [key: string]: string } = {
            numero_documento: 'Número Documento',
            nombres: 'Nombres',
            primer_apellido: 'Primer Apellido',
            nombre_razon_social: 'Nombre o Razón Social',
            correo_electronico: 'Correo Electrónico',
            correo_electronico_empresarial: 'Correo Empresarial',
            rep_correo_electronico: 'Correo Rep. Legal',
            rep_numero_documento: 'Documento Rep. Legal',
            rep_nombres: 'Nombres Rep. Legal',
            rep_primer_apellido: 'Primer Apellido Rep. Legal',
            pais: 'País',
            departamento: 'Departamento',
            ciudad: 'Ciudad'
          };
          const errList = Object.keys(validationErrors)
            .map(k => fieldNames[k] || k)
            .join(', ');
          this.fireAlert('Validación', `Por favor verifique los siguientes campos con errores: ${errList}`, 'warning');
        } else {
          const errorMsg = err.error?.message || 'Ocurrió un error al procesar el registro.';
          this.fireAlert('Error', errorMsg, 'error');
        }
      }
    });
  }

  deactivateCliente(client: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: `Se desactivará el cliente "${client.nombre}" y se suspenderá su acceso de usuario al sistema.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, desactivar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#ef4444'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/clientes/${client.id}`).subscribe({
          next: () => {
            Swal.fire('Desactivado', 'El cliente ha sido desactivado.', 'success');
            this.loadClientes();
          },
          error: () => {
            Swal.fire('Error', 'No se pudo desactivar el cliente.', 'error');
          }
        });
      }
    });
  }

  private isValidEmail(email: string): boolean {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  private fireAlert(title: string, text: string, icon: 'success' | 'error' | 'warning' | 'info' | 'question') {
    Swal.fire({
      title,
      text,
      icon
    });
  }
}
