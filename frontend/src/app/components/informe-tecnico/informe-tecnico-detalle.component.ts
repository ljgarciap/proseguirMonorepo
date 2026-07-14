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
 * SCRUM-120 Fase 2: shape itemizado real (fórmulas del Excel de referencia
 * CR-RO-09A v5). Estos son solo los INPUTS crudos que el frontend manda —
 * los totales/porcentajes SIEMPRE se calculan y devuelven desde el backend
 * (InformeTecnicoCalculoService), nunca se recalculan aquí, para que no
 * puedan divergir del valor persistido.
 */
interface VentasInput {
  casas: number | null;
  apartamentos: number | null;
  parqueaderos: number | null;
  conexion_gas_arras: number | null;
  local_comercial: number | null;
  cuartos_utiles: number | null;
  otros: number | null;
}

interface CostosInput {
  lote: number | null;
  directos: number | null;
  directos_urbanismo: number | null;
  indirectos: number | null;
  honorarios: number | null;
  incremento_costos: number | null;
  financieros: number | null;
}

interface InvertidoInput {
  lote: number | null;
  costos_directos: number | null;
  costos_indirectos: number | null;
  recursos_propios: number | null;
  cuotas_iniciales_ya_pagadas: number | null;
}

interface CreditoSolicitadoInput {
  credito_solicitado: number | null;
  aptos_vendidos: number | null;
  porcentaje_cuotas_iniciales_pendientes: number | null;
}

interface SaldosPorRecaudarInput {
  porcentaje_cuotas_iniciales: number | null;
  cuotas_iniciales_pendientes: number | null;
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

  // Inputs editables — hidratados desde `informe` al cargar/guardar.
  ventasInput: VentasInput = {
    casas: null, apartamentos: null, parqueaderos: null, conexion_gas_arras: null,
    local_comercial: null, cuartos_utiles: null, otros: null
  };
  costosInput: CostosInput = {
    lote: null, directos: null, directos_urbanismo: null, indirectos: null,
    honorarios: null, incremento_costos: null, financieros: null
  };
  invertidoInput: InvertidoInput = {
    lote: null, costos_directos: null, costos_indirectos: null,
    recursos_propios: null, cuotas_iniciales_ya_pagadas: null
  };
  observacionesIngeniero = '';

  creditoSolicitadoInput: CreditoSolicitadoInput = {
    credito_solicitado: null, aptos_vendidos: null, porcentaje_cuotas_iniciales_pendientes: 0.30
  };
  saldosPorRecaudarInput: SaldosPorRecaudarInput = {
    porcentaje_cuotas_iniciales: 0.10, cuotas_iniciales_pendientes: null
  };
  observacionesCoordinador = '';

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

    const v = this.informe.ventas_totales_proyecto || {};
    this.ventasInput = {
      casas: v.casas ?? null, apartamentos: v.apartamentos ?? null, parqueaderos: v.parqueaderos ?? null,
      conexion_gas_arras: v.conexion_gas_arras ?? null, local_comercial: v.local_comercial ?? null,
      cuartos_utiles: v.cuartos_utiles ?? null, otros: v.otros ?? null
    };

    const c = this.informe.costos || {};
    this.costosInput = {
      lote: c.lote ?? null, directos: c.directos ?? null, directos_urbanismo: c.directos_urbanismo ?? null,
      indirectos: c.indirectos ?? null, honorarios: c.honorarios ?? null,
      incremento_costos: c.incremento_costos ?? null, financieros: c.financieros ?? null
    };

    const i = this.informe.invertido || {};
    this.invertidoInput = {
      lote: i.lote ?? null, costos_directos: i.costos_directos ?? null, costos_indirectos: i.costos_indirectos ?? null,
      recursos_propios: i.recursos_propios ?? null, cuotas_iniciales_ya_pagadas: i.cuotas_iniciales_ya_pagadas ?? null
    };

    this.observacionesIngeniero = this.informe.observaciones_ingeniero || '';

    const cs = this.informe.credito_solicitado || {};
    this.creditoSolicitadoInput = {
      credito_solicitado: cs.credito_solicitado ?? null,
      aptos_vendidos: cs.aptos_vendidos ?? null,
      porcentaje_cuotas_iniciales_pendientes: cs.porcentaje_cuotas_iniciales_pendientes ?? 0.30
    };

    const s = this.informe.saldos_por_recaudar_contraentrega || {};
    this.saldosPorRecaudarInput = {
      porcentaje_cuotas_iniciales: s.porcentaje_cuotas_iniciales ?? 0.10,
      cuotas_iniciales_pendientes: s.cuotas_iniciales_pendientes ?? null
    };

    this.observacionesCoordinador = this.informe.observaciones_coordinador || '';
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

  // Habilitado apenas exista algún dato guardado, aunque sea borrador
  // (así lo pide el prototipo — no solo cuando el informe está finalizado).
  get puedeDescargar(): boolean {
    if (!this.informe) return false;
    const v = this.informe.ventas_totales_proyecto;
    return !!(v && (v.total_ventas || 0) > 0) || !!this.informe.observaciones_ingeniero;
  }

  private payloadSegunEstado(): any {
    if (this.credito.estado === 'informe_tecnico_ingeniero') {
      return {
        ventas_totales_proyecto: this.ventasInput,
        costos: this.costosInput,
        invertido: this.invertidoInput,
        observaciones_ingeniero: this.observacionesIngeniero
      };
    }
    if (this.credito.estado === 'informe_tecnico_coordinador') {
      return {
        credito_solicitado: this.creditoSolicitadoInput,
        saldos_por_recaudar_contraentrega: this.saldosPorRecaudarInput,
        observaciones_coordinador: this.observacionesCoordinador
      };
    }
    return {};
  }

  guardarBorrador(): void {
    this.http.put(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/borrador`, this.payloadSegunEstado(), {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (informe) => {
        this.informe = informe;
        this.hidratarFormularios();
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

  descargar(formato: 'pdf' | 'excel'): void {
    this.http.get(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/descargar`, {
      headers: { 'X-Active-Role': this.activeRole },
      params: { formato },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `informe-tecnico-${this.creditoId}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el informe técnico.', 'error');
      }
    });
  }

  volver(): void {
    this.router.navigate(['/informes-tecnicos']);
  }
}
