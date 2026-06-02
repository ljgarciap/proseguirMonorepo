import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-db-cleaner',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './db-cleaner.component.html',
  styleUrls: ['./db-cleaner.component.css']
})
export class DbCleanerComponent implements OnInit {
  modulesList = [
    { key: 'factoring', label: 'Factoring y Carteras', icon: 'account_balance', desc: 'Truncar operaciones de factoring, confirming, carteras, compraventas, pagos y cargas asociadas.', selected: false },
    { key: 'contable', label: 'Módulo Contable', icon: 'account_balance_wallet', desc: 'Truncar facturas, movimientos bancarios, contables auxiliares, gastos contables e importaciones.', selected: false },
    { key: 'planilla', label: 'Nóminas y Fincas', icon: 'agriculture', desc: 'Truncar fincas, trabajadores, labores, actividades y gastos agrícolas de planillas.', selected: false },
    { key: 'creditos', label: 'Créditos Ordinarios', icon: 'payments', desc: 'Truncar solicitudes de crédito ordinario y sus bitácoras de estados BPMN.', selected: false },
    { key: 'mandatos', label: 'Mandatos y Contratos', icon: 'contract', desc: 'Truncar mandatos diligenciados y soportes jurídicos cargados.', selected: false },
    { key: 'internal_docs', label: 'Documentación Interna', icon: 'mail', desc: 'Truncar bandeja de documentos internos para procesamiento administrativo.', selected: false },
    { key: 'notificaciones', label: 'Notificaciones y Envíos', icon: 'notifications_active', desc: 'Truncar bitácora de notificaciones y base de datos de destinatarios.', selected: false },
    { key: 'system_logs', label: 'Registros de Auditoría (Logs)', icon: 'history', desc: 'Truncar bitácora de logs e historial de reintentos del sistema.', selected: false }
  ];

  loading = false;
  message = '';
  isError = false;

  constructor(private http: HttpClient) {}

  ngOnInit() {}

  // Toggle selection for all modules
  toggleAll(event: any) {
    const isChecked = event.target.checked;
    this.modulesList.forEach(m => m.selected = isChecked);
  }

  // Check if any module is selected
  hasSelection(): boolean {
    return this.modulesList.some(m => m.selected);
  }

  // Clear tables for selected modules
  clearSelectedModules() {
    const selectedKeys = this.modulesList.filter(m => m.selected).map(m => m.key);
    
    if (selectedKeys.length === 0) {
      Swal.fire('Atención', 'Por favor selecciona al menos un módulo para limpiar.', 'warning');
      return;
    }

    Swal.fire({
      title: '¿Confirmar Limpieza Parcial?',
      text: `Se vaciarán por completo las tablas correspondientes a los ${selectedKeys.length} módulos seleccionados. Esta acción no se puede deshacer.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, vaciar seleccionados',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#E53E3E',
      cancelButtonColor: '#718096'
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.message = 'Vaciando tablas seleccionadas...';
        this.isError = false;

        this.http.post(`${environment.apiUrl}/db-cleaner/clear-tables`, { modules: selectedKeys }).subscribe({
          next: (res: any) => {
            this.loading = false;
            this.message = res.message || 'Módulos limpiados correctamente.';
            Swal.fire('¡Vaciado Completado!', this.message, 'success');
            // Uncheck modules
            this.modulesList.forEach(m => m.selected = false);
          },
          error: (err) => {
            this.loading = false;
            this.isError = true;
            this.message = err.error.message || 'Ocurrió un error al limpiar los datos.';
            Swal.fire('Error', this.message, 'error');
          }
        });
      }
    });
  }

  // Reset entire database and re-run seeders
  resetAndSeedDatabase() {
    Swal.fire({
      title: '🚨 ¿REINICIAR TODO EL SISTEMA? 🚨',
      html: '<p style="color: #dc2626; font-weight: 700;">¡ESTA ES UNA ACCIÓN CRÍTICA Y ALTAMENTE DESTRUCTIVA!</p><p>Se vaciarán absolutamente todas las tablas del sistema (clientes, operaciones, planillas, auditorías y usuarios) y se volverán a correr los seeds iniciales con los perfiles por defecto.</p><p>Escribe <b>CONFIRMAR</b> para proceder:</p>',
      input: 'text',
      inputPlaceholder: 'CONFIRMAR',
      icon: 'error',
      showCancelButton: true,
      confirmButtonText: 'Sí, reiniciar sistema completo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#718096',
      preConfirm: (value) => {
        if (value !== 'CONFIRMAR') {
          Swal.showValidationMessage('Debes escribir CONFIRMAR exactamente para proceder');
        }
        return value;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        this.loading = true;
        this.message = 'Restableciendo base de datos y aplicando semillas... Por favor espera.';
        this.isError = false;

        this.http.post(`${environment.apiUrl}/db-cleaner/reset`, {}).subscribe({
          next: (res: any) => {
            this.loading = false;
            this.message = res.message || 'El sistema ha sido reiniciado con éxito.';
            Swal.fire({
              title: '¡Reinicio Exitoso!',
              text: 'La base de datos está limpia y los usuarios por defecto han sido recreados.',
              icon: 'success'
            });
          },
          error: (err) => {
            this.loading = false;
            this.isError = true;
            this.message = err.error.message || 'Ocurrió un error al restablecer el sistema.';
            Swal.fire('Error de reinicio', this.message, 'error');
          }
        });
      }
    });
  }
}
