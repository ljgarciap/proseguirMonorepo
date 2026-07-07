import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';

@Component({
  selector: 'app-planilla',
  standalone: true,
  imports: [CommonModule, FormsModule, MilesSeparatorDirective],
  templateUrl: './planilla.component.html',
  styleUrls: ['./planilla.component.scss']
})
export class PlanillaComponent implements OnInit {
  activeTab = 'actividades'; // actividades, gastos, maestros, resumen
  activeMaster = 'fincas'; // fincas, trabajadores, labores
  
  // Catalogs
  fincas: any[] = [];
  trabajadores: any[] = [];
  labores: any[] = [];
  
  // Data
  actividades: any[] = [];
  gastos: any[] = [];
  resumen: any = null;
  
  // Forms / Input
  selectedFincaId: number | null = null;
  newActividad: any = {
    planilla_finca_id: null,
    planilla_trabajador_id: null,
    planilla_labor_id: null,
    fecha: new Date().toISOString().split('T')[0],
    cantidad: 1,
    precio_unitario: null,
    retencion_porcentaje: null,
    observaciones: ''
  };

  newGasto: any = {
    planilla_finca_id: null,
    fecha: new Date().toISOString().split('T')[0],
    concepto: '',
    beneficiario: '',
    valor: 0,
    tipo: 'gasto',
    observaciones: ''
  };

  newFinca: any = { nombre: '', descripcion: '' };
  newTrabajador: any = { nombre: '', identificacion: '', telefono: '', retencion_pactada: null };
  newLabor: any = { nombre: '', unidad: 'Jornal', precio_sugerido: null, retencion_sugerida: 0 };

  loading = false;
  message = '';

  private apiUrl = environment.apiUrl + '/planilla';

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadCatalogs();
    this.loadData();
  }

  loadCatalogs() {
    this.http.get(`${this.apiUrl}/fincas`).subscribe((res: any) => this.fincas = res);
    this.http.get(`${this.apiUrl}/trabajadores`).subscribe((res: any) => this.trabajadores = res);
    this.http.get(`${this.apiUrl}/labores`).subscribe((res: any) => this.labores = res);
  }

  loadData() {
    this.loading = true;
    const fincaParam = this.selectedFincaId ? `?planilla_finca_id=${this.selectedFincaId}` : '';
    
    if (this.activeTab === 'actividades') {
      this.http.get(`${this.apiUrl}/actividades${fincaParam}`).subscribe((res: any) => {
        this.actividades = res.data || [];
        this.loading = false;
      });
    } else if (this.activeTab === 'gastos') {
      this.http.get(`${this.apiUrl}/gastos${fincaParam}`).subscribe((res: any) => {
        this.gastos = res.data || [];
        this.loading = false;
      });
    } else if (this.activeTab === 'resumen') {
      this.http.get(`${this.apiUrl}/resumen${fincaParam}`).subscribe((res: any) => {
        this.resumen = res;
        this.loading = false;
      });
    } else {
      this.loading = false;
    }
  }

  switchTab(tab: string) {
    this.activeTab = tab;
    this.loadData();
  }

  // --- Actions ---

  saveActividad() {
    if (!this.newActividad.planilla_finca_id || !this.newActividad.planilla_trabajador_id || !this.newActividad.planilla_labor_id) {
        this.message = 'Error: Finca, Trabajador y Labor son obligatorios';
        return;
    }
    this.http.post(`${this.apiUrl}/actividades`, this.newActividad).subscribe({
      next: () => {
        this.message = 'Actividad guardada con éxito';
        this.loadData();
        this.resetActividadForm();
      },
      error: () => this.message = 'Error al guardar actividad'
    });
  }

  saveGasto() {
    this.http.post(`${this.apiUrl}/gastos`, this.newGasto).subscribe({
      next: () => {
        this.message = 'Gasto guardado con éxito';
        this.loadData();
        this.resetGastoForm();
      },
      error: () => this.message = 'Error al guardar gasto'
    });
  }

  saveMaster(type: string) {
    let url = `${this.apiUrl}/${type}`;
    let body = type === 'fincas' ? this.newFinca : (type === 'trabajadores' ? this.newTrabajador : this.newLabor);
    
    this.http.post(url, body).subscribe({
      next: () => {
        this.message = 'Maestro actualizado';
        this.loadCatalogs();
        this.resetMasterForms();
      },
      error: () => this.message = 'Error al guardar maestro'
    });
  }

  deleteActividad(id: number) {
    if (confirm('¿Eliminar esta actividad?')) {
      this.http.delete(`${this.apiUrl}/actividades/${id}`).subscribe(() => this.loadData());
    }
  }

  // --- Helpers ---

  resetActividadForm() {
    this.newActividad = { ...this.newActividad, cantidad: 1, precio_unitario: null, retencion_porcentaje: null, observaciones: '' };
  }

  resetGastoForm() {
    this.newGasto = { ...this.newGasto, concepto: '', beneficiario: '', valor: 0, observaciones: '' };
  }

  resetMasterForms() {
    this.newFinca = { nombre: '', descripcion: '' };
    this.newTrabajador = { nombre: '', identificacion: '', telefono: '', retencion_pactada: null };
    this.newLabor = { nombre: '', unidad: 'Jornal', precio_sugerido: null, retencion_sugerida: 0 };
  }
}
