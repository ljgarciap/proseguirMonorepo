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
    pendiente_formalizacion_garantias: 0, pendiente_registro_cyf: 0,
    // SCRUM-211/215/219/224: el backend solo devuelve las claves visibles
    // para el rol activo (ver ROLES_POR_CLAVE) — estas 4 quedan en undefined
    // para coordinador_comercial, y el template las oculta con *ngIf.
    aprobacion_registro_cyf: undefined, desembolso_ingreso: undefined, desembolso_aprobacion: undefined,
    ejecucion_transferencia: undefined
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
      aprobacion_registro_cyf: 'Pendiente Aprobación Registro de Crédito en CYF',
      desembolso_ingreso: 'Pendiente Registro de Operación de Desembolso en CYF',
      desembolso_aprobacion: 'Pendiente Aprobación Registro de Operación de Desembolso en CYF',
      ejecucion_transferencia: 'Pendiente Registro de Transferencia Bancaria',
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
      'success': credito.resultado_origen === 'comite_aprobado' || credito.estado === 'desembolso_aprobacion',
      'warning': credito.resultado_origen === 'comite_pendiente' || credito.estado === 'aprobacion_registro_cyf',
      'purple': credito.estado === 'pendiente_formalizacion_garantias' || credito.estado === 'desembolso_ingreso',
      'info': credito.estado === 'pendiente_registro_cyf' || credito.estado === 'ejecucion_transferencia',
    };
  }

  /** SCRUM-211/215/219: cada uno de los 3 estados nuevos es del dominio
   * exclusivo de un rol (Gerente o Operativo) — mismo mapa que el backend
   * (ROLES_POR_CLAVE), replicado acá porque el rol que puede actuar cambia
   * según el estado de la fila, no es fijo para todo el módulo. */
  private rolPorEstado(estado: string): string | null {
    const mapa: Record<string, string> = {
      aprobacion_registro_cyf: 'gerente',
      desembolso_ingreso: 'operativo',
      desembolso_aprobacion: 'gerente',
      ejecucion_transferencia: 'tesoreria',
    };
    return mapa[estado] || null;
  }

  puedeGestionar(credito: any): boolean {
    if (credito.solicitud_gestionada) return false;
    if (this.activeRole === 'superadmin') return true;

    const rolRequerido = this.rolPorEstado(credito.estado);
    if (rolRequerido) return this.activeRole === rolRequerido;

    return this.activeRole === 'coordinador_comercial';
  }

  accionLabel(credito: any): string {
    return this.puedeGestionar(credito) ? 'Gestionar' : 'Ver';
  }

  accionIcono(credito: any): string {
    return this.puedeGestionar(credito) ? 'edit_note' : 'visibility';
  }

  /** SCRUM-193/205/211/215/219: cada estado nuevo tiene pantalla propia,
   * distinta de la genérica /gestion-creditos/:id (4 resultados
   * originales). */
  rutaGestion(credito: any): any[] {
    const rutasPorEstado: Record<string, string> = {
      pendiente_formalizacion_garantias: 'formalizacion-garantias',
      pendiente_registro_cyf: 'registro-cyf',
      aprobacion_registro_cyf: 'aprobacion-registro-cyf',
      desembolso_ingreso: 'desembolso-ingreso',
      desembolso_aprobacion: 'desembolso-aprobacion',
      ejecucion_transferencia: 'transferencia-bancaria',
    };
    const sufijo = rutasPorEstado[credito.estado];
    return sufijo ? ['/gestion-creditos', credito.id, sufijo] : ['/gestion-creditos', credito.id];
  }
}
