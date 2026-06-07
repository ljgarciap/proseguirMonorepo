import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-document-requests',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './document-requests.component.html',
  styleUrls: ['./document-requests.component.css']
})
export class DocumentRequestsComponent implements OnInit {
  // expose environment to template
  public environment = environment
  loading = false;
  submitting = false;

  // Requests history list
  requests: any[] = [];
  currentPage = 1;
  lastPage = 1;
  totalRequests = 0;
  statusFilter = 'todos';

  // Modal / Form state for sending new request
  showNewRequestForm = false;
  clients: any[] = [];
  presets: any[] = [];
  requirements: any[] = [];

  // Form selections
  selectedClientId = '';
  selectedPresetId = '';
  selectedRequirementIds: number[] = [];

  // Selected request details modal
  selectedRequest: any = null;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadRequests();
    this.loadClients();
    this.loadPresets();
    this.loadRequirements();
  }

  loadRequests() {
    this.loading = true;
    let url = `${environment.apiUrl}/document-requests?page=${this.currentPage}`;
    if (this.statusFilter !== 'todos') {
      url += `&estado=${this.statusFilter}`;
    }
    
    this.http.get<any>(url).subscribe({
      next: (res) => {
        this.loading = false;
        this.requests = res.data || [];
        this.currentPage = res.current_page || 1;
        this.lastPage = res.last_page || 1;
        this.totalRequests = res.total || 0;
      },
      error: () => { this.loading = false; }
    });
  }

  loadClients() {
    this.http.get<any[]>(`${environment.apiUrl}/document-requests/clients`).subscribe(res => {
      this.clients = res;
    });
  }

  loadPresets() {
    this.http.get<any[]>(`${environment.apiUrl}/document-presets`).subscribe(res => {
      this.presets = res;
    });
  }

  loadRequirements() {
    this.http.get<any[]>(`${environment.apiUrl}/document-requirements`).subscribe(res => {
      this.requirements = res.filter(r => r.activo);
    });
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.lastPage) {
      this.currentPage = page;
      this.loadRequests();
    }
  }

  onFilterChange() {
    this.currentPage = 1;
    this.loadRequests();
  }

  openNewRequest() {
    this.selectedClientId = '';
    this.selectedPresetId = '';
    this.selectedRequirementIds = [];
    this.showNewRequestForm = true;
  }

  onPresetChange() {
    if (this.selectedPresetId) {
      const preset = this.presets.find(p => p.id === +this.selectedPresetId);
      if (preset && preset.requirements) {
        this.selectedRequirementIds = preset.requirements.map((r: any) => r.id);
      }
    } else {
      this.selectedRequirementIds = [];
    }
  }

  toggleRequirement(reqId: number) {
    // If they manually toggle a checkbox, clear preset selection
    this.selectedPresetId = '';
    
    const idx = this.selectedRequirementIds.indexOf(reqId);
    if (idx > -1) {
      this.selectedRequirementIds.splice(idx, 1);
    } else {
      this.selectedRequirementIds.push(reqId);
    }
  }

  submitRequest() {
    if (!this.selectedClientId) {
      Swal.fire('Atención', 'Debe seleccionar un cliente.', 'warning');
      return;
    }
    if (this.selectedRequirementIds.length === 0) {
      Swal.fire('Atención', 'Debe seleccionar al menos un documento.', 'warning');
      return;
    }

    this.submitting = true;
    const payload = {
      cliente_id: +this.selectedClientId,
      requirements: this.selectedRequirementIds
    };

    this.http.post(`${environment.apiUrl}/document-requests`, payload).subscribe({
      next: () => {
        this.submitting = false;
        this.showNewRequestForm = false;
        Swal.fire('Solicitud Enviada', 'Se ha notificado e iniciado la solicitud de documentos para el cliente.', 'success');
        this.currentPage = 1;
        this.loadRequests();
      },
      error: (err) => {
        this.submitting = false;
        Swal.fire('Error', err.error.message || 'No se pudo crear la solicitud.', 'error');
      }
    });
  }

  deleteRequest(id: number) {
    Swal.fire({
      title: '¿Eliminar Solicitud?',
      text: 'Esta acción cancelará la solicitud de documentos y eliminará su trazabilidad.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/document-requests/${id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'Solicitud cancelada correctamente.', 'success');
            this.loadRequests();
          },
          error: () => {
            Swal.fire('Error', 'No se pudo cancelar la solicitud.', 'error');
          }
        });
      }
    });
  }

  viewRequestDetails(req: any) {
    this.selectedRequest = req;
  }

  closeDetails() {
    this.selectedRequest = null;
  }

  getApprovedCount(req: any): number {
    if (!req || !req.items) return 0;
    return req.items.filter((i: any) => i.estado === 'aprobado').length;
  }

  getProgressPercentage(req: any): number {
    if (!req || !req.items || req.items.length === 0) return 0;
    const approved = this.getApprovedCount(req);
    return Math.round((approved / req.items.length) * 100);
  }
}
