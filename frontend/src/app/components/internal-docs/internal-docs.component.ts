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
  selectedDocIds = new Set<number>();
  loading = false;

  // Tabs
  currentTab: 'pendientes' | 'procesados' = 'pendientes';

  // Table Controls
  searchTerm = '';
  sortColumn = 'created_at';
  sortDirection: 'desc' | 'asc' = 'desc';
  currentPage = 1;
  itemsPerPage = 5;

  // Lightbox State
  showViewer = false;
  selectedDoc: any = null;
  viewerUrl: string | null = null;
  isImage = false;
  isExcel = false;

  currentUser: any = null;

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit() {
    this.currentUser = this.authService.getUser();
    this.loadInitialData();
    this.loadDocuments();
  }

  loadInitialData() {
    this.http.get<any[]>(`${environment.apiUrl}/parameters/accounting_categories`).subscribe(res => this.categories = res);
    this.http.get<any[]>(`${environment.apiUrl}/parameters/accounting_priorities`).subscribe(res => this.priorities = res);
  }

  loadDocuments() {
    this.loading = true;
    this.http.get<any[]>(`${environment.apiUrl}/internal-docs`).subscribe({
      next: (res) => {
        console.log('Docs received:', res);
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
    const activeRole = this.authService.getActiveRole();
    const isSuperadmin = activeRole === 'superadmin';
    const userEmail = this.authService.getUser()?.email;

    let filtered = this.documents.filter(doc => {
      // 1. View / Role Filter
      let roleMatch = isSuperadmin;
      if (!isSuperadmin) {
        if (doc.sender?.email === userEmail) {
          roleMatch = true;
        } else if (activeRole === 'operativo' && doc.target_role === 'operativo') {
          roleMatch = true;
        } else if (activeRole === 'contable' && doc.target_role === 'contable') {
          roleMatch = true;
        } else if (activeRole === 'gerente' && doc.target_role === 'gerente') {
          roleMatch = true;
        }
      }

      // 2. Tab Filter
      let tabMatch = false;
      if (this.currentTab === 'pendientes') {
        tabMatch = doc.estado === 'pendiente';
      } else {
        tabMatch = doc.estado !== 'pendiente';
      }

      // 3. Search Filter
      let searchMatch = true;
      if (this.searchTerm) {
        const term = this.searchTerm.toLowerCase();
        searchMatch = (
          (doc.titulo && doc.titulo.toLowerCase().includes(term)) ||
          (doc.sender?.name && doc.sender.name.toLowerCase().includes(term)) ||
          (doc.category?.nombre && doc.category.nombre.toLowerCase().includes(term)) ||
          (doc.mensaje && doc.mensaje.toLowerCase().includes(term))
        );
      }

      return roleMatch && tabMatch && searchMatch;
    });

    // 4. Sorting
    filtered.sort((a, b) => {
      let valA = this.getSortValue(a, this.sortColumn);
      let valB = this.getSortValue(b, this.sortColumn);
      
      if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
      if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }

  getSortValue(doc: any, column: string) {
    switch(column) {
      case 'titulo': return doc.titulo?.toLowerCase() || '';
      case 'remitente': return doc.sender?.name?.toLowerCase() || '';
      case 'categoria': return doc.category?.nombre?.toLowerCase() || '';
      case 'prioridad': return doc.priority?.nombre?.toLowerCase() || '';
      case 'estado': return doc.estado?.toLowerCase() || '';
      case 'created_at': default: return new Date(doc.created_at).getTime();
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

  // SLA Calculation
  getRemainingTime(doc: any): { text: string, status: 'ok' | 'warning' | 'critical' | 'expired' | 'none' } {
    if (!doc.priority || !doc.priority.horas_vencimiento || doc.estado !== 'pendiente') {
      return { text: '-', status: 'none' };
    }
    
    const createdAt = new Date(doc.created_at).getTime();
    const expiresAt = createdAt + (doc.priority.horas_vencimiento * 60 * 60 * 1000);
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
  }

  async openUploadModal() {
    const categoryOptions = this.categories.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    const priorityOptions = this.priorities.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');
    let selectedFiles: File[] = [];

    const { value: formValues } = await Swal.fire({
      title: 'Nuevo Envío de Documento',
      html: `
        <div class="swal-form" style="text-align: left;">
          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Título del Documento</label>
            <input id="swal-titulo" class="pro-input" style="width:100%" placeholder="Ej: Reporte Mensual">
          </div>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1rem;">
            <div class="pro-input-group">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Enviar a:</label>
              <select id="swal-target" class="pro-input" style="width:100%">
                <option value="contable">CONTABILIDAD</option>
                <option value="gerente">GERENCIA PSL</option>
                <option value="operativo">ÁREA ADMINISTRATIVA</option>
              </select>
            </div>
            <div class="pro-input-group">
              <label style="display:block; margin-bottom:5px; font-weight:600;">Prioridad</label>
              <select id="swal-priority" class="pro-input" style="width:100%">
                ${priorityOptions}
              </select>
            </div>
          </div>

          <div class="pro-input-group" style="margin-bottom: 1rem;">
            <label style="display:block; margin-bottom:5px; font-weight:600;">Categoría</label>
            <select id="swal-category" class="pro-input" style="width:100%">
              ${categoryOptions}
            </select>
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

          // Attach delete events
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
      },
      preConfirm: () => {
        return {
          titulo: (document.getElementById('swal-titulo') as HTMLInputElement).value,
          target_role: (document.getElementById('swal-target') as HTMLSelectElement).value,
          prioridad_id: (document.getElementById('swal-priority') as HTMLSelectElement).value,
          categoria_id: (document.getElementById('swal-category') as HTMLSelectElement).value,
          mensaje: (document.getElementById('swal-mensaje') as HTMLTextAreaElement).value,
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
      formData.append('target_role', formValues.target_role);
      formData.append('prioridad_id', formValues.prioridad_id);
      formData.append('categoria_id', formValues.categoria_id);
      formData.append('mensaje', formValues.mensaje);
      
      formValues.archivos.forEach((file: File) => {
        formData.append('archivos[]', file, file.name);
      });

      this.loading = true;
      this.http.post(`${environment.apiUrl}/internal-docs`, formData).subscribe({
        next: () => {
          Swal.fire('¡Éxito!', 'Documento(s) enviado(s) correctamente.', 'success');
          this.loadDocuments();
        },
        error: (err) => {
          Swal.fire('Error', err.error.message || 'Error al subir los archivos.', 'error');
          this.loading = false;
        }
      });
    }
  }

  isDocSelected(id: number): boolean {
    return this.selectedDocIds.has(id);
  }

  toggleSelectDoc(id: number): void {
    if (this.selectedDocIds.has(id)) {
      this.selectedDocIds.delete(id);
    } else {
      this.selectedDocIds.add(id);
    }
  }

  toggleSelectAll(): void {
    const docs = this.filteredDocuments;
    if (this.isAllSelected()) {
      docs.forEach(doc => this.selectedDocIds.delete(doc.id));
    } else {
      docs.forEach(doc => {
        if (doc.estado === 'pendiente' && (doc.target_role === this.authService.getActiveRole() || doc.sender_id == this.currentUser?.id || this.authService.getActiveRole() === 'superadmin')) {
          this.selectedDocIds.add(doc.id);
        }
      });
    }
  }

  isAllSelected(): boolean {
    const docs = this.filteredDocuments.filter(doc => doc.estado === 'pendiente' && (doc.target_role === this.authService.getActiveRole() || doc.sender_id == this.currentUser?.id || this.authService.getActiveRole() === 'superadmin'));
    if (docs.length === 0) return false;
    return docs.every(doc => this.selectedDocIds.has(doc.id));
  }

  canDeleteSelected(): boolean {
    const ids = Array.from(this.selectedDocIds);
    if (ids.length === 0) return false;
    const isSuperadmin = this.authService.getActiveRole() === 'superadmin';
    return ids.every(id => {
      const doc = this.documents.find(d => d.id === id);
      if (!doc) return false;
      if (isSuperadmin) return true;
      return doc.sender_id == this.currentUser?.id && doc.estado === 'pendiente';
    });
  }

  bulkDelete() {
    const ids = Array.from(this.selectedDocIds);
    if (ids.length === 0) return;

    Swal.fire({
      title: '¿Eliminar documentos seleccionados?',
      text: `Esta acción eliminará ${ids.length} documento(s) y no se puede deshacer.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, eliminar todos',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.http.delete(`${environment.apiUrl}/internal-docs/bulk-delete`, {
          body: { ids }
        }).subscribe({
          next: () => {
            this.loading = false;
            this.selectedDocIds.clear();
            Swal.fire('Eliminados', 'Los documentos han sido eliminados correctamente.', 'success');
            this.loadDocuments();
          },
          error: (err) => {
            this.loading = false;
            Swal.fire('Error', err.error?.message || 'No se pudieron eliminar los documentos.', 'error');
          }
        });
      }
    });
  }

  bulkUpdateStatus(status: string) {
    const ids = Array.from(this.selectedDocIds);
    if (ids.length === 0) return;

    if (status === 'rechazado') {
      Swal.fire({
        title: 'Motivo de Rechazo Masivo',
        input: 'textarea',
        inputPlaceholder: 'Escriba el motivo del rechazo para todos los documentos aquí...',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Rechazo Masivo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        inputValidator: (value) => {
          if (!value || !value.trim()) {
            return '¡Debe escribir un motivo de rechazo!';
          }
          return null;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeBulkUpdate(ids, 'rechazado', result.value);
        }
      });
    } else {
      Swal.fire({
        title: '¿Procesar documentos seleccionados?',
        text: `Se marcarán como procesados ${ids.length} documentos.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, procesar todos',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeBulkUpdate(ids, 'procesado');
        }
      });
    }
  }

  executeBulkUpdate(ids: number[], status: string, motivoRechazo?: string) {
    this.loading = true;
    this.http.patch(`${environment.apiUrl}/internal-docs/bulk-status`, {
      ids,
      estado: status,
      motivo_rechazo: motivoRechazo
    }).subscribe({
      next: () => {
        this.loading = false;
        this.selectedDocIds.clear();
        Swal.fire('¡Éxito!', 'Documentos actualizados en bloque correctamente.', 'success');
        this.loadDocuments();
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', 'No se pudieron actualizar los documentos.', 'error');
        console.error(err);
      }
    });
  }

  processBatch(batchId: string) {
    if (!batchId) return;
    const batchDocs = this.documents.filter(doc => doc.batch_id === batchId && doc.estado === 'pendiente' && doc.target_role === this.authService.getActiveRole());
    const ids = batchDocs.map(doc => doc.id);
    if (ids.length === 0) {
      Swal.fire('Información', 'No hay documentos pendientes por procesar en este lote.', 'info');
      return;
    }

    Swal.fire({
      title: '¿Procesar lote completo?',
      text: `Se marcarán como procesados todos los ${ids.length} documentos de este lote.`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, procesar lote',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.executeBulkUpdate(ids, 'procesado');
      }
    });
  }

  updateStatus(doc: any, status: string) {
    if (status === 'rechazado') {
      Swal.fire({
        title: 'Motivo de Rechazo',
        input: 'textarea',
        inputPlaceholder: 'Escriba el motivo del rechazo aquí...',
        inputAttributes: {
          'aria-label': 'Escriba el motivo del rechazo aquí'
        },
        showCancelButton: true,
        confirmButtonText: 'Confirmar Rechazo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#718096',
        inputValidator: (value) => {
          if (!value || !value.trim()) {
            return '¡Debe escribir un motivo de rechazo!';
          }
          return null;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const motivo = result.value;
          this.http.patch(`${environment.apiUrl}/internal-docs/${doc.id}/status`, { 
            estado: 'rechazado',
            motivo_rechazo: motivo 
          }).subscribe(() => {
            doc.estado = 'rechazado';
            doc.motivo_rechazo = motivo;
            Swal.fire('Rechazado', 'El documento ha sido rechazado.', 'success');
          });
        }
      });
    } else {
      this.http.patch(`${environment.apiUrl}/internal-docs/${doc.id}/status`, { estado: status }).subscribe(() => {
        doc.estado = status;
        Swal.fire('Actualizado', `Estado cambiado a ${status}.`, 'success');
      });
    }
  }

  deleteDocument(doc: any) {
    Swal.fire({
      title: '¿Eliminar documento?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.http.delete(`${environment.apiUrl}/internal-docs/${doc.id}`).subscribe({
          next: () => {
            Swal.fire('Eliminado', 'El documento ha sido eliminado.', 'success');
            this.loadDocuments();
          },
          error: (err) => {
            Swal.fire('Error', err.error?.message || 'No se pudo eliminar el documento.', 'error');
          }
        });
      }
    });
  }

  openViewer(doc: any) {
    this.selectedDoc = doc;
    const baseUrl = environment.apiUrl.replace('/api', '');
    this.viewerUrl = `${baseUrl}/storage/${doc.archivo_path}`;
    this.isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(doc.archivo_path);
    this.isExcel = /\.(xls|xlsx|csv)$/i.test(doc.archivo_path);
    this.showViewer = true;
  }

  closeViewer() {
    this.showViewer = false;
    this.selectedDoc = null;
    this.viewerUrl = null;
  }

  downloadFile(doc: any) {
    const baseUrl = environment.apiUrl.replace('/api', '');
    const url = `${baseUrl}/storage/${doc.archivo_path}`;
    const link = document.createElement('a');
    link.href = url;
    link.download = doc.titulo;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
