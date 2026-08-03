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
  imports: [CommonModule, FormsModule, RouterModule, QuillModule],
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
  nuevaSolicitud = { cliente_nombre: '', tipo_solicitud: '', monto: null as number | null };

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
        // solo lo que el usuario no está editando en pantalla.
        if (this.acta) {
          this.acta.estado = resp.estado;
          this.acta.updated_at = resp.updated_at;
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
  agregarSolicitudManual(): void {
    if (!this.nuevaSolicitud.cliente_nombre || !this.nuevaSolicitud.tipo_solicitud || !this.nuevaSolicitud.monto) {
      Swal.fire('Datos incompletos', 'Complete los campos obligatorios de la solicitud adicionada.', 'warning');
      return;
    }
    this.http.post<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/solicitudes`, this.nuevaSolicitud, this.headers()).subscribe({
      next: (solicitud) => {
        this.acta.solicitudes.push(solicitud);
        this.nuevaSolicitud = { cliente_nombre: '', tipo_solicitud: '', monto: null };
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
  onEditorCreated(key: string, editor: any): void {
    this.editorRefs[key] = editor;
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
      const formData = new FormData();
      formData.append('imagen', file);
      this.http.post<any>(`${environment.apiUrl}/actas-comite/${this.actaId}/imagenes`, formData, this.headers()).subscribe({
        next: (resp) => {
          const editor = this.editorRefs[key];
          if (!editor) return;
          const range = editor.getSelection(true) || { index: editor.getLength() };
          editor.insertEmbed(range.index, 'image', resp.url, 'user');
          editor.setSelection(range.index + 1, 0);
        },
        error: () => Swal.fire('Imagen no admitida', 'El archivo seleccionado no cumple el formato o tamaño permitido.', 'warning')
      });
    };
    input.click();
  }

  // --- Previsualizar / descargar / registrar ---
  urlPrevisualizar(): string {
    return `${environment.apiUrl}/actas-comite/${this.actaId}/previsualizar?X-Active-Role=${this.activeRole}`;
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
