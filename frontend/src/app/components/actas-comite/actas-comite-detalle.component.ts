import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { QuillModule } from 'ngx-quill';
import { Subject, Subscription } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { CreditoElegibleAutocompleteComponent } from '../shared/credito-elegible-autocomplete/credito-elegible-autocomplete.component';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import Swal from 'sweetalert2';

type Tab = 'orden_dia' | 'desarrollo' | 'decision' | 'resumen' | 'observaciones' | 'firmantes';

interface OrdenDiaItem {
  id: number;
  texto: string;
  orden: number;
}

/**
 * SCRUM-169 — wizard de elaboración del Acta de Comité. El autoguardado NUNCA
 * reemplaza el estado editable local (lugar, asistentes, orden_dia,
 * desarrollo, observaciones, firmantes) con lo que devuelve el backend tras
 * guardar — solo refresca campos derivados (estado, updated_at). Reemplazar
 * el estado editable local desde una respuesta de guardado introduce una
 * condición de carrera real (el usuario sigue escribiendo mientras la
 * respuesta del guardado anterior vuelve) — mismo bug ya encontrado en
 * Análisis Financiero (SCRUM-155, ver memoria de proyecto). Por el mismo
 * motivo, 'desarrollo' se hidrata siempre como objeto nunca como array
 * (PHP no distingue '{}' de '[]' — json_decode de un mapa vacío puede
 * volver como array vacío).
 */
@Component({
  selector: 'app-actas-comite-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, QuillModule, CreditoElegibleAutocompleteComponent, MilesSeparatorDirective],
  templateUrl: './actas-comite-detalle.component.html',
  styleUrls: ['./actas-comite-detalle.component.css']
})
export class ActasComiteDetalleComponent implements OnInit, OnDestroy {
  actaId!: number;
  acta: any = null;
  activeTab: Tab = 'orden_dia';
  activeRole = '';
  loading = false;
  guardando = false;
  registrando = false;

  fechaReunion = '';
  lugar = '';
  horaInicio = '';
  horaFinalizacion = '';
  asistentes: { nombre: string }[] = [];
  ordenDia: OrdenDiaItem[] = [];
  ordenDiaAprobado = false;
  desarrollo: Record<string, string> = {};
  observacionesGenerales = '';
  firmantes: { nombre: string; rol: string }[] = [];

  nuevoAsistente = '';
  nuevoItemOrdenDia = '';
  amortizaciones: any[] = [];

