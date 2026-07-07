import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { SafeUrlPipe } from '../../pipes/safe-url.pipe';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-internal-docs',
  standalone: true,
  imports: [CommonModule, FormsModule, SafeUrlPipe],
  templateUrl: './internal-docs.component.html',
  styleUrls: ['./internal-docs.component.scss']
})
export class InternalDocsComponent implements OnInit {
  documents: any[] = [];
  categories: any[] = [];
  priorities: any[] = [];
  areas: any[] = [];
  loading = false;
  expandedEnvios = new Set<number>();

  // Tabs
  currentTab: 'pendientes' | 'procesados' = 'pendientes';

  // Table Controls
  searchTerm = '';
  filterStartDate = '';
  filterEndDate = '';
  sortColumn = 'created_at';
  sortDirection: 'desc' | 'asc' = 'desc';
  currentPage = 1;
  itemsPerPage = 5;

  // Lightbox State
  showViewer = false;
  selectedEnvio: any = null;
  selectedFile: any = null;
  viewerUrl: string | null = null;
  isImage = false;
  isExcel = false;
  isWord = false;

  currentUser: any = null;

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit() {
    this.currentUser = this.authService.getUser();
    this.loadInitialData();
    this.loadDocuments();
  }

  loadInitialData() {
    this.http.get<any[]>(`${environment.apiUrl}/parameters/accounting_categories`).subscribe({ next: res => this.categories = res, error: () => {} });
    this.http.get<any[]>(`${environment.apiUrl}/parameters/accounting_priorities`).subscribe({ next: res => this.priorities = res, error: () => {} });
    this.http.get<any[]>(`${environment.apiUrl}/document-areas?activo=1`).subscribe({ next: res => this.areas = res, error: () => {} });
  }

