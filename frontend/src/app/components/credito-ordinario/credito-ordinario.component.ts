import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-credito-ordinario',
  standalone: true,
  imports: [CommonModule, FormsModule, MilesSeparatorDirective],
  templateUrl: './credito-ordinario.component.html',
  styleUrls: ['./credito-ordinario.component.css']
})
export class CreditoOrdinarioComponent implements OnInit {
  creditos: any[] = [];
  selectedCredito: any = null;
  activeRole: string = 'cliente';
  searchTerm: string = '';
  
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
    // SCRUM-128: el paso combinado analisis_sarlaft_financiero se separó en dos
    // etapas secuenciales. La validación de Listas Restrictivas y SARLAFT ahora
    // se diligencia en el módulo dedicado (/listas-sarlaft, Oficial de
    // Cumplimiento) — este paso del stepper solo queda como referencia visual
    // de progreso, sin panel de acción propio en esta pantalla.
    { key: 'sarlaft_control_interno', label: 'Listas Restrictivas / SARLAFT', role: 'oficial_cumplimiento', roleLabel: 'Oficial de Cumplimiento', desc: 'Validar Listas Restrictivas y emitir concepto SARLAFT (gestionado desde el módulo Listas Restrictivas y SARLAFT).' },
    { key: 'pendiente_analisis_financiero', label: 'Análisis Financiero', role: 'coordinador_comercial', roleLabel: 'Coordinador Comercial', desc: 'Realizar el análisis financiero y preparar la presentación del cliente para el Comité.' },
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

  // SCRUM-143: filtro client-side por cliente o número de documento —
  // la lista ya llega completa del backend, no hace falta re-consultarlo.
  get filteredCreditos(): any[] {
    const term = this.searchTerm.trim().toLowerCase();
    if (!term) return this.creditos;
    return this.creditos.filter(item => {
      const nombre = (item.cliente?.name || '').toLowerCase();
      const documento = (item.cliente?.numero_documento || '').toLowerCase();
      return nombre.includes(term) || documento.includes(term);
    });
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

    // SCRUM-128: sarlaft_control_interno no tiene panel de acción en esta
    // pantalla (se gestiona en /listas-sarlaft) — nadie puede "actuar" acá.
    if (currentStatus === 'sarlaft_control_interno') {
      return false;
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

  // SCRUM-146: los documentos de Etapa 1 se derivan de la Solicitud de
  // Documentos (DocumentRequestItem) creada a partir del preset elegido al
  // registrar la SolicitudCredito. Si el crédito no tiene preset asociado
  // (créditos legacy anteriores a SCRUM-120/146), se mantiene la lista fija
  // original de 4 documentos.
  get etapa1Docs(): { key: string; nombre: string; descripcion: string }[] {
    const items = this.selectedCredito?.solicitud_credito?.document_request?.items;
    if (items && items.length > 0) {
      return items.map((item: any) => ({
        key: 'req_item_' + item.id,
        nombre: item.requirement?.nombre || 'Documento requerido',
        descripcion: item.requirement?.descripcion || ''
      }));
    }
    return [
      { key: 'formulario_solicitud', nombre: 'Formulario de Solicitud', descripcion: 'Formulario de solicitud del cliente diligenciado.' },
      { key: 'documentos_identidad', nombre: 'Documentos de Identidad', descripcion: 'Copia de Cédula de Ciudadanía o NIT del cliente.' },
      { key: 'estados_financieros', nombre: 'Estados Financieros', descripcion: 'Balance y Estados Financieros firmados por contador.' },
      { key: 'certificados_laborales', nombre: 'Certificados Laborales / Comerciales', descripcion: 'Soporte de ingresos o referencias de la empresa.' }
    ];
  }

  onFileUpload(event: Event, campoDoc: string) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (file.type !== 'application/pdf') {
      Swal.fire('Formato Inválido', 'Solo se permite subir archivos en formato PDF.', 'warning');
      return;
    }

    this.executeTransition('subir_archivo', `Carga de soporte PDF obligatorio en campo: ${campoDoc}`, {
      _file: file,
      campo_documento: campoDoc
    });
  }

  // SCRUM-146: documentos de Etapa 1 dirigidos por preset admiten varios
  // archivos por documento (input con atributo "multiple").
  onMultiFileUpload(event: Event, campoDoc: string) {
    const target = event.target as HTMLInputElement;
    const files = target.files ? Array.from(target.files) : [];
    if (!files.length) return;

    const invalido = files.find(f => f.type !== 'application/pdf');
    if (invalido) {
      Swal.fire('Formato Inválido', 'Solo se permite subir archivos en formato PDF.', 'warning');
      return;
    }

    this.executeTransition('subir_archivo', `Carga de soporte(s) PDF en campo: ${campoDoc}`, {
      _files: files,
      campo_documento: campoDoc
    });
  }

  // Devuelve siempre un arreglo, sin importar si documentos[campoDoc] quedó
  // guardado como string único (documentos legacy de una sola etapa) o como
  // arreglo (Etapa 1 dirigida por preset, SCRUM-146).
  getDocFiles(campoDoc: string): string[] {
    const valor = this.selectedCredito?.documentos?.[campoDoc];
    if (!valor) return [];
    return Array.isArray(valor) ? valor : [valor];
  }

  getDocFileName(url: string): string {
    try {
      return decodeURIComponent(url.split('/').pop() || url);
    } catch {
      return url;
    }
  }

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
        const comentario = result.value || comentarioDefecto;
        const headers = { 'X-Active-Role': this.activeRole };
        const url = `${environment.apiUrl}/creditos/${this.selectedCredito.id}/transition`;

        const request$ = (extraData._files && extraData._files.length)
          ? (() => {
              const formData = new FormData();
              formData.append('accion', accion);
              formData.append('comentario', comentario);
              extraData._files.forEach((f: File) => formData.append('archivos[]', f, f.name));
              formData.append('campo_documento', extraData.campo_documento);
              return this.http.post(url, formData, { headers });
            })()
          : (extraData._file instanceof File)
          ? (() => {
              const formData = new FormData();
              formData.append('accion', accion);
              formData.append('comentario', comentario);
              formData.append('archivo', extraData._file, extraData._file.name);
              formData.append('campo_documento', extraData.campo_documento);
              return this.http.post(url, formData, { headers });
            })()
          : this.http.post(url, { accion, comentario, ...extraData }, { headers });

        request$.subscribe({
          next: () => {
            Swal.fire('¡Procesado!', 'El estado del crédito se ha actualizado correctamente.', 'success');
            this.loadCreditos();
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'No se pudo actualizar el estado del crédito.', 'error');
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
