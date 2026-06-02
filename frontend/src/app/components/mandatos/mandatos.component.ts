import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-mandatos',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './mandatos.component.html',
  styleUrls: ['./mandatos.component.scss']
})
export class MandatosComponent implements OnInit {
  mandato = {
    mandante_razon_social: '',
    mandante_tipo_documento: 'CC',
    mandante_numero_documento: '',
    mandante_domicilio: '',
    mandante_direccion: '',
    mandante_telefono: '',
    mandante_rep_legal_nombre: '',
    mandante_rep_legal_tipo_doc: 'CC',
    mandante_rep_legal_num_doc: '',
    mandante_rep_legal_email: '',
    factor_razon_social: '',
    factor_tipo_documento: '',
    factor_numero_documento: '',
    factor_rep_legal_nombre: '',
    factor_rep_legal_tipo_doc: 'CC',
    factor_rep_legal_num_doc: '',
    factor_rep_legal_email: ''
  };

  documentTypes = ['CC', 'CE', 'NIT', 'PAS', 'PEP'];
  loading = false;
  validationErrors: { [key: string]: string[] } = {};
  
  currentTab: 'diligenciar' | 'historial' = 'diligenciar';
  activeRequest: any = null;
  historialMandatos: any[] = [];
  Math = Math;
  
  // Búsqueda y Paginación
  searchText: string = '';
  currentPage: number = 1;
  itemsPerPage: number = 10;

  constructor(private http: HttpClient, public authService: AuthService) {}

  get filteredMandatos() {
    if (!this.searchText) return this.historialMandatos;
    const search = this.searchText.toLowerCase();
    return this.historialMandatos.filter(m => 
      m.mandante_razon_social?.toLowerCase().includes(search) ||
      m.mandante_numero_documento?.toLowerCase().includes(search) ||
      m.factor_razon_social?.toLowerCase().includes(search) ||
      m.status?.toLowerCase().includes(search)
    );
  }

  get paginatedMandatos() {
    const start = (this.currentPage - 1) * this.itemsPerPage;
    return this.filteredMandatos.slice(start, start + this.itemsPerPage);
  }

  get totalPages() {
    return Math.ceil(this.filteredMandatos.length / this.itemsPerPage);
  }

  nextPage() {
    if (this.currentPage < this.totalPages) this.currentPage++;
  }

  prevPage() {
    if (this.currentPage > 1) this.currentPage--;
  }

  ngOnInit(): void {
    if (!this.authService.isAuthorized(['cliente'])) {
      this.currentTab = 'historial';
    } else {
      this.loadActiveRequest();
    }
    this.loadHistorial();
  }

  loadActiveRequest(): void {
    this.http.get<any>(`${environment.apiUrl}/document-requests/active`).subscribe(res => {
      this.activeRequest = res;
    });
  }

