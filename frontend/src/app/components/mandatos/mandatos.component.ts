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
  
  currentTab: 'diligenciar' | 'historial' = 'diligenciar';
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
    }
    this.loadHistorial();
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
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudo guardar el mandato. Verifique los campos.',
          confirmButtonColor: '#ef4444'
        });
        this.loading = false;
      }
    });
  }

  resetForm(): void {
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
