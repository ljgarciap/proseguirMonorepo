import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import Swal from 'sweetalert2';

/**
 * Registro de Transferencia Bancaria (SCRUM-224): Tesorería (o Super Admin)
 * registra y valida los datos bancarios del beneficiario y la transacción
 * de la transferencia que efectúa el desembolso, disponible después de que
 * Gerencia aprobó el Registro de Operación de Desembolso en CYF (SCRUM-219,
 * estado 'ejecucion_transferencia'). Mismo patrón de los otros 3 estados
 * legacy tomados por Gestión de Créditos — ver docblock de
 * GestionCreditoController::transferenciaBancaria().
 */
@Component({
  selector: 'app-gestion-creditos-transferencia-bancaria',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, MilesSeparatorDirective],
  templateUrl: './gestion-creditos-transferencia-bancaria.component.html',
  styleUrls: ['./gestion-creditos-transferencia-bancaria.component.css']
})
export class GestionCreditosTransferenciaBancariaComponent implements OnInit {
  creditoId!: number;
  credito: any = null;
  tiposDocumento: any[] = [];
  entidadesBancarias: any[] = [];
  loading = false;
  guardando = false;
  activeRole = '';
  mostrarTrazabilidad = false;
  today = new Date().toISOString().slice(0, 10);

  // Información bancaria (§5.2)
  titularCuenta = '';
  tipoDocumentoTitularId: number | null = null;
  numeroDocumentoTitular = '';
  entidadBancariaId: number | null = null;
  tipoCuenta: 'ahorros' | 'corriente' | '' = '';
  numeroCuenta = '';
  numeroCuentaConfirmacion = '';
  monedaCuenta = 'COP';
  correoNotificacionPago = '';
  certificadoBancario: File | null = null;
  observacionesBancarias = '';

  // Registro de transferencia (§5.2)
  fechaTransferencia = '';
  horaTransferencia = '';
  valorTransaccion: number | null = null;
  valorTransaccionConfirmacion: number | null = null;
  numeroTransaccion = '';
  comprobanteTransferencia: File | null = null;
  declaracionValidacionBancaria = false;

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
    this.cargarCatalogos();
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

