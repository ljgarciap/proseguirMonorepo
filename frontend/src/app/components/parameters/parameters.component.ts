import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-parameters',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './parameters.component.html',
  styleUrls: ['./parameters.component.scss']
})
export class ParametersComponent implements OnInit {
  tables = [
    { id: 'sectores', label: 'Sectores', fields: [{ name: 'nombre', label: 'Nombre', type: 'text' }] },
    { id: 'document_types', label: 'Tipos Documento', fields: [
      { name: 'codigo', label: 'Código', type: 'text' },
      { name: 'nombre', label: 'Nombre', type: 'text' }
    ]},
    { id: 'accounting_categories', label: 'Categorías Contables', fields: [{ name: 'nombre', label: 'Nombre', type: 'text' }] },
    { id: 'accounting_priorities', label: 'Prioridades', fields: [
      { name: 'nombre', label: 'Nombre', type: 'text' },
      { name: 'color', label: 'Color', type: 'color' },
      { name: 'horas_vencimiento', label: 'Horas Venc.', type: 'number' }
    ]}
  ];

  activeTable = this.tables[0];
  records: any[] = [];
  loading = false;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadRecords();
  }

  selectTable(table: any) {
    this.activeTable = table;
    this.loadRecords();
  }

  loadRecords() {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/parameters/${this.activeTable.id}`).subscribe({
      next: (data) => {
        this.records = data;
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  async openModal(record: any = null) {
    const isEdit = !!record;
    
    // Build HTML fields for Swal
    const htmlFields = this.activeTable.fields.map(f => `
      <div class="pro-input-group" style="margin-bottom: 1rem; text-align: left;">
        <label style="display:block; margin-bottom:5px; font-weight:600;">${f.label}</label>
        <input id="swal-${f.name}" type="${f.type}" class="pro-input" style="width:100%" value="${record ? record[f.name] : ''}">
      </div>
    `).join('');

    const { value: formValues } = await Swal.fire({
      title: `${isEdit ? 'Editar' : 'Nuevo'} ${this.activeTable.label.slice(0, -1)}`,
      html: `<div class="swal-form">${htmlFields}</div>`,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: isEdit ? 'Actualizar' : 'Crear',
      preConfirm: () => {
        const results: any = {};
        this.activeTable.fields.forEach(f => {
          results[f.name] = (document.getElementById(`swal-${f.name}`) as HTMLInputElement).value;
        });
        return results;
      }
    });

    if (formValues) {
      const request = isEdit 
        ? this.http.put(`${environment.apiUrl}/parameters/${this.activeTable.id}/${record.id}`, formValues)
        : this.http.post(`${environment.apiUrl}/parameters/${this.activeTable.id}`, formValues);

      request.subscribe(() => {
        this.loadRecords();
        Swal.fire('¡Éxito!', 'Registro guardado correctamente.', 'success');
      });
    }
  }

  deleteRecord(record: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: 'No podrás revertir esta acción.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      confirmButtonColor: '#ef4444'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/parameters/${this.activeTable.id}/${record.id}`).subscribe(() => {
          this.loadRecords();
          Swal.fire('Eliminado', 'El registro ha sido borrado.', 'success');
        });
      }
    });
  }
}
