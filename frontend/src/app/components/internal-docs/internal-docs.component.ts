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
  expandedBatches = new Set<string>();

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
  selectedDoc: any = null;
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
        } else if (activeRole === 'operativo') {
          if (doc.target_role === 'operativo') {
            roleMatch = true;
          } else if (doc.target_role === 'gerente' && doc.estado === 'pendiente' && doc.sender?.roles?.includes('coordinador_comercial')) {
            roleMatch = true;
          }
        } else if (activeRole === 'contable' && doc.target_role === 'contable') {
          roleMatch = true;
        } else if (activeRole === 'gerente') {
          if (doc.target_role === 'gerente') {
            const isFromCoordinator = doc.sender?.roles?.includes('coordinador_comercial');
            if (!isFromCoordinator || doc.estado !== 'pendiente') {
              roleMatch = true;
            }
          }
        }
      }

      // 2. Tab Filter
      let tabMatch = false;
      if (this.currentTab === 'pendientes') {
        tabMatch = doc.estado === 'pendiente' || doc.estado === 'visto_bueno' || doc.estado === 'visto';
      } else {
        tabMatch = doc.estado === 'procesado' || doc.estado === 'rechazado';
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

      // 4. Date Range Filter
      let dateMatch = true;
      if (this.filterStartDate || this.filterEndDate) {
        // Obtenemos el timestamp del campo last_modified (que añadimos en el backend)
        const docDate = new Date(doc.last_modified).getTime();
        
        if (this.filterStartDate) {
          const start = new Date(this.filterStartDate + 'T00:00:00').getTime();
          if (docDate < start) dateMatch = false;
        }
        
        if (this.filterEndDate) {
          const end = new Date(this.filterEndDate + 'T23:59:59').getTime();
          if (docDate > end) dateMatch = false;
        }
      }

      return roleMatch && tabMatch && searchMatch && dateMatch;
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

  getFolderTitle(doc: any): string {
    if (doc.batch_id && doc.original_name && doc.titulo) {
      const suffix = ' - ' + doc.original_name;
      if (doc.titulo.endsWith(suffix)) {
        return doc.titulo.substring(0, doc.titulo.length - suffix.length);
      }
    }
    return doc.titulo;
  }

  get groupedDocuments(): any[] {
    const grouped: any[] = [];
    const batchMap = new Map<string, any>();

    for (const doc of this.filteredDocuments) {
      const hasMultiBatch = doc.batch_id && this.documents.filter(d => d.batch_id === doc.batch_id).length > 1;

      if (hasMultiBatch) {
        let folder = batchMap.get(doc.batch_id!);
        if (!folder) {
          folder = {
            isFolder: true,
            batch_id: doc.batch_id,
            titulo: this.getFolderTitle(doc),
            created_at: doc.created_at,
            sender: doc.sender,
            target_role: doc.target_role,
            category: doc.category,
            mensaje: doc.mensaje,
            priority: doc.priority,
            estado: doc.estado,
            children: []
          };
          batchMap.set(doc.batch_id!, folder);
          grouped.push(folder);
        }
        folder.children.push(doc);
      } else {
        grouped.push(doc);
      }
    }

    // Consolidated status
    for (const folder of batchMap.values()) {
      const states = folder.children.map((c: any) => c.estado);
      const uniqueStates = Array.from(new Set(states));
      if (uniqueStates.length === 1) {
        folder.estado = uniqueStates[0];
      } else if (uniqueStates.includes('pendiente')) {
        folder.estado = 'pendiente';
      } else if (uniqueStates.includes('visto_bueno')) {
        folder.estado = 'visto_bueno';
      } else {
        folder.estado = uniqueStates[0];
      }
    }

    return grouped;
  }

  get pagedDocuments() {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    return this.groupedDocuments.slice(startIndex, startIndex + this.itemsPerPage);
  }

  get totalPages() {
    return Math.ceil(this.groupedDocuments.length / this.itemsPerPage);
  }

  get showingFrom() {
    return this.groupedDocuments.length === 0 ? 0 : (this.currentPage - 1) * this.itemsPerPage + 1;
  }

  get showingTo() {
    return Math.min(this.currentPage * this.itemsPerPage, this.groupedDocuments.length);
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
    this.filterStartDate = '';
    this.filterEndDate = '';
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

  toggleBatch(batchId: string, event: Event): void {
    event.stopPropagation();
    if (this.expandedBatches.has(batchId)) {
      this.expandedBatches.delete(batchId);
    } else {
      this.expandedBatches.add(batchId);
    }
  }

  isBatchExpanded(batchId: string): boolean {
    return this.expandedBatches.has(batchId);
  }

  isFolderSelected(folder: any): boolean {
    if (!folder.children || folder.children.length === 0) return false;
    return folder.children.every((child: any) => this.selectedDocIds.has(child.id));
  }

  isFolderIndeterminate(folder: any): boolean {
    if (!folder.children || folder.children.length === 0) return false;
    const selectedCount = folder.children.filter((child: any) => this.selectedDocIds.has(child.id)).length;
    return selectedCount > 0 && selectedCount < folder.children.length;
  }

  toggleSelectFolder(folder: any, event: any): void {
    const checked = event.target.checked;
    folder.children.forEach((child: any) => {
      if (checked) {
        if (child.estado === 'pendiente' && (child.target_role === this.authService.getActiveRole() || child.sender_id == this.currentUser?.id || this.authService.getActiveRole() === 'superadmin')) {
          this.selectedDocIds.add(child.id);
        }
      } else {
        this.selectedDocIds.delete(child.id);
      }
    });
  }

  docHasAction(doc: any, type: 'process' | 'visto_bueno' | 'approve' | 'delete' | 'reject'): boolean {
    const activeRole = this.authService.getActiveRole();
    const isSuperadmin = activeRole === 'superadmin';
    
    switch (type) {
      case 'delete':
        if (isSuperadmin) return true;
        return doc.sender_id == this.currentUser?.id && doc.target_role !== activeRole && doc.estado === 'pendiente';
      case 'process':
        return doc.target_role === activeRole && doc.estado === 'pendiente' && !(activeRole === 'operativo' && doc.target_role === 'gerente');
      case 'visto_bueno':
        return activeRole === 'operativo' && doc.target_role === 'gerente' && doc.estado === 'pendiente' && doc.sender?.roles?.includes('coordinador_comercial');
      case 'approve':
        return activeRole === 'gerente' && doc.target_role === 'gerente' && doc.estado === 'visto_bueno';
      case 'reject':
        const canProc = doc.target_role === activeRole && doc.estado === 'pendiente' && !(activeRole === 'operativo' && doc.target_role === 'gerente');
        const canVB = activeRole === 'operativo' && doc.target_role === 'gerente' && doc.estado === 'pendiente' && doc.sender?.roles?.includes('coordinador_comercial');
        const canApp = activeRole === 'gerente' && doc.target_role === 'gerente' && doc.estado === 'visto_bueno';
        return canProc || canVB || canApp;
      default:
        return false;
    }
  }

  folderHasAction(folder: any, type: 'process' | 'visto_bueno' | 'approve' | 'delete' | 'reject'): boolean {
    return folder.children && folder.children.some((child: any) => this.docHasAction(child, type));
  }

  deleteFolder(folder: any) {
    const ids = folder.children.map((child: any) => child.id);
    if (ids.length === 0) return;

    Swal.fire({
      title: '¿Eliminar carpeta completa?',
      text: `Esta acción eliminará todos los ${ids.length} documentos de la carpeta "${folder.titulo}" y no se puede deshacer.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Sí, eliminar todo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.http.delete(`${environment.apiUrl}/internal-docs/bulk-delete`, {
          body: { ids }
        }).subscribe({
          next: () => {
            this.loading = false;
            ids.forEach((id: number) => this.selectedDocIds.delete(id));
            Swal.fire('Eliminados', 'La carpeta y sus documentos han sido eliminados.', 'success');
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

  updateFolderStatus(folder: any, status: string) {
    let validChildren = folder.children;
    if (status === 'procesado') {
      validChildren = folder.children.filter((child: any) => this.docHasAction(child, 'process') || this.docHasAction(child, 'approve'));
    } else if (status === 'visto_bueno') {
      validChildren = folder.children.filter((child: any) => this.docHasAction(child, 'visto_bueno'));
    } else if (status === 'rechazado') {
      validChildren = folder.children.filter((child: any) => this.docHasAction(child, 'reject'));
    }

    const ids = validChildren.map((child: any) => child.id);
    if (ids.length === 0) return;

    if (status === 'rechazado') {
      Swal.fire({
        title: 'Motivo de Rechazo de la Carpeta',
        input: 'textarea',
        inputPlaceholder: 'Escriba el motivo del rechazo para todos los documentos de la carpeta...',
        showCancelButton: true,
        confirmButtonText: 'Confirmar Rechazo',
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
    } else if (status === 'visto_bueno') {
      Swal.fire({
        title: '¿Dar Visto Bueno a la carpeta completa?',
        text: `Se dará visto bueno a los ${ids.length} documentos pendientes de la carpeta "${folder.titulo}".`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar Visto Bueno',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeBulkUpdate(ids, 'visto_bueno');
        }
      });
    } else {
      Swal.fire({
        title: '¿Procesar carpeta completa?',
        text: `Se marcarán como procesados los ${ids.length} documentos de la carpeta "${folder.titulo}".`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, procesar todo',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          this.executeBulkUpdate(ids, 'procesado');
        }
      });
    }
  }

  downloadFolder(folder: any) {
    if (!folder.children || folder.children.length === 0) return;
    folder.children.forEach((child: any, index: number) => {
      setTimeout(() => {
        this.downloadFile(child);
      }, index * 250);
    });
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
    this.isWord = /\.(doc|docx)$/i.test(doc.archivo_path);
    
    if (this.isWord) {
      // Usar Google Docs Viewer oficial para previsualizar archivos Word
      const encodedUrl = encodeURIComponent(this.viewerUrl);
      this.viewerUrl = `https://docs.google.com/gview?url=${encodedUrl}&embedded=true`;
    }
    
    this.showViewer = true;
  }

  closeViewer() {
    this.showViewer = false;
    this.selectedDoc = null;
    this.viewerUrl = null;
    this.isWord = false;
  }

  downloadFile(doc: any) {
    const baseUrl = environment.apiUrl.replace('/api', '');
    const url = `${baseUrl}/storage/${doc.archivo_path}`;
    
    // Hacemos un GET como Blob para forzar al navegador a respetar el nombre original
    this.http.get(url, { responseType: 'blob' }).subscribe({
      next: (blob) => {
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = doc.original_name || doc.titulo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
      },
      error: (err) => {
        console.error('Error al descargar el archivo:', err);
        // Fallback clásico en caso de error
        const link = document.createElement('a');
        link.href = url;
        link.download = doc.original_name || doc.titulo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      }
    });
  }
}