  private modulesCache: Record<string, any> = {};
  private editorRefs: Record<string, any> = {};
  private save$ = new Subject<void>();
  private saveSub?: Subscription;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    public authService: AuthService,
  ) {}

  ngOnInit(): void {
    this.actaId = Number(this.route.snapshot.paramMap.get('actaId'));
    this.activeRole = this.authService.getActiveRole() || '';
    this.saveSub = this.save$.pipe(debounceTime(800)).subscribe(() => this.guardar());
    this.cargar();
    this.cargarCatalogos();
  }

  // SCRUM-189 (2026-08-12, punto 4): catálogo real para el dropdown de
  // Amortización en Decisión — mismo endpoint que usa Solicitudes de Crédito.
  private cargarCatalogos(): void {
    this.http.get<any[]>(`${environment.apiUrl}/parameters/amortizaciones`).subscribe({
      next: data => this.amortizaciones = data,
      error: () => {}
    });
  }

  ngOnDestroy(): void {
    this.saveSub?.unsubscribe();
  }

  private headers() {
    return { headers: { 'X-Active-Role': this.activeRole } };
  }

  cargar(): void {
    this.loading = true;
    this.http.get<any>(`${environment.apiUrl}/actas-comite/${this.actaId}`, this.headers()).subscribe({
      next: (acta) => {
        this.hidratar(acta);
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        Swal.fire('Error', 'No se pudo cargar el acta.', 'error').then(() => this.router.navigate(['/actas-comite']));
      }
    });
  }

  private hidratar(acta: any): void {
    this.acta = acta;
    this.fechaReunion = acta.fecha_reunion ? String(acta.fecha_reunion).substring(0, 10) : '';
    this.lugar = acta.lugar || '';
    this.horaInicio = acta.hora_inicio || '';
    this.horaFinalizacion = acta.hora_finalizacion || '';
    this.asistentes = Array.isArray(acta.asistentes) ? acta.asistentes : [];
    this.ordenDia = Array.isArray(acta.orden_dia) ? acta.orden_dia : [];
    this.ordenDiaAprobado = !!acta.orden_dia_aprobado;
    // 'desarrollo' es un mapa {orden_dia_id: html} — nunca tratarlo como array
    // aunque json_decode de un '{}' vacío vuelva como '[]' (ver docstring de la clase).
    this.desarrollo = Array.isArray(acta.desarrollo) ? {} : (acta.desarrollo || {});
    this.observacionesGenerales = acta.observaciones_generales || '';
    this.firmantes = Array.isArray(acta.firmantes) ? acta.firmantes : [];
    (this.acta.solicitudes || []).forEach((s: any) => this.prellenarMontoDecision(s));
  }

  // SCRUM-189 (2026-08-12, punto 4): "Decisión" en Resumen no traía el
  // monto del crédito — se prellena editable, nunca pisa un valor que el
  // usuario ya haya guardado.
  private prellenarMontoDecision(solicitud: any): void {
    if (solicitud.monto_decision === null || solicitud.monto_decision === undefined) {
      solicitud.monto_decision = solicitud.monto;
    }
  }

  // SCRUM-189 (2026-08-12, punto 1): el autoguardado (PUT) ahora puede
  // traer solicitudes nuevas incorporadas por sincronizarSolicitudesElegibles()
  // en el backend — se agregan sin tocar las que el usuario ya tiene en
  // pantalla (mismo criterio de "nunca reemplazar estado editable local").
  private mezclarSolicitudesNuevas(solicitudesServidor: any[] | undefined): void {
    if (!solicitudesServidor || !this.acta?.solicitudes) return;
    const idsActuales = new Set(this.acta.solicitudes.map((s: any) => s.id));
    const nuevas = solicitudesServidor.filter((s: any) => !idsActuales.has(s.id));
    if (nuevas.length) {
      nuevas.forEach((s: any) => this.prellenarMontoDecision(s));
      this.acta.solicitudes.push(...nuevas);
    }
  }

  get soloLectura(): boolean {
    return this.acta?.estado === 'aprobada';
  }

  cambiarTab(tab: Tab): void {
    this.activeTab = tab;
  }

  onFieldChange(): void {
    if (this.soloLectura) return;
    this.save$.next();
  }

  onFieldChangeInmediato(): void {
    if (this.soloLectura) return;
    this.guardar();
  }

  private guardar(): void {
    if (this.soloLectura) return;
    this.guardando = true;
    const payload = {
      fecha_reunion: this.fechaReunion || null,
      lugar: this.lugar,
      hora_inicio: this.horaInicio || null,
      hora_finalizacion: this.horaFinalizacion || null,
      asistentes: this.asistentes,
      orden_dia: this.ordenDia,
      desarrollo: this.desarrollo,
      observaciones_generales: this.observacionesGenerales,
      firmantes: this.firmantes,
    };

    this.http.put<any>(`${environment.apiUrl}/actas-comite/${this.actaId}`, payload, this.headers()).subscribe({
      next: (resp) => {
        this.guardando = false;
        // Nunca reasignar this.lugar/asistentes/ordenDia/desarrollo/etc. acá —
        // solo lo que el usuario no está editando en pantalla. Las solicitudes
        // son la excepción: solo se agregan las nuevas (auto-sync), nunca se
        // pisan/reordenan las existentes.
        if (this.acta) {
          this.acta.estado = resp.estado;
          this.acta.updated_at = resp.updated_at;
          this.mezclarSolicitudesNuevas(resp.solicitudes);
        }
      },
      error: (err) => {
        this.guardando = false;
        Swal.fire('No se pudo guardar', err?.error?.message || 'Intente nuevamente.', 'error');
      }
    });
  }

  // --- Asistentes ---
  agregarAsistente(): void {
    const nombre = this.nuevoAsistente.trim();
    if (!nombre) return;
    this.asistentes.push({ nombre });
    this.nuevoAsistente = '';
    this.onFieldChangeInmediato();
  }

  eliminarAsistente(i: number): void {
    this.asistentes.splice(i, 1);
    this.onFieldChangeInmediato();
  }

  // --- Orden del día ---
  agregarItemOrdenDia(): void {
    const texto = this.nuevoItemOrdenDia.trim();
    if (!texto) return;
    const maxId = this.ordenDia.reduce((m, i) => Math.max(m, i.id), 0);
    this.ordenDia.push({ id: maxId + 1, texto, orden: this.ordenDia.length + 1 });
    this.nuevoItemOrdenDia = '';
    this.onFieldChangeInmediato();
  }

  eliminarItemOrdenDia(i: number): void {
    delete this.desarrollo[this.ordenDia[i].id];
    this.ordenDia.splice(i, 1);
    this.onFieldChangeInmediato();
  }

  aprobarOrdenDia(): void {
    this.http.post<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/aprobar-orden-dia`, {}, this.headers()).subscribe({
      next: (resp) => {
        this.ordenDiaAprobado = true;
        if (this.acta) this.acta.estado = resp.estado;
        Swal.fire('Orden del día aprobado', '', 'success');
      },
      error: (err) => Swal.fire('No se pudo aprobar', err?.error?.message || 'Complete el orden del día antes de aprobarlo.', 'warning')
    });
  }

  // --- Solicitudes ---
  // SCRUM-198: el alta manual solo enlaza un crédito ya existente y
  // elegible para comité (buscarCreditosElegibles) — ya no se puede
  // tipear un crédito desde cero.
  onCreditoElegibleSeleccionado(credito: any): void {
    this.http.post<any>(
      `${environment.apiUrl}/actas-comite/${this.actaId}/solicitudes`,
      { credito_ordinario_id: credito.id },
      this.headers()
    ).subscribe({
      next: (solicitud) => {
        this.prellenarMontoDecision(solicitud);
        this.acta.solicitudes.push(solicitud);
      },
      error: (err) => Swal.fire('Error', err?.error?.message || 'No se pudo agregar la solicitud.', 'error')
    });
  }

  eliminarSolicitud(solicitud: any): void {
    Swal.fire({
      title: '¿Eliminar solicitud?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Eliminar',
      cancelButtonText: 'Cancelar',
    }).then((r) => {
      if (!r.isConfirmed) return;
      this.http.delete(`${environment.apiUrl}/actas-comite/${this.actaId}/solicitudes/${solicitud.id}`, this.headers()).subscribe({
        next: () => {
          this.acta.solicitudes = this.acta.solicitudes.filter((s: any) => s.id !== solicitud.id);
        },
        error: (err) => Swal.fire('Error', err?.error?.message || 'No se pudo eliminar la solicitud.', 'error')
      });
    });
  }

  guardarSolicitud(solicitud: any): void {
    const { id, acta_comite_id, credito_ordinario_id, origen, created_at, updated_at, ...datos } = solicitud;
    this.http.put<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/solicitudes/${solicitud.id}`, datos, this.headers()).subscribe({
      error: (err) => Swal.fire('Error', err?.error?.message || 'No se pudo guardar la solicitud.', 'error')
    });
  }

  // SCRUM-183: la Presentación para el Comité se adjunta acá, por
  // solicitud — ya no bloquea ninguna transición del crédito (antes había
  // que cargarla para pasar de Análisis Financiero a Comité, ese paso se
  // retiró del BPMN).
  subirPresentacion(solicitud: any, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('archivo', file);
    this.http.post<any>(
      `${environment.apiUrl}/actas-comite/${this.actaId}/solicitudes/${solicitud.id}/presentacion`,
      formData,
      this.headers()
    ).subscribe({
      next: (resp) => {
        solicitud.presentacion_comite = resp.presentacion_comite;
      },
      error: (err) => Swal.fire('Error', err?.error?.message || 'El archivo debe ser un PDF de hasta 20 MB.', 'error')
    });
    input.value = '';
  }

  // --- Firmantes ---
  agregarFirmanteDesdeAsistentes(nombre: string): void {
    if (this.firmantes.some(f => f.nombre === nombre)) return;
    this.firmantes.push({ nombre, rol: 'Miembro del Comité' });
    this.onFieldChangeInmediato();
  }

  eliminarFirmante(i: number): void {
    this.firmantes.splice(i, 1);
    this.onFieldChangeInmediato();
  }

  // --- Resumen ---
  totalPorEstado(estado: string): number {
    return (this.acta?.solicitudes || [])
      .filter((s: any) => (s.estado_decision || 'pendiente') === estado)
      .reduce((sum: number, s: any) => sum + Number(s.monto_decision || 0), 0);
  }

  // --- Editor de texto enriquecido (ngx-quill) ---
  // SCRUM-189 (2026-08-13, "las imágenes no se ven en el PDF" — 3ª vuelta):
  // el fix anterior (resolverImagenesParaPdf en el backend) es correcto para
  // imágenes subidas con el botón de la barra de herramientas (quedan en
  // /storage/... y DomPDF las resuelve por ruta local). La causa raíz real
  // de que Juan siguiera viendo el ícono de imagen rota, incluso con acta e
  // imagen nuevas, es que Quill tiene DOS rutas más para insertar una imagen
  // que nunca pasan por nuestro endpoint de subida:
  //   1. Pegar (Ctrl+V) una imagen copiada de otra fuente (una página web,
  //      otra app) cuyo portapapeles trae HTML con un <img src="https://...">
  //      externo en vez de los bytes del archivo — Quill inserta ese <img>
  //      TAL CUAL (ver quill/modules/clipboard.js, onCapturePaste: el
  //      atajo a quill.uploader.upload() sólo dispara si el HTML pegado es
  //      un <img> suelto Y además hay bytes de archivo en el portapapeles;
  //      cualquier otro caso cae a onPaste() y preserva el src original).
  //      Esa URL externa nunca contiene "/storage/", así que
  //      resolverImagenesParaPdf() la deja intacta, y DomPDF (enable_remote
  //      = false a propósito, ver ActaComiteController::resolverImagenesParaPdf)
  //      no puede traerla — sale el ícono roto, en la previsualización y en
  //      el PDF final, aunque en el navegador se vea bien (el navegador sí
  //      puede cargar la URL externa directamente).
  //   2. Pegar o soltar (drag&drop) un archivo de imagen real: el módulo
  //      Uploader por defecto de Quill NO usa nuestro endpoint — lee el
  //      archivo con FileReader y lo inserta como data:image/...;base64,...
  //      (ver quill/modules/uploader.js, Uploader.DEFAULTS.handler). Esto sí
  //      se ve bien en el PDF (DomPDF soporta data: URIs sin necesitar
  //      enable_remote), pero es inconsistente con el resto de imágenes del
  //      acta (nunca queda un archivo real en disco).
  // Fix: el módulo 'uploader' de Quill se reconfigura para que CUALQUIER
  // archivo con bytes reales (pegado o soltado) pase por el mismo endpoint
  // de subida que ya usa el botón de la barra de herramientas — un solo
  // camino, ya probado, que sí resuelve bien en el PDF. Y un matcher de
  // clipboard bloquea explícitamente cualquier <img> pegado que NO sea de
  // nuestro disco 'public' (no hay bytes que subir en ese caso — no hay
  // forma segura de "arreglarlo" sin salir a red desde el backend, que es
  // justo la superficie que resolverImagenesParaPdf evitó a propósito).
  onEditorCreated(key: string, editor: any): void {
    this.editorRefs[key] = editor;
    editor.clipboard.addMatcher('IMG', (node: HTMLImageElement, delta: any) => {
      const src = node.getAttribute('src') || '';
      const esPropia = src.includes('/storage/') || src.startsWith('data:');
      if (!esPropia) {
        Swal.fire(
          'Imagen no admitida',
          'No se pueden pegar imágenes copiadas de otra fuente (una página web, otro documento). Usá el botón de imagen de la barra de herramientas para adjuntarla.',
          'warning'
        );
        delta.ops = [];
      }
      return delta;
    });
  }

  quillModules(key: string): any {
    if (!this.modulesCache[key]) {
      this.modulesCache[key] = {
        toolbar: {
          container: [
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['image'],
            ['clean'],
          ],
          handlers: { image: () => this.insertarImagen(key) },
        },
        uploader: {
          mimetypes: ['image/png', 'image/jpeg', 'image/webp'],
          handler: (range: any, files: File[]) => {
            files.forEach((file) => this.subirImagenYEmbeder(file, key, range));
          },
        },
      };
    }
    return this.modulesCache[key];
  }

  private insertarImagen(key: string): void {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp';
    input.onchange = () => {
      const file = input.files?.[0];
      if (!file) return;
      const editor = this.editorRefs[key];
      const range = editor?.getSelection(true) || { index: editor?.getLength() ?? 0 };
      this.subirImagenYEmbeder(file, key, range);
    };
    input.click();
  }

  private subirImagenYEmbeder(file: File, key: string, range: { index: number }): void {
    const formData = new FormData();
    formData.append('imagen', file);
    this.http.post<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/imagenes`, formData, this.headers()).subscribe({
      next: (resp) => {
        const editor = this.editorRefs[key];
        if (!editor) return;
        editor.insertEmbed(range.index, 'image', resp.url, 'user');
        editor.setSelection(range.index + 1, 0);
      },
      error: () => Swal.fire('Imagen no admitida', 'El archivo seleccionado no cumple el formato o tamaño permitido.', 'warning')
    });
  }

  // --- Previsualizar / descargar / registrar ---
  // SCRUM-189 (2026-08-12, punto 5): antes era un <a href> directo a la API.
  // Un <a> no pasa por el interceptor de auth (no hay Authorization en la
  // request) y el X-Active-Role iba como query param — el backend solo lee
  // headers — así que Auth::user() llegaba null y ResolvesActiveRole
  // reventaba con 500 al leer $user->roles. Mismo patrón que descargar():
  // pedir el PDF por HttpClient (con headers()) y abrirlo como blob.
  previsualizar(): void {
    const nuevaVentana = window.open('', '_blank');
    this.http.get(`${environment.apiUrl}/actas-comite/${this.actaId}/previsualizar`, { ...this.headers(), responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        if (nuevaVentana) {
          nuevaVentana.location.href = url;
        }
      },
      error: () => {
        nuevaVentana?.close();
        Swal.fire('Error', 'No fue posible generar la previsualización. Intente nuevamente o contacte al administrador.', 'error');
      }
    });
  }

  descargar(): void {
    this.http.get(`${environment.apiUrl}/actas-comite/${this.actaId}/descargar`, { ...this.headers(), responseType: 'blob' }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `acta-comite-${this.acta.numero}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => Swal.fire('Error', 'No fue posible generar el documento. Intente nuevamente o contacte al administrador.', 'error')
    });
  }

  registrar(): void {
    Swal.fire({
      title: 'Esta acción es irreversible',
      text: 'Una vez registrada, el acta no podrá modificarse. Podrá consultarse y descargarse en PDF.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Registrar acta',
      cancelButtonText: 'Cancelar',
    }).then((r) => {
      if (!r.isConfirmed) return;
      this.registrando = true;
      this.http.post<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/registrar`, {}, this.headers()).subscribe({
        next: (resp) => {
          this.registrando = false;
          this.hidratar(resp.acta);
          Swal.fire('Acta registrada', resp.message, 'success');
        },
        error: (err) => {
          this.registrando = false;
          const faltantes = (err?.error?.campos_faltantes || []).join(', ');
          Swal.fire('Complete la información requerida', faltantes || err?.error?.message, 'warning');
        }
      });
    });
  }
}
