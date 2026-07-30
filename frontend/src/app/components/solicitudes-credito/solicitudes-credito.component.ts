import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import { ClienteAutocompleteComponent } from '../shared/cliente-autocomplete/cliente-autocomplete.component';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-solicitudes-credito',
  standalone: true,
  imports: [CommonModule, FormsModule, MilesSeparatorDirective, ClienteAutocompleteComponent],
  templateUrl: './solicitudes-credito.component.html',
  styleUrls: ['./solicitudes-credito.component.css']
})
export class SolicitudesCreditoComponent implements OnInit {
  activeTab: 'pendientes' | 'registrar' = 'pendientes';
  
  // Lists for dropdowns
  activeClientes: any[] = [];
  tipoCreditos: any[] = [];
  amortizaciones: any[] = [];
  documentTypes: any[] = [];
  tipoPersonas: any[] = [];
  presets: any[] = [];
  
  // Pending visits list
  pendingVisits: any[] = [];
  loadingPending = false;

  // Ubicación (Departamento -> Ciudad anidados)
  departamentos: any[] = [];
  ciudadesDelDepartamento: any[] = [];
  // Ubicación del proyecto (Crédito Constructor, SCRUM-141) — independiente
  // de la ubicación del cliente.
  ciudadesDelDepartamentoProyecto: any[] = [];
  
  // History list
  historyRequests: any[] = [];
  
  // Form State
  isFromVisit = false;
  selectedVisitId: number | null = null;
  selectedPresetId: number | null = null;
  selectedPresetDocs: any[] = [];

  // SCRUM-159: edición de "Condiciones Financieras del Crédito" de una
  // solicitud ya registrada (por Coordinador Comercial / superadmin).
  // No nulo cuando el formulario está en modo edición en vez de creación.
  editingSolicitudId: number | null = null;

  // SCRUM-159 (hallazgo Senior Reviewer): true cuando la solicitud en
  // edición ya tiene un CreditoOrdinario asociado (workflow BPMN en curso).
  // El backend es la fuente de verdad del bloqueo (422 en el PUT) — esto
  // solo evita que el usuario intente un cambio que sabemos que va a fallar.
  editingTieneCreditoOrdinario = false;

  // Form Fields
  form: any = {
    cliente_id: '',
    tipo_persona_id: '',
    tipo_documento_id: '',
    numero_documento: '',
    nombres: '',
    primer_apellido: '',
    segundo_apellido: '',
    correo_electronico: '',
    telefono: '',
    direccion: '',
    pais: 'Colombia',
    departamento_id: '',
    ciudad_id: '',

    // Juridica fields
    nombre_razon_social: '',
    tipo_empresa: '',
    actividad_economica: '',
    correo_electronico_empresarial: '',
    
    // Representative legal fields
    rep_tipo_documento_id: '',
    rep_numero_documento: '',
    rep_nombres: '',
    rep_primer_apellido: '',
    rep_segundo_apellido: '',
    rep_cargo: '',
    rep_correo_electronico: '',
    rep_telefono: '',

    // Credit fields
    tipo_credito_id: '',
    proyecto: '',
    proyecto_direccion: '',
    proyecto_departamento_id: '',
    proyecto_ciudad_id: '',
    monto_solicitado: null,
    plazo_meses: null,
    amortizacion_id: '',
    destino_recurso: '',
    garantia: '',
    fuente_pago: '',

    // Notification fields
    correo_notificacion: '',
    asunto_notificacion: '',
    mensaje_notificacion: ''
  };

  constructor(private http: HttpClient, private authService: AuthService) {}

  // SCRUM-159: Coordinador Comercial y superadmin pueden editar
  // "Condiciones Financieras del Crédito" de una solicitud ya registrada.
  canEditCondicionesFinancieras(): boolean {
    const role = this.authService.getActiveRole();
    return role === 'coordinador_comercial' || role === 'superadmin';
  }

  get isEditingSolicitud(): boolean {
    return this.editingSolicitudId !== null;
  }

  ngOnInit() {
    this.loadPendingVisits();
    this.loadDropdowns();
    this.loadHistory();
    this.loadDepartamentos();
  }

  loadDepartamentos() {
    this.http.get<any[]>(`${environment.apiUrl}/ubicaciones/departamentos`).subscribe({ next: data => this.departamentos = data, error: () => {} });
  }