  cargarCatalogos(): void {
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe({
      next: (data) => { this.tiposDocumento = data; },
      error: () => {}
    });
    this.http.get<any[]>(`${environment.apiUrl}/parameters/entidades_bancarias`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => { this.entidadesBancarias = data; },
      error: () => {}
    });
  }

  get cliente(): any {
    return this.credito?.solicitud_credito?.cliente || null;
  }

  get solicitud(): any {
    return this.credito?.solicitud_credito || null;
  }

  get esPersonaJuridica(): boolean {
    return this.cliente?.tipo_persona?.codigo === 'JURIDICA';
  }

  get puedeGestionar(): boolean {
    if (!this.credito) return false;
    if (this.credito.estado !== 'ejecucion_transferencia') return false;
    return this.activeRole === 'tesoreria' || this.activeRole === 'superadmin';
  }

  /** "Aprobación operación CYF" (§5.2, no editable): se deriva del
   * historial, igual que ultimoIngreso en desembolso-ingreso y
   * validacionGarantias en registro-cyf — la transición de Gerencia
   * (desembolsoAprobacion) es la que dejó el crédito en
   * 'ejecucion_transferencia'. */
  get aprobacionCyf(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'ejecucion_transferencia');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  /** "Usuario que realizó el registro en CYF" (§5.2, no editable): quien
   * ejecutó desembolsoIngreso(), transición hacia 'desembolso_aprobacion'. */
  get registroCyfUsuario(): any {
    const historial: any[] = this.credito?.historial_estados || [];
    const candidatas = historial.filter(h => h.estado_nuevo === 'desembolso_aprobacion');
    return candidatas.length ? candidatas[candidatas.length - 1] : null;
  }

  get trazabilidad(): any[] {
    return this.credito?.historial_estados || [];
  }

  onCertificadoSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.certificadoBancario = input.files && input.files.length ? input.files[0] : null;
  }

  onComprobanteSeleccionado(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.comprobanteTransferencia = input.files && input.files.length ? input.files[0] : null;
  }

  /** RN-10/§12: los números de cuenta se muestran enmascarados en
   * confirmaciones y trazabilidad — últimos 4 dígitos visibles. */
  private enmascarar(numero: string): string {
    const limpio = (numero || '').trim();
    if (limpio.length <= 4) return limpio;
    return '*'.repeat(limpio.length - 4) + limpio.slice(-4);
  }

  private entidadNombre(): string {
    return this.entidadesBancarias.find(e => e.id === this.entidadBancariaId)?.nombre || '';
  }

  private validarFormulario(): string | null {
    if (!this.titularCuenta.trim()) return 'Ingrese el titular de la cuenta.';
    if (!this.tipoDocumentoTitularId) return 'Seleccione el tipo de documento del titular.';
    if (!this.numeroDocumentoTitular.trim()) return 'Ingrese el número de documento del titular.';
    if (!this.entidadBancariaId) return 'Seleccione la entidad bancaria.';
    if (!this.tipoCuenta) return 'Seleccione el tipo de cuenta.';
    if (!this.numeroCuenta.trim()) return 'Ingrese el número de cuenta.';
    if (this.numeroCuenta.trim() !== this.numeroCuentaConfirmacion.trim()) return 'El número de cuenta y su confirmación no coinciden.';
    if (!this.monedaCuenta.trim()) return 'Seleccione la moneda de la cuenta.';
    if (!this.correoNotificacionPago.trim()) return 'Ingrese el correo electrónico para notificación del pago.';
    if (!this.certificadoBancario) return 'Adjunte el certificado bancario.';
    if (!this.fechaTransferencia) return 'Ingrese la fecha de la transferencia.';
    if (new Date(this.fechaTransferencia) > new Date()) return 'La fecha de la transferencia no puede ser posterior a la fecha actual.';
    if (!this.horaTransferencia) return 'Ingrese la hora de la transferencia.';
    if (this.valorTransaccion === null) return 'Ingrese el valor de la transacción.';
    if (this.valorTransaccionConfirmacion === null || this.valorTransaccion !== this.valorTransaccionConfirmacion) {
      return 'El valor de la transacción y su confirmación no coinciden.';
    }
    if (Number(this.valorTransaccion) !== Number(this.credito?.monto)) {
      return 'El valor de la transacción debe ser igual al valor aprobado para desembolso.';
    }
    if (!this.numeroTransaccion.trim()) return 'Ingrese el número de la transacción.';
    if (!this.comprobanteTransferencia) return 'Adjunte el comprobante de la transferencia.';
    if (!this.declaracionValidacionBancaria) return 'Confirme la validación de los datos bancarios del beneficiario.';
    return null;
  }

  registrar(): void {
    const error = this.validarFormulario();
    if (error) {
      Swal.fire('Falta información', error, 'warning');
      return;
    }

    const cuentaEnmascarada = this.enmascarar(this.numeroCuenta);

    Swal.fire({
      title: '¿Confirmar registro de la transferencia?',
      html: `¿Confirma el registro de la transferencia por valor de <strong>$${Number(this.valorTransaccion).toLocaleString('es-CO')}</strong> `
        + `al beneficiario <strong>${this.titularCuenta}</strong>?<br><br>`
        + `Entidad bancaria: <strong>${this.entidadNombre()}</strong><br>`
        + `Cuenta destino: <strong>${cuentaEnmascarada}</strong>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Confirmar registro',
      cancelButtonText: 'Volver y revisar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;
      this.enviar();
    });
  }

  private enviar(): void {
    this.guardando = true;

    const formData = new FormData();
    formData.append('titular_cuenta', this.titularCuenta.trim());
    formData.append('tipo_documento_titular_id', String(this.tipoDocumentoTitularId));
    formData.append('numero_documento_titular', this.numeroDocumentoTitular.trim());
    formData.append('entidad_bancaria_id', String(this.entidadBancariaId));
    formData.append('tipo_cuenta', this.tipoCuenta);
    formData.append('numero_cuenta', this.numeroCuenta.trim());
    formData.append('numero_cuenta_confirmacion', this.numeroCuentaConfirmacion.trim());
    formData.append('moneda_cuenta', this.monedaCuenta);
    formData.append('correo_notificacion_pago', this.correoNotificacionPago.trim());
    formData.append('certificado_bancario', this.certificadoBancario as File, (this.certificadoBancario as File).name);
    if (this.observacionesBancarias?.trim()) {
      formData.append('observaciones_bancarias', this.observacionesBancarias.trim());
    }
    formData.append('fecha_transferencia', this.fechaTransferencia);
    formData.append('hora_transferencia', this.horaTransferencia);
    formData.append('valor_transaccion', String(this.valorTransaccion));
    formData.append('valor_transaccion_confirmacion', String(this.valorTransaccionConfirmacion));
    formData.append('numero_transaccion', this.numeroTransaccion.trim());
    formData.append('comprobante_transferencia', this.comprobanteTransferencia as File, (this.comprobanteTransferencia as File).name);
    formData.append('declaracion_validacion_bancaria', '1');

    this.http.post<any>(`${environment.apiUrl}/gestion-creditos/${this.creditoId}/transferencia-bancaria`, formData, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (resp) => {
        this.guardando = false;
        Swal.fire('¡Listo!', resp.message, 'success')
          .then(() => this.router.navigate(['/gestion-creditos']));
      },
      error: (err) => {
        this.guardando = false;
        Swal.fire('Error', err.error?.message || 'No se pudo registrar la transferencia bancaria.', 'error');
      }
    });
  }

  /** SCRUM-239: pide confirmación antes de descartar — el formulario tiene
   * varios campos obligatorios (info bancaria + registro de transferencia)
   * y hasta ahora "Cancelar" navegaba directo sin avisar que se perdían. */
  cancelar(): void {
    Swal.fire({
      title: '¿Cancelar el registro?',
      text: 'Se perderán todos los datos ingresados en este formulario.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cancelar',
      cancelButtonText: 'Volver al formulario',
      confirmButtonColor: '#dc2626'
    }).then(result => {
      if (!result.isConfirmed) return;
      this.router.navigate(['/gestion-creditos']);
    });
  }
}
