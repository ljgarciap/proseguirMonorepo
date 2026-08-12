import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

type Resultado = 'sarlaft_desfavorable' | 'aprobada_garantias' | 'rechazada_comite' | 'pendiente_comite';

/**
 * Pantalla de gestión de Gestión de Créditos (SCRUM-178, §5). Un único
 * componente parametrizado por el resultado de la solicitud (Garantías /
 * SARLAFT desfavorable / Rechazada por Comité / Pendiente por Comité) — los
 * 4 comparten el mismo layout de "info readonly + formulario de
 * notificación"; solo cambian los campos obligatorios del formulario.
 */
@Component({
  selector: 'app-gestion-creditos-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './gestion-creditos-detalle.component.html',
  styleUrls: ['./gestion-creditos-detalle.component.css']
})
export class GestionCreditosDetalleComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  loading = false;
  activeRole = '';
  presets: any[] = [];

  destino = '';
  asunto = '';
  mensaje = '';
  presetId: number | null = null;
  requiereDocumentos: boolean | null = null;

  // SCRUM-191 (2026-08-12, punto 1): documentos que el cliente reenvió tras
  // la notificación de "Pendiente por Comité con documentos requeridos".
  documentRequest: any = null;
  cargandoDocumentos = false;

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
        this.destino = this.correoCliente();
        this.loading = false;
        if (this.mostrarPanelDocumentos) {
          this.cargarDocumentosPendientes();
        }
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

  get esNatural(): boolean {
    return (this.cliente?.tipo_persona?.codigo || 'NATURAL').toUpperCase() === 'NATURAL';
  }

  get esJuridica(): boolean {
    return !this.esNatural;
  }

  private correoCliente(): string {
    return this.cliente?.correo_electronico || this.cliente?.correo_electronico_empresarial
      || this.credito?.cliente?.email || '';
  }

  /** Resultado de la solicitud (§5): deriva el título, los campos
   * obligatorios y la vista de "Información del Comité/validación". */
  get resultado(): Resultado | null {
    if (!this.credito) return null;
    const origen = this.credito.resultado_origen;
    if (origen === 'sarlaft') return 'sarlaft_desfavorable';
    if (origen === 'comite_aprobado') return 'aprobada_garantias';
    if (origen === 'comite_rechazado') return 'rechazada_comite';
    if (origen === 'comite_pendiente') return 'pendiente_comite';
    return null;
  }

  get titulo(): string {
    return this.resultado === 'aprobada_garantias' ? 'Gestión de Garantías' : 'Gestión de la Solicitud';
  }

  /** Decisión del Comité correspondiente a este resultado (§5.1/5.3/5.4):
   * la última acta registrada que decidió sobre este crédito con el mismo
   * sentido que el estado actual. */
  get decisionComite(): any {
    if (!this.credito || this.resultado === 'sarlaft_desfavorable') return null;
    const decisionEsperada: Record<string, string> = {
      aprobada_garantias: 'aprobado',
      rechazada_comite: 'rechazado',
      pendiente_comite: 'pendiente',
    };
    const esperada = decisionEsperada[this.resultado || ''];
    const solicitudes: any[] = this.credito.acta_comite_solicitudes || [];
    const candidatas = solicitudes.filter(s => s.estado_decision === esperada && s.acta_comite?.estado === 'aprobada');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  get sintesisPdf(): string | null {
    return this.credito?.documentos?.sintesis_oficial_cumplimiento || null;
  }

  /** Modo solo lectura (§3.5 "Ver"): ya gestionada, o rol sin permiso. */
  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.solicitud_gestionada) return false;
    return this.activeRole === 'coordinador_comercial' || this.activeRole === 'superadmin';
  }

  get requierePresetObligatorio(): boolean {
    return this.resultado === 'aprobada_garantias' || (this.resultado === 'pendiente_comite' && this.requiereDocumentos === true);
  }

  /** SCRUM-191 (2026-08-12, punto 1): el último "Registrar y enviar
   * notificación" de este crédito pidió documentación — de ahí sale si
   * corresponde mostrar el panel de revisión de lo que el cliente reenvíe. */
  private get ultimaGestionRequirioDocumentos(): boolean {
    const detalle: any[] = this.credito?.gestion_detalle || [];
    if (!detalle.length) return false;
    return detalle[detalle.length - 1]?.requiere_documentos === true;
  }

  get mostrarPanelDocumentos(): boolean {
    return this.resultado === 'pendiente_comite'
      && !!this.credito?.solicitud_gestionada
      && this.ultimaGestionRequirioDocumentos;
  }

  /** El crédito ya volvió a comite_evaluacion (todo lo reenviado quedó
   * aprobado) — deja de estar "gestionable" acá, vuelve a la cola del Acta. */
  get creditoDisponibleParaComite(): boolean {
    return this.credito?.estado === 'comite_evaluacion';
  }

  cargarDocumentosPendientes(): void {
    this.cargandoDocumentos = true;
    this.http.get<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/documentos`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => { this.documentRequest = data; this.cargandoDocumentos = false; },
      error: () => { this.cargandoDocumentos = false; }
    });
  }

  /** Mismo patrón que ClientUploadComponent.viewFile(). */
  verArchivo(uploadId: number, originalName: string): void {
    this.http.get(`${environment.apiUrl}/uploads/${uploadId}/download`, {
      headers: { 'X-Active-Role': this.activeRole },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        if (blob.type === 'application/pdf' || blob.type.startsWith('image/')) {
          window.open(url, '_blank');
        } else {
          const a = document.createElement('a');
          a.href = url;
          a.download = originalName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
        }
      },
      error: () => Swal.fire('Error', 'No se pudo abrir el documento.', 'error')
    });
  }

  revisarItem(item: any, accion: 'aprobar' | 'rechazar'): void {
    const tituloConfirm = accion === 'aprobar' ? '¿Aprobar este documento?' : '¿Rechazar este documento?';
    Swal.fire({
      title: tituloConfirm,
      input: accion === 'rechazar' ? 'textarea' : undefined,
      inputLabel: accion === 'rechazar' ? 'Motivo del rechazo (el cliente lo verá)' : undefined,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: accion === 'aprobar' ? 'Sí, aprobar' : 'Sí, rechazar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: accion === 'aprobar' ? '#1d4ed8' : '#dc2626',
    }).then(result => {
      if (!result.isConfirmed) return;

      // result.value es el texto del textarea cuando accion='rechazar', pero
      // para 'aprobar' (sin input) SweetAlert2 devuelve el booleano `true`
      // — enviarlo tal cual rompe la validación "observaciones: string" del
      // backend. Solo viaja como observaciones si es texto de verdad.
      const observaciones = typeof result.value === 'string' ? result.value : null;

      this.http.post<any>(
        `${environment.apiUrl}/gestion-creditos/${this.creditoId}/documentos/${item.id}/revisar`,
        { accion, observaciones },
        { headers: { 'X-Active-Role': this.activeRole } }
      ).subscribe({
        next: (resp) => {
          this.documentRequest = resp.document_request;
          if (resp.credito_disponible_comite) {
            this.credito.estado = 'comite_evaluacion';
            Swal.fire('¡Listo!', 'Toda la documentación fue aprobada — el crédito volvió a estar disponible para el Comité de Crédito.', 'success');
          }
        },
        error: (err) => Swal.fire('Error', err.error?.message || 'No se pudo procesar el documento.', 'error')
      });
    });
  }

  registrarYEnviar(): void {
    if (!this.destino) {
      Swal.fire('Falta información', 'Ingrese una dirección de correo electrónico válida.', 'warning');
      return;
    }
    if (!this.asunto) {
      Swal.fire('Falta información', 'Ingrese el asunto del correo.', 'warning');
      return;
    }
    if (!this.mensaje) {
      Swal.fire('Falta información', 'Ingrese el mensaje de acompañamiento.', 'warning');
      return;
    }
    if (this.resultado === 'pendiente_comite' && this.requiereDocumentos === null) {
      Swal.fire('Falta información', 'Indique si el cliente debe enviar documentación.', 'warning');
      return;
    }
    if (this.requierePresetObligatorio && !this.presetId) {
      Swal.fire('Falta información', 'Seleccione la documentación requerida.', 'warning');
      return;
    }

    Swal.fire({
      title: '¿Registrar y enviar notificación?',
      text: 'Se enviará el correo al destinatario indicado y quedará registrada la gestión.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, enviar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      const payload: any = {
        destino: this.destino,
        asunto: this.asunto,
        mensaje: this.mensaje,
      };
      if (this.presetId) payload.preset_id = this.presetId;
      if (this.resultado === 'pendiente_comite') payload.requiere_documentos = this.requiereDocumentos;

      this.http.post(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/notificar`, payload, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: () => {
          Swal.fire('¡Listo!', 'La gestión fue registrada y la notificación enviada correctamente.', 'success')
            .then(() => this.router.navigate(['/gestion-creditos']));
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'La notificación no pudo enviarse. La solicitud continúa pendiente de gestión.', 'error');
        }
      });
    });
  }

  cancelar(): void {
    this.router.navigate(['/gestion-creditos']);
  }
}