  onDepartamentoChange(preservarCiudad = false) {
    if (!preservarCiudad) {
      this.form.ciudad_id = '';
    }
    this.ciudadesDelDepartamento = [];
    if (this.form.departamento_id) {
      this.http.get<any[]>(`${environment.apiUrl}/ubicaciones/ciudades?departamento_id=${this.form.departamento_id}`).subscribe({
        next: data => this.ciudadesDelDepartamento = data,
        error: () => {}
      });
    }
  }

  onDepartamentoProyectoChange(preservarCiudad = false) {
    if (!preservarCiudad) {
      this.form.proyecto_ciudad_id = '';
    }
    this.ciudadesDelDepartamentoProyecto = [];
    if (this.form.proyecto_departamento_id) {
      this.http.get<any[]>(`${environment.apiUrl}/ubicaciones/ciudades?departamento_id=${this.form.proyecto_departamento_id}`).subscribe({
        next: data => this.ciudadesDelDepartamentoProyecto = data,
        error: () => {}
      });
    }
  }

  loadPendingVisits() {
    this.loadingPending = true;
    this.http.get<any[]>(`${environment.apiUrl}/solicitudes-credito/pendientes`).subscribe({
      next: (data) => {
        this.pendingVisits = data;
        this.loadingPending = false;
      },
      error: () => {
        this.loadingPending = false;
        Swal.fire('Error', 'No se pudieron cargar las solicitudes pendientes.', 'error');
      }
    });
  }

  loadHistory() {
    this.http.get<any[]>(`${environment.apiUrl}/solicitudes-credito`).subscribe({
      next: (data) => {
        this.historyRequests = data;
      },
      error: () => {}
    });
  }

  loadDropdowns() {
    // Clientes
    this.http.get<any[]>(`${environment.apiUrl}/clientes?activo=true`).subscribe({ next: data => this.activeClientes = data, error: () => {} });
    // Tipos Credito
    this.http.get<any[]>(`${environment.apiUrl}/parameters/tipo_creditos`).subscribe({ next: data => this.tipoCreditos = data, error: () => {} });
    // Amortizaciones
    this.http.get<any[]>(`${environment.apiUrl}/parameters/amortizaciones`).subscribe({ next: data => this.amortizaciones = data, error: () => {} });
    // Document Types
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe({ next: data => this.documentTypes = data, error: () => {} });
    // Tipo Personas (Natural / Jurídica)
    this.http.get<any[]>(`${environment.apiUrl}/parameters/tipo_personas`).subscribe({ next: data => this.tipoPersonas = data, error: () => {} });
    // Presets
    this.http.get<any[]>(`${environment.apiUrl}/document-presets`).subscribe({ next: data => this.presets = data, error: () => {} });
  }

  switchTab(tab: 'pendientes' | 'registrar') {
    this.activeTab = tab;
    if (tab === 'pendientes') {
      this.loadPendingVisits();
      this.loadHistory();
    }
  }

