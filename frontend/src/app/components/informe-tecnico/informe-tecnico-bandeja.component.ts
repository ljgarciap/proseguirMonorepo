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
 * Rol requerido por cada estado del flujo de Informe Técnico (SCRUM-120).
 * Debe reflejar App\Http\Controllers\InformeTecnicoController::ROL_POR_ESTADO.
 */
const ROL_POR_ESTADO: Record<string, string> = {
  informe_tecnico_ingeniero: 'ingeniero',
  informe_tecnico_coordinador: 'coordinador_comercial',
};

const ESTADO_LABELS: Record<string, string> = {
  informe_tecnico_ingeniero: 'Diligenciando Ingeniero',
  informe_tecnico_coordinador: 'Diligenciando Coordinador Comercial',
  informe_tecnico_finalizado: 'Finalizado',
};

@Component({
  selector: 'app-informe-tecnico-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './informe-tecnico-bandeja.component.html',
  styleUrls: ['./informe-tecnico-bandeja.component.css']
})
export class InformeTecnicoBandejaComponent implements OnInit, OnDestroy {
  creditos: any[] = [];
  loading = false;
  activeRole: string = '';
  private roleSub?: Subscription;

  // Filtros de búsqueda (SCRUM-120 Fase 2)
  filtroNumero = '';
  filtroUbicacion = '';
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
    this.http.get<any[]>(`${environment.apiUrl}/informes-tecnicos`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar la bandeja de Informe Técnico.', 'error');
      }
    });
  }

  get creditosFiltrados(): any[] {
    return this.creditos.filter(credito => {
      if (this.filtroNumero && !String(credito.numero_solicitud || '').toLowerCase().includes(this.filtroNumero.toLowerCase())) {
        return false;
      }
      if (this.filtroUbicacion) {
        const q = this.filtroUbicacion.toLowerCase();
        const ubicacion = `${credito.ciudad || ''} ${credito.direccion || ''}`.toLowerCase();
        if (!ubicacion.includes(q)) return false;
      }
      if (this.filtroEstado && this.estadoInformeTecnico(credito) !== this.filtroEstado) {
        return false;
      }
      return true;
    });
  }

  // SCRUM-154: una vez registrado el informe, CreditoOrdinario.estado
  // encadena a 'sarlaft_control_interno' (SCRUM-128) y deja de reflejar el
  // estado del informe técnico — informe_tecnico.estado === 'registrado' es
  // la señal real de que quedó "Finalizado".
  estadoInformeTecnico(credito: any): string {
    if (credito.informe_tecnico?.estado === 'registrado') return 'informe_tecnico_finalizado';
    return credito.estado;
  }

  estadoLabel(estado: string): string {
    return ESTADO_LABELS[estado] || estado;
  }

  rolActual(estado: string): string {
    if (estado === 'informe_tecnico_finalizado') return '—';
    const rol = ROL_POR_ESTADO[estado];
    return rol === 'ingeniero' ? 'Ingeniero' : rol === 'coordinador_comercial' ? 'Coordinador Comercial' : '—';
  }

  proyecto(credito: any): string {
    return credito.proyecto || '—';
  }

  solicitante(credito: any): string {
    return credito.cliente?.name || '—';
  }

  puedeEditar(credito: any): boolean {
    if (this.estadoInformeTecnico(credito) === 'informe_tecnico_finalizado') return false;
    if (this.activeRole === 'superadmin') return true;
    return ROL_POR_ESTADO[credito.estado] === this.activeRole;
  }

  // La sección del informe correspondiente al rol activo ya tiene algún
  // dato guardado (borrador) — distingue Iniciar de Continuar.
  private tieneBorrador(credito: any): boolean {
    const informe = credito.informe_tecnico;
    if (!informe) return false;

    if (this.activeRole === 'ingeniero' || credito.estado === 'informe_tecnico_ingeniero') {
      return !!(informe.observaciones_ingeniero || (informe.ventas_totales_proyecto?.total_ventas || 0) > 0);
    }

    if (this.activeRole === 'coordinador_comercial' || credito.estado === 'informe_tecnico_coordinador') {
      return !!(informe.observaciones_coordinador || (informe.credito_solicitado?.credito_solicitado || 0) > 0);
    }

    return false;
  }

  accionLabel(credito: any): string {
    if (!this.puedeEditar(credito)) return 'Abrir';
    return this.tieneBorrador(credito) ? 'Continuar' : 'Iniciar';
  }

  accionIcono(credito: any): string {
    if (!this.puedeEditar(credito)) return 'visibility';
    return this.tieneBorrador(credito) ? 'edit_note' : 'add_circle';
  }

  // Habilitado apenas exista algo registrado (borrador o finalizado), no
  // solo cuando está finalizado — así lo pide el prototipo.
  puedeDescargar(credito: any): boolean {
    const informe = credito.informe_tecnico;
    if (!informe) return false;
    return !!(informe.observaciones_ingeniero || (informe.ventas_totales_proyecto?.total_ventas || 0) > 0);
  }

  descargar(credito: any, formato: 'pdf' | 'excel'): void {
    this.http.get(`${environment.apiUrl}/informes-tecnicos/${credito.id}/descargar`, {
      headers: { 'X-Active-Role': this.activeRole },
      params: { formato },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `informe-tecnico-${credito.id}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el informe técnico.', 'error');
      }
    });
  }

  // No existe todavía un endpoint de detalle reusable de SolicitudCredito
  // (GET /api/solicitudes-credito/{id}) — se muestra lo ya disponible en
  // el objeto credito.solicitud_credito con un modal simple, suficiente
  // para esta fase (ver docs/designs/scrum-120-fase2-...).
  verSolicitud(credito: any): void {
    const s = credito.solicitud_credito;
    if (!s) {
      Swal.fire('Sin datos', 'Este crédito no tiene una Solicitud de Crédito asociada.', 'info');
      return;
    }

    const monto = (s.monto_solicitado || 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

    Swal.fire({
      title: `Solicitud de Crédito — ${credito.numero_solicitud}`,
      html: `
        <div style="text-align:left; font-size: 0.9rem; line-height: 1.6;">
          <p><strong>Proyecto:</strong> ${s.proyecto || '—'}</p>
          <p><strong>Monto Solicitado:</strong> ${monto}</p>
          <p><strong>Plazo:</strong> ${s.plazo_meses || '—'} meses</p>
          <p><strong>Destino del Recurso:</strong> ${s.destino_recurso || '—'}</p>
          <p><strong>Garantía:</strong> ${s.garantia || '—'}</p>
          <p><strong>Fuente de Pago:</strong> ${s.fuente_pago || '—'}</p>
        </div>
      `,
      confirmButtonText: 'Cerrar',
      confirmButtonColor: '#1d4ed8'
    });
  }
}
