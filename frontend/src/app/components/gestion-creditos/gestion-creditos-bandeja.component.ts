import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Bandeja de Gestión de Créditos (SCRUM-178). Consulta consolidada de
 * solicitudes con SARLAFT desfavorable o decisión del Comité de Crédito
 * (Actas de Comité, SCRUM-169/183), pendientes o ya gestionadas por el
 * Coordinador Comercial.
 */
@Component({
  selector: 'app-gestion-creditos-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './gestion-creditos-bandeja.component.html',
  styleUrls: ['./gestion-creditos-bandeja.component.css']
})
export class GestionCreditosBandejaComponent implements OnInit, OnDestroy {
  creditos: any[] = [];
  tarjetas: any = {
    sarlaft_desfavorable: 0, aprobada_garantias: 0, rechazada_comite: 0, pendiente_comite: 0,
    pendiente_formalizacion_garantias: 0, pendiente_registro_cyf: 0
  };
  loading = false;
  activeRole = '';
  private roleSub?: Subscription;

  // Filtros (§3.2 del ticket)
  filtroBusqueda = '';
  filtroTipoCredito = 'todos';
  filtroTipoPersona = 'todos';
  filtroEstado = 'todos';
  filtroGestionada = 'todos';

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.buscar();
    this.cargarTarjetas();

    this.roleSub = this.authService.activeRole$.subscribe(role => {
      if (role) {
        this.activeRole = role;
        this.buscar();
        this.cargarTarjetas();
      }
    });
  }

  ngOnDestroy(): void {
    this.roleSub?.unsubscribe();
  }

  cargarTarjetas(): void {
    this.http.get<any>(`${environment.apiUrl}/gestion-creditos/tarjetas`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => { this.tarjetas = data; },
      error: () => {}
    });
  }

  buscar(): void {
    this.loading = true;

    let params = new HttpParams();
    if (this.filtroBusqueda) params = params.set('busqueda', this.filtroBusqueda);
    if (this.filtroTipoCredito !== 'todos') params = params.set('tipo_credito', this.filtroTipoCredito);
    if (this.filtroTipoPersona !== 'todos') params = params.set('tipo_persona', this.filtroTipoPersona);
    if (this.filtroEstado !== 'todos') params = params.set('estado', this.filtroEstado);
    if (this.filtroGestionada !== 'todos') params = params.set('gestionada', this.filtroGestionada);

    this.http.get<any[]>(`${environment.apiUrl}/gestion-creditos`, {
      headers: { 'X-Active-Role': this.activeRole },
      params
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar la bandeja de Gestión de Créditos.', 'error');
      }
    });
  }

  limpiarFiltros(): void {
    this.filtroBusqueda = '';
    this.filtroTipoCredito = 'todos';
    this.filtroTipoPersona = 'todos';
    this.filtroEstado = 'todos';
    this.filtroGestionada = 'todos';
    this.buscar();
  }

  filtrarPorTarjeta(estado: string): void {
    this.filtroEstado = estado;
    this.filtroGestionada = 'no';
    this.buscar();
  }

  // ---- Presentación por fila --------------------------------------------

  clienteNombre(credito: any): string {
    return credito.solicitud_credito?.cliente?.nombre || credito.cliente?.name || '—';
  }

  clienteIdentificacion(credito: any): string {
    return credito.solicitud_credito?.cliente?.identificacion || credito.solicitud_credito?.cliente?.numero_documento || '—';
  }

  tipoPersonaLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.tipo_persona?.nombre || '—';
  }

  tipoCreditoLabel(credito: any): string {
    return credito.solicitud_credito?.tipo_credito?.nombre || '—';
  }

  tipoDocumentoLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.document_type?.nombre || '—';
  }

  tipoEmpresaLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.tipo_persona?.codigo === 'JURIDICA'
      ? (credito.solicitud_credito?.cliente?.tipo_empresa || '—')
      : 'No aplica';
  }

  /** Etiqueta de Estado (§3.3, col. 9): combina estado + resultado_origen.
   * SCRUM-193/205: 'pendiente_formalizacion_garantias'/'pendiente_registro_cyf'
   * no tienen resultado_origen propio (ver ESTADOS_SIMPLES en el backend) —
   * se resuelven directo por `estado`. */
  estadoLabel(credito: any): string {
    const mapaEstado: Record<string, string> = {
      pendiente_formalizacion_garantias: 'Pendiente Formalización de Garantías',
      pendiente_registro_cyf: 'Pendiente Registro de Crédito en CYF',
    };
    if (mapaEstado[credito.estado]) return mapaEstado[credito.estado];

    const mapaOrigen: Record<string, string> = {
      sarlaft: 'SARLAFT desfavorable',
      comite_aprobado: 'Aprobada para garantías',
      comite_rechazado: 'Rechazada por Comité',
      comite_pendiente: 'Pendiente por Comité',
    };
    return mapaOrigen[credito.resultado_origen] || credito.estado;
  }

  estadoPillClass(credito: any): any {
    return {
      'danger': credito.resultado_origen === 'sarlaft' || credito.resultado_origen === 'comite_rechazado',
      'success': credito.resultado_origen === 'comite_aprobado',
      'warning': credito.resultado_origen === 'comite_pendiente',
      'purple': credito.estado === 'pendiente_formalizacion_garantias',
      'info': credito.estado === 'pendiente_registro_cyf',
    };
  }

  puedeGestionar(credito: any): boolean {
    if (credito.solicitud_gestionada) return false;
    return this.activeRole === 'coordinador_comercial' || this.activeRole === 'superadmin';
  }

  accionLabel(credito: any): string {
    return this.puedeGestionar(credito) ? 'Gestionar' : 'Ver';
  }

  accionIcono(credito: any): string {
    return this.puedeGestionar(credito) ? 'edit_note' : 'visibility';
  }

  /** SCRUM-193/205: los 2 estados nuevos tienen pantalla propia, distinta
   * de la genérica /gestion-creditos/:id (4 resultados originales). */
  rutaGestion(credito: any): any[] {
    if (credito.estado === 'pendiente_formalizacion_garantias') {
      return ['/gestion-creditos', credito.id, 'formalizacion-garantias'];
    }
    if (credito.estado === 'pendiente_registro_cyf') {
      return ['/gestion-creditos', credito.id, 'registro-cyf'];
    }
    return ['/gestion-creditos', credito.id];
  }
}
