import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Formalización de Garantías (SCRUM-205): el Coordinador Comercial (o
 * Super Admin) valida cada garantía del preset diligenciado por el cliente
 * (aprobada/no aprobada + observaciones), y el sistema decide el destino
 * de la solicitud (vuelve al cliente para ajustes, o pasa a Registro de
 * Crédito en CYF) — ver GestionCreditoController::guardarFormalizacionGarantias().
 * No reusa gestion-creditos-detalle.component (atado al patrón de correo
 * libre de los 4 resultados originales): acá el correo es automático y la
 * validación es por ítem, no un formulario único.
 */
@Component({
  selector: 'app-gestion-creditos-formalizacion-garantias',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-formalizacion-garantias.component.html',
  styleUrls: ['./gestion-creditos-formalizacion-garantias.component.css']
})
export class GestionCreditosFormalizacionGarantiasComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  documentRequest: any = null;
  loading = false;
  guardando = false;
  activeRole = '';

  /** Estado local por ítem, indexado por item.id — {validacion, observaciones}. */
  validaciones: Record<number, { validacion: 'aprobada' | 'no_aprobada' | null; observaciones: string }> = {};

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
    this.http.get<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/formalizacion-garantias`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data.credito;
        this.documentRequest = data.document_request;
        this.validaciones = {};
        (this.documentRequest?.items || []).forEach((item: any) => {
          this.validaciones[item.id] = { validacion: null, observaciones: '' };
        });
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo cargar la solicitud.', 'error')
          .then(() => this.router.navigate(['/gestion-creditos']));
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

  /** Solo lectura si ya no está pendiente de Formalización (gestionada por
   * otra sesión, o rol sin permiso de escritura en esta pantalla). */
  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.estado !== 'pendiente_formalizacion_garantias') return false;
    return this.activeRole === 'coordinador_comercial' || this.activeRole === 'superadmin';
  }

  get items(): any[] {
    return this.documentRequest?.items || [];
  }

  verArchivo(uploadId: number, originalName: string): void {
    this.http.get(`${environment.apiUrl}/uploads/${uploadId}/download`, {
      headers: { 'X-Active-Role': this.activeRole },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        if (blob.type === 'application/pdf' || blob.type.startsWith('image/')) {
          window.open(url, '_blank');
        } else {
          const a = document.createElement('a');
          a.href = url;
          a.download = originalName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }
      },
      error: () => Swal.fire('Error', 'No se pudo abrir el documento.', 'error')
    });
  }

  guardarValidacion(): void {
    const items = this.items;
    if (!items.length) {
      Swal.fire('Sin garantías', 'No hay garantías para validar.', 'warning');
      return;
    }

    for (const item of items) {
      const v = this.validaciones[item.id];
      if (!v?.validacion) {
        Swal.fire('Falta información', 'Toda garantía debe tener un resultado de validación.', 'warning');
        return;
      }
      if (v.validacion === 'no_aprobada' && !v.observaciones?.trim()) {
        Swal.fire('Falta información', 'Las garantías no aprobadas requieren observaciones.', 'warning');
        return;
      }
    }

    Swal.fire({
      title: '¿Guardar validación?',
      text: 'Se registrará el resultado y se notificará al cliente por correo.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.guardando = true;
      const payload = {
        items: items.map(item => ({
          item_id: item.id,
          validacion: this.validaciones[item.id].validacion,
          observaciones: this.validaciones[item.id].observaciones?.trim() || null
        }))
      };

      this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/formalizacion-garantias`, payload, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: (resp) => {
          this.guardando = false;
          Swal.fire('¡Listo!', resp.message, 'success')
            .then(() => this.router.navigate(['/gestion-creditos']));
        },
        error: (err) => {
          this.guardando = false;
          Swal.fire('Error', err.error?.message || 'No se pudo guardar la validación.', 'error');
        }
      });
    });
  }

  cancelar(): void {
    this.router.navigate(['/gestion-creditos']);
  }
}
