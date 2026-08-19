import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Aprobación Registro de Crédito en CYF (SCRUM-211): Gerente (o Super
 * Admin) revisa el registro capturado por Operativo/Coordinador Comercial
 * (registro-cyf.component, SCRUM-193) y decide Aprobar/Rechazar. Estado de
 * entrada 'aprobacion_registro_cyf'. Aprobar pasa a
 * 'desembolso_ingreso' (SCRUM-215); rechazar exige observaciones y vuelve a
 * 'pendiente_registro_cyf' para que se registre de nuevo.
 */
@Component({
  selector: 'app-gestion-creditos-aprobacion-registro-cyf',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-aprobacion-registro-cyf.component.html',
  styleUrls: ['./gestion-creditos-aprobacion-registro-cyf.component.css']
})
export class GestionCreditosAprobacionRegistroCyfComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  loading = false;
  guardando = false;
  activeRole = '';

  decision: 'aprobar' | 'rechazar' | '' = '';
  observaciones = '';

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
    this.http.get<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data;
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

  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.estado !== 'aprobacion_registro_cyf') return false;
    return this.activeRole === 'gerente' || this.activeRole === 'superadmin';
  }

  /** Última entrada del historial que registró el crédito en CYF (viene de
   * registro-cyf.component, SCRUM-193) — quién y cuándo. */
  get registroCyf(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'aprobacion_registro_cyf');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  guardar(): void {
    if (!this.decision) {
      Swal.fire('Falta información', 'Seleccione una decisión.', 'warning');
      return;
    }
    if (this.decision === 'rechazar' && !this.observaciones?.trim()) {
      Swal.fire('Falta información', 'Ingrese las observaciones del rechazo.', 'warning');
      return;
    }

    const esAprobar = this.decision === 'aprobar';
    Swal.fire({
      title: esAprobar ? '¿Aprobar el registro de CYF?' : '¿Rechazar el registro de CYF?',
      text: esAprobar
        ? 'La solicitud quedará disponible para el Registro de Operación de Desembolso.'
        : 'La solicitud vuelve a Registro de Crédito en CYF para corrección.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: esAprobar ? '#1d4ed8' : '#dc2626'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.guardando = true;
      const payload = {
        decision: this.decision,
        observaciones: this.observaciones?.trim() || null
      };

      this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/aprobacion-registro-cyf`, payload, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: (resp) => {
          this.guardando = false;
          Swal.fire('¡Listo!', resp.message, 'success')
            .then(() => this.router.navigate(['/gestion-creditos']));
        },
        error: (err) => {
          this.guardando = false;
          Swal.fire('Error', err.error?.message || 'No se pudo registrar la decisión.', 'error');
        }
      });
    });
  }

  cancelar(): void {
    this.router.navigate(['/gestion-creditos']);
  }
}
