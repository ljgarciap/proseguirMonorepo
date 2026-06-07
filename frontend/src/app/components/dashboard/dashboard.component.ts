import { Component, OnInit, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { BaseChartDirective, provideCharts, withDefaultRegisterables } from 'ng2-charts';
import { ChartConfiguration, ChartData, ChartType, Chart, registerables } from 'chart.js';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';

import { environment } from '../../../environments/environment';

// Register all Chart.js components
Chart.register(...registerables);

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, BaseChartDirective],
  providers: [provideCharts(withDefaultRegisterables())],
    template: `
    <div class="dashboard-wrapper" #dashboardContent>
      <!-- CONTABLE DASHBOARD VIEW -->
      <div class="view-container" *ngIf="authService.getActiveRole() === 'contable'" style="padding: 2.5rem 3rem;">
        <header class="view-header" style="border: none; padding: 0; margin-bottom: 2rem; background: transparent;">
          <div class="title-area">
            <h1>Panel de Control Contable</h1>
            <p>Bienvenido al módulo de gestión y control documental interno.</p>
          </div>
        </header>

        <div class="kpi-grid">
          <div class="kpi-card pro-card navy" (click)="router.navigate(['/internal-docs'])">
            <div class="kpi-icon"><span class="material-symbols-outlined">mail</span></div>
            <div class="kpi-body">
              <label>Bandeja Interna</label>
              <div class="value">{{ pendingInternalDocs }}</div>
              <div class="footer">Documentos pendientes de gestión</div>
            </div>
          </div>
          <div class="kpi-card pro-card cyan" (click)="router.navigate(['/profile'])">
            <div class="kpi-icon"><span class="material-symbols-outlined">person</span></div>
            <div class="kpi-body">
              <label>Estado de Cuenta</label>
              <div class="value">Activo</div>
              <div class="footer">Perfil de usuario verificado</div>
            </div>
          </div>
        </div>

        <div class="card p-4" style="background: white; border-radius: 20px; padding: 2rem;">
           <h3 style="color: var(--primary); margin-bottom: 1.5rem;">Acciones Rápidas</h3>
           <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
              <button class="btn-pro secondary" (click)="router.navigate(['/internal-docs'])" style="justify-content: center; height: 60px;">
                <span class="material-symbols-outlined">visibility</span> Ver Bandeja de Entrada
              </button>
              <button class="btn-pro primary" (click)="router.navigate(['/profile'])" style="justify-content: center; height: 60px;">
                <span class="material-symbols-outlined">manage_accounts</span> Configurar mi Perfil
              </button>
           </div>
        </div>
      </div>

      <!-- STAFF DASHBOARD VIEW (Operativo/Gerente/Superadmin) -->
      <div *ngIf="authService.getActiveRole() !== 'contable'">
        <header class="view-header">
        <div class="title-area">
          <h1>Análisis de Liquidez & Cartera</h1>
          <p>Supervisión detallada de métricas operativas y financieras.</p>
        </div>
        
        <div class="header-filters">
          <div class="pro-input-group">
            <label>Desde</label>
            <input type="date" [(ngModel)]="filterFechaInicio" (change)="loadStats()" class="pro-input">
          </div>
          <div class="pro-input-group">
            <label>Hasta</label>
            <input type="date" [(ngModel)]="filterFechaFin" (change)="loadStats()" class="pro-input">
          </div>
          <div class="pro-input-group">
            <label>Cliente / NIT</label>
            <div class="search-box">
              <input type="text" [(ngModel)]="filterCliente" (keyup.enter)="loadStats()" placeholder="Filtrar..." class="pro-input">
              <span class="material-symbols-outlined" *ngIf="!filterCliente">search</span>
              <span class="material-symbols-outlined clear" *ngIf="filterCliente" (click)="filterCliente = ''; loadStats()">close</span>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <div class="pro-tabs">
            <button (click)="setTab('cartera')" [class.active]="currentTab === 'cartera'">
              <span class="material-symbols-outlined">payments</span> Cartera CYF
            </button>
            <button (click)="setTab('cartera_factoring')" [class.active]="currentTab === 'cartera_factoring'">
              <span class="material-symbols-outlined">analytics</span> Cartera Factoring
            </button>
            <button (click)="setTab('factoring')" [class.active]="currentTab === 'factoring'">
              <span class="material-symbols-outlined">account_balance</span> Factoring
            </button>
            <button (click)="setTab('pagos')" [class.active]="currentTab === 'pagos'">
              <span class="material-symbols-outlined">receipt_long</span> Pagos Factoring
            </button>
            <button (click)="setTab('confirming')" [class.active]="currentTab === 'confirming'">
              <span class="material-symbols-outlined">assignment</span> CompraVenta
            </button>
            <button (click)="setTab('pagos_compraventa')" [class.active]="currentTab === 'pagos_compraventa'">
              <span class="material-symbols-outlined">paid</span> Pagos CompraVenta
            </button>
          </div>

          <div class="btn-group">
            <button (click)="loadStats()" class="btn-pro secondary icon-only" [class.spinning]="isRefreshing" title="Refrescar">
              <span class="material-symbols-outlined">{{ isRefreshing ? 'sync' : 'refresh' }}</span>
            </button>
            <button (click)="exportToPdf()" class="btn-pro primary" [disabled]="isGeneratingPdf">
              <span class="material-symbols-outlined">{{ isGeneratingPdf ? 'hourglass_bottom' : 'picture_as_pdf' }}</span>
              {{ isGeneratingPdf ? 'Generando...' : 'Exportar PDF' }}
            </button>
          </div>
        </div>
      </header>
      
      <div class="content-viewport" *ngIf="!isLoading && stats">
        
        <!-- PENDING TASKS ALERT (FOR DASHBOARD) -->
        <div class="dashboard-alert-section" *ngIf="pendingCount > 0">
           <div class="alert-card shadow-sm">
              <div class="icon-side">
                 <span class="material-symbols-outlined pulse">priority_high</span>
              </div>
              <div class="text-side">
                 <h3>Tareas Pendientes de Acción</h3>
                 <p>Tienes <strong>{{ pendingCount }}</strong> documentos esperando tu gestión en el módulo de validación.</p>
              </div>
              <button class="btn-pro primary" (click)="router.navigate(['/validation'])">
                 Ir a Validaciones <span class="material-symbols-outlined">chevron_right</span>
              </button>
           </div>
        </div>
        
        <!-- TAB: CARTERA -->
        <div class="tab-view" *ngIf="currentTab === 'cartera' && stats.cartera">
          <div class="kpi-grid">
            <div class="kpi-card pro-card navy" (click)="navigateToSheets('cartera')">
              <div class="kpi-icon"><span class="material-symbols-outlined">group</span></div>
              <div class="kpi-body">
                <label>Clientes Activos</label>
                <div class="value">{{ stats.cartera.unique_clients }}</div>
                <div class="footer">Promedio Ops: {{ stats.cartera.movs_per_client }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card cyan" (click)="navigateToSheets('cartera')">
              <div class="kpi-icon"><span class="material-symbols-outlined">account_balance_wallet</span></div>
              <div class="kpi-body">
                <label>Saldo Capital Total</label>
                <div class="value">{{ formatMoney(stats.cartera.saldo_capital) }}</div>
                <div class="footer">Cartera activa gestionada</div>
              </div>
            </div>
            <div class="kpi-card pro-card orange" (click)="navigateToSheets('cartera', '', 'vencido')">
              <div class="kpi-icon"><span class="material-symbols-outlined">running_with_errors</span></div>
              <div class="kpi-body">
                <label>Valor Vencido</label>
                <div class="value">{{ formatMoney(stats.cartera.total_vencido) }}</div>
                <div class="footer">Retraso <= 3 días</div>
              </div>
            </div>
            <div class="kpi-card pro-card red" (click)="navigateToSheets('cartera', '', 'mora')">
              <div class="kpi-icon"><span class="material-symbols-outlined">error</span></div>
              <div class="kpi-body">
                <label>Valor Mora</label>
                <div class="value">{{ formatMoney(stats.cartera.total_mora) }}</div>
                <div class="footer">Días de retraso > 30</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-8">
              <div class="card-header">
                <h3>Saldo de Mora por Cliente (Top 10)</h3>
              </div>
              <div class="chart-box">
                <canvas baseChart
                  [data]="moraChartData"
                  [options]="barChartOptions"
                  (chartClick)="onMoraChartClick($event)"
                  [type]="'bar'">
                </canvas>
              </div>
            </div>

            <div class="card table-container span-4">
              <div class="card-header" (click)="showClientsBalanceModal = true" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center;" title="Ver todos los clientes con saldo">
                <h3>Top Clientes por Saldo</h3>
                <span class="material-symbols-outlined" style="font-size: 20px; color: #718096;">open_in_new</span>
              </div>
              <table class="pro-table x-small">
                <thead>
                  <tr>
                    <th>Cliente</th>
                    <th class="text-right">Saldo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngFor="let c of getProcessedData(stats.cartera.client_ranking, 'carteraRanking').items | slice:0:6" (click)="navigateToSheets('cartera', c.cliente)" class="clickable">
                    <td class="truncate">{{ c.cliente }}</td>
                    <td class="text-right bold">{{ formatMoney(c.saldo_total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Actividad Económica</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="activityChartData"
                  [options]="pieChartOptions"
                  (chartClick)="onActivityClick($event)"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>

            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Distribución Geográfica</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="cityChartData"
                  [options]="pieChartOptions"
                  (chartClick)="onCityClick($event)"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: PAGOS -->
        <div class="tab-view" *ngIf="currentTab === 'pagos' && stats.factoring">
          <div class="kpi-grid">
            <div class="kpi-card pro-card cyan">
              <div class="kpi-icon"><span class="material-symbols-outlined">savings</span></div>
              <div class="kpi-body">
                <label>Recaudación Total</label>
                <div class="value">{{ formatMoney(stats.factoring.total_collected) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card navy">
              <div class="kpi-icon"><span class="material-symbols-outlined">speed</span></div>
              <div class="kpi-body">
                <label>Eficiencia de Cobro</label>
                <div class="value">{{ stats.factoring.efficiency_score }} <small>días</small></div>
              </div>
            </div>
            <div class="kpi-card pro-card orange" (click)="navigateToSheets('pagos')">
              <div class="kpi-icon"><span class="material-symbols-outlined">receipt</span></div>
              <div class="kpi-body">
                <label>Facturas Canceladas</label>
                <div class="value">{{ stats.factoring.pagos_count }}</div>
                <div class="footer">Click para ver detalle</div>
              </div>
            </div>
            <div class="kpi-card pro-card purple">
              <div class="kpi-icon"><span class="material-symbols-outlined">pending_actions</span></div>
              <div class="kpi-body">
                <label>Saldos Pendientes</label>
                <div class="value">{{ formatMoney(stats.factoring.outstanding_balance) }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-12">
              <div class="card-header">
                <h3>Histórico de Recaudación</h3>
              </div>
              <div class="chart-box tall">
                <canvas baseChart
                  [data]="paymentTimelineChartData"
                  [options]="barChartOptions"
                  [type]="'bar'">
                </canvas>
              </div>
            </div>

            <div class="card chart-container span-12">
              <div class="card-header">
                <h3>Recaudación Diaria por Cliente</h3>
              </div>
              <div class="chart-box tall">
                <canvas baseChart
                  [data]="dailyPaymentsChartData"
                  [options]="stackedBarChartOptions"
                  [type]="'bar'">
                </canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: FACTORING -->
        <div class="tab-view" *ngIf="currentTab === 'factoring' && stats.factoring">
          <div class="kpi-grid">
            <div class="kpi-card pro-card cyan">
              <div class="kpi-icon"><span class="material-symbols-outlined">analytics</span></div>
              <div class="kpi-body">
                <label>Volumen Financiado</label>
                <div class="value">{{ formatMoney(stats.factoring.volumen_total) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card navy">
              <div class="kpi-icon"><span class="material-symbols-outlined">payments</span></div>
              <div class="kpi-body">
                <label>Valor Desembolsado</label>
                <div class="value">{{ formatMoney(stats.factoring.valor_desembolsado) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card orange">
              <div class="kpi-icon"><span class="material-symbols-outlined">percent</span></div>
              <div class="kpi-body">
                <label>Tasa Ponderada</label>
                <div class="value">{{ stats.factoring.tasa_ponderada }}%</div>
                <div class="footer">Promedio por desembolso</div>
              </div>
            </div>
            <div class="kpi-card pro-card purple">
              <div class="kpi-icon"><span class="material-symbols-outlined">account_balance_wallet</span></div>
              <div class="kpi-body">
                <label>Reserva Total</label>
                <div class="value">{{ formatMoney(stats.factoring.valor_reserva) }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-8">
              <div class="card-header">
                <h3>Exposición por Pagador</h3>
              </div>
              <div class="chart-box">
                <canvas baseChart
                  [data]="exposureChartData"
                  [options]="barChartOptions"
                  [type]="'bar'">
                </canvas>
              </div>
            </div>
            
            <div class="card table-container span-4">
              <div class="card-header">
                <h3>Próximos Vencimientos</h3>
              </div>
              <table class="pro-table x-small">
                <thead>
                  <tr>
                    <th>Pagador</th>
                    <th class="text-right">Monto</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngFor="let v of stats.factoring.vencimientos">
                    <td>
                      <div class="payer-cell" [title]="'Vence en ' + v.dias + ' días (' + safeDate(v.fecha, 'dd/MM/yyyy') + ')'">
                        <span class="name">{{ v.pagador }}</span>
                        <span class="due-badge" [ngClass]="v.estado.toLowerCase().replace(' ', '-')">
                          {{ v.dias === 0 ? 'Hoy' : (v.dias === 1 ? 'Mañana' : 'En ' + v.dias + ' días') }}
                        </span>
                      </div>
                    </td>
                    <td class="text-right bold text-nowrap" style="vertical-align: middle;">{{ formatMoney(v.monto) }}</td>
                  </tr>
                  <tr *ngIf="!stats.factoring?.vencimientos || stats.factoring.vencimientos.length === 0">
                    <td colspan="2" class="text-center py-4 text-muted">No hay vencimientos próximos (5 días)</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Distribución de Tasas de Colocación</h3>
              </div>
              <div class="chart-box">
                 <canvas baseChart
                  [data]="tasaDistributionChartData"
                  [options]="pieChartOptions"
                  [type]="'pie'">
                </canvas>
              </div>
            </div>

            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Promedio Ponderado por Pagador</h3>
              </div>
              <div class="chart-box">
                 <canvas baseChart
                  [data]="weightedRatesChartData"
                  [options]="pieChartOptions"
                  (chartClick)="onWeightedRateClick($event)"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: CONFIRMING -->
        <div class="tab-view" *ngIf="currentTab === 'confirming' && stats.confirming">
          <div class="kpi-grid">
            <div class="kpi-card pro-card navy">
              <div class="kpi-icon"><span class="material-symbols-outlined">request_quote</span></div>
              <div class="kpi-body">
                <label>Valor Nominal Total</label>
                <div class="value">{{ formatMoney(stats.confirming.total_val) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card cyan">
              <div class="kpi-icon"><span class="material-symbols-outlined">trending_up</span></div>
              <div class="kpi-body">
                <label>Rendimientos Proyectados</label>
                <div class="value">{{ formatMoney(stats.confirming.rendimientos_proyectados) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card orange">
              <div class="kpi-icon"><span class="material-symbols-outlined">percent</span></div>
              <div class="kpi-body">
                <label>Tasa Factor Promedio</label>
                <div class="value">{{ stats.confirming.avg_tasa }}%</div>
              </div>
            </div>
            <div class="kpi-card pro-card purple">
              <div class="kpi-icon"><span class="material-symbols-outlined">account_balance</span></div>
              <div class="kpi-body">
                <label>Valor a Pagar Deudores</label>
                <div class="value">{{ formatMoney(stats.confirming.total_pagar_deudores) }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Distribución por Emisor</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="emitterAnalysisChartData"
                  [options]="pieChartOptions"
                  [type]="'pie'">
                </canvas>
              </div>
            </div>
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Tasas por Emisor</h3>
              </div>
              <div class="chart-box">
                <canvas baseChart
                  [data]="emitterTasaChartData"
                  [options]="barChartOptions"
                  [type]="'bar'">
                </canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: COMPRAVENTA -->
        <div class="tab-view" *ngIf="currentTab === 'compraventa' && stats.compraventa">
          <div class="kpi-grid">
            <div class="kpi-card pro-card navy">
              <div class="kpi-icon"><span class="material-symbols-outlined">assignment</span></div>
              <div class="kpi-body">
                <label>Total Negociado</label>
                <div class="value">{{ formatMoney(stats.compraventa.total_val) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card cyan">
              <div class="kpi-icon"><span class="material-symbols-outlined">numbers</span></div>
              <div class="kpi-body">
                <label>Nro. Documentos</label>
                <div class="value">{{ stats.compraventa.count }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card orange">
              <div class="kpi-icon"><span class="material-symbols-outlined">group</span></div>
              <div class="kpi-body">
                <label>Vendedores Únicos</label>
                <div class="value">{{ stats.compraventa.unique_vendedores }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Top Vendedores</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="topVendedoresChartData"
                  [options]="pieChartOptions"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Top Compradores</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="topCompradoresChartData"
                  [options]="pieChartOptions"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: PAGOS COMPRAVENTA -->
        <div class="tab-view" *ngIf="currentTab === 'pagos_compraventa' && stats.pagos_compraventa">
          <div class="kpi-grid">
            <div class="kpi-card pro-card navy">
              <div class="kpi-icon"><span class="material-symbols-outlined">paid</span></div>
              <div class="kpi-body">
                <label>Total Recaudado</label>
                <div class="value">{{ formatMoney(stats.pagos_compraventa.total_recaudado) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card cyan">
              <div class="kpi-icon"><span class="material-symbols-outlined">account_balance_wallet</span></div>
              <div class="kpi-body">
                <label>Capital Pagado</label>
                <div class="value">{{ formatMoney(stats.pagos_compraventa.total_capital) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card orange">
              <div class="kpi-icon"><span class="material-symbols-outlined">price_check</span></div>
              <div class="kpi-body">
                <label>Total Descuento</label>
                <div class="value">{{ formatMoney(stats.pagos_compraventa.total_descuento) }}</div>
              </div>
            </div>
            <div class="kpi-card pro-card purple">
              <div class="kpi-icon"><span class="material-symbols-outlined">reorder</span></div>
              <div class="kpi-body">
                <label>Operaciones</label>
                <div class="value">{{ stats.pagos_compraventa.count }}</div>
              </div>
            </div>
          </div>

          <div class="dashboard-grid">
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Top Pagadores (Recaudo)</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="topPagadoresPCChartData"
                  [options]="pieChartOptions"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>
            <div class="card chart-container span-6">
              <div class="card-header">
                <h3>Top Clientes (Recaudo)</h3>
              </div>
              <div class="chart-box doughnut">
                <canvas baseChart
                  [data]="topClientesPCChartData"
                  [options]="pieChartOptions"
                  [type]="'doughnut'">
                </canvas>
              </div>
            </div>

            <div class="card table-container span-12">
              <div class="card-header">
                <h3>Últimos Pagos Procesados</h3>
              </div>
              <div class="table-scroll">
                <table class="pro-table">
                  <thead>
                    <tr>
                      <th>Ref. Pago</th>
                      <th>Pagador</th>
                      <th>Cliente</th>
                      <th>Fecha Recaudo</th>
                      <th class="text-right">Capital Pagado</th>
                      <th class="text-right">Total Pagado</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr *ngFor="let p of stats.pagos_compraventa.ultimos_pagos">
                      <td><span class="badge secondary">{{ p.pago_ref }}</span></td>
                      <td>{{ p.pagador }}</td>
                      <td>{{ p.cliente }}</td>
                      <td>{{ p.fecha_recaudo }}</td>
                      <td class="text-right">{{ formatMoney(p.capital_pagado) }}</td>
                      <td class="text-right">{{ formatMoney(p.total_pagado) }}</td>
                      <td><span class="badge success">{{ p.estado }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- MODAL FOR ALL CLIENTS WITH BALANCE (SCRUM-66) -->
      <div class="modal-backdrop" *ngIf="showClientsBalanceModal">
        <div class="modal-card" style="max-width: 600px;">
          <header class="modal-header">
            <h3>Clientes con Saldo</h3>
            <button class="btn-close" (click)="showClientsBalanceModal = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </header>
          <main class="modal-body" style="padding: 1.5rem;">
            <div class="search-wrapper mb-3" style="max-width: 100%; position: relative; margin-bottom: 1rem;">
              <span class="material-symbols-outlined search-icon" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #a0aec0; pointer-events: none;">search</span>
              <input type="text" [(ngModel)]="searchClientBalance" placeholder="Buscar cliente..." class="filter-input" style="width: 100%; padding: 0.5rem 0.5rem 0.5rem 2.2rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; outline: none;" />
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
              <table class="pro-table small" style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr style="border-bottom: 2px solid #edf2f7; text-align: left;">
                    <th style="padding: 8px; color: #4a5568; font-weight: 600;">Cliente</th>
                    <th style="padding: 8px; text-align: right; color: #4a5568; font-weight: 600;">Saldo Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngFor="let c of filteredClientsBalance" (click)="showClientsBalanceModal = false; navigateToSheets('cartera', c.cliente)" class="clickable" style="cursor: pointer; border-bottom: 1px solid #edf2f7;">
                    <td style="padding: 8px; color: #2d3748;">{{ c.cliente }}</td>
                    <td style="padding: 8px; text-align: right; font-weight: 700; color: #1e40af;">{{ formatMoney(c.saldo_total) }}</td>
                  </tr>
                  <tr *ngIf="filteredClientsBalance.length === 0">
                    <td colspan="2" class="text-center py-3" style="padding: 16px; text-align: center; color: #718096;">No se encontraron clientes con saldo.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </main>
        </div>
      </div>

      <div class="loading-overlay" *ngIf="isLoading && authService.getActiveRole() !== 'contable'">
        <div class="pro-spinner"></div>
        <p>Procesando Inteligencia Financiera...</p>
      </div>
    </div>
  `,
  styles: [`
    .dashboard-wrapper {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: #F4F7FE;
      position: relative;
    }

    .view-header {
      background: white;
      padding: 1.5rem 2rem;
      border-bottom: 1px solid #E2E8F0;
      display: grid;
      grid-template-columns: 1fr auto;
      grid-template-rows: auto auto;
      gap: 1.5rem;
      align-items: center;

      .title-area {
        h1 { margin: 0; font-size: 1.5rem; color: var(--primary); }
        p { margin: 4px 0 0 0; color: var(--text-muted); font-size: 0.85rem; }
      }
    }

    .header-filters {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    /* Using global pro-input-group */
    .search-box {
      position: relative;
      display: flex;
      align-items: center;
      .material-symbols-outlined {
        position: absolute; right: 10px; font-size: 18px; color: #A0AEC0;
        &.clear { cursor: pointer; &:hover { color: var(--danger); } }
      }
    }

    .header-actions {
      grid-column: span 2;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 0.5rem;

      .btn-group {
        display: flex;
        gap: 0.75rem;
        align-items: center;
      }
    }

    .pro-tabs {
      display: flex;
      background: #EDF2F7;
      padding: 4px;
      border-radius: 12px;
      gap: 4px;

      button {
        border: none;
        background: transparent;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #718096;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;

        .material-symbols-outlined { font-size: 18px; }
        &.active { background: white; color: var(--primary); box-shadow: var(--shadow-sm); }
        &:hover:not(.active) { background: rgba(255,255,255,0.5); }
      }
    }

    .content-viewport {
      flex-grow: 1;
      overflow-y: auto;
    }

    /* KPI Cards */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .pro-card {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1.5rem;
      cursor: pointer;
      
      .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        .material-symbols-outlined { font-size: 24px; }
      }

      .kpi-body {
        flex-grow: 1;
        label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .value { font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 2px 0; }
        .footer { font-size: 0.75rem; color: #718096; font-weight: 500; }
      }

      &.navy { .kpi-icon { background: #EBF4FF; color: var(--primary); } }
      &.cyan { .kpi-icon { background: #E0F2F1; color: var(--secondary); } }
      &.orange { .kpi-icon { background: #FFF7ED; color: var(--warning); } }
      &.red { .kpi-icon { background: #FFF5F5; color: var(--danger); } }
    }

    /* Dashboard Layout Grid */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 1.5rem;
    }

    .span-12 { grid-column: span 12; }
    .span-8 { grid-column: span 8; }
    .span-6 { grid-column: span 6; }
    .span-4 { grid-column: span 4; }

    .card-header {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #F7FAFC;
      h3 { font-size: 1rem; margin: 0; color: var(--primary); }
    }

    .chart-box {
      padding: 1.5rem;
      height: 300px;
      &.tall { height: 400px; }
      &.doughnut { height: 280px; display: flex; justify-content: center; }
    }

    .truncate { max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .empty-state {
        grid-column: span 12;
        padding: 5rem;
        text-align: center;
        background: white;
        border-radius: 20px;
        border: 2px dashed #E2E8F0;
        color: #A0AEC0;
        .material-symbols-outlined { font-size: 64px; margin-bottom: 1rem; }
    }

    .loading-overlay {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(244, 247, 254, 0.8);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      z-index: 100;
      p { margin-top: 1rem; font-weight: 600; color: var(--primary); }
    }

    .pro-spinner {
      width: 40px; height: 40px; border: 4px solid #E2E8F0; border-top: 4px solid var(--primary);
      border-radius: 50%; animation: spin 1s linear infinite;
    }

    /* Modal Backdrop and Card */
    .modal-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(15, 23, 42, 0.3);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1100;
    }

    .modal-card {
      background: white;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      border-radius: 16px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(226, 232, 240, 0.8);
      animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalSlideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      h3 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; }
    }

    .btn-close {
      background: transparent;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      color: #64748b;
      &:hover { color: #0f172a; }
    }

    .modal-body {
      padding: 1.5rem;
      overflow-y: auto;
    }

    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .spinning { animation: spin 1s linear infinite; }

    .dashboard-alert-section {
      padding: 0 2rem; margin-top: 1.5rem; margin-bottom: 2rem;
      .alert-card {
        display: flex; align-items: center; gap: 1.5rem; background: white; padding: 1.5rem; border-radius: 16px; border-left: 6px solid #E53E3E;
        .icon-side { width: 50px; height: 50px; border-radius: 12px; background: #FFF5F5; display: flex; align-items: center; justify-content: center; .material-symbols-outlined { color: #E53E3E; font-size: 28px; } }
        .text-side { flex-grow: 1; h3 { margin: 0; font-size: 1.1rem; color: #2D3748; } p { margin: 4px 0 0 0; font-size: 0.9rem; color: #718096; } }
        .btn-pro { display: flex; align-items: center; gap: 8px; }
      }
    }

    .shadow-sm { box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06); }
    .pulse { animation: alertPulse 1.5s infinite; }
    @keyframes alertPulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }

    .payer-cell {
      display: flex;
      flex-direction: column;
      gap: 2px;
      .name { font-weight: 600; color: #2D3748; }
      .due-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 4px;
        width: fit-content;
        text-transform: uppercase;
        &.vencido { background: #FED7D7; color: #C53030; }
        &.por-vencer { background: #FEEBC8; color: #C05621; }
        &.vigente { background: #EBF8FF; color: #2B6CB0; }
      }
    }
    .text-nowrap {
      white-space: nowrap !important;
    }
  `]
})
export class DashboardComponent implements OnInit {
  @ViewChild('dashboardContent') dashboardContent!: ElementRef;

