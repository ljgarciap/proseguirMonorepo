import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Registro de Crédito en CYF (SCRUM-193): el Coordinador Comercial (o
 * Super Admin) captura la fecha del registro y el # de radicado, disponible
 * después de que Formalización de Garantías (SCRUM-205) aprobó todas las
 * garantías. Al guardar, el crédito pasa a 'aprobacion_registro_cyf' —
 * mismo estado legacy que usa Gerencia para su aprobación en la pantalla de
 * Crédito Ordinario, que no se toca (ver GestionCreditoController::registroCyf()).
 */
@Component({
  selector: 'app-gestion-creditos-registro-cyf',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-registro-cyf.component.html',
  styleUrls: ['./gestion-creditos-registro-cyf.component.css']
})
export class GestionCreditosRegistroCyfComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  loading = false;
  guardando = false;
  activeRole = '';

  fechaRegistroCyf = '';
  radicadoCyf = '';

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

  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.estado !== 'pendiente_registro_cyf') return false;
    return this.activeRole === 'coordinador_comercial' || this.activeRole === 'superadmin';
  }

  /** Última entrada del historial que dejó la solicitud en
   * 'pendiente_registro_cyf' — resultado de Formalización de Garantías
   * (SCRUM-205), mostrado acá como "Validación de Garantías". */
  get validacionGarantias(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'pendiente_registro_cyf');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  guardar(): void {
    if (!this.fechaRegistroCyf) {
      Swal.fire('Falta información', 'Ingrese la fecha del registro del crédito en CYF.', 'warning');
      return;
    }
    if (!this.radicadoCyf?.trim()) {
      Swal.fire('Falta información', 'Ingrese el número de radicado en CYF.', 'warning');
      return;
    }

    Swal.fire({
      title: '¿Registrar crédito en CYF?',
      text: 'La solicitud quedará disponible para la aprobación de Gerencia.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.guardando = true;
      const payload = {
        fecha_registro_cyf: this.fechaRegistroCyf,
        radicado_cyf: this.radicadoCyf.trim()
      };

      this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/registro-cyf`, payload, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: (resp) => {
          this.guardando = false;
          Swal.fire('¡Listo!', resp.message, 'success')
            .then(() => this.router.navigate(['/gestion-creditos']));
        },
        error: (err) => {
          this.guardando = false;
          Swal.fire('Error', err.error?.message || 'No se pudo registrar el crédito en CYF.', 'error');
        }
      });
    });
  }

  cancelar(): void {
    this.router.navigate(['/gestion-creditos']);
  }
}
