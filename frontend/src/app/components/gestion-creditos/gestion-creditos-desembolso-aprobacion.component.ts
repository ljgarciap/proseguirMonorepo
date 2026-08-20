import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Aprobación de Registro de Operación Desembolso en CYF (SCRUM-219):
 * Gerente (o Super Admin) revisa el registro de Operativo (SCRUM-215) —
 * info de contexto, trazabilidad completa y documentos adjuntos — y decide
 * Aprobar/Rechazar. Estado de entrada 'desembolso_aprobacion'. Aprobar pasa
 * al estado legacy 'ejecucion_transferencia' (pantalla ya existente de
 * Crédito Ordinario para Tesorería); rechazar vuelve a 'desembolso_ingreso'
 * para que Operativo ajuste (SCRUM-215).
 */
@Component({
  selector: 'app-gestion-creditos-desembolso-aprobacion',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-desembolso-aprobacion.component.html',
  styleUrls: ['./gestion-creditos-desembolso-aprobacion.component.css']
})
export class GestionCreditosDesembolsoAprobacionComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  loading = false;
  guardando = false;
  activeRole = '';
  mostrarTrazabilidad = false;

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

  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.estado !== 'desembolso_aprobacion') return false;
    return this.activeRole === 'gerente' || this.activeRole === 'superadmin';
  }

  /** Aprobación previa de Gerencia sobre el Registro de Crédito en CYF
   * (SCRUM-211) — filtra específicamente la transición
   * aprobacion_registro_cyf -> desembolso_ingreso, porque un rechazo de
   * ESTA pantalla (SCRUM-219) también deja una entrada con
   * estado_nuevo = 'desembolso_ingreso' y no debe confundirse con ella. */
  get aprobacionPrevia(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_anterior === 'aprobacion_registro_cyf' && h.estado_nuevo === 'desembolso_ingreso');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  /** Última entrada del historial que dejó la solicitud en
   * 'desembolso_aprobacion' — registro de Operativo (SCRUM-215). */
  get registroDesembolso(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'desembolso_aprobacion');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  get documentosDesembolso(): any[] {
    return this.credito?.documentos_desembolso?.documentos || [];
  }

  get trazabilidad(): any[] {
    return this.credito?.historial_estados || [];
  }

  verArchivo(url: string): void {
    window.open(url, '_blank');
  }

  guardar(): void {
    if (!this.decision) {
      Swal.fire('Falta información', 'Seleccione una decisión.', 'warning');
      return;
    }

    const esAprobar = this.decision === 'aprobar';
    Swal.fire({
      title: esAprobar ? '¿Aprobar la operación de desembolso?' : '¿Rechazar la operación de desembolso?',
      text: esAprobar
        ? 'La solicitud se enviará a Tesorería para ejecutar la transferencia.'
        : 'La solicitud vuelve a Registro de Operación de Desembolso para que Operativo ajuste.',
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

      this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/desembolso-aprobacion`, payload, {
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