  currentTab: string = 'cartera';
  isRefreshing = false;
  isLoading = true;
  isGeneratingPdf = false;
  showClientsBalanceModal = false;
  searchClientBalance = '';

  get filteredClientsBalance() {
    const list = this.stats?.cartera?.client_ranking || [];
    if (!this.searchClientBalance) return list;
    const term = this.searchClientBalance.toLowerCase();
    return list.filter((c: any) => c.cliente?.toLowerCase().includes(term));
  }

  stats: any = {
    cartera: null,
    factoring: null,
    confirming: null,
    compraventa: null
  };
  pendingCount: number = 0;
  pendingInternalDocs: number = 0;
  private apiUrl = `${environment.apiUrl}/dashboard/stats`;
  // Pagination & Search States
  tableSettings: any = {
    carteraRanking: { page: 1, pageSize: 5, search: '' },
    carteraDaily: { page: 1, pageSize: 5, search: '' },
    carteraDebtors: { page: 1, pageSize: 5, search: '' },
    pagosEntries: { page: 1, pageSize: 5, search: '' },
    factoringVencimientos: { page: 1, pageSize: 5, search: '' },
    confirmingVencimientos: { page: 1, pageSize: 5, search: '' },
    confirmingRendimientos: { page: 1, pageSize: 5, search: '' }
  };