  uploadDocument(event: any, itemId: number): void {
    const file = event.target.files[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
      Swal.fire('Formato no permitido', 'Únicamente se permiten archivos en formato PDF.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('active_role', 'cliente');
    formData.append('document_request_item_id', itemId.toString());

    Swal.fire({
      title: 'Subiendo archivo...',
      text: 'Por favor espere mientras se carga el documento.',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    this.http.post(`${environment.apiUrl}/uploads`, formData).subscribe({
      next: () => {
        Swal.fire('Cargado', 'El documento ha sido cargado para validación.', 'success');
        this.loadActiveRequest();
      },
      error: (err) => {
        Swal.fire('Error', err.error?.message || 'No se pudo subir el archivo.', 'error');
      }
    });
  }

  getApprovedCount(): number {
    if (!this.activeRequest || !this.activeRequest.items) return 0;
    return this.activeRequest.items.filter((i: any) => i.estado === 'aprobado').length;
  }

  getProgressPercentage(): number {
    if (!this.activeRequest || !this.activeRequest.items || this.activeRequest.items.length === 0) return 0;
    const approved = this.getApprovedCount();
    return Math.round((approved / this.activeRequest.items.length) * 100);
  }

  loadHistorial(): void {
    this.http.get<any[]>(`${environment.apiUrl}/mandatos`).subscribe(res => {
      this.historialMandatos = res;
    });
  }

  updateMandatoStatus(mandato: any, status: string): void {
    if (status === 'rechazado') {
      Swal.fire({
        title: 'Rechazar Mandato',
        input: 'textarea',
        inputLabel: 'Observaciones del rechazo',
        inputPlaceholder: 'Indique por qué se rechaza este mandato...',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Rechazo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        preConfirm: (observaciones) => {
          if (!observaciones) {
            Swal.showValidationMessage('Debe ingresar una observación');
          }
          return observaciones;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeStatusUpdate(mandato.id, status, result.value);
        }
      });
    } else {
      // Aprobar (Firmar)
      Swal.fire({
        title: '¿Aprobar Mandato?',
        text: 'El mandato se marcará como firmado/aprobado.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16a34a'
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeStatusUpdate(mandato.id, status, 'Aprobado satisfactoriamente.');
        }
      });
    }
  }

  private executeStatusUpdate(id: number, status: string, observaciones: string) {
    this.http.patch(`${environment.apiUrl}/mandatos/${id}/status`, { status, observaciones }).subscribe({
      next: () => {
        Swal.fire('Actualizado', `Mandato ${status === 'firmado' ? 'aprobado' : 'rechazado'}.`, 'success');
        this.loadHistorial();
      },
      error: (err) => {
        Swal.fire('Error', 'No se pudo actualizar el estado.', 'error');
      }
    });
  }

  onSubmit(): void {
    this.loading = true;
    this.validationErrors = {};
    this.http.post(`${environment.apiUrl}/mandatos`, this.mandato).subscribe({
      next: (res) => {
        Swal.fire({
          icon: 'success',
          title: 'Mandato Creado',
          text: 'El mandato se ha diligenciado correctamente.',
          confirmButtonColor: '#2563eb'
        });
        this.resetForm();
        this.loadHistorial();
        this.currentTab = 'historial';
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        if (err.status === 422 && err.error?.errors) {
          this.validationErrors = err.error.errors;
          
          const errorList = Object.keys(this.validationErrors)
            .map(key => {
              const friendlyName = this.getFieldFriendlyName(key);
              const friendlyError = this.getFieldError(key);
              return `<li><strong>${friendlyName}:</strong> ${friendlyError}</li>`;
            }).join('');

          Swal.fire({
            icon: 'error',
            title: 'Error de Validación',
            html: `
              <p>Por favor verifique los siguientes campos con errores:</p>
              <ul style="text-align: left; font-size: 0.9rem; margin-top: 10px; max-height: 200px; overflow-y: auto;">
                ${errorList}
              </ul>
            `,
            confirmButtonColor: '#ef4444'
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.error?.message || 'No se pudo guardar el mandato. Verifique los campos.',
            confirmButtonColor: '#ef4444'
          });
        }
      }
    });
  }

  getFieldFriendlyName(key: string): string {
    const names: { [key: string]: string } = {
      mandante_razon_social: 'Nombre / Razón Social Mandante',
      mandante_tipo_documento: 'Tipo Documento Mandante',
      mandante_numero_documento: 'Número Documento Mandante',
      mandante_domicilio: 'Domicilio Mandante',
      mandante_direccion: 'Dirección Mandante',
      mandante_telefono: 'Teléfono Mandante',
      mandante_rep_legal_nombre: 'Nombre Representante Legal',
      mandante_rep_legal_tipo_doc: 'Tipo Doc. Rep. Legal',
      mandante_rep_legal_num_doc: 'Número Doc. Rep. Legal',
      mandante_rep_legal_email: 'E-mail Representante Legal',
      factor_razon_social: 'Nombre / Razón Social Factor',
      factor_tipo_documento: 'Tipo Documento Factor',
      factor_numero_documento: 'Número Documento Factor',
      factor_rep_legal_nombre: 'Nombre Representante Factor',
      factor_rep_legal_tipo_doc: 'Tipo Doc. Rep. Factor',
      factor_rep_legal_num_doc: 'Número Doc. Rep. Factor',
      factor_rep_legal_email: 'E-mail Representante Factor'
    };
    return names[key] || key;
  }

  getFieldError(key: string): string | null {
    if (this.validationErrors && this.validationErrors[key] && this.validationErrors[key].length > 0) {
      const errorMsg = this.validationErrors[key][0].toLowerCase();
      if (errorMsg.includes('must be a valid email') || errorMsg.includes('correo electrónico') || errorMsg.includes('email')) {
        return 'Debe ser un correo electrónico válido (ej: usuario@dominio.com).';
      }
      if (errorMsg.includes('required') || errorMsg.includes('obligatorio') || errorMsg.includes('requerido')) {
        return 'Este campo es obligatorio.';
      }
      return this.validationErrors[key][0];
    }
    return null;
  }

  resetForm(): void {
    this.validationErrors = {};
    this.mandato = {
      mandante_razon_social: '',
      mandante_tipo_documento: 'CC',
      mandante_numero_documento: '',
      mandante_domicilio: '',
      mandante_direccion: '',
      mandante_telefono: '',
      mandante_rep_legal_nombre: '',
      mandante_rep_legal_tipo_doc: 'CC',
      mandante_rep_legal_num_doc: '',
      mandante_rep_legal_email: '',
      factor_razon_social: '',
      factor_tipo_documento: '',
      factor_numero_documento: '',
      factor_rep_legal_nombre: '',
      factor_rep_legal_tipo_doc: 'CC',
      factor_rep_legal_num_doc: '',
      factor_rep_legal_email: ''
    };
  }
}
