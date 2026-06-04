import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-document-config',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './document-config.component.html',
  styleUrls: ['./document-config.component.css']
})
export class DocumentConfigComponent implements OnInit {
  currentTab: 'requirements' | 'presets' = 'requirements';
  loading = false;

  // Requirements data
  requirements: any[] = [];
  requirementForm = { id: null as number | null, nombre: '', descripcion: '', activo: true };
  showRequirementForm = false;

  // Presets data
  presets: any[] = [];
  presetForm = { id: null as number | null, nombre: '', descripcion: '', requirements: [] as number[] };
  showPresetForm = false;

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadRequirements();
    this.loadPresets();
  }

  // --- Requirements Logic ---
  loadRequirements() {
    this.http.get<any[]>(`${environment.apiUrl}/document-requirements`).subscribe(res => {
      this.requirements = res;
    });
  }

  openNewRequirement() {
    this.requirementForm = { id: null, nombre: '', descripcion: '', activo: true };
    this.showRequirementForm = true;
  }

  editRequirement(req: any) {
    this.requirementForm = { ...req };
    this.showRequirementForm = true;
  }

  saveRequirement() {
    this.loading = true;
    if (this.requirementForm.id) {
      this.http.put(`${environment.apiUrl}/document-requirements/${this.requirementForm.id}`, this.requirementForm).subscribe({
        next: () => {
          this.loading = false;
          this.showRequirementForm = false;
          Swal.fire('Guardado', 'Requisito actualizado correctamente.', 'success');
          this.loadRequirements();
        },
        error: () => { this.loading = false; }
      });
    } else {
      this.http.post(`${environment.apiUrl}/document-requirements`, this.requirementForm).subscribe({
        next: () => {
          this.loading = false;
          this.showRequirementForm = false;
          Swal.fire('Creado', 'Requisito creado correctamente.', 'success');
          this.loadRequirements();
        },
        error: () => { this.loading = false; }
      });
    }
  }

  deleteRequirement(id: number) {
    Swal.fire({
      title: '¿Eliminar Requisito?',
      text: 'Esta acción borrará el requisito de forma permanente.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/document-requirements/${id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'Requisito de documento borrado.', 'success');
            this.loadRequirements();
            this.loadPresets(); // Update presets requirement list
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo eliminar.', 'error');
          }
        });
      }
    });
  }

  // --- Presets Logic ---
  loadPresets() {
    this.http.get<any[]>(`${environment.apiUrl}/document-presets`).subscribe(res => {
      this.presets = res;
    });
  }

  openNewPreset() {
    this.presetForm = { id: null, nombre: '', descripcion: '', requirements: [] };
    this.showPresetForm = true;
  }

  editPreset(preset: any) {
    this.presetForm = {
      id: preset.id,
      nombre: preset.nombre,
      descripcion: preset.descripcion,
      requirements: preset.requirements ? preset.requirements.map((r: any) => r.id) : []
    };
    this.showPresetForm = true;
  }

  toggleRequirementInPreset(reqId: number) {
    const idx = this.presetForm.requirements.indexOf(reqId);
    if (idx > -1) {
      this.presetForm.requirements.splice(idx, 1);
    } else {
      this.presetForm.requirements.push(reqId);
    }
  }

  savePreset() {
    if (this.presetForm.requirements.length === 0) {
      Swal.fire('Atención', 'Debe seleccionar al menos un requisito de documento.', 'warning');
      return;
    }
    
    this.loading = true;
    if (this.presetForm.id) {
      this.http.put(`${environment.apiUrl}/document-presets/${this.presetForm.id}`, this.presetForm).subscribe({
        next: () => {
          this.loading = false;
          this.showPresetForm = false;
          Swal.fire('Guardado', 'Plantilla actualizada correctamente.', 'success');
          this.loadPresets();
        },
        error: () => { this.loading = false; }
      });
    } else {
      this.http.post(`${environment.apiUrl}/document-presets`, this.presetForm).subscribe({
        next: () => {
          this.loading = false;
          this.showPresetForm = false;
          Swal.fire('Creada', 'Plantilla creada correctamente.', 'success');
          this.loadPresets();
        },
        error: () => { this.loading = false; }
      });
    }
  }

  deletePreset(id: number) {
    Swal.fire({
      title: '¿Eliminar Plantilla?',
      text: 'Esta acción borrará la plantilla permanentemente.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(result => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/document-presets/${id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'Plantilla eliminada.', 'success');
            this.loadPresets();
          },
          error: (err) => {
            Swal.fire('Error', 'No se pudo eliminar la plantilla.', 'error');
          }
        });
      }
    });
  }
}