  loadDocuments() {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/document-envios`).subscribe({
      next: (res) => {
        this.documents = res;
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error de Conexión', 'No se pudo contactar con el servidor.', 'error');
        console.error(err);
      }
    });
  }

  get filteredDocuments() {
    let filtered = this.documents.filter(envio => {
      // 1. Tab Filter
      let tabMatch = false;
      if (this.currentTab === 'pendientes') {
        tabMatch = envio.estado_general === 'pendiente' || envio.estado_general === 'en_proceso';
      } else {
        tabMatch = envio.estado_general === 'procesado' || envio.estado_general === 'devuelto';
      }

      // 2. Search Filter
      let searchMatch = true;
      if (this.searchTerm) {
        const term = this.searchTerm.toLowerCase();
        searchMatch = (
          (envio.titulo && envio.titulo.toLowerCase().includes(term)) ||
          (envio.sender?.name && envio.sender.name.toLowerCase().includes(term)) ||
          (envio.category?.nombre && envio.category.nombre.toLowerCase().includes(term)) ||
          (envio.observaciones && envio.observaciones.toLowerCase().includes(term))
        );
      }

      // 3. Date Range Filter
      let dateMatch = true;
      if (this.filterStartDate || this.filterEndDate) {
        const docDate = new Date(envio.updated_at || envio.created_at).getTime();

        if (this.filterStartDate) {
          const start = new Date(this.filterStartDate + 'T00:00:00').getTime();
          if (docDate < start) dateMatch = false;
        }

        if (this.filterEndDate) {
          const end = new Date(this.filterEndDate + 'T23:59:59').getTime();
          if (docDate > end) dateMatch = false;
        }
      }

      return tabMatch && searchMatch && dateMatch;
    });

    // Sorting
    filtered.sort((a, b) => {
      let valA = this.getSortValue(a, this.sortColumn);
      let valB = this.getSortValue(b, this.sortColumn);

      if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
      if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }

  getSortValue(envio: any, column: string) {
    switch(column) {
      case 'titulo': return envio.titulo?.toLowerCase() || '';
      case 'remitente': return envio.sender?.name?.toLowerCase() || '';
      case 'categoria': return envio.category?.nombre?.toLowerCase() || '';
      case 'prioridad': return envio.priority?.nombre?.toLowerCase() || '';
      case 'estado': return envio.estado_general?.toLowerCase() || '';
      case 'created_at': default: return new Date(envio.created_at).getTime();
    }
  }

  get pagedDocuments() {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    return this.filteredDocuments.slice(startIndex, startIndex + this.itemsPerPage);
  }

  get totalPages() {
    return Math.ceil(this.filteredDocuments.length / this.itemsPerPage);
  }

  get showingFrom() {
    return this.filteredDocuments.length === 0 ? 0 : (this.currentPage - 1) * this.itemsPerPage + 1;
  }

  get showingTo() {
    return Math.min(this.currentPage * this.itemsPerPage, this.filteredDocuments.length);
  }

  // Ruta de aprobación: "1. Contabilidad → 2. Gerencia"
  rutaLabel(envio: any): string {
    if (!envio.steps || envio.steps.length === 0) return '-';
    return envio.steps.map((s: any) => `${s.orden}. ${s.area?.nombre}`).join(' → ');
  }

  // Área responsable del paso actual
  pasoActualLabel(envio: any): string {
    const step = envio.steps?.find((s: any) => s.orden === envio.current_step_order);
    return step?.area?.nombre || '-';
  }

  // SLA Calculation
  getRemainingTime(envio: any): { text: string, status: 'ok' | 'warning' | 'critical' | 'expired' | 'none' } {
    const enCurso = envio.estado_general === 'pendiente' || envio.estado_general === 'en_proceso';
    if (!envio.priority || !envio.priority.horas_vencimiento || !enCurso) {
      return { text: '-', status: 'none' };
    }

    const createdAt = new Date(envio.created_at).getTime();
    const expiresAt = createdAt + (envio.priority.horas_vencimiento * 60 * 60 * 1000);
    const now = new Date().getTime();
    const remainingMs = expiresAt - now;

    if (remainingMs <= 0) {
      return { text: 'Vencido', status: 'expired' };
    }

    const hours = Math.floor(remainingMs / (1000 * 60 * 60));
    const minutes = Math.floor((remainingMs % (1000 * 60 * 60)) / (1000 * 60));

    // Critical if <= 2 hours remaining
    const status = hours <= 2 ? 'critical' : (hours <= 6 ? 'warning' : 'ok');

    return { text: `${hours}h ${minutes}m`, status };
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
    }
  }

  sortBy(column: string) {
    if (this.sortColumn === column) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortColumn = column;
      this.sortDirection = 'asc';
    }
    this.currentPage = 1;
  }

  setTab(tab: 'pendientes' | 'procesados') {
    this.currentTab = tab;
    this.currentPage = 1;
    this.searchTerm = '';
    this.filterStartDate = '';
    this.filterEndDate = '';
  }

  toggleExpand(envioId: number, event: Event): void {
    event.stopPropagation();
    if (this.expandedEnvios.has(envioId)) {
      this.expandedEnvios.delete(envioId);
    } else {
      this.expandedEnvios.add(envioId);
    }
  }

  isExpanded(envioId: number): boolean {
    return this.expandedEnvios.has(envioId);
  }

  private currentStepOf(envio: any): any {
    return envio.steps?.find((s: any) => s.orden === envio.current_step_order);
  }

  canDeleteEnvio(envio: any): boolean {
    const activeRole = this.authService.getActiveRole();
    if (activeRole === 'superadmin') return true;
    return envio.sender_id === this.currentUser?.id && envio.estado_general === 'pendiente';
  }

  deleteEnvio(envio: any) {
    Swal.fire({
      title: '¿Eliminar envío?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/document-envios/${envio.id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'El envío ha sido eliminado.', 'success');
            this.loadDocuments();
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'No se pudo eliminar el envío.', 'error');
          }
        });
      }
    });
  }

  canProcessEnvio(envio: any): boolean {
    const activeRole = this.authService.getActiveRole();
    const enCurso = envio.estado_general === 'pendiente' || envio.estado_general === 'en_proceso';
    if (!enCurso) return false;

    if (activeRole === 'superadmin') return true;

    const step = this.currentStepOf(envio);
    return !!step && step.estado === 'pendiente' && step.area?.codigo === activeRole;
  }

  procesarPaso(envio: any) {
    const step = this.currentStepOf(envio);
    if (!step) return;

    Swal.fire({
      title: '¿Procesar este paso?',
      text: `Se marcará como procesado el paso "${step.area?.nombre}" del documento "${envio.titulo}".`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, procesar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarAccionPaso(envio, step, 'procesar');
      }
    });
  }

  devolverPaso(envio: any) {
    const step = this.currentStepOf(envio);
    if (!step) return;

    Swal.fire({
      title: 'Devolver documento',
      text: 'Esta acción es un rechazo: el envío no continúa por la ruta y el remitente deberá subir un documento nuevo.',
      input: 'textarea',
      inputPlaceholder: 'Escriba el motivo de la devolución...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Confirmar Devolución',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#d33',
      inputValidator: (value) => {
        if (!value || !value.trim()) {
          return 'Debe registrar una observación para devolver el documento.';
        }
        return null;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        this.ejecutarAccionPaso(envio, step, 'devolver', result.value);
      }
    });
  }

  private ejecutarAccionPaso(envio: any, step: any, accion: 'procesar' | 'devolver', observacion?: string) {
    this.loading = true;
    this.http.patch(`${environment.apiUrl}/document-envios/${envio.id}/steps/${step.id}`, {
      accion,
      observacion
    }).subscribe({
      next: () => {
        this.loading = false;
        Swal.fire('¡Listo!', accion === 'procesar' ? 'El paso fue procesado correctamente.' : 'El documento fue devuelto.', 'success');
        this.loadDocuments();
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo actualizar el paso.', 'error');
      }
    });
  }

  async openUploadModal() {
    const categoryOptions = this.categories.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    const priorityOptions = this.priorities.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');
    let selectedFiles: File[] = [];
    let ruta: any[] = [];
    const availableAreas = this.areas;

    const { value: formValues } = await Swal.fire({
      title: 'Nuevo Envío de Documento',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Título del Documento</label>
            <input id="swal-titulo" class="pro-input" style="width:100%" placeholder="Ej: Reporte Mensual">
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Ruta de aprobación</label>
            <div style="font-size:0.8rem;color:#64748b;margin-bottom:6px;">Seleccione las áreas y defina el orden de revisión del documento.</div>
            <div id="ruta-list-container" style="margin-bottom:8px;"></div>
            <div style="display:flex; gap:8px; align-items:center;">
              <select id="ruta-area-select" class="pro-input" style="flex:1;"></select>
              <button type="button" id="btn-add-area" class="btn-pro secondary sm" style="white-space:nowrap; padding: 6px 12px; height: 36px;">+ Agregar área a la ruta</button>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1rem;">
            <div class="pro-input-group">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Prioridad</label>
              <select id="swal-priority" class="pro-input" style="width:100%">
                ${priorityOptions}
              </select>
            </div>
            <div class="pro-input-group">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Categoría</label>
              <select id="swal-category" class="pro-input" style="width:100%">
                ${categoryOptions}
              </select>
            </div>
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Observaciones</label>
            <textarea id="swal-mensaje" class="pro-input" style="width:100%; height: 80px;"></textarea>
          </div>

          <div class="pro-input-group">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Archivos seleccionados</label>
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
              <button type="button" id="btn-add-files" class="btn-pro secondary sm" style="display:inline-flex; align-items:center; gap:4px; padding: 6px 12px; height: 36px;">
                <span class="material-symbols-outlined" style="font-size:1.1rem;">add_circle</span> Seleccionar Archivos
              </button>
              <span id="files-counter" style="font-size: 0.85rem; color: #64748b;">0 archivos</span>
            </div>
            <input id="swal-file" type="file" class="pro-input" style="display:none" multiple>
            <div id="file-list-container" style="max-height: 120px; overflow-y: auto; border: 1px dashed #cbd5e1; padding: 8px; border-radius: 8px; background: #f8fafc; font-size: 0.85rem;">
              <i style="color: #94a3b8; display: block; text-align: center; padding: 10px 0;">Ningún archivo seleccionado</i>
            </div>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Enviar Documento',
      didOpen: () => {
        const btnAdd = Swal.getHtmlContainer()?.querySelector('#btn-add-files');
        const fileInput = Swal.getHtmlContainer()?.querySelector('#swal-file') as HTMLInputElement;
        const listContainer = Swal.getHtmlContainer()?.querySelector('#file-list-container');
        const counterSpan = Swal.getHtmlContainer()?.querySelector('#files-counter');

        const updateFileList = () => {
          if (!listContainer || !counterSpan) return;
          counterSpan.textContent = `${selectedFiles.length} archivo(s)`;
          if (selectedFiles.length === 0) {
            listContainer.innerHTML = '<i style="color: #94a3b8; display: block; text-align: center; padding: 10px 0;">Ningún archivo seleccionado</i>';
            return;
          }
          listContainer.innerHTML = selectedFiles.map((f, index) => `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; background:#fff; padding:6px 10px; border:1px solid #e2e8f0; border-radius:6px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:80%; font-weight: 500; color: #334155;" title="${f.name}">${f.name}</span>
              <button type="button" class="btn-remove-file" data-index="${index}" style="background:none; border:none; color:#ef4444; cursor:pointer; padding: 4px; display:flex; align-items:center; border-radius: 4px; transition: background 0.2s;">
                <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
              </button>
            </div>
          `).join('');

          listContainer.querySelectorAll('.btn-remove-file').forEach(btn => {
            btn.addEventListener('click', (e) => {
              const idx = parseInt((e.currentTarget as HTMLElement).getAttribute('data-index') || '0');
              selectedFiles.splice(idx, 1);
              updateFileList();
            });
          });
        };

        btnAdd?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', () => {
          if (fileInput.files) {
            Array.from(fileInput.files).forEach(f => {
              if (!selectedFiles.some(existing => existing.name === f.name && existing.size === f.size)) {
                selectedFiles.push(f);
              }
            });
            updateFileList();
          }
        });

        // --- Ruta de aprobación: agregar, reordenar y quitar áreas ---
        const rutaContainer = Swal.getHtmlContainer()?.querySelector('#ruta-list-container');
        const areaSelect = Swal.getHtmlContainer()?.querySelector('#ruta-area-select') as HTMLSelectElement;
        const btnAddArea = Swal.getHtmlContainer()?.querySelector('#btn-add-area');

        const updateAreaSelectOptions = () => {
          if (!areaSelect) return;
          const usedIds = new Set(ruta.map(a => a.id));
          const pendientes = availableAreas.filter(a => !usedIds.has(a.id));
          areaSelect.innerHTML = pendientes.length === 0
            ? '<option value="">No hay más áreas disponibles</option>'
            : pendientes.map(a => `<option value="${a.id}">${a.nombre}</option>`).join('');
        };

        const renderRuta = () => {
          if (!rutaContainer) return;
          if (ruta.length === 0) {
            rutaContainer.innerHTML = '<i style="color: #94a3b8; display: block; padding: 6px 0;">Ningún área agregada todavía.</i>';
          } else {
            rutaContainer.innerHTML = ruta.map((area, idx) => `
              <div style="display:flex;align-items:center;gap:8px;background:#f0fdf4;border:1px solid #bbf7d0;padding:6px 10px;border-radius:6px;margin-bottom:6px;">
                <span style="font-weight:700; min-width:18px;">${idx + 1}</span>
                <span style="flex:1; font-weight:500; color:#334155;">${area.nombre}</span>
                <button type="button" class="btn-move-up" data-index="${idx}" ${idx === 0 ? 'disabled' : ''} style="background:none;border:none;cursor:pointer;padding:2px 6px;">↑</button>
                <button type="button" class="btn-move-down" data-index="${idx}" ${idx === ruta.length - 1 ? 'disabled' : ''} style="background:none;border:none;cursor:pointer;padding:2px 6px;">↓</button>
                <button type="button" class="btn-remove-area" data-index="${idx}" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:2px 6px;">✕</button>
              </div>
            `).join('');

            rutaContainer.querySelectorAll('.btn-move-up').forEach(btn => {
              btn.addEventListener('click', (e) => {
                const idx = parseInt((e.currentTarget as HTMLElement).getAttribute('data-index') || '0');
                if (idx > 0) {
                  [ruta[idx - 1], ruta[idx]] = [ruta[idx], ruta[idx - 1]];
                  renderRuta();
                }
              });
            });
            rutaContainer.querySelectorAll('.btn-move-down').forEach(btn => {
              btn.addEventListener('click', (e) => {
                const idx = parseInt((e.currentTarget as HTMLElement).getAttribute('data-index') || '0');
                if (idx < ruta.length - 1) {
                  [ruta[idx + 1], ruta[idx]] = [ruta[idx], ruta[idx + 1]];
                  renderRuta();
                }
              });
            });
            rutaContainer.querySelectorAll('.btn-remove-area').forEach(btn => {
              btn.addEventListener('click', (e) => {
                const idx = parseInt((e.currentTarget as HTMLElement).getAttribute('data-index') || '0');
                ruta.splice(idx, 1);
                renderRuta();
              });
            });
          }
          updateAreaSelectOptions();
        };

        btnAddArea?.addEventListener('click', () => {
          const id = parseInt(areaSelect.value);
          if (!id) return;
          const area = availableAreas.find(a => a.id === id);
          if (area && !ruta.some(r => r.id === id)) {
            ruta.push(area);
            renderRuta();
          }
        });

        renderRuta();
      },
      preConfirm: () => {
        if (!ruta.length) {
          Swal.showValidationMessage('Debe agregar al menos un área a la ruta de aprobación.');
          return false;
        }
        return {
          titulo: (document.getElementById('swal-titulo') as HTMLInputElement).value,
          ruta: ruta.map(a => a.id),
          prioridad_id: (document.getElementById('swal-priority') as HTMLSelectElement).value,
          categoria_id: (document.getElementById('swal-category') as HTMLSelectElement).value,
          observaciones: (document.getElementById('swal-mensaje') as HTMLTextAreaElement).value,
          archivos: selectedFiles
        };
      }
    });

    if (formValues) {
      if (!formValues.titulo || !formValues.archivos || formValues.archivos.length === 0) {
        Swal.fire('Error', 'Debe indicar un título y seleccionar al menos un archivo.', 'error');
        return;
      }

      const formData = new FormData();
      formData.append('titulo', formValues.titulo);
      formValues.ruta.forEach((areaId: number) => formData.append('ruta[]', String(areaId)));
      formData.append('prioridad_id', formValues.prioridad_id);
      formData.append('categoria_id', formValues.categoria_id);
      formData.append('observaciones', formValues.observaciones);

      formValues.archivos.forEach((file: File) => {
        formData.append('archivos[]', file, file.name);
      });

      this.loading = true;
      this.http.post(`${environment.apiUrl}/document-envios`, formData).subscribe({
        next: () => {
          Swal.fire('¡Éxito!', 'El documento fue enviado correctamente y quedó pendiente en el primer paso de aprobación.', 'success');
          this.loadDocuments();
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'Error al enviar el documento.', 'error');
          this.loading = false;
        }
      });
    }
  }

  openViewer(envio: any, file: any) {
    this.selectedEnvio = envio;
    this.selectedFile = file;
    const baseUrl = environment.apiUrl.replace('/api', '');
    this.viewerUrl = `${baseUrl}/storage/${file.path}`;
    this.isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(file.path);
    this.isExcel = /\.(xls|xlsx|csv)$/i.test(file.path);
    this.isWord = /\.(doc|docx)$/i.test(file.path);

    if (this.isWord) {
      // Usar Google Docs Viewer oficial para previsualizar archivos Word
      const encodedUrl = encodeURIComponent(this.viewerUrl);
      this.viewerUrl = `https://docs.google.com/gview?url=${encodedUrl}&embedded=true`;
    }

    this.showViewer = true;
  }

  closeViewer() {
    this.showViewer = false;
    this.selectedEnvio = null;
    this.selectedFile = null;
    this.viewerUrl = null;
    this.isWord = false;
  }

  downloadFile(envio: any, file: any) {
    const url = `${environment.apiUrl}/document-envios/${envio.id}/files/${file.id}/download`;

    this.http.get(url, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = file.original_name || envio.titulo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
      },
      error: (err) => {
        console.error('Error al descargar el archivo:', err);
        Swal.fire('Error', 'No se pudo descargar el archivo.', 'error');
      }
    });
  }
}