  // Preload from a pending visit
  onRegisterVisit(visit: any) {
    this.resetForm();
    this.isFromVisit = true;
    this.selectedVisitId = visit.id;

    // Load client info
    const cliente = visit.cliente;
    if (cliente) {
      this.form.cliente_id = cliente.id;
      this.form.tipo_persona_id = cliente.tipo_persona_id || '';
      this.form.tipo_documento_id = cliente.tipo_documento_id || '';
      this.form.numero_documento = cliente.numero_documento || '';
      
      // Populate fields
      this.form.nombres = cliente.nombres || '';
      this.form.primer_apellido = cliente.primer_apellido || '';
      this.form.segundo_apellido = cliente.segundo_apellido || '';
      this.form.correo_electronico = cliente.correo_electronico || '';
      this.form.telefono = cliente.telefono || '';
      this.form.direccion = cliente.direccion || '';
      this.form.pais = cliente.pais || 'Colombia';
      this.form.departamento_id = cliente.departamento_id || '';
      this.form.ciudad_id = cliente.ciudad_id || '';
      if (this.form.departamento_id) {
        this.onDepartamentoChange(true);
      }

      this.form.nombre_razon_social = cliente.nombre_razon_social || '';
      this.form.tipo_empresa = cliente.tipo_empresa || '';
      this.form.actividad_economica = cliente.actividad_economica || '';
      this.form.correo_electronico_empresarial = cliente.correo_electronico_empresarial || '';

      this.form.rep_tipo_documento_id = cliente.rep_tipo_documento_id || '';
      this.form.rep_numero_documento = cliente.rep_numero_documento || '';
      this.form.rep_nombres = cliente.rep_nombres || '';
      this.form.rep_primer_apellido = cliente.rep_primer_apellido || '';
      this.form.rep_segundo_apellido = cliente.rep_segundo_apellido || '';
      this.form.rep_cargo = cliente.rep_cargo || '';
      this.form.rep_correo_electronico = cliente.rep_correo_electronico || '';
      this.form.rep_telefono = cliente.rep_telefono || '';
      
      // Preload notification email
      this.form.correo_notificacion = (cliente.tipo_persona?.codigo === 'JURIDICA')
        ? cliente.correo_electronico_empresarial
        : cliente.correo_electronico;
    }

    // Load credit info
    this.form.tipo_credito_id = visit.tipo_credito_id || '';
    this.form.monto_solicitado = visit.monto_solicitado || null;
    this.form.plazo_meses = visit.plazo || null;
    this.form.amortizacion_id = visit.amortizacion_id || '';
    this.form.destino_recurso = visit.destino_recurso || '';
    this.form.garantia = visit.garantia || '';
    this.form.fuente_pago = visit.fuente_pago || '';

    // Generate subject and message template
    const tcName = visit.tipo_credito?.nombre || 'Crédito Ordinario';
    this.form.asunto_notificacion = `Solicitud de documentos para solicitud de ${tcName.toLowerCase()}`;
    this.form.mensaje_notificacion = `Estimado cliente, nos complace informarle que hemos registrado su solicitud de crédito de acuerdo con nuestra última reunión. Para proceder con el estudio, requerimos que adjunte los soportes correspondientes en su portal de cliente.`;

    // Attempt to auto-select a preset matching the credit type name
    this.autoSelectPreset(tcName);

    this.activeTab = 'registrar';
  }

  autoSelectPreset(creditTypeName: string) {
    if (!creditTypeName) return;
    const cleanTypeName = creditTypeName.toLowerCase().trim();
    
    // Find preset with similar name
    const matchedPreset = this.presets.find(p => 
      p.nombre.toLowerCase().includes(cleanTypeName) ||
      cleanTypeName.includes(p.nombre.toLowerCase())
    );

    if (matchedPreset) {
      this.selectedPresetId = matchedPreset.id;
      this.onPresetChange();
    }
  }

