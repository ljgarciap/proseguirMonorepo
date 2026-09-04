import { Component, HostListener, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-credito-ordinario',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './credito-ordinario.component.html',
  styleUrls: ['./credito-ordinario.component.css']
})
export class CreditoOrdinarioComponent implements OnInit {
  creditos: any[] = [];
  selectedCredito: any = null;
  activeRole: string = 'cliente';
  searchTerm: string = '';
  // SCRUM-176: id de crédito tomado de la URL (/creditos/:creditoId). Fuente de
  // verdad de "qué crédito estoy viendo" — reemplaza el fallback anterior de
  // asumir el primero de la lista, que en la práctica era el crédito más
  // recién creado por cualquiera en el sistema, no el que el usuario venía
  // revisando.
  routeCreditoId: number | null = null;

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
    // SCRUM-183: se retiró el paso "Aprobación Pres." (Gerencia) — confirmar
    // el Análisis Financiero ya pasa directo a Comité de Crédito. La
    // presentación para el Comité se adjunta después, en Actas Comité de
    // Crédito (una por solicitud dentro del acta).
    { key: 'pendiente_analisis_financiero', label: 'Análisis Financiero', role: 'coordinador_comercial', roleLabel: 'Coordinador Comercial', desc: 'Realizar el análisis financiero del cliente.' },
    { key: 'comite_evaluacion', label: 'Comité de Crédito', role: 'comite_credito', roleLabel: 'Comité de Crédito', desc: 'Evaluar el perfil de crédito y firmar el Acta oficial de decisión del Comité.' },
    { key: 'formalizacion_garantias', label: 'Garantías', role: 'operativo', roleLabel: 'Dirección Administrativa', desc: 'Revisar y registrar las garantías firmadas por el cliente.' },
    { key: 'aprobacion_registro_cyf', label: 'Registro CYF', role: 'gerente', roleLabel: 'Gerencia', desc: 'Aprobar el registro de la operación en la plataforma core CYF.' },
    { key: 'desembolso_ingreso', label: 'Egreso CYF', role: 'operativo', roleLabel: 'Dirección Administrativa', desc: 'Ingresar y registrar la operación de desembolso en la plataforma core CYF.' },
    { key: 'desembolso_aprobacion', label: 'Aprobación Des.', role: 'gerente', roleLabel: 'Gerencia', desc: 'Dar aprobación final a la orden de desembolso bancario.' },
    { key: 'ejecucion_transferencia', label: 'Transferencia', role: 'tesoreria', roleLabel: 'Tesorería', desc: 'Ejecutar la transferencia bancaria y enviar el comprobante de pago al cliente.' }
  ];

  constructor(
    private http: HttpClient,
    public authService: AuthService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.activeRole = this.authService.getActiveRole() || 'cliente';

    // Subscribe to active role changes to dynamically update dashboard view
    this.authService.activeRole$.subscribe(role => {
      if (role) {
        this.activeRole = role;
        this.loadCreditos();
      }
    });

    // SCRUM-176: reactivo al param de ruta (no solo lectura inicial) para que
    // navegar entre créditos, o volver a /creditos sin id, siempre dispare una
    // recarga que resuelva correctamente cuál debe quedar seleccionado.
    this.route.paramMap.subscribe(params => {
      const idParam = params.get('creditoId');
      this.routeCreditoId = idParam ? Number(idParam) : null;
      this.loadCreditos();
    });
  }

  // SCRUM-176: volver a esta pantalla con atrás/adelante del navegador (bfcache) o
  // cambiando de pestaña y regresando no dispara ngOnInit ni ninguna petición nueva —
  // 'selectedCredito' (y su historial_estados) queda congelado con lo último que se
  // cargó, aunque el backend ya tenga las transiciones de SARLAFT/Análisis
  // Financiero hechas en otro módulo. Reproduce el síntoma reportado: BD completa,
  // pantalla mostrando solo el primer evento.
  @HostListener('window:pageshow', ['$event'])
  onPageShow(event: PageTransitionEvent): void {
    if (event.persisted) {
      this.loadCreditos();
    }
  }

  @HostListener('document:visibilitychange')
  onVisibilityChange(): void {
    if (document.visibilityState === 'visible') {
      this.loadCreditos();
    }
  }

  loadCreditos() {
    this.http.get<any[]>(`${environment.apiUrl}/creditos`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.creditos = data;
        if (this.routeCreditoId) {
          // La URL indica explícitamente qué crédito mostrar — nunca se
          // adivina. Si no aparece en la lista (rol sin acceso, id inválido),
          // queda sin selección en vez de mostrar uno distinto en silencio.
          this.selectedCredito = data.find(c => c.id === this.routeCreditoId) || null;
        } else if (this.selectedCredito) {
          // Sin id en la URL pero ya había una selección en memoria (cambio de
          // rol, refresco por pageshow/visibilitychange): mantenerla actualizada.
          this.selectedCredito = data.find(c => c.id === this.selectedCredito.id) || null;
        }
        // SCRUM-176: sin id en la URL y sin selección previa, NO se cae a
        // data[0] (el crédito más recién creado por cualquiera en el
        // sistema) — eso era la causa raíz real de la trazabilidad
        // "estancada": mostraba en silencio un crédito distinto al que el
        // usuario venía revisando. Se deja sin seleccionar hasta que el
        // usuario elija uno de la lista.
      },
      error: () => {
        Swal.fire('Error', 'No se pudieron cargar las solicitudes de crédito.', 'error');
      }
    });
  }

  selectCredito(credito: any) {
    this.selectedCredito = credito;
    this.router.navigate(['/creditos', credito.id]);
  }

  // SCRUM-143: filtro client-side por cliente, documento o número de
  // solicitud — la lista ya llega completa del backend, no hace falta
  // re-consultarlo.
  // SCRUM-176 (re-investigación 2026-08-10): faltaba `numero_solicitud`
  // acá. Es el único identificador que QA usa en los reportes ("el crédito
  // CO-2026-XXXXX"), y sin poder buscarlo tenía que ubicar la fila a ojo en
  // una lista con muchas solicitudes del mismo cliente de prueba — terreno
  // fértil para abrir por error un crédito viejo, ver su trazabilidad corta
  // (genuina, no un bug) y reportarlo como recurrencia del mismo defecto.
  get filteredCreditos(): any[] {
    const term = this.searchTerm.trim().toLowerCase();
    if (!term) return this.creditos;
    return this.creditos.filter(item => {
      const nombre = (item.cliente?.name || '').toLowerCase();
      const documento = (item.cliente?.numero_documento || '').toLowerCase();
      const numeroSolicitud = (item.numero_solicitud || '').toLowerCase();
      return nombre.includes(term) || documento.includes(term) || numeroSolicitud.includes(term);
    });
  }

  // SCRUM-151 (comentarios 2026-07-23): Crédito Constructor corre su propio
  // expediente inicial + Informe Técnico antes de continuar por el resto del
  // stepper de Ordinario sin cambios. Los 3 estados de Informe Técnico
  // (ingeniero/coordinador/finalizado, ver InformeTecnicoController) se
  // consolidan en un solo paso visual, igual que ya hace el checklist de
  // "Expediente de Documentos" (informeTecnicoStatusLabel).
  get bpmnStepsConstructor() {
    return [
      { key: 'validacion_documental_constructor', label: 'Revisión Solicitud', role: 'coordinador_comercial', roleLabel: 'Coordinador Comercial', desc: 'Revisar el expediente inicial del cliente y verificar que los soportes estén completos.' },
      { key: 'completar_solicitud_constructor', label: 'Completar Sop.', role: 'cliente', roleLabel: 'Cliente', desc: 'Completar la documentación faltante solicitada por el Coordinador Comercial.' },
      { key: 'informe_tecnico', label: 'Informe Técnico', role: 'ingeniero', roleLabel: 'Ingeniero / Coordinador Comercial', desc: 'Elaboración y registro del Informe Técnico del proyecto.', altKeys: ['informe_tecnico_ingeniero', 'informe_tecnico_coordinador', 'informe_tecnico_finalizado'] },
      ...this.bpmnSteps.slice(2)
    ];
  }

  get displaySteps() {
    return this.isCreditoConstructor ? this.bpmnStepsConstructor : this.bpmnSteps;
  }

  // SCRUM-176 (UX, re-investigación 2026-08-10): la tarjeta de cada fila en
  // el listado solo distinguía 3 estados genéricos (Completado / Rechazado
  // / En Proceso) — con varias solicitudes del mismo cliente en distintas
  // etapas reales del BPMN, todas se veían idénticas en la lista. Variante
  // de `getStepClass`/`displaySteps` que no depende de `selectedCredito`,
  // para poder etiquetar cada fila con su paso real sin tener el crédito
  // abierto.
  stepLabelFor(item: any): string {
    if (item.estado === 'completado') return 'Completado';
    if (item.estado === 'rechazado') return 'Negado'; // SCRUM-257: solo la etiqueta visible, la clave interna 'rechazado' se mantiene (reusada en otros dominios)
    const esConstructor = (item.solicitud_credito?.tipo_credito?.codigo || '').toUpperCase() === 'CONSTRUCTOR';
    const pasos = esConstructor ? this.bpmnStepsConstructor : this.bpmnSteps;
    const paso = pasos.find(s => s.key === item.estado || (s as any).altKeys?.includes(item.estado));
    return paso?.label || 'En Proceso';
  }

  // Get index of a state in the BPMN workflow
  getStateIndex(state: string): number {
    if (state === 'completado') return 99;
    if (state === 'rechazado') return -1;
    return this.displaySteps.findIndex(step => step.key === state || (step as any).altKeys?.includes(state));
  }

  getProgressPercent(): number {
    if (!this.selectedCredito) return 0;
    const currentStatus = this.selectedCredito.estado;
    if (currentStatus === 'completado') return 100;
    if (currentStatus === 'rechazado') return 0;
    const idx = this.getStateIndex(currentStatus);
    if (idx < 0) return 0;
    return Math.round(((idx + 1) / this.displaySteps.length) * 100);
  }

  // Determine class for a stepper node
  getStepClass(stepKey: string): string {
    if (!this.selectedCredito) return '';
    const currentStatus = this.selectedCredito.estado;
    if (currentStatus === 'completado') return 'completed';
    if (currentStatus === 'rechazado') return 'disabled';

    const currentIndex = this.getStateIndex(currentStatus);
    const stepIndex = this.displaySteps.findIndex(s => s.key === stepKey);

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

    // SCRUM-151: expediente inicial de Constructor, revisado por Coordinador
    // Comercial igual que revision_documental en Ordinario (no está en
    // bpmnSteps porque Constructor no sigue el stepper de 11 pasos completo).
    if (currentStatus === 'validacion_documental_constructor') {
      return ['coordinador_comercial', 'cliente'].includes(this.activeRole);
    }

    if (currentStatus === 'completar_solicitud_constructor') {
      return this.activeRole === 'cliente';
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

  // SCRUM-151: el crédito es "Constructor" según el tipo de crédito de su
  // Solicitud de Crédito asociada. Créditos legacy sin solicitud asociada se
  // tratan como Ordinario (no tienen el sub-flujo de Informe Técnico).
  get isCreditoConstructor(): boolean {
    return (this.selectedCredito?.solicitud_credito?.tipo_credito?.codigo || '').toUpperCase() === 'CONSTRUCTOR';
  }

  // SCRUM-151: Crédito Constructor inserta la etapa de Informe Técnico antes
  // de SARLAFT, corriendo en 1 la numeración de las etapas siguientes en el
  // checklist "Expediente de Documentos de Crédito".
  get etapaOffset(): number {
    return this.isCreditoConstructor ? 1 : 0;
  }

  get informeTecnicoStatusLabel(): string {
    if (this.selectedCredito?.informe_tecnico?.estado === 'registrado') return 'Completado';
    // SCRUM-256: 'validacion_documental_constructor' y 'completar_solicitud_constructor'
    // son ANTERIORES a la aprobación de documentos del Coordinador Comercial —
    // la transición real a Informe Técnico (informe_tecnico_ingeniero) solo
    // ocurre al aprobar (CreditoOrdinarioController::transition()). Incluirlos
    // acá hacía que "En Proceso" saliera antes de que el Coordinador aprobara
    // nada.
    const estadosEnProceso = ['informe_tecnico_ingeniero', 'informe_tecnico_coordinador', 'informe_tecnico_finalizado'];
    if (this.selectedCredito?.informe_tecnico || estadosEnProceso.includes(this.selectedCredito?.estado)) return 'En Proceso';
    return 'Pendiente';
  }

  // SCRUM-164: la "Síntesis SARLAFT" de la Etapa de Análisis SARLAFT y
  // Financiero se quedaba en "Pendiente" para siempre porque chequeaba
  // documentos.sarlft_sintesis, un campo legacy que el módulo dedicado
  // (ListasRestrictivasSarlaftController, SCRUM-128) nunca llena — el
  // resultado real vive en la columna sarlaft_concepto del crédito.
  get sarlaftEstadoLabel(): string {
    const concepto = this.selectedCredito?.sarlaft_concepto;
    if (concepto === 'favorable') return 'Favorable';
    if (concepto === 'desfavorable') return 'Desfavorable';
    return 'Pendiente';
  }

  // SCRUM-151: algunos estados del flujo no tienen panel de acción en esta
  // pantalla porque se gestionan en un módulo dedicado (Informe Técnico,
  // Listas Restrictivas y SARLAFT) o avanzan automáticamente al completar la
  // aprobación de documentos en otra pantalla. Mostrar "Acceso Restringido"
  // ahí es engañoso — no es un problema de permisos de rol.
  get managedElsewhereInfo(): { title: string; message: string; link?: string; linkLabel?: string; roles?: string[] } | null {
    if (!this.selectedCredito) return null;
    const creditoId = this.selectedCredito.id;

    switch (this.selectedCredito.estado) {
      case 'validacion_documental_constructor':
        // SCRUM-151: este panel solo lo ve un rol distinto de Coordinador
        // Comercial/Cliente (isUserRoleAuthorized ya los autoriza a ellos con
        // acciones propias). El paso avanza cuando Coordinador Comercial
        // aprueba el expediente en esta pantalla; Operaciones conserva
        // /validation como vía alterna (avanza igual si aprueba ahí primero).
        return {
          title: 'Validación documental en curso',
          message: 'El expediente inicial de este crédito Constructor está siendo revisado por el Coordinador Comercial. Alternativamente, este paso también avanza si Operaciones valida y aprueba todos los soportes desde el módulo de Validación de Documentos.',
          link: '/validation',
          linkLabel: 'Ir a Validación de Documentos',
          roles: ['operativo', 'gerente']
        };
      case 'informe_tecnico_ingeniero':
        return {
          title: 'Informe Técnico en elaboración',
          message: 'El Ingeniero está elaborando el Informe Técnico del proyecto. El flujo continuará automáticamente cuando lo envíe a revisión del Coordinador Comercial.',
          link: `/informes-tecnicos/${creditoId}`,
          linkLabel: 'Ver Informe Técnico',
          roles: ['ingeniero', 'coordinador_comercial']
        };
      case 'informe_tecnico_coordinador':
        return {
          title: 'Informe Técnico en revisión',
          message: 'El Informe Técnico está pendiente de revisión y registro por el Coordinador Comercial. El flujo continuará automáticamente al finalizar esa revisión.',
          link: `/informes-tecnicos/${creditoId}`,
          linkLabel: 'Ver Informe Técnico',
          roles: ['ingeniero', 'coordinador_comercial']
        };
      case 'informe_tecnico_finalizado':
        return {
          title: 'Informe Técnico finalizado',
          message: 'El Informe Técnico fue registrado. El crédito continuará automáticamente a la etapa de Listas Restrictivas y SARLAFT.'
        };
      case 'sarlaft_control_interno':
        return {
          title: 'Listas Restrictivas y SARLAFT en curso',
          message: 'La validación de Listas Restrictivas y el concepto SARLAFT se gestionan desde el módulo dedicado.',
          link: `/listas-sarlaft/${creditoId}`,
          linkLabel: 'Ir a Listas Restrictivas y SARLAFT',
          roles: ['oficial_cumplimiento']
        };
      default:
        return null;
    }
  }

  // SCRUM-146: los documentos de Etapa 1 se derivan de la Solicitud de
  // Documentos (DocumentRequestItem) creada a partir del preset elegido al
  // registrar la SolicitudCredito. Si el crédito no tiene preset asociado
  // (créditos legacy anteriores a SCRUM-120/146), se mantiene la lista fija
  // original de 4 documentos.
  get etapa1Docs(): { key: string; nombre: string; descripcion: string; upload?: any; estado?: string }[] {
    const items = this.selectedCredito?.solicitud_credito?.document_request?.items;
    if (items && items.length > 0) {
      return items.map((item: any) => ({
        key: 'req_item_' + item.id,
        nombre: item.requirement?.nombre || 'Documento requerido',
        descripcion: item.requirement?.descripcion || '',
        upload: item.upload || null,
        estado: item.estado || null
      }));
    }
    return [
      { key: 'formulario_solicitud', nombre: 'Formulario de Solicitud', descripcion: 'Formulario de solicitud del cliente diligenciado.' },
      { key: 'documentos_identidad', nombre: 'Documentos de Identidad', descripcion: 'Copia de Cédula de Ciudadanía o NIT del cliente.' },
      { key: 'estados_financieros', nombre: 'Estados Financieros', descripcion: 'Balance y Estados Financieros firmados por contador.' },
      { key: 'certificados_laborales', nombre: 'Certificados Laborales / Comerciales', descripcion: 'Soporte de ingresos o referencias de la empresa.' }
    ];
  }

  // SCRUM-292: 'aprobada_garantias' se fija apenas el Comité aprueba
  // (antes de cualquier gestión) y solo pasa a solicitud_gestionada = true
  // cuando el Coordinador Comercial completa GestionCreditoController::
  // notificar() con el preset elegido. En esa ventana intermedia no existe
  // todavía ningún DocumentRequest real de garantías — la carga del
  // cliente debe quedar deshabilitada.
  get etapa4PendienteGestion(): boolean {
    return this.selectedCredito?.estado === 'aprobada_garantias' && !this.selectedCredito?.solicitud_gestionada;
  }

  // SCRUM-229: los documentos de Etapa 4 (Formalización de Garantías) se
  // derivan del DocumentRequest de garantías (preset elegido por el
  // Coordinador Comercial al gestionar 'aprobada_garantias', tageado
  // 'etapa' = 'garantias' — ver GestionCreditoController::
  // crearSolicitudDocumentos()). Si el crédito es legacy (estado
  // 'formalizacion_garantias', pre-SCRUM-193/205, que nunca tuvo ese
  // DocumentRequest) se mantiene la lista fija original de 4 documentos.
  // SCRUM-292: si en cambio todavía está 'aprobada_garantias' SIN
  // gestionar, no se muestra ningún documento — la lista fija no
  // corresponde a ningún preset real todavía elegido.
  get etapa4Docs(): { key: string; nombre: string; descripcion: string; upload?: any }[] {
    const items = this.selectedCredito?.solicitud_credito?.garantias_document_request?.items;
    if (items && items.length > 0) {
      return items.map((item: any) => ({
        key: 'req_item_' + item.id,
        nombre: item.requirement?.nombre || 'Documento requerido',
        descripcion: item.requirement?.descripcion || '',
        upload: item.upload || null
      }));
    }
    if (this.etapa4PendienteGestion) {
      return [];
    }
    return [
      { key: 'pagare_borrador', nombre: 'Pagaré (Borrador)', descripcion: 'Plantilla de Pagaré para firma. Responsable de carga: Comercial.' },
      { key: 'carta_instrucciones_borrador', nombre: 'Carta de Instrucciones (Borrador)', descripcion: 'Plantilla de Carta de Instrucciones para firma. Responsable de carga: Comercial.' },
      { key: 'contrato_borrador', nombre: 'Contrato (Borrador)', descripcion: 'Plantilla de Contrato para firma. Responsable de carga: Comercial.' },
      { key: 'garantias_firmadas', nombre: 'Pagarés y Garantías Firmadas', descripcion: 'Documentos jurídicos formalizados por el cliente. Validación: Dirección Administrativa.' }
    ];
  }

  get etapa4PresetNombre(): string | null {
    return this.selectedCredito?.solicitud_credito?.garantias_document_request?.preset_nombre || null;
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
  // archivos por documento (input con atributo "multiple"). Este mismo
  // método también sirve la grilla de Etapa 4 (garantías, ver template),
  // que se queda en "solo PDF".
  //
  // SCRUM-328: el cliente en Etapa 1 (completar_solicitud/
  // completar_solicitud_constructor) además de PDF puede cargar Word/Excel
  // — mismo criterio que valida CreditoOrdinarioController::transition().
  private static readonly MIMES_OFFICE = [
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ];

  onMultiFileUpload(event: Event, campoDoc: string) {
    const target = event.target as HTMLInputElement;
    const files = target.files ? Array.from(target.files) : [];
    if (!files.length) return;

    const esEtapa1Cliente = this.activeRole === 'cliente'
      && ['completar_solicitud', 'completar_solicitud_constructor'].includes(this.selectedCredito?.estado);
    const mimesPermitidos = esEtapa1Cliente
      ? ['application/pdf', ...CreditoOrdinarioComponent.MIMES_OFFICE]
      : ['application/pdf'];

    const invalido = files.find(f => !mimesPermitidos.includes(f.type));
    if (invalido) {
      const mensaje = esEtapa1Cliente
        ? 'Solo se permite subir archivos en formato PDF, Word o Excel.'
        : 'Solo se permite subir archivos en formato PDF.';
      Swal.fire('Formato Inválido', mensaje, 'warning');
      return;
    }

    this.executeTransition('subir_archivo', `Carga de soporte(s) en campo: ${campoDoc}`, {
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

  // Total de archivos disponibles para un documento de preset: los legacy
  // guardados en credito.documentos[key] MÁS el ClientUpload real cuando el
  // cliente lo cargó desde la pantalla de "Solicitud de Documentos"
  // (Gestión de Créditos), que nunca escribe en credito.documentos (SCRUM-229).
  //
  // SCRUM-256: ese +1 asumía que credito.documentos[key] y doc.upload eran
  // siempre orígenes mutuamente excluyentes para la misma clave. Dejó de
  // serlo cuando CreditoOrdinarioController::transition() (SCRUM-146)
  // empezó a escribir en AMBOS al subir desde esta misma pantalla — el
  // mismo archivo quedaba contado (y mostrado, ver getDocFiles/doc.upload en
  // el template) dos veces. Si el arreglo ya tiene algo para esta clave, el
  // upload vino de acá mismo y doc.upload es ese mismo archivo — no se suma
  // aparte. doc.upload solo cuenta solo cuando el arreglo está vacío, es
  // decir, cuando la carga vino exclusivamente de otra pantalla.
  docFileCount(doc: { key: string; upload?: any }): number {
    const enArreglo = this.getDocFiles(doc.key).length;
    return enArreglo > 0 ? enArreglo : (doc.upload ? 1 : 0);
  }

  // SCRUM-256: el botón "Subir" debe desaparecer una vez el documento ya
  // está cargado — antes quedaba visible siempre y cada carga nueva se
  // apilaba sobre la anterior en credito.documentos[key], produciendo
  // entradas duplicadas. Excepción: si el ítem fue marcado 'rechazado' por
  // el Coordinador (corrección solicitada), debe poder volver a cargarse —
  // mismo criterio que ya usa etapa1KeySatisfecha() en el backend.
  puedeSubirDocumento(doc: { key: string; upload?: any; estado?: string }): boolean {
    if (doc.estado === 'rechazado') return true;
    return this.docFileCount(doc) === 0;
  }

  // Descarga/abre un ClientUpload subido vía "Solicitud de Documentos"
  // (mismo patrón que gestion-creditos-formalizacion-garantias.component.ts).
  verArchivoSubido(uploadId: number, originalName: string): void {
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

  // SCRUM-257: `esNegarSolicitud` solo lo pasan los 2 botones de Etapa 1
  // (revision_documental / validacion_documental_constructor) — el resto de
  // los "rechazar" del BPMN (Comité, Garantías, CYF, Desembolso) no cambian
  // de wording, la clave interna `accion` sigue siendo 'rechazar' en todos.
  executeTransition(accion: string, comentarioDefecto: string = '', extraData: any = {}, esNegarSolicitud: boolean = false) {
    Swal.fire({
      title: accion === 'rechazar' ? (esNegarSolicitud ? '¿Negar Solicitud?' : '¿Rechazar Solicitud?') : 'Confirmar Acción',
      text: accion === 'rechazar' ? (esNegarSolicitud ? 'Por favor ingresa el motivo de la negación:' : 'Por favor ingresa el motivo del rechazo:') : 'Ingresa un comentario de auditoría para este paso (Opcional):',
      input: 'text',
      inputValue: comentarioDefecto,
      inputPlaceholder: 'Escribe un comentario...',
      icon: accion === 'rechazar' ? 'warning' : 'question',
      showCancelButton: true,
      confirmButtonText: accion === 'rechazar' ? (esNegarSolicitud ? 'Sí, negar' : 'Sí, rechazar') : 'Confirmar y Avanzar',
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

}
