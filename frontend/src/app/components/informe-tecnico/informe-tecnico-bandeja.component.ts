import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
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
  imports: [CommonModule, RouterModule],
  templateUrl: './informe-tecnico-bandeja.component.html',
  styleUrls: ['./informe-tecnico-bandeja.component.css']
})
export class InformeTecnicoBandejaComponent implements OnInit, OnDestroy {
  creditos: any[] = [];
  loading = false;
  activeRole: string = '';
  private roleSub?: Subscription;

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

  estadoLabel(estado: string): string {
    return ESTADO_LABELS[estado] || estado;
  }

  rolActual(estado: string): string {
    if (estado === 'informe_tecnico_finalizado') return '—';
    const rol = ROL_POR_ESTADO[estado];
    return rol === 'ingeniero' ? 'Ingeniero' : rol === 'coordinador_comercial' ? 'Coordinador Comercial' : '—';
  }

  // Nota conocida (SCRUM-120 Fase 1): SolicitudCredito no tiene un campo
  // "proyecto" explícito en el modelo de datos actual, se usa el nombre
  // del cliente como aproximación hasta que se defina ese campo.
  proyecto(credito: any): string {
    return credito.cliente?.name || '—';
  }

  solicitante(credito: any): string {
    return credito.cliente?.name || '—';
  }

  puedeEditar(credito: any): boolean {
    if (credito.estado === 'informe_tecnico_finalizado') return false;
    if (this.activeRole === 'superadmin') return true;
    return ROL_POR_ESTADO[credito.estado] === this.activeRole;
  }

  accionLabel(credito: any): string {
    if (credito.estado === 'informe_tecnico_finalizado') return 'Ver';
    return this.puedeEditar(credito) ? 'Diligenciar' : 'Ver';
  }
}
