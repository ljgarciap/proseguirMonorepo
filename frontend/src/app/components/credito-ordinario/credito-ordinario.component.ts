import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-credito-ordinario',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './credito-ordinario.component.html',
  styleUrls: ['./credito-ordinario.component.css']
})
export class CreditoOrdinarioComponent implements OnInit {
  creditos: any[] = [];
  selectedCredito: any = null;
  activeRole: string = 'cliente';
  
  // Modal state
  showNewRequestModal = false;
  newRequest = {
    monto: null as number | null,
    plazo_meses: 12
  };

  // BPMN Stepper definition
  bpmnSteps = [
    { key: 'revision_documental', label: 'Revisión Solicitud', role: 'coordinador_comercial', roleLabel: 'Coordinador Comercial', desc: 'Revisar la solicitud inicial del cliente y verificar que los soportes y formularios estén completos.' },
    { key: 'completar_solicitud', label: 'Completar Sop.', role: 'cliente', roleLabel: 'Cliente', desc: 'Completar la documentación faltante solicitada por el Coordinador Comercial.' },
    { key: 'analisis_sarlaft_financiero', label: 'Análisis Dual', role: 'cumplimiento_y_comercial', roleLabel: 'Cumplimiento & Comercial', desc: 'Validar listas restrictivas/SARLAFT (Cumplimiento) y realizar análisis financiero / presentación del cliente (Comercial).' },
    { key: 'aprobacion_presentacion', label: 'Aprobación Pres.', role: 'gerente', roleLabel: 'Gerencia', desc: 'Revisar y aprobar la presentación del cliente elaborada para el Comité de Créditos.' },
    { key: 'comite_evaluacion', label: 'Comité de Crédito', role: 'comite_credito', roleLabel: 'Comité de Crédito', desc: 'Evaluar el perfil de crédito y firmar el Acta oficial de decisión del Comité.' },
    { key: 'formalizacion_garantias', label: 'Garantías', role: 'operativo', roleLabel: 'Dirección Administrativa', desc: 'Revisar y registrar las garantías firmadas por el cliente.' },
    { key: 'aprobacion_registro_cyf', label: 'Registro CYF', role: 'gerente', roleLabel: 'Gerencia', desc: 'Aprobar el registro de la operación en la plataforma core CYF.' },
    { key: 'desembolso_ingreso', label: 'Egreso CYF', role: 'operativo', roleLabel: 'Dirección Administrativa', desc: 'Ingresar y registrar la operación de desembolso en la plataforma core CYF.' },
    { key: 'desembolso_aprobacion', label: 'Aprobación Des.', role: 'gerente', roleLabel: 'Gerencia', desc: 'Dar aprobación final a la orden de desembolso bancario.' },
    { key: 'ejecucion_transferencia', label: 'Transferencia', role: 'tesoreria', roleLabel: 'Tesorería', desc: 'Ejecutar la transferencia bancaria y enviar el comprobante de pago al cliente.' }
  ];

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit() {
    this.activeRole = this.authService.getActiveRole() || 'cliente';
    
    // Subscribe to active role changes to dynamically update dashboard view
    this.authService.activeRole$.subscribe(role => {
      if (role) {
        this.activeRole = role;
        this.loadCreditos();
      }
    });

    this.loadCreditos();
  }

