import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

const ESTADO_LABELS: Record<string, string> = {
  pendiente: 'Pendiente',
  borrador: 'En Borrador',
  confirmado: 'Confirmado',
};

@Component({
  selector: 'app-analisis-financiero-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './analisis-financiero-bandeja.component.html',
  styleUrls: ['./analisis-financiero-bandeja.component.css']
})
export class AnalisisFinancieroBandejaComponent implements OnInit {
  creditos: any[] = [];
  loading = false;
  activeRole: string = '';

  filtroTexto = '';
  filtroEstado = '';
  filtroTipoCredito = '';

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.loadCreditos();
  }

  loadCreditos(): void {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/analisis-financiero`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar la bandeja de Análisis Financiero.', 'error');
      }
    });
  }

  // Estado derivado del análisis (no el estado crudo de CreditoOrdinario,
  // que puede haber avanzado más allá una vez confirmado — mismo criterio
  // aplicado en Informe Técnico, SCRUM-154).
  estadoAnalisis(credito: any): string {
    const analisis = credito.analisis_financiero;
    if (!analisis) return 'pendiente';
    return analisis.estado === 'confirmado' ? 'confirmado' : 'borrador';
  }

  estadoLabel(credito: any): string {
    return ESTADO_LABELS[this.estadoAnalisis(credito)] || '—';
  }

  get creditosFiltrados(): any[] {
    return this.creditos.filter(credito => {
      if (this.filtroTexto) {
        const q = this.filtroTexto.toLowerCase();
        const texto = `${credito.numero_solicitud || ''} ${this.cliente(credito)} ${this.nit(credito)}`.toLowerCase();
        if (!texto.includes(q)) return false;
      }
      if (this.filtroEstado && this.estadoAnalisis(credito) !== this.filtroEstado) {
        return false;
      }
      if (this.filtroTipoCredito && credito.tipo_credito !== this.filtroTipoCredito) {
        return false;
      }
      return true;
    });
  }

  get totalPendientes(): number {
    return this.creditos.filter(c => this.estadoAnalisis(c) === 'pendiente').length;
  }

  get totalBorrador(): number {
    return this.creditos.filter(c => this.estadoAnalisis(c) === 'borrador').length;
  }

  cliente(credito: any): string {
    return credito.solicitud_credito?.cliente?.nombre || '—';
  }

  nit(credito: any): string {
    return credito.solicitud_credito?.cliente?.numero_documento || '—';
  }

  tiposCredito(): string[] {
    const tipos = new Set(this.creditos.map(c => c.tipo_credito).filter(Boolean));
    return Array.from(tipos);
  }

  puedeEditar(credito: any): boolean {
    return this.estadoAnalisis(credito) !== 'confirmado';
  }

  accionLabel(credito: any): string {
    const estado = this.estadoAnalisis(credito);
    if (estado === 'confirmado') return 'Ver';
    if (estado === 'borrador') return 'Continuar';
    return 'Analizar';
  }

  accionIcono(credito: any): string {
    const estado = this.estadoAnalisis(credito);
    if (estado === 'confirmado') return 'visibility';
    if (estado === 'borrador') return 'edit_note';
    return 'add_circle';
  }
}