  // SCRUM-159: preload a registered solicitud into the form to edit its
  // "Condiciones Financieras del Crédito". Reuses the isFromVisit lock for
  // the rest of the sections (cliente, representante legal, proyecto,
  // notificación) — solo los 7 campos financieros quedan habilitados,
  // condicionado además a canEditCondicionesFinancieras().
  onEditSolicitud(req: any) {
    this.resetForm();
    this.isFromVisit = true;
    this.editingSolicitudId = req.id;
    this.editingTieneCreditoOrdinario = !!req.credito_ordinario_exists;
    this.selectedVisitId = req.visita_id || null;
    this.selectedPresetId = req.document_preset_id || null;

    const cliente = req.cliente;
    if (cliente) {
      this.form.cliente_id = cliente.id;
      this.form.tipo_persona_id = cliente.tipo_persona_id || '';
      this.form.tipo_documento_id = cliente.tipo_documento_id || '';
      this.form.numero_documento = cliente.numero_documento || '';

      this.form.nombres = cliente.nombres || '';
      this.form.primer_apellido = cliente.primer_apellido || '';
      this.form.segundo_apellido = cliente.segundo_apellido || '';
      this.form.correo_electronico = cliente.correo_electronico || '';
      this.form.telefono = cliente.telefono || '';
      this.form.direccion = cliente.direccion || '';
      this.form.pais = cliente.pais || 'Colombia';
      this.form.departamento_id = cliente.departamento_id || '';
      this.form.ciudad_id = cliente.ciudad_id || '';
      if (this.form.departamento_id) {
        this.onDepartamentoChange(true);
      }

      this.form.nombre_razon_social = cliente.nombre_razon_social || '';
      this.form.tipo_empresa = cliente.tipo_empresa || '';
      this.form.actividad_economica = cliente.actividad_economica || '';
      this.form.correo_electronico_empresarial = cliente.correo_electronico_empresarial || '';

      this.form.rep_tipo_documento_id = cliente.rep_tipo_documento_id || '';
      this.form.rep_numero_documento = cliente.rep_numero_documento || '';
      this.form.rep_nombres = cliente.rep_nombres || '';
      this.form.rep_primer_apellido = cliente.rep_primer_apellido || '';
      this.form.rep_segundo_apellido = cliente.rep_segundo_apellido || '';
      this.form.rep_cargo = cliente.rep_cargo || '';
      this.form.rep_correo_electronico = cliente.rep_correo_electronico || '';
      this.form.rep_telefono = cliente.rep_telefono || '';
    }

    // Condiciones Financieras del Crédito — única sección realmente editable
    this.form.tipo_credito_id = req.tipo_credito_id || '';
    this.form.monto_solicitado = req.monto_solicitado || null;
    this.form.plazo_meses = req.plazo_meses || null;
    this.form.amortizacion_id = req.amortizacion_id || '';
    this.form.destino_recurso = req.destino_recurso || '';
    this.form.garantia = req.garantia || '';
    this.form.fuente_pago = req.fuente_pago || '';

    // Información del Proyecto — solo lectura en modo edición (SCRUM-159 no
    // incluye este bloque en el alcance)
    this.form.proyecto = req.proyecto || '';
    this.form.proyecto_direccion = req.proyecto_direccion || '';
    this.form.proyecto_departamento_id = req.proyecto_departamento_id || '';
    this.form.proyecto_ciudad_id = req.proyecto_ciudad_id || '';
    if (this.form.proyecto_departamento_id) {
      this.onDepartamentoProyectoChange(true);
    }

    // Notificación — se precarga solo para no romper isFormValid(); esta
    // sección se oculta en modo edición y no se reenvía al backend.
    this.form.correo_notificacion = req.correo_notificacion || '';
    this.form.asunto_notificacion = req.asunto_notificacion || '';
    this.form.mensaje_notificacion = req.mensaje_notificacion || '';

    this.activeTab = 'registrar';
  }

  // Handle manual creation
  onNewManualRequest() {
    this.resetForm();
    this.isFromVisit = false;
    this.selectedVisitId = null;
    this.form.asunto_notificacion = 'Solicitud de documentos para solicitud de crédito';
    this.form.mensaje_notificacion = 'Estimado cliente, hemos iniciado el registro de su solicitud de crédito. Para continuar con el proceso, solicitamos cargue los documentos requeridos.';
    this.activeTab = 'registrar';
  }

  // Handle client selection change to autocomplete form fields
  onClientChange() {
    const selected = this.activeClientes.find(c => c.id === Number(this.form.cliente_id));
    if (!selected) return;

    this.form.tipo_persona_id = selected.tipo_persona_id || '';
    this.form.tipo_documento_id = selected.tipo_documento_id || '';
    this.form.numero_documento = selected.numero_documento || '';
    
    this.form.nombres = selected.nombres || '';
    this.form.primer_apellido = selected.primer_apellido || '';
    this.form.segundo_apellido = selected.segundo_apellido || '';
    this.form.correo_electronico = selected.correo_electronico || '';
    this.form.telefono = selected.telefono || '';
    this.form.direccion = selected.direccion || '';
    this.form.pais = selected.pais || 'Colombia';
    this.form.departamento_id = selected.departamento_id || '';
    this.form.ciudad_id = selected.ciudad_id || '';
    if (this.form.departamento_id) {
      this.onDepartamentoChange(true);
    }

    this.form.nombre_razon_social = selected.nombre_razon_social || '';
    this.form.tipo_empresa = selected.tipo_empresa || '';
    this.form.actividad_economica = selected.actividad_economica || '';
    this.form.correo_electronico_empresarial = selected.correo_electronico_empresarial || '';

    this.form.rep_tipo_documento_id = selected.rep_tipo_documento_id || '';
    this.form.rep_numero_documento = selected.rep_numero_documento || '';
    this.form.rep_nombres = selected.rep_nombres || '';
    this.form.rep_primer_apellido = selected.rep_primer_apellido || '';
    this.form.rep_segundo_apellido = selected.rep_segundo_apellido || '';
    this.form.rep_cargo = selected.rep_cargo || '';
    this.form.rep_correo_electronico = selected.rep_correo_electronico || '';
    this.form.rep_telefono = selected.rep_telefono || '';

    const isJuridica = this.isPersonaJuridica();
    this.form.correo_notificacion = isJuridica ? selected.correo_electronico_empresarial : selected.correo_electronico;
  }

