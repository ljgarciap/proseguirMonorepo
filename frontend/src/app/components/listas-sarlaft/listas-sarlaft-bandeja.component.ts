import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Bandeja de Validación de Listas Restrictivas y SARLAFT (SCRUM-128).
 * Reemplaza el paso combinado analisis_sarlaft_financiero de
 * CreditoOrdinario por una bandeja dedicada al Oficial de Cumplimiento,
 * a cargo del estado sarlaft_control_interno.
 */
@Component({
  selector: 'app-listas-sarlaft-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './listas-sarlaft-bandeja.component.html',
  styleUrls: ['./listas-sarlaft-bandeja.component.css']
})
export class ListasSarlaftBandejaComponent implements OnInit, OnDestroy {
  creditos: any[] = [];
  loading = false;
  activeRole = '';
  private roleSub?: Subscription;

  // Filtros (SCRUM-128, prototipo 02)
  filtroTexto = '';
  filtroTipoCredito = '';
  filtroTipoPersona = '';
  filtroEstado = '';

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.loadCreditos();

    this.roleSub = this.authService.activeRole$.subscribe(role => {
      if (role) {
        this.activeRole = role;
        this.loadCreditos();
      }
    });
  }

  ngOnDestroy(): void {
    this.roleSub?.unsubscribe();
  }

  loadCreditos(): void {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/listas-sarlaft`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar la bandeja de Listas Restrictivas y SARLAFT.', 'error');
      }
    });
  }

  get creditosFiltrados(): any[] {
    return this.creditos.filter(credito => {
      if (this.filtroTexto) {
        const q = this.filtroTexto.toLowerCase();
        const cliente = credito.solicitud_credito?.cliente;
        const haystack = [credito.numero_solicitud, cliente?.nombre, cliente?.identificacion]
          .filter(Boolean).join(' ').toLowerCase();
        if (!haystack.includes(q)) return false;
      }
      if (this.filtroTipoCredito && this.tipoCreditoCodigo(credito) !== this.filtroTipoCredito) return false;
      if (this.filtroTipoPersona && this.tipoPersonaCodigo(credito) !== this.filtroTipoPersona) return false;
      if (this.filtroEstado && credito.sarlaft_estado_derivado !== this.filtroEstado) return false;
      return true;
    });
  }

  get countPendientes(): number { return this.creditos.filter(c => c.sarlaft_estado_derivado === 'Pendiente').length; }
  get countEnRevision(): number { return this.creditos.filter(c => c.sarlaft_estado_derivado === 'En revisión').length; }
  get countFavorables(): number { return this.creditos.filter(c => c.sarlaft_estado_derivado === 'Favorable').length; }
  get countDesfavorables(): number { return this.creditos.filter(c => c.sarlaft_estado_derivado === 'Desfavorable').length; }

  private tipoCreditoCodigo(credito: any): string {
    return credito.solicitud_credito?.tipo_credito?.codigo || '';
  }

  private tipoPersonaCodigo(credito: any): string {
    return credito.solicitud_credito?.cliente?.tipo_persona?.codigo || '';
  }

  clienteNombre(credito: any): string {
    return credito.solicitud_credito?.cliente?.nombre || credito.cliente?.name || '—';
  }

  clienteIdentificacion(credito: any): string {
    return credito.solicitud_credito?.cliente?.identificacion || '—';
  }

  tipoPersonaLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.tipo_persona?.nombre || '—';
  }

  tipoCreditoLabel(credito: any): string {
    return credito.solicitud_credito?.tipo_credito?.nombre || '—';
  }

  tipoDocumentoLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.document_type?.codigo || '—';
  }

  tipoEmpresaLabel(credito: any): string {
    return credito.solicitud_credito?.cliente?.tipo_empresa || '—';
  }

  estadoPillClass(credito: any): any {
    const e = credito.sarlaft_estado_derivado;
    return {
      'warning': e === 'Pendiente' || e === 'En revisión',
      'success': e === 'Favorable',
      'danger': e === 'Desfavorable',
    };
  }

  puedeValidar(credito: any): boolean {
    if (credito.estado !== 'sarlaft_control_interno') return false;
    return this.activeRole === 'oficial_cumplimiento' || this.activeRole === 'superadmin';
  }

  accionLabel(credito: any): string {
    if (!this.puedeValidar(credito)) return 'Ver';
    return credito.sarlaft_estado_derivado === 'En revisión' ? 'Continuar' : 'Validar';
  }

  accionIcono(credito: any): string {
    if (!this.puedeValidar(credito)) return 'visibility';
    return credito.sarlaft_estado_derivado === 'En revisión' ? 'edit_note' : 'fact_check';
  }
}
