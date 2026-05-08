import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-mandatos',
  standalone: true,
  imports: [CommonModule, FormsModule, HttpClientModule],
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
    factor_razon_social: 'FACTORES Y SERVICIOS S.A.S.',
    factor_tipo_documento: 'NIT',
    factor_numero_documento: '901.234.567-8',
    factor_rep_legal_nombre: '',
    factor_rep_legal_tipo_doc: 'CC',
    factor_rep_legal_num_doc: '',
    factor_rep_legal_email: ''
  };

  documentTypes = ['CC', 'CE', 'NIT', 'PAS', 'PEP'];
  loading = false;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {}

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
      factor_razon_social: 'FACTORES Y SERVICIOS S.A.S.',
      factor_tipo_documento: 'NIT',
      factor_numero_documento: '901.234.567-8',
      factor_rep_legal_nombre: '',
      factor_rep_legal_tipo_doc: 'CC',
      factor_rep_legal_num_doc: '',
      factor_rep_legal_email: ''
    };
  }
}
