import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { ConciliacionSusuerteService } from '../../services/conciliacion-susuerte.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-conciliacion-susuerte',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './conciliacion-susuerte.component.html',
  styleUrl: './conciliacion-susuerte.component.scss'
})
export class ConciliacionSusuerteComponent {
  xlsxFile: File | null = null;
  pdfFile: File | null = null;
  isProcessing = false;
  results: any[] = [];
  downloadUrl: string | null = null;

  constructor(
    private conciliationService: ConciliacionSusuerteService,
    public router: Router
  ) {}

  openFileInput(id: string) {
    document.getElementById(id)?.click();
  }

  onFileSelected(event: any, type: 'xlsx' | 'pdf') {
    const file = event.target.files[0];
    if (file) {
      if (type === 'xlsx') this.xlsxFile = file;
      else this.pdfFile = file;
    }
  }

  getStatusClass(status: string): string {
    if (status === 'CONCILIADO') return 'matched';
    if (status === 'SOLO EN SUSUERTE') return 'only-susuerte';
    return 'only-bank';
  }

  newConciliation() {
    this.xlsxFile = null;
    this.pdfFile = null;
    this.results = [];
    this.downloadUrl = null;
    // Reset file inputs
    const xlsxInput = document.getElementById('xlsxInput') as HTMLInputElement;
    const pdfInput = document.getElementById('pdfInput') as HTMLInputElement;
    if (xlsxInput) xlsxInput.value = '';
    if (pdfInput) pdfInput.value = '';
  }

  onConciliate() {
    if (!this.xlsxFile || !this.pdfFile) return;

    this.isProcessing = true;
    this.results = [];
    this.downloadUrl = null;

    this.conciliationService.conciliate(this.xlsxFile, this.pdfFile).subscribe({
      next: (response) => {
        this.results = response.results;
        this.downloadUrl = response.download_url;
        this.isProcessing = false;
        Swal.fire({
          icon: 'success',
          title: 'Conciliación Completada',
          text: 'Se han procesado los archivos correctamente.',
          timer: 2000,
          showConfirmButton: false
        });
      },
      error: (error) => {
        this.isProcessing = false;
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Hubo un problema procesando los archivos: ' + (error.error?.error || 'Error desconocido')
        });
      }
    });
  }
}