  loadCreditos() {
    this.http.get<any[]>(`${environment.apiUrl}/creditos`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        if (this.selectedCredito) {
          // Keep current selected credit updated
          const updated = data.find(c => c.id === this.selectedCredito.id);
          this.selectedCredito = updated || data[0] || null;
        } else if (data.length > 0) {
          this.selectedCredito = data[0];
        }
      },
      error: () => {
        Swal.fire('Error', 'No se pudieron cargar las solicitudes de crédito.', 'error');
      }
    });
  }

  selectCredito(credito: any) {
    this.selectedCredito = credito;
  }

  // Get index of a state in the BPMN workflow
  getStateIndex(state: string): number {
    if (state === 'completado') return 99;
    if (state === 'rechazado') return -1;
    return this.bpmnSteps.findIndex(step => step.key === state);
  }

  getProgressPercent(): number {
    if (!this.selectedCredito) return 0;
    const currentStatus = this.selectedCredito.estado;
    if (currentStatus === 'completado') return 100;
    if (currentStatus === 'rechazado') return 0;
    const idx = this.getStateIndex(currentStatus);
    if (idx < 0) return 0;
    return Math.round(((idx + 1) / this.bpmnSteps.length) * 100);
  }

  // Determine class for a stepper node
  getStepClass(stepKey: string): string {
    if (!this.selectedCredito) return '';
    const currentStatus = this.selectedCredito.estado;
    if (currentStatus === 'completado') return 'completed';
    if (currentStatus === 'rechazado') return 'disabled';

    const currentIndex = this.getStateIndex(currentStatus);
    const stepIndex = this.bpmnSteps.findIndex(s => s.key === stepKey);

    if (stepIndex < currentIndex) return 'completed';
    if (stepIndex === currentIndex) return 'active';
    return '';
  }

  // Check if current role has permission to act on the current credit status
  isUserRoleAuthorized(): boolean {
    if (!this.selectedCredito) return false;
    if (this.activeRole === 'superadmin') return true;

    const currentStatus = this.selectedCredito.estado;
    if (currentStatus === 'completado' || currentStatus === 'rechazado') return false;

    // Custom check for parallel SARLAFT/Financial analysis step
    if (currentStatus === 'analisis_sarlaft_financiero') {
      return ['coordinador_comercial', 'oficial_cumplimiento'].includes(this.activeRole);
    }

    if (currentStatus === 'formalizacion_garantias') {
      return ['cliente', 'operativo', 'coordinador_comercial'].includes(this.activeRole);
    }

    if (currentStatus === 'aprobacion_registro_cyf') {
      return ['coordinador_comercial', 'gerente'].includes(this.activeRole);
    }

    const currentStep = this.bpmnSteps.find(step => step.key === currentStatus);
    return currentStep ? currentStep.role === this.activeRole : false;
  }

  // Handle file select and convert to base64
  onFileUpload(event: Event, campoDoc: string) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (file.type !== 'application/pdf') {
      Swal.fire('Formato Inválido', 'Solo se permite subir archivos en formato PDF.', 'warning');
      return;
    }

    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = () => {
      const base64String = reader.result as string;
      this.executeTransition('subir_archivo', `Carga de soporte PDF obligatorio en campo: ${campoDoc}`, {
        archivo: base64String,
        archivo_nombre: file.name,
        campo_documento: campoDoc
      });
    };
  }

  // Execute stage transitions or approvals
  executeTransition(accion: string, comentarioDefecto: string = '', extraData: any = {}) {
    Swal.fire({
      title: accion === 'rechazar' ? '¿Rechazar Solicitud?' : 'Confirmar Acción',
      text: accion === 'rechazar' ? 'Por favor ingresa el motivo del rechazo:' : 'Ingresa un comentario de auditoría para este paso (Opcional):',
      input: 'text',
      inputValue: comentarioDefecto,
      inputPlaceholder: 'Escribe un comentario...',
      icon: accion === 'rechazar' ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonText: accion === 'rechazar' ? 'Sí, rechazar' : 'Confirmar y Avanzar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: accion === 'rechazar' ? '#E53E3E' : '#3182CE'
    }).then((result) => {
      if (result.isConfirmed) {
        const payload = {
          accion: accion,
          comentario: result.value || comentarioDefecto,
          ...extraData
        };

        this.http.post(`${environment.apiUrl}/creditos/${this.selectedCredito.id}/transition`, payload, {
          headers: { 'X-Active-Role': this.activeRole }
        }).subscribe({
          next: (updatedCredito) => {
            Swal.fire('¡Procesado!', 'El estado del crédito se ha actualizado correctamente.', 'success');
            this.loadCreditos();
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'No se pudo actualizar el estado del crédito.', 'error');
          }
        });
      }
    });
  }

  // Open creation modal
  openNewRequestModal() {
    this.newRequest = { monto: null, plazo_meses: 12 };
    this.showNewRequestModal = true;
  }

  // Submit new request
  submitNewRequest() {
    if (!this.newRequest.monto || this.newRequest.monto <= 0) {
      Swal.fire('Error', 'Por favor ingresa un monto válido superior a cero.', 'warning');
      return;
    }

    this.http.post(`${environment.apiUrl}/creditos`, this.newRequest, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (created) => {
        this.showNewRequestModal = false;
        Swal.fire('¡Solicitud Creada!', 'Tu solicitud de crédito ordinario fue registrada con éxito.', 'success');
        this.loadCreditos();
      },
      error: (err) => {
        Swal.fire('Error', err.error.message || 'No se pudo crear la solicitud.', 'error');
      }
    });
  }
}