  // Filters
  filterFechaInicio: string = '';
  filterFechaFin: string = '';
  filterCliente: string = '';

  // Chart: Actividad
  public activityChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600'] }]
  };

  // Chart: Ciudades
  public cityChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600'] }]
  };

  // Chart: Amortización
  public amortChartData: ChartData<'bar'> = {
    labels: [],
    datasets: [{
      data: [],
      label: 'Frecuencia de Plan',
      backgroundColor: 'rgba(94, 114, 228, 0.6)',
      borderColor: '#5e72e4',
      borderWidth: 1
    }]
  };

  // Chart: Mora
  public moraChartData: ChartData<'bar'> = {
    labels: [],
    datasets: [{
      data: [],
      label: 'Saldo en Mora',
      backgroundColor: 'rgba(245, 54, 92, 0.6)',
      borderColor: '#f5365c',
      borderWidth: 1
    }]
  };

  // Chart: Exposición por Pagador
  public exposureChartData: ChartData<'bar'> = {
    labels: [],
    datasets: [{
      data: [],
      label: 'Monto Expuesto',
      backgroundColor: '#5e72e4',
      borderRadius: 5
    }]
  };

  // Chart: Análisis de Emisores (Confirming)
  public emitterAnalysisChartData: ChartData<'pie'> = {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600']
    }]
  };

  // Chart: Tasa Media Emisor (Confirming)
  public emitterTasaChartData: ChartData<'bar'> = {
    labels: [],
    datasets: [{
      data: [],
      label: 'Tasa Factor',
      backgroundColor: '#5e72e4',
      borderRadius: 5
    }]
  };

  // Chart: Monto Pagado Timeline (Pagos)
  public paymentTimelineChartData: ChartData<'bar'> = {
    labels: [],
    datasets: [{
      data: [],
      label: 'Monto Recaudado',
      backgroundColor: '#5e72e4',
      borderColor: '#2b3d95',
      borderWidth: 1,
      borderRadius: 5
    }]
  };

  // Chart: Distribución por Cliente (Pagos)
  public paymentDistributionChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600']
    }]
  };

  // Chart: Pagos Diarios por Cliente
  public dailyPaymentsChartData: ChartData<'bar'> = {
    labels: [],
    datasets: []
  };

  // Chart: Distribución de Tasas (Factoring)
  public tasaDistributionChartData: ChartData<'pie'> = {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600']
    }]
  };

  // Chart: Promedio Ponderado por Pagador
  public weightedRatesChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c', '#8965e0', '#ffd600']
    }]
  };

  // Compraventa Charts
  public topVendedoresChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c'] }]
  };

  public topCompradoresChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c'] }]
  };

  // Pagos Compraventa Charts
  public topPagadoresPCChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c'] }]
  };

  public topClientesPCChartData: ChartData<'doughnut'> = {
    labels: [],
    datasets: [{ data: [], backgroundColor: ['#5e72e4', '#2dce89', '#fb6340', '#11cdef', '#f5365c'] }]
  };

  public pieChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'right' } }
  };

  public barChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    maintainAspectRatio: false,
    scales: { y: { beginAtZero: true } }
  };

  public horizontalBarChartOptions: ChartConfiguration['options'] = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true } }
  };

  public stackedBarChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
      legend: { position: 'bottom' },
      tooltip: { mode: 'index', intersect: false }
    },
    scales: { 
      x: { stacked: true },
      y: { stacked: true, beginAtZero: true } 
    }
  };

  constructor(private http: HttpClient, public router: Router, public authService: AuthService) { }

  ngOnInit() {
    if (this.authService.getActiveRole() === 'contable') {
      this.loadContableStats();
    } else {
      this.loadStats();
    }
  }

  loadContableStats() {
    this.isLoading = true;
    this.http.get<any>(`${environment.apiUrl}/uploads/pending-count`).subscribe(res => {
      this.pendingInternalDocs = res.contable;
      this.isLoading = false;
    });
  }

  setTab(tab: string) {
    this.currentTab = tab;
    // Map pagos to factoring key
    const dataKey = (tab === 'pagos') ? 'factoring' : tab;
    
    if (!this.stats[dataKey]) {
      this.loadStats();
    }
  }

  loadStats() {
    this.isRefreshing = true;
    // Only show full-screen loader if we have no data at all
    if (!this.stats) this.isLoading = true;

    const cat = this.currentTab;
    let params = `?t=${Date.now()}&categoria=${cat}`;
    if (this.filterFechaInicio) params += `&fecha_inicio=${this.filterFechaInicio}`;
    if (this.filterFechaFin) params += `&fecha_fin=${this.filterFechaFin}`;
    if (this.filterCliente) params += `&cliente=${this.filterCliente}`;

    // Also fetch pending count
    this.http.get<any>(`${environment.apiUrl}/uploads/pending-count`).subscribe(res => {
      // Note: Use AuthService role logic or just get it here
      const role = localStorage.getItem('active_role');
      if (role === 'operativo') this.pendingCount = res.operativo;
      else if (role === 'gerente') this.pendingCount = res.gerente;
      else this.pendingCount = res.total;
    });

    this.http.get(this.apiUrl + params).subscribe({
      next: (data: any) => {
        // Merge data into existing stats object
        Object.assign(this.stats, data);

        this.updateCharts();
        this.isRefreshing = false;
        this.isLoading = false;
      },
      error: (err) => {
        console.error('Error fetching dashboard stats:', err);
        this.isRefreshing = false;
        this.isLoading = false;
        // Optionally set a default empty stats object so the UI doesn't break
        if (!this.stats) {
          // We'll leave it undefined to show an error or empty state if we wanted,
          // but keeping it undefined means the chart html might hide. 
          // For now, let's just create a dummy object to stop errors if needed.
          // However the ngIf="!isLoading && stats" will keep it hidden.
        }
      }
    });
  }

  async exportToPdf() {
    this.isGeneratingPdf = true;
    const element = this.dashboardContent.nativeElement;

    // Preparation: Hide toolbar and adjust content for capture
    const toolbar = element.querySelector('.toolbar') as HTMLElement;
    const scrollContent = element.querySelector('.content-scroll') as HTMLElement;

    const originalToolbarDisplay = toolbar ? toolbar.style.display : '';
    const originalScrollPadding = scrollContent ? scrollContent.style.padding : '';

    if (toolbar) toolbar.style.display = 'none';
    if (scrollContent) scrollContent.style.padding = '0';

    const originalHeight = element.style.height;
    const originalOverflow = element.style.overflow;

    element.style.height = 'auto';
    element.style.overflow = 'visible';

    // Wait for layout and charts to settle
    await new Promise(resolve => setTimeout(resolve, 800));

    try {
      const canvas = await html2canvas(element, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#ffffff'
      });

      const imgData = canvas.toDataURL('image/png');
      const pdf = new jsPDF('p', 'mm', 'a4');

      const pdfWidth = pdf.internal.pageSize.getWidth();
      const pdfHeight = pdf.internal.pageSize.getHeight();

      const margin = 15;
      const headerHeight = 25;
      const footerHeight = 15;
      const printableWidth = pdfWidth - (margin * 2);
      const printableHeight = pdfHeight - headerHeight - footerHeight - margin; // Space for content per page

      const imgProps = pdf.getImageProperties(imgData);
      const imgScaledHeight = (imgProps.height * printableWidth) / imgProps.width;

      let heightLeft = imgScaledHeight;
      let currentPosition = 0; // Position in the source image
      let pageNumber = 1;

      while (heightLeft > 0) {
        if (pageNumber > 1) pdf.addPage();

        // --- DRAW HEADER ---
        pdf.setFillColor(248, 249, 250);
        pdf.rect(0, 0, pdfWidth, headerHeight, 'F');
        pdf.setTextColor(52, 71, 103);
        pdf.setFontSize(14);
        pdf.setFont('helvetica', 'bold');
        pdf.text(`REPORTE DE DASHBOARD - ${this.currentTab.toUpperCase()}`, margin, 12);

        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(103, 116, 142);
        const dateStr = new Date().toLocaleString();
        pdf.text(`Generado el: ${dateStr}`, margin, 18);
        pdf.setDrawColor(233, 236, 239);
        pdf.line(margin, 20, pdfWidth - margin, 20);

        // --- DRAW CONTENT SLICE ---
        const sliceY = headerHeight + 5; // Start content below header

        pdf.addImage(
          imgData,
          'PNG',
          margin,
          sliceY - currentPosition,
          printableWidth,
          imgScaledHeight,
          undefined,
          'FAST'
        );

        // --- DRAW COVER BLOCKS (to clean up overflows) ---
        pdf.setFillColor(248, 249, 250);
        pdf.rect(0, 0, pdfWidth, headerHeight, 'F');
        pdf.setTextColor(52, 71, 103);
        pdf.setFontSize(14);
        pdf.setFont('helvetica', 'bold');
        pdf.text(`REPORTE DE DASHBOARD - ${this.currentTab.toUpperCase()}`, margin, 12);
        pdf.setFontSize(8);
        pdf.text(`Generado el: ${dateStr}`, margin, 18);

        // --- DRAW FOOTER ---
        pdf.setFillColor(255, 255, 255);
        pdf.rect(0, pdfHeight - footerHeight, pdfWidth, footerHeight, 'F');
        pdf.setDrawColor(233, 236, 239);
        pdf.line(margin, pdfHeight - footerHeight, pdfWidth - margin, pdfHeight - footerHeight);
        pdf.setTextColor(103, 116, 142);
        pdf.setFontSize(8);
        pdf.text(`Página ${pageNumber}`, pdfWidth / 2, pdfHeight - 7, { align: 'center' });
        pdf.text('Factoring Softclass - Confidencial', margin, pdfHeight - 7);

        currentPosition += printableHeight;
        heightLeft -= printableHeight;
        pageNumber++;
      }

      pdf.save(`reporte_dashboard_${this.currentTab}_${new Date().getTime()}.pdf`);
    } catch (error) {
      console.error('Error generating PDF', error);
    } finally {
      // Revert styles
      if (toolbar) toolbar.style.display = originalToolbarDisplay;
      if (scrollContent) scrollContent.style.padding = originalScrollPadding;
      element.style.height = originalHeight;
      element.style.overflow = originalOverflow;
      this.isGeneratingPdf = false;
    }
  }

  updateCharts() {
    if (!this.stats) return;

    // CARTERA
    if (this.stats.cartera) {
      if (this.stats.cartera.actividad) {
        this.activityChartData.labels = this.stats.cartera.actividad.map((a: any) => a.sector_economico || 'Otros');
        this.activityChartData.datasets[0].data = this.stats.cartera.actividad.map((a: any) => parseFloat(a.total));
      }

      if (this.stats.cartera.ciudades) {
        this.cityChartData.labels = this.stats.cartera.ciudades.map((c: any) => c.ciudad || 'Desconocida');
        this.cityChartData.datasets[0].data = this.stats.cartera.ciudades.map((c: any) => parseFloat(c.total));
      }

      const amort = this.stats.cartera.amortizacion;
      if (amort) {
        const total = amort.reduce((sum: number, a: any) => sum + (a.count || 0), 0);
        this.amortChartData.labels = amort.map((a: any) => a.plan_amortizacion || 'N/A');
        this.amortChartData.datasets[0].data = amort.map((a: any) => total > 0 ? (a.count / total * 100).toFixed(1) : 0);
      }

      if (this.stats.cartera.debtors) {
        this.moraChartData.labels = this.stats.cartera.debtors.map((d: any) => d.cliente || 'Otros');
        this.moraChartData.datasets[0].data = this.stats.cartera.debtors.map((d: any) => parseFloat(d.valor_mora));
      }
    }

    // FACTORING & PAGOS
    if (this.stats.factoring) {
      if (this.stats.factoring.exposure_by_payer) {
        this.exposureChartData.labels = this.stats.factoring.exposure_by_payer.map((e: any) => e.pagador);
        this.exposureChartData.datasets[0].data = this.stats.factoring.exposure_by_payer.map((e: any) => parseFloat(e.total));
      }

      if (this.stats.factoring.payment_timeline) {
        this.paymentTimelineChartData.labels = this.stats.factoring.payment_timeline.map((t: any) => {
          const d = new Date(t.fecha);
          return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
        });
        this.paymentTimelineChartData.datasets[0].data = this.stats.factoring.payment_timeline.map((t: any) => parseFloat(t.total));
      }

      if (this.stats.factoring.payment_distribution) {
        this.paymentDistributionChartData.labels = this.stats.factoring.payment_distribution.map((d: any) => d.cliente);
        this.paymentDistributionChartData.datasets[0].data = this.stats.factoring.payment_distribution.map((d: any) => parseFloat(d.total));
      }

      if (this.stats.factoring.tasa_distribution) {
        this.tasaDistributionChartData.labels = this.stats.factoring.tasa_distribution.map((t: any) => t.tasa + '%');
        this.tasaDistributionChartData.datasets[0].data = this.stats.factoring.tasa_distribution.map((t: any) => parseFloat(t.total));
      }

      if (this.stats.factoring.weighted_by_payer) {
        this.weightedRatesChartData.labels = this.stats.factoring.weighted_by_payer.map((w: any) => w.pagador);
        this.weightedRatesChartData.datasets[0].data = this.stats.factoring.weighted_by_payer.map((w: any) => parseFloat(w.weighted_avg));
      }

      if (this.stats.factoring.daily_payments) {
        const daily = this.stats.factoring.daily_payments;
        const uniqueDates = [...new Set(daily.map((d: any) => d.fecha))].sort() as string[];
        const uniqueClients = [...new Set(daily.map((d: any) => d.cliente))] as string[];

        this.dailyPaymentsChartData.labels = uniqueDates.map(d => new Date(d).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' }));
        this.dailyPaymentsChartData.datasets = uniqueClients.map((client, idx) => {
          return {
            label: client,
            data: uniqueDates.map(date => {
              const match = daily.find((d: any) => d.fecha === date && d.cliente === client);
              return match ? parseFloat(match.total) : 0;
            }),
            backgroundColor: (this.activityChartData.datasets[0] as any).backgroundColor![idx % 7]
          };
        });
      }
    }

    // CONFIRMING
    if (this.stats.confirming) {
      if (this.stats.confirming.analisis_emisores) {
        this.emitterAnalysisChartData.labels = this.stats.confirming.analisis_emisores.map((e: any) => e.emisor);
        this.emitterAnalysisChartData.datasets[0].data = this.stats.confirming.analisis_emisores.map((e: any) => parseFloat(e.total));
      }

      if (this.stats.confirming.tasa_media_emisor) {
        this.emitterTasaChartData.labels = this.stats.confirming.tasa_media_emisor.map((e: any) => e.emisor);
        this.emitterTasaChartData.datasets[0].data = this.stats.confirming.tasa_media_emisor.map((e: any) => parseFloat(e.avg_tasa));
      }
    }

    // COMPRAVENTA
    if (this.stats.compraventa) {
      if (this.stats.compraventa.top_vendedores) {
        this.topVendedoresChartData.labels = this.stats.compraventa.top_vendedores.map((v: any) => v.vendedor);
        this.topVendedoresChartData.datasets[0].data = this.stats.compraventa.top_vendedores.map((v: any) => parseFloat(v.total));
      }
      if (this.stats.compraventa.top_compradores) {
        this.topCompradoresChartData.labels = this.stats.compraventa.top_compradores.map((c: any) => c.comprador);
        this.topCompradoresChartData.datasets[0].data = this.stats.compraventa.top_compradores.map((c: any) => parseFloat(c.total));
      }
    }

    // PAGOS COMPRAVENTA
    if (this.stats.pagos_compraventa) {
      if (this.stats.pagos_compraventa.top_pagadores) {
        this.topPagadoresPCChartData.labels = this.stats.pagos_compraventa.top_pagadores.map((p: any) => p.pagador);
        this.topPagadoresPCChartData.datasets[0].data = this.stats.pagos_compraventa.top_pagadores.map((p: any) => parseFloat(p.total));
      }
      if (this.stats.pagos_compraventa.top_clientes) {
        this.topClientesPCChartData.labels = this.stats.pagos_compraventa.top_clientes.map((c: any) => c.cliente);
        this.topClientesPCChartData.datasets[0].data = this.stats.pagos_compraventa.top_clientes.map((c: any) => parseFloat(c.total));
      }
    }
  }

  onCityClick(event: any) {
    if (event.active && event.active.length > 0) {
      const index = event.active[0].index;
      const label = this.cityChartData.labels![index] as string;
      this.navigateToSheets('cartera', label);
    }
  }

  onActivityClick(event: any) {
    if (event.active && event.active.length > 0) {
      const index = event.active[0].index;
      const label = this.activityChartData.labels![index] as string;
      this.navigateToSheets('cartera', label);
    }
  }

  onAmortClick(event: any) {
    if (event.active && event.active.length > 0) {
      const index = event.active[0].index;
      const label = this.amortChartData.labels![index] as string;
      this.navigateToSheets('cartera', label);
    }
  }

  onMoraChartClick(event: any) {
    if (event.active && event.active.length > 0) {
      const index = event.active[0].index;
      const label = this.moraChartData.labels![index] as string;
      this.navigateToSheets('cartera', label);
    }
  }

  onWeightedRateClick(event: any) {
    if (event.active && event.active.length > 0) {
      const index = event.active[0].index;
      const label = this.weightedRatesChartData.labels![index] as string;
      this.navigateToSheets('op', label);
    }
  }

  navigateToSheets(category: string, filterText: string = '', filter: string = '') {
    this.router.navigate(['/sheets'], {
      queryParams: {
        categoria: category,
        q: filterText,
        filter: filter
      }
    });
  }

  // Data Processing Helpers
  getProcessedData(data: any[], settingsKey: string) {
    if (!data) return { items: [], total: 0, pages: 1 };

    const settings = this.tableSettings[settingsKey];

    // 1. Search
    let filtered = data;
    if (settings.search) {
      const term = settings.search.toLowerCase();
      filtered = data.filter(item => {
        return Object.values(item).some(val =>
          val !== null && val !== undefined && String(val).toLowerCase().includes(term)
        );
      });
    }

    // 2. Pagination
    const total = filtered.length;
    const pageSize = settings.pageSize === 'all' ? total : parseInt(settings.pageSize);
    const pages = Math.ceil(total / pageSize) || 1;

    // Adjust current page if out of bounds
    if (settings.page > pages) settings.page = pages;
    if (settings.page < 1) settings.page = 1;

    const start = (settings.page - 1) * pageSize;
    const items = settings.pageSize === 'all' ? filtered : filtered.slice(start, start + pageSize);

    return { items, total, pages };
  }

  changePage(settingsKey: string, delta: number) {
    const settings = this.tableSettings[settingsKey];
    settings.page += delta;
  }

  formatMoney(value: any): string {
    if (value === null || value === undefined || value === '') return '-';

    let cleanVal = value;
    if (typeof value === 'string') {
      // Strip out any existing currency symbols, commas, or spaces if n8n ingested it roughly
      cleanVal = value.replace(/[\$,\s]/g, '');
    }

    const num = Number(cleanVal);
    if (isNaN(num)) return value; // Return original string if it is completely unparseable

    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(num);
  }

  safeDate(value: any, format: string): string {
    if (!value || value === '0000-00-00' || value === '0000-00-00 00:00:00') return '-';
    try {
      const date = new Date(value);
      if (isNaN(date.getTime())) return '-';

      const day = date.getDate().toString().padStart(2, '0');
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const year = date.getFullYear();

      if (format === 'dd/MM/yyyy') {
        return `${day}/${month}/${year}`;
      } else if (format === 'dd MMM yyyy') {
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${day} ${months[date.getMonth()]} ${year}`;
      }
      return value;
    } catch (e) {
      return '-';
    }
  }
}