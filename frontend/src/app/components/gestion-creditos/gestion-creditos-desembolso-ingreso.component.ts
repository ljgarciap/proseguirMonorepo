import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

/**
 * Registro de Operación Desembolso en CYF (SCRUM-215): Operativo (o Super
 * Admin) adjunta los documentos obligatorios definidos por un preset
 * (mismo catálogo document-presets que Formalización de Garantías) para la
 * solicitud, disponible después de que Gerencia aprobó el Registro de
 * Crédito en CYF (SCRUM-211). Estado de entrada 'desembolso_ingreso' —
 * también reutilizado para editar tras un rechazo de Gerencia en
 * Aprobación de Operación de Desembolso (SCRUM-219).
 */
@Component({
  selector: 'app-gestion-creditos-desembolso-ingreso',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-desembolso-ingreso.component.html',
  styleUrls: ['./gestion-creditos-desembolso-ingreso.component.css']
})
export class GestionCreditosDesembolsoIngresoComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  presets: any[] = [];
  loading = false;
  guardando = false;
  activeRole = '';

  presetId: number | null = null;
  observaciones = '';
  /** Archivo elegido por requirement_id del preset seleccionado. */
  archivos: Record<number, File | null> = {};

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
    this.cargarPresets();
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

  cargarPresets(): void {
    this.http.get<any[]>(`${environment.apiUrl}/document-presets`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => { this.presets = data; },
      error: () => {}
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
    if (this.credito.estado !== 'desembolso_ingreso') return false;
    return this.activeRole === 'operativo' || this.activeRole === 'superadmin';
  }

  /** Última entrada del historial que dejó la solicitud pendiente de este
   * registro — indica si viene de la aprobación de CYF o de un rechazo de
   * Gerencia en la aprobación de desembolso (SCRUM-219), para mostrar el
   * comentario/observaciones correspondiente. */
  get ultimoIngreso(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'desembolso_ingreso');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  get presetSeleccionado(): any {
    return this.presets.find(p => p.id === this.presetId) || null;
  }

  onPresetChange(): void {
    this.archivos = {};
  }

  onArchivoSeleccionado(event: Event, requirementId: number): void {
    const input = event.target as HTMLInputElement;
    this.archivos[requirementId] = input.files && input.files.length ? input.files[0] : null;
  }

  guardar(): void {
    if (!this.presetId) {
      Swal.fire('Falta información', 'Seleccione el preset de documentos de la operación.', 'warning');
      return;
    }
    const preset = this.presetSeleccionado;
    const faltantes = (preset?.requirements || []).filter((r: any) => !this.archivos[r.id]);
    if (faltantes.length) {
      Swal.fire('Faltan documentos', 'Cargue todos los documentos obligatorios: ' + faltantes.map((r: any) => r.nombre).join(', ') + '.', 'warning');
      return;
    }

    Swal.fire({
      title: '¿Registrar operación de desembolso?',
      text: 'La solicitud quedará disponible para la aprobación de Gerencia.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.guardando = true;
      const formData = new FormData();
      formData.append('document_preset_id', String(this.presetId));
      if (this.observaciones?.trim()) {
        formData.append('observaciones', this.observaciones.trim());
      }
      (preset?.requirements || []).forEach((r: any) => {
        const archivo = this.archivos[r.id];
        if (archivo) formData.append(`documentos[${r.id}]`, archivo, archivo.name);
      });

      this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/desembolso-ingreso`, formData, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: (resp) => {
          this.guardando = false;
          Swal.fire('¡Listo!', resp.message, 'success')
            .then(() => this.router.navigate(['/gestion-creditos']));
        },
        error: (err) => {
          this.guardando = false;
          Swal.fire('Error', err.error?.message || 'No se pudo registrar la operación de desembolso.', 'error');
        }
      });
    });
  }

  cancelar(): void {
    this.router.navigate(['/gestion-creditos']);
  }
}
