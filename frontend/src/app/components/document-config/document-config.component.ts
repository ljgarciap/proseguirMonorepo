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
  requirementForm = { id: null as number | null, nombre: '', descripcion: '', activo: true, tiene_plantilla: false, plantilla_nombre: '' };
  showRequirementForm = false;
  selectedTemplateFile: File | null = null;

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
    this.requirementForm = { id: null, nombre: '', descripcion: '', activo: true, tiene_plantilla: false, plantilla_nombre: '' };
    this.selectedTemplateFile = null;
    this.showRequirementForm = true;
  }

  editRequirement(req: any) {
    this.requirementForm = { ...req, tiene_plantilla: !!req.tiene_plantilla };
    this.selectedTemplateFile = null;
    this.showRequirementForm = true;
  }

  onTemplateSelected(event: any) {
    this.selectedTemplateFile = event.target.files[0] || null;
  }

  saveRequirement() {
    this.loading = true;
    const formData = new FormData();
    formData.append('nombre', this.requirementForm.nombre);
    formData.append('descripcion', this.requirementForm.descripcion || '');
    formData.append('activo', this.requirementForm.activo ? '1' : '0');
    formData.append('tiene_plantilla', this.requirementForm.tiene_plantilla ? '1' : '0');
    
    if (this.selectedTemplateFile) {
      formData.append('plantilla', this.selectedTemplateFile);
    }

    if (this.requirementForm.id) {
      this.http.post(`${environment.apiUrl}/document-requirements/${this.requirementForm.id}`, formData).subscribe({
        next: () => {
          this.loading = false;
          this.showRequirementForm = false;
          this.selectedTemplateFile = null;
          Swal.fire('Guardado', 'Requisito actualizado correctamente.', 'success');
          this.loadRequirements();
        },
        error: () => { this.loading = false; }
      });
    } else {
      this.http.post(`${environment.apiUrl}/document-requirements`, formData).subscribe({
        next: () => {
          this.loading = false;
          this.showRequirementForm = false;
          this.selectedTemplateFile = null;
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

  downloadTemplate(reqId: number, originalName: string) {
    this.http.get(`${environment.apiUrl}/document-requirements/${reqId}/download-template`, {
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = originalName || 'plantilla.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el archivo de formato o plantilla.', 'error');
      }
    });
  }
}
