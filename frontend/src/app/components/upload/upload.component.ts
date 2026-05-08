import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-upload',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './upload.component.html',
  styleUrls: ['./upload.component.scss']
})
export class UploadComponent {
  selectedFile: File | null = null;
  categoria: string = 'cartera'; // Valor por defecto
  cargando: boolean = false;
  mensaje: string = '';
  status: 'success' | 'error' | '' = '';

  constructor(private http: HttpClient) { }

  // Se ejecuta cuando seleccionas un archivo en el input
  onFileSelected(event: any): void {
    const file: File = event.target.files[0];
    if (file) {
      this.selectedFile = file;
    }
  }

  // Se ejecuta al hacer clic en el botón
  procesar(): void {
    if (!this.selectedFile) {
      this.mensaje = 'Por favor, selecciona un archivo primero.';
      this.status = 'error';
      return;
    }

    this.cargando = true;
    this.mensaje = 'Enviando archivo al servidor...';
    this.status = '';

    const formData = new FormData();
    // Laravel espera el campo 'file'
    formData.append('file', this.selectedFile);
    formData.append('categoria', this.categoria);

    // Llamamos a nuestra propia API en lugar de n8n directamente
    const apiUrl = `${environment.apiUrl}/uploads`;

    this.http.post(apiUrl, formData).subscribe({
      next: (response: any) => {
        console.log('Respuesta del servidor:', response);
        this.mensaje = 'Archivo recibido y enviado a procesamiento interno con éxito.';
        this.status = 'success';
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error completo:', err);
        this.mensaje = 'Error al subir el archivo al servidor: ' + (err.error?.message || err.message);
        this.status = 'error';
        this.cargando = false;
      }
    });
  }

  // Permite limpiar todo y volver a empezar
  reset(): void {
    this.selectedFile = null;
    this.mensaje = '';
    this.status = '';
    this.cargando = false;
    // Forzar el reset del input file en el DOM si es necesario
    const fileInput = document.querySelector('.file-input') as HTMLInputElement;
    if (fileInput) fileInput.value = '';
  }
}
