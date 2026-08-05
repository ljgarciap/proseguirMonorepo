import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Detalle de Validación de Listas Restrictivas y SARLAFT (SCRUM-128).
 * Información del cliente y del crédito solicitado en modo solo lectura,
 * más el panel de resultado (concepto + observaciones + PDF único) que
 * diligencia el Oficial de Cumplimiento.
 */
@Component({
  selector: 'app-listas-sarlaft-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './listas-sarlaft-detalle.component.html',
  styleUrls: ['./listas-sarlaft-detalle.component.css']
})
export class ListasSarlaftDetalleComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  estadoDerivado = '';
  loading = false;
  activeRole = '';

  concepto: 'favorable' | 'desfavorable' | null = null;
  observaciones = '';
  archivoSeleccionado: File | null = null;
  archivoNombre = '';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    public authService: AuthService
  ) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.creditoId = Number(this.route.snapshot.paramMap.get('creditoId'));
    this.cargar();
  }

  cargar(): void {
    this.loading = true;
    this.http.get<any>(`${environment.apiUrl}/listas-sarlaft/${this.creditoId}`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data.credito;
        this.estadoDerivado = data.sarlaft_estado_derivado;
        this.concepto = this.credito.sarlaft_concepto || null;
        this.observaciones = this.credito.sarlaft_observaciones || '';
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo cargar la validación.', 'error')
          .then(() => this.router.navigate(['/listas-sarlaft']));
      }
    });
  }

  get cliente(): any {
    return this.credito?.solicitud_credito?.cliente || null;
  }

  get solicitud(): any {
    return this.credito?.solicitud_credito || null;
  }

  get esNatural(): boolean {
    return (this.cliente?.tipo_persona?.codigo || 'NATURAL').toUpperCase() === 'NATURAL';
  }

  get esJuridica(): boolean {
    return !this.esNatural;
  }

  get pdfActual(): string | null {
    return this.credito?.documentos?.sintesis_oficial_cumplimiento || null;
  }

  get puedeEditar(): boolean {
    if (!this.credito) return false;
    if (this.activeRole === 'superadmin') return true;
    return this.activeRole === 'oficial_cumplimiento' && this.credito.estado === 'sarlaft_control_interno';
  }

  onArchivoSeleccionado(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (file.type !== 'application/pdf') {
      Swal.fire('Formato inválido', 'El archivo debe estar en formato PDF.', 'warning');
      target.value = '';
      return;
    }

    this.archivoSeleccionado = file;
    this.archivoNombre = file.name;
  }

  private buildFormData(): FormData {
    const fd = new FormData();
    if (this.concepto) {
      fd.append('sarlaft_concepto', this.concepto);
    }
    fd.append('sarlaft_observaciones', this.observaciones || '');
    if (this.archivoSeleccionado) {
      fd.append('archivo', this.archivoSeleccionado);
    }
    return fd;
  }

  guardarBorrador(): void {
    // Laravel solo parsea multipart/form-data en peticiones POST reales —
    // se manda como POST con _method=PUT (spoofing estándar de Laravel)
    // para que el archivo adjunto llegue correctamente a la ruta PUT.
    const fd = this.buildFormData();
    fd.append('_method', 'PUT');

    this.http.post(`${environment.apiUrl}/listas-sarlaft/${this.creditoId}/borrador`, fd, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: () => {
        Swal.fire('Guardado', 'El borrador de la validación se guardó correctamente.', 'success');
        this.archivoSeleccionado = null;
        this.cargar();
      },
      error: (err) => {
        Swal.fire('Error', err.error?.message || 'No se pudo guardar el borrador.', 'error');
      }
    });
  }

  finalizar(): void {
    if (!this.concepto) {
      Swal.fire('Falta información', 'Seleccione el resultado de la validación.', 'warning');
      return;
    }

    const esFavorable = this.concepto === 'favorable';
    Swal.fire({
      title: esFavorable ? '¿Finalizar con concepto favorable?' : '¿Finalizar con concepto desfavorable?',
      text: esFavorable
        ? 'El crédito pasará a la etapa de Análisis Financiero.'
        : 'El crédito quedará rechazado y disponible en Gestión de Créditos para que el Coordinador Comercial notifique al cliente.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, finalizar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: esFavorable ? '#1d4ed8' : '#dc2626'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.http.post(`${environment.apiUrl}/listas-sarlaft/${this.creditoId}/finalizar`, this.buildFormData(), {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: () => {
          Swal.fire('¡Listo!', 'La validación se finalizó correctamente.', 'success')
            .then(() => this.router.navigate(['/listas-sarlaft']));
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'No se pudo finalizar la validación.', 'error');
        }
      });
    });
  }

  volver(): void {
    this.router.navigate(['/listas-sarlaft']);
  }
}
