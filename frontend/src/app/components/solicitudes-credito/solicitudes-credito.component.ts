import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-solicitudes-credito',
  standalone: true,
  imports: [CommonModule, FormsModule],
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
  presets: any[] = [];
  
  // Pending visits list
  pendingVisits: any[] = [];
  loadingPending = false;
  
  // History list
  historyRequests: any[] = [];
  
  // Form State
  isFromVisit = false;
  selectedVisitId: number | null = null;
  selectedPresetId: number | null = null;
  selectedPresetDocs: any[] = [];

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
    departamento: '',
    ciudad: '',
    
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

  // Schemes of amortization from description
  amortizacionPresets = [
    { nombre: 'Interés Mensual Capital Mensual', codigo: 'INT_MEN_CAP_MEN' },
    { nombre: 'Interés y Capital Trimestral', codigo: 'INT_CAP_TRI' },
    { nombre: 'Cuota Fija Mensual', codigo: 'CUOTA_FIJA_MEN' },
    { nombre: 'Interés Mensual y Capital Semestral', codigo: 'INT_MEN_CAP_SEM' }
  ];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadPendingVisits();
    this.loadDropdowns();
    this.loadHistory();
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
    this.http.get<any[]>(`${environment.apiUrl}/clientes?activo=true`).subscribe(data => this.activeClientes = data);
    // Tipos Credito
    this.http.get<any[]>(`${environment.apiUrl}/parameters/tipo_creditos`).subscribe(data => this.tipoCreditos = data);
    // Amortizaciones
    this.http.get<any[]>(`${environment.apiUrl}/parameters/amortizaciones`).subscribe(data => this.amortizaciones = data);
    // Document Types
    this.http.get<any[]>(`${environment.apiUrl}/document-types`).subscribe(data => this.documentTypes = data);
    // Presets
    this.http.get<any[]>(`${environment.apiUrl}/document-presets`).subscribe(data => this.presets = data);
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
      this.form.departamento = cliente.departamento || '';
      this.form.ciudad = cliente.ciudad || '';

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
    this.form.departamento = selected.departamento || '';
    this.form.ciudad = selected.ciudad || '';

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

  // Check if JURIDICA selected
  isPersonaJuridica(): boolean {
    const selectedTp = this.documentTypes.find(t => t.id === Number(this.form.tipo_documento_id));
    if (selectedTp && selectedTp.codigo === 'NIT') return true;
    
    // Check by tipo_persona relation if loaded
    const selectedClient = this.activeClientes.find(c => c.id === Number(this.form.cliente_id));
    if (selectedClient && selectedClient.tipo_persona?.codigo === 'JURIDICA') return true;
    
    return false;
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

  resetForm() {
    this.isFromVisit = false;
    this.selectedVisitId = null;
    this.selectedPresetId = null;
    this.selectedPresetDocs = [];
    
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
      departamento: '',
      ciudad: '',
      
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
