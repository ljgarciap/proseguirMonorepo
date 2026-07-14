import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Rol requerido por cada estado del flujo de Informe Técnico (SCRUM-120).
 * Debe reflejar App\Http\Controllers\InformeTecnicoController::ROL_POR_ESTADO.
 */
const ROL_POR_ESTADO: Record<string, string> = {
  informe_tecnico_ingeniero: 'ingeniero',
  informe_tecnico_coordinador: 'coordinador_comercial',
};

/**
 * Fase 1 (SCRUM-120): captura simple por sección, sin las fórmulas del
 * Excel de referencia (eso es Fase 2). Cada sección se guarda como JSON
 * en el backend (columnas `json` en `informes_tecnicos`).
 */
interface SeccionIngeniero {
  ventas_totales_proyecto: { valor_total: number | null; detalle: string };
  costos: { valor_total: number | null; detalle: string };
  invertido: { valor_total: number | null; detalle: string };
  observaciones_ingeniero: string;
}

interface SeccionCoordinador {
  credito_solicitado: { valor: number | null; detalle: string };
  saldos_por_recaudar_contraentrega: { valor: number | null; detalle: string };
  analisis_financiacion: { detalle: string };
  coberturas: { detalle: string };
  observaciones_coordinador: string;
}

@Component({
  selector: 'app-informe-tecnico-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './informe-tecnico-detalle.component.html',
  styleUrls: ['./informe-tecnico-detalle.component.css']
})
export class InformeTecnicoDetalleComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  informe: any = null;
  loading = false;
  activeRole: string = '';

  ingeniero: SeccionIngeniero = {
    ventas_totales_proyecto: { valor_total: null, detalle: '' },
    costos: { valor_total: null, detalle: '' },
    invertido: { valor_total: null, detalle: '' },
    observaciones_ingeniero: ''
  };

  coordinador: SeccionCoordinador = {
    credito_solicitado: { valor: null, detalle: '' },
    saldos_por_recaudar_contraentrega: { valor: null, detalle: '' },
    analisis_financiacion: { detalle: '' },
    coberturas: { detalle: '' },
    observaciones_coordinador: ''
  };

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
    this.http.get<any>(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data.credito;
        this.informe = data.informe;
        this.hidratarFormularios();
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo cargar el informe técnico.', 'error')
          .then(() => this.router.navigate(['/informes-tecnicos']));
      }
    });
  }

  private hidratarFormularios(): void {
    if (!this.informe) return;

    this.ingeniero = {
      ventas_totales_proyecto: this.informe.ventas_totales_proyecto || { valor_total: null, detalle: '' },
      costos: this.informe.costos || { valor_total: null, detalle: '' },
      invertido: this.informe.invertido || { valor_total: null, detalle: '' },
      observaciones_ingeniero: this.informe.observaciones_ingeniero || ''
    };

    this.coordinador = {
      credito_solicitado: this.informe.credito_solicitado || { valor: null, detalle: '' },
      saldos_por_recaudar_contraentrega: this.informe.saldos_por_recaudar_contraentrega || { valor: null, detalle: '' },
      analisis_financiacion: this.informe.analisis_financiacion || { detalle: '' },
      coberturas: this.informe.coberturas || { detalle: '' },
      observaciones_coordinador: this.informe.observaciones_coordinador || ''
    };
  }

  get esFinalizado(): boolean {
    return this.credito?.estado === 'informe_tecnico_finalizado';
  }

  get puedeEditar(): boolean {
    if (!this.credito || this.esFinalizado) return false;
    if (this.activeRole === 'superadmin') return true;
    return ROL_POR_ESTADO[this.credito.estado] === this.activeRole;
  }

  get seccionIngenieroVisible(): boolean {
    // Visible siempre que exista (diligenciada o en curso); editable solo
    // mientras el crédito está en informe_tecnico_ingeniero.
    return true;
  }

  get seccionIngenieroEditable(): boolean {
    return this.puedeEditar && this.credito?.estado === 'informe_tecnico_ingeniero';
  }

  get seccionCoordinadorVisible(): boolean {
    return this.credito?.estado === 'informe_tecnico_coordinador' || this.credito?.estado === 'informe_tecnico_finalizado';
  }

  get seccionCoordinadorEditable(): boolean {
    return this.puedeEditar && this.credito?.estado === 'informe_tecnico_coordinador';
  }

  private payloadSegunEstado(): any {
    if (this.credito.estado === 'informe_tecnico_ingeniero') {
      return { ...this.ingeniero };
    }
    if (this.credito.estado === 'informe_tecnico_coordinador') {
      return { ...this.coordinador };
    }
    return {};
  }

  guardarBorrador(): void {
    this.http.put(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/borrador`, this.payloadSegunEstado(), {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (informe) => {
        this.informe = informe;
        Swal.fire('Guardado', 'El borrador del informe técnico se guardó correctamente.', 'success');
      },
      error: (err) => {
        Swal.fire('Error', err.error?.message || 'No se pudo guardar el borrador.', 'error');
      }
    });
  }

  registrar(): void {
    const esIngeniero = this.credito.estado === 'informe_tecnico_ingeniero';
    Swal.fire({
      title: esIngeniero ? '¿Registrar informe del Ingeniero?' : '¿Registrar informe del Coordinador Comercial?',
      text: esIngeniero
        ? 'Tu sección quedará bloqueada para edición y pasará a Coordinador Comercial.'
        : 'El informe técnico quedará finalizado y no podrá editarse más.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.http.post(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/registrar`, this.payloadSegunEstado(), {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: () => {
          Swal.fire('¡Registrado!', 'El informe técnico se registró correctamente.', 'success')
            .then(() => this.router.navigate(['/informes-tecnicos']));
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'No se pudo registrar el informe técnico.', 'error');
        }
      });
    });
  }

  volver(): void {
    this.router.navigate(['/informes-tecnicos']);
  }
}
