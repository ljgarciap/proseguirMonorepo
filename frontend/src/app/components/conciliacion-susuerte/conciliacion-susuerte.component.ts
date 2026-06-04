import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ConciliacionSusuerteService } from '../../services/conciliacion-susuerte.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-conciliacion-susuerte',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './conciliacion-susuerte.component.html',
  styleUrl: './conciliacion-susuerte.component.scss'
})
export class ConciliacionSusuerteComponent {
  xlsxFile: File | null = null;
  pdfFile: File | null = null;
  isProcessing = false;
  results: any[] = [];
  downloadUrl: string | null = null;
  conciliationId: number | null = null;

  // Search filter
  searchTerm: string = '';
  isSavingObservations = false;

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
    this.conciliationId = null;
    this.searchTerm = '';
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
    this.conciliationId = null;

    this.conciliationService.conciliate(this.xlsxFile, this.pdfFile).subscribe({
      next: (response) => {
        // Map results ensuring they all have an Observations field
        this.results = (response.results || []).map((r: any) => ({
          ...r,
          Observations: r.Observations || ''
        }));
        this.conciliationId = response.id;
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

  get filteredResults() {
    if (!this.searchTerm) {
      return this.results;
    }
    const term = this.searchTerm.toLowerCase();
    return this.results.filter(item => {
      const descSusuerte = (item['Description (Susuerte)'] || '').toLowerCase();
      const descBank = (item['Description (Bank)'] || '').toLowerCase();
      const dateSusuerte = (item['Date (Susuerte)'] || '').toLowerCase();
      const dateBank = (item['Date (Bank)'] || '').toLowerCase();
      const amount = (item.Amount || '').toString();

      return descSusuerte.includes(term) ||
             descBank.includes(term) ||
             dateSusuerte.includes(term) ||
             dateBank.includes(term) ||
             amount.includes(term);
    });
  }

  saveObservations() {
    if (!this.conciliationId) return;

    this.isSavingObservations = true;
    this.conciliationService.updateConciliation(this.conciliationId, this.results).subscribe({
      next: (response) => {
        this.downloadUrl = response.download_url;
        this.isSavingObservations = false;
        Swal.fire({
          icon: 'success',
          title: 'Guardado',
          text: 'Observaciones guardadas con éxito.',
          timer: 1500,
          showConfirmButton: false
        });
      },
      error: (error) => {
        this.isSavingObservations = false;
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'No se pudieron guardar las observaciones: ' + (error.error?.message || 'Error desconocido')
        });
      }
    });
  }
}