  // When type of persona changes manually
  onTipoPersonaChange() {
    const isJuridica = this.isPersonaJuridica();
    this.form.correo_notificacion = isJuridica ? this.form.correo_electronico_empresarial : this.form.correo_electronico;
  }

  // Check if JURIDICA selected — uses tipoPersonas list by tipo_persona_id,
  // with fallback to NIT document type for pre-loaded visits.
  isPersonaJuridica(): boolean {
    // When form comes from a visit, use the loaded client's tipo_persona relation
    const selectedClient = this.activeClientes.find(c => c.id === Number(this.form.cliente_id));
    if (selectedClient && selectedClient.tipo_persona?.codigo === 'JURIDICA') return true;

    // In manual mode, use the tipo_persona_id field against the tipoPersonas list
    if (this.form.tipo_persona_id && this.tipoPersonas.length > 0) {
      const selectedTp = this.tipoPersonas.find(tp => tp.id === Number(this.form.tipo_persona_id));
      if (selectedTp && selectedTp.codigo === 'JURIDICA') return true;
    }

    // Fallback: if document type is NIT, treat as juridica
    const selectedDocType = this.documentTypes.find(t => t.id === Number(this.form.tipo_documento_id));
    if (selectedDocType && selectedDocType.codigo === 'NIT') return true;

    return false;
  }

  // SCRUM-120 Fase 2: el campo Proyecto solo aplica (y es obligatorio) para
  // Crédito Constructor — la bandeja de Informe Técnico lo necesita.
  isTipoCreditoConstructor(): boolean {
    if (!this.form.tipo_credito_id || this.tipoCreditos.length === 0) return false;
    const selected = this.tipoCreditos.find(tc => tc.id === Number(this.form.tipo_credito_id));
    return !!selected && String(selected.codigo).toUpperCase() === 'CONSTRUCTOR';
  }

  // Load preset documents
  onPresetChange() {
    if (!this.selectedPresetId) {
      this.selectedPresetDocs = [];
      return;
    }
    const preset = this.presets.find(p => p.id === Number(this.selectedPresetId));
    this.selectedPresetDocs = preset ? (preset.requirements || []) : [];
  }

  // Validate form before submit
  isFormValid(): boolean {
    if (!this.form.cliente_id && !this.form.numero_documento) return false;
    if (!this.form.tipo_credito_id || !this.form.monto_solicitado || !this.form.plazo_meses || !this.form.amortizacion_id) return false;
    if (this.isTipoCreditoConstructor() && (!this.form.proyecto || !this.form.proyecto_direccion || !this.form.proyecto_departamento_id || !this.form.proyecto_ciudad_id)) return false;
    if (!this.form.destino_recurso || !this.form.fuente_pago) return false;
    if (!this.form.correo_notificacion || !this.form.asunto_notificacion || !this.form.mensaje_notificacion) return false;

    // Check email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(this.form.correo_notificacion)) return false;

    if (this.isPersonaJuridica()) {
      if (!this.form.nombre_razon_social || !this.form.tipo_empresa || !this.form.actividad_economica) return false;
      if (!this.form.rep_tipo_documento_id || !this.form.rep_numero_documento || !this.form.rep_nombres || !this.form.rep_primer_apellido || !this.form.rep_cargo || !this.form.rep_correo_electronico || !this.form.rep_telefono) return false;
    } else {
      if (!this.form.nombres || !this.form.primer_apellido || !this.form.correo_electronico || !this.form.telefono) return false;
    }

    return true;
  }

