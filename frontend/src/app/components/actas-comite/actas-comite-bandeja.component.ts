import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

const ESTADO_LABELS: Record<string, string> = {
  pendiente: 'Pendiente',
  borrador: 'Borrador',
  aprobada: 'Aprobada',
};

@Component({
  selector: 'app-actas-comite-bandeja',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './actas-comite-bandeja.component.html',
  styleUrls: ['./actas-comite-bandeja.component.css']
})
export class ActasComiteBandejaComponent implements OnInit {
  actas: any[] = [];
  loading = false;
  generando = false;
  activeRole: string = '';

  filtroTexto = '';
  filtroEstado = '';

  constructor(private http: HttpClient, public authService: AuthService, private router: Router) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.loadActas();
  }

  loadActas(): void {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/actas-comite`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.actas = data;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar la bandeja de Actas Comité.', 'error');
      }
    });
  }

  generarActaPendiente(): void {
    this.generando = true;
    this.http.post<any>(`${environment.apiUrl}/actas-comite/generar`, {}, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (acta) => {
        this.generando = false;
        Swal.fire('Acta generada', 'El acta pendiente fue generada correctamente e incorporada al listado.', 'success');
        this.router.navigate(['/actas-comite', acta.id]);
      },
      error: (err) => {
        this.generando = false;
        Swal.fire('No se pudo generar el acta', err?.error?.message || 'Intente nuevamente o contacte al administrador.', 'warning');
      }
    });
  }

  estadoLabel(acta: any): string {
    return ESTADO_LABELS[acta.estado] || '—';
  }

  get actasFiltradas(): any[] {
    return this.actas.filter(acta => {
      if (this.filtroTexto) {
        const q = this.filtroTexto.toLowerCase();
        if (!String(acta.numero).includes(q)) return false;
      }
      if (this.filtroEstado && acta.estado !== this.filtroEstado) return false;
      return true;
    });
  }

  get hayActaSinRegistrar(): boolean {
    return this.actas.some(a => a.estado === 'pendiente' || a.estado === 'borrador');
  }

  accionLabel(acta: any): string {
    if (acta.estado === 'aprobada') return 'Ver y descargar';
    if (acta.estado === 'borrador') return 'Continuar';
    return 'Elaborar';
  }

  accionIcono(acta: any): string {
    if (acta.estado === 'aprobada') return 'visibility';
    if (acta.estado === 'borrador') return 'edit_note';
    return 'add_circle';
  }

  elaboradaPor(acta: any): string {
    return acta.elaborada_por?.name || '—';
  }
}
