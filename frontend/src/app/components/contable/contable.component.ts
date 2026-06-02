import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-contable',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './contable.component.html',
  styleUrls: ['./contable.component.scss']
})
export class ContableComponent implements OnInit {
  activeTab = 'facturas'; // facturas, auxiliar, bancos, gastos
  historyMode = false;
  math = Math;
  perPage = 15;
  totalItems = 0;
  
  // Data lists
  facturas: any[] = [];
  bancos: any[] = [];
  auxiliar: any[] = [];
  gastos: any[] = [];
  imports: any[] = [];
  
  // Upload state
  selectedFile: File | null = null;
  uploading: boolean = false;
  message: string = '';

  // Data table state
  searchTerm: string = '';
  currentPage: number = 1;
  totalPages: number = 1;
  sortBy: string = 'id';
  sortDir: string = 'desc';

  constructor(private http: HttpClient, public authService: AuthService) {}

  ngOnInit() {
    this.loadData();
  }

  switchTab(tab: string) {
    this.historyMode = false;
    this.activeTab = tab;
    this.currentPage = 1;
    this.searchTerm = '';
    this.sortBy = 'id';
    this.sortDir = 'desc';
    this.loadData();
  }

  toggleHistory() {
    this.historyMode = !this.historyMode;
    this.currentPage = 1;
    this.searchTerm = '';
    this.loadData();
  }

  onFileSelected(event: any) {
    this.selectedFile = event.target.files[0];
  }

  uploadFile() {
    if (!this.selectedFile) return;

    this.uploading = true;
    const formData = new FormData();
    formData.append('file', this.selectedFile);

    // Determines the upload path based on active tab
    this.http.post(`${environment.apiUrl}/contable/upload/${this.activeTab}`, formData)
      .subscribe({
        next: (res: any) => {
          this.message = res.message || 'Subido con éxito';
          this.uploading = false;
          this.selectedFile = null;
          this.loadData(); // refresh
        },
        error: (err) => {
          this.message = 'Error subiendo archivo';
          this.uploading = false;
        }
      });
  }

  loadData(page: number = this.currentPage) {
    this.currentPage = page;

    if (this.historyMode) {
      this.http.get(`${environment.apiUrl}/contable/imports?page=${this.currentPage}`).subscribe((res: any) => {
        this.imports = res.data || [];
        this.totalPages = res.last_page || 1;
      });
      return;
    }

    const qs = `?page=${this.currentPage}&search=${this.searchTerm}&sortBy=${this.sortBy}&sortDir=${this.sortDir}`;

    if (this.activeTab === 'facturas') {
      this.http.get(`${environment.apiUrl}/contable/facturas${qs}`).subscribe((res: any) => {
        this.facturas = (res.data || []).map((f: any) => ({
          ...f,
          vlr_bruto: Number(f.vlr_bruto),
          vlr_iva_5: Number(f.vlr_iva_5),
          vlr_iva_19: Number(f.vlr_iva_19),
          total: Number(f.total)
        }));
        this.bancos = []; this.auxiliar = []; this.gastos = [];
        this.totalPages = res.last_page || 1;
        this.totalItems = res.total || 0;
      });
    } else if (this.activeTab === 'bancos') {
      this.http.get(`${environment.apiUrl}/contable/bancos${qs}`).subscribe((res: any) => {
        this.bancos = (res.data || []).map((b: any) => ({
          ...b,
          valor: Number(b.valor)
        }));
        this.facturas = []; this.auxiliar = []; this.gastos = [];
        this.totalPages = res.last_page || 1;
        this.totalItems = res.total || 0;
      });
    } else if (this.activeTab === 'auxiliar') {
      this.http.get(`${environment.apiUrl}/contable/auxiliar${qs}`).subscribe((res: any) => {
        this.auxiliar = (res.data || []).map((a: any) => ({
          ...a,
          debito_local: Number(a.debito_local),
          credito_local: Number(a.credito_local),
          saldo_local: Number(a.saldo_local)
        }));
        this.facturas = []; this.bancos = []; this.gastos = [];
        this.totalPages = res.last_page || 1;
        this.totalItems = res.total || 0;
      });
    } else if (this.activeTab === 'gastos') {
      this.http.get(`${environment.apiUrl}/contable/gastos${qs}`).subscribe((res: any) => {
        this.gastos = (res.data || []).map((g: any) => ({
          ...g,
          valor: Number(g.valor)
        }));
        this.facturas = []; this.bancos = []; this.auxiliar = [];
        this.totalPages = res.last_page || 1;
        this.totalItems = res.total || 0;
      });
    }
  }

  reconcile() {
    this.uploading = true;
    this.http.post(`${environment.apiUrl}/contable/reconcile`, {}).subscribe({
      next: (res: any) => {
        this.message = `Conciliación completa: ${res.matched} coincidencias, ${res.gastos} gastos generados.`;
        this.uploading = false;
        this.loadData();
      },
      error: (err) => {
        this.message = 'Error al ejecutar conciliación';
        this.uploading = false;
      }
    });
  }

  clearData() {
    if (confirm('¿Estás seguro de que deseas eliminar TODOS los datos del módulo contable? Esta acción no se puede deshacer.')) {
      this.uploading = true;
      this.http.delete(`${environment.apiUrl}/contable/clear`).subscribe({
        next: (res: any) => {
          this.message = res.message;
          this.loadData();
          this.uploading = false;
        },
        error: (err) => {
          console.error(err);
          this.message = 'Error al limpiar los datos';
          this.uploading = false;
        }
      });
    }
  }

  sort(column: string) {
    if (this.sortBy === column) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = column;
      this.sortDir = 'asc';
    }
    this.loadData(1);
  }

  search() {
    this.loadData(1);
  }
}
