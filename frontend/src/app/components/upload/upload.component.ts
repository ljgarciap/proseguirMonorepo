import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

interface UploadEntry {
  file: File;
  status: 'pendiente' | 'subiendo' | 'exitoso' | 'error';
  mensaje: string;
}

@Component({
  selector: 'app-upload',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './upload.component.html',
  styleUrls: ['./upload.component.scss']
})
export class UploadComponent {
  entries: UploadEntry[] = [];
  categoria: string = 'cartera'; // Valor por defecto
  cargando: boolean = false;
  mensaje: string = '';
  status: 'success' | 'error' | '' = '';

  constructor(private http: HttpClient) { }

  // Se ejecuta cuando seleccionas uno o varios archivos en el input
  onFileSelected(event: any): void {
    const files: FileList = event.target.files;
    if (files && files.length) {
      this.entries = Array.from(files).map(file => ({
        file,
        status: 'pendiente' as const,
        mensaje: ''
      }));
      this.mensaje = '';
      this.status = '';
    }
  }

  removeFile(index: number): void {
    this.entries.splice(index, 1);
  }

  // Sube todos los archivos seleccionados, uno por uno, a la misma categoría
  procesar(): void {
    if (!this.entries.length) {
      this.mensaje = 'Por favor, selecciona al menos un archivo primero.';
      this.status = 'error';
      return;
    }

    this.cargando = true;
    this.mensaje = '';
    this.status = '';

    const activeRole = localStorage.getItem('active_role') || 'operativo';
    const apiUrl = `${environment.apiUrl}/uploads`;

    const subidas = this.entries.map(entry => {
      entry.status = 'subiendo';
      entry.mensaje = 'Enviando al servidor...';

      const formData = new FormData();
      // Laravel espera el campo 'file'
      formData.append('file', entry.file);
      formData.append('categoria', this.categoria);
      formData.append('active_role', activeRole);

      return this.http.post(apiUrl, formData).toPromise()
        .then(() => {
          entry.status = 'exitoso';
          entry.mensaje = 'Enviado a procesamiento interno con éxito.';
        })
        .catch(err => {
          entry.status = 'error';
          entry.mensaje = 'Error al subir: ' + (err.error?.message || err.message);
        });
    });

    Promise.all(subidas).then(() => {
      this.cargando = false;
      const exitosos = this.entries.filter(e => e.status === 'exitoso').length;
      const fallidos = this.entries.filter(e => e.status === 'error').length;

      if (fallidos === 0) {
        this.status = 'success';
        this.mensaje = `${exitosos} archivo(s) recibido(s) y enviado(s) a procesamiento interno con éxito.`;
      } else if (exitosos === 0) {
        this.status = 'error';
        this.mensaje = `Los ${fallidos} archivo(s) fallaron al subir. Revisa el detalle de cada uno abajo.`;
      } else {
        this.status = 'error';
        this.mensaje = `${exitosos} archivo(s) exitoso(s), ${fallidos} archivo(s) fallaron. Revisa el detalle de cada uno abajo.`;
      }
    });
  }

  // Permite limpiar todo y volver a empezar
  reset(): void {
    this.entries = [];
    this.mensaje = '';
    this.status = '';
    this.cargando = false;
    // Forzar el reset del input file en el DOM si es necesario
    const fileInput = document.querySelector('.file-input') as HTMLInputElement;
    if (fileInput) fileInput.value = '';
  }
}