  // Submit form
  submitRequest() {
    if (!this.isFormValid()) {
      Swal.fire('Validación', 'Por favor complete todos los campos obligatorios del formulario.', 'warning');
      return;
    }

    if (this.isEditingSolicitud) {
      this.submitCondicionesFinancierasUpdate();
      return;
    }

    const clientName = this.isPersonaJuridica() ? this.form.nombre_razon_social : `${this.form.nombres} ${this.form.primer_apellido}`;
    const clientDoc = this.form.numero_documento;

    Swal.fire({
      title: '¿Registrar y Notificar Cliente?',
      text: `¿Está seguro de que desea realizar y notificar el registro de la solicitud de crédito para el cliente ${clientDoc} - ${clientName}?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Aceptar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then((result) => {
      if (result.isConfirmed) {
        const payload = {
          ...this.form,
          visita_id: this.selectedVisitId,
          document_preset_id: this.selectedPresetId
        };

        this.http.post(`${environment.apiUrl}/solicitudes-credito`, payload).subscribe({
          next: () => {
            Swal.fire('Registro Exitoso', 'La solicitud de crédito ha sido registrada y se ha enviado la notificación al cliente.', 'success');
            this.resetForm();
            this.switchTab('pendientes');
          },
          error: (err) => {
            Swal.fire('Error', err.error.message || 'Ocurrió un error al procesar la solicitud.', 'error');
          }
        });
      }
    });
  }

  // SCRUM-159: guarda solo los 7 campos de "Condiciones Financieras del
  // Crédito" de una solicitud ya registrada — no reenvía notificación ni
  // toca las demás secciones.
  submitCondicionesFinancierasUpdate() {
    const payload = {
      tipo_credito_id: this.form.tipo_credito_id,
      monto_solicitado: this.form.monto_solicitado,
      plazo_meses: this.form.plazo_meses,
      amortizacion_id: this.form.amortizacion_id,
      destino_recurso: this.form.destino_recurso,
      garantia: this.form.garantia,
      fuente_pago: this.form.fuente_pago
    };

    Swal.fire({
      title: '¿Guardar cambios en Condiciones Financieras?',
      text: '¿Está seguro de que desea actualizar las condiciones financieras de esta solicitud de crédito?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.put(`${environment.apiUrl}/solicitudes-credito/${this.editingSolicitudId}`, payload).subscribe({
          next: () => {
            Swal.fire('Actualización Exitosa', 'Las condiciones financieras de la solicitud fueron actualizadas.', 'success');
            this.resetForm();
            this.switchTab('pendientes');
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'Ocurrió un error al actualizar la solicitud.', 'error');
          }
        });
      }
    });
  }

  resetForm() {
    this.isFromVisit = false;
    this.selectedVisitId = null;
    this.editingSolicitudId = null;
    this.editingTieneCreditoOrdinario = false;
    this.selectedPresetId = null;
    this.selectedPresetDocs = [];
    this.ciudadesDelDepartamento = [];
    this.ciudadesDelDepartamentoProyecto = [];

    this.form = {
      cliente_id: '',
      tipo_persona_id: '',
      tipo_documento_id: '',
      numero_documento: '',
      nombres: '',
      primer_apellido: '',
      segundo_apellido: '',
      correo_electronico: '',
      telefono: '',
      direccion: '',
      pais: 'Colombia',
      departamento_id: '',
      ciudad_id: '',

      nombre_razon_social: '',
      tipo_empresa: '',
      actividad_economica: '',
      correo_electronico_empresarial: '',
      
      rep_tipo_documento_id: '',
      rep_numero_documento: '',
      rep_nombres: '',
      rep_primer_apellido: '',
      rep_segundo_apellido: '',
      rep_cargo: '',
      rep_correo_electronico: '',
      rep_telefono: '',

      tipo_credito_id: '',
      proyecto: '',
      proyecto_direccion: '',
      proyecto_departamento_id: '',
      proyecto_ciudad_id: '',
      monto_solicitado: null,
      plazo_meses: null,
      amortizacion_id: '',
      destino_recurso: '',
      garantia: '',
      fuente_pago: '',

      correo_notificacion: '',
      asunto_notificacion: '',
      mensaje_notificacion: ''
    };
  }

  formatDocNumber(docNum: string): string {
    if (!docNum) return '';
    if (docNum.length > 1) {
      return docNum.slice(0, -1) + '-' + docNum.slice(-1);
    }
    return docNum;
  }
}
