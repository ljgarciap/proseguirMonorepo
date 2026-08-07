import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { BaseChartDirective, provideCharts, withDefaultRegisterables } from 'ng2-charts';
import { ChartData } from 'chart.js';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import Swal from 'sweetalert2';

export interface FilaConcepto {
  label: string;
  clave: string;
}

const ACTIVO_CORRIENTE: FilaConcepto[] = [
  { label: 'Caja y bancos', clave: 'caja_bancos' },
  { label: 'Cuentas por cobrar comerciales', clave: 'cxc_comerciales' },
  { label: 'CXC vinculados económicos', clave: 'cxc_vinculados_economicos' },
  { label: 'Impuestos corrientes', clave: 'impuestos_corrientes' },
  { label: 'Otras cuentas por cobrar', clave: 'otras_cxc' },
  { label: 'Inventarios', clave: 'inventarios' },
  { label: 'Gastos pagados por anticipado', clave: 'gastos_pagados_anticipado' },
];

const ACTIVO_NO_CORRIENTE: FilaConcepto[] = [
  { label: 'Propiedad, planta y equipo', clave: 'propiedad_planta_equipo' },
  { label: 'Construcciones en curso', clave: 'construcciones_en_curso' },
  { label: 'Inversiones en subsidiarias', clave: 'inversiones_subsidiarias' },
  { label: 'Otras cuentas por cobrar no corrientes', clave: 'otras_cxc_no_corrientes' },
  { label: 'Inversión en proyectos solares', clave: 'inversion_proyectos_solares' },
  { label: 'Otros activos no financieros', clave: 'otros_activos_no_financieros' },
  { label: 'Impuesto diferido', clave: 'impuesto_diferido' },
  { label: 'Gastos pagados por anticipado', clave: 'gastos_pagados_anticipado_nc' },
  { label: 'Inversión en posicionamiento de marca', clave: 'inversion_posicionamiento_marca' },
  { label: 'Valorizaciones', clave: 'valorizaciones' },
  { label: 'Inversiones en sociedades', clave: 'inversiones_sociedades' },
  { label: 'CXC vinculados económicos', clave: 'cxc_vinculados_economicos_nc' },
  { label: 'Impuesto a la renta', clave: 'impuesto_renta_activo' },
  { label: 'Otros activos no corrientes', clave: 'otros_activos_no_corrientes' },
];

const PASIVO_CORRIENTE: FilaConcepto[] = [
  { label: 'Obligaciones financieras', clave: 'obligaciones_financieras' },
  { label: 'Obligaciones con particulares', clave: 'obligaciones_particulares' },
  { label: 'Proveedores', clave: 'proveedores' },
  { label: 'Otras cuentas por pagar', clave: 'otras_cxp' },
  { label: 'Impuestos corrientes', clave: 'impuestos_corrientes_pasivo' },
  { label: 'Beneficios a empleados', clave: 'beneficios_empleados' },
  { label: 'Otros pasivos', clave: 'otros_pasivos' },
  { label: 'Pasivos estimados y provisiones', clave: 'pasivos_estimados_provisiones' },
];

const PASIVO_NO_CORRIENTE: FilaConcepto[] = [
  { label: 'Obligaciones financieras de largo plazo', clave: 'obligaciones_financieras_lp' },
  { label: 'Obligaciones con vinculados de largo plazo', clave: 'obligaciones_vinculados_lp' },
  { label: 'Impuesto diferido', clave: 'impuesto_diferido_pasivo' },
];

const PATRIMONIO_CONCEPTOS: FilaConcepto[] = [
  { label: 'Capital suscrito y pagado', clave: 'capital_suscrito_pagado' },
  { label: 'Reservas', clave: 'reservas' },
  { label: 'Superávit por revaluación de activos', clave: 'superavit_revaluacion_activos' },
  { label: 'Resultados acumulados', clave: 'resultados_acumulados' },
  { label: 'Resultados acumulados por NIIF', clave: 'resultados_acumulados_niif' },
  { label: 'Resultados del ejercicio', clave: 'resultados_ejercicio' },
  { label: 'ORI - Conversión de moneda extranjera', clave: 'ori_conversion_moneda' },
];

const CARTERA_CONCEPTOS: FilaConcepto[] = [
  { label: 'Cartera corriente', clave: 'cartera_corriente' },
  { label: 'Vencida entre 0 y 60 días', clave: 'vencida_0_60' },
  { label: 'Vencida con más de 60 días', clave: 'vencida_mas_60' },
  { label: 'Deudas de difícil cobro', clave: 'dificil_cobro' },
  { label: 'Provisión de cartera', clave: 'provision' },
];

// SCRUM-187 (segunda entrega): Estado de Resultados (antes "Utilidad Neta")
// es una cascada de 6 buckets con rol de signo fijo (ver
// AnalisisFinancieroCalculoService::calcularUtilidadNeta) — cada uno admite
// Agregar fila/ocultar/reordenar igual que el resto de secciones, sin tocar
// la fórmula de la cascada en sí.
const INGRESOS_ORDINARIOS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Ingresos ordinarios', clave: 'ingresos_ordinarios' },
];
const COSTO_VENTAS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Costo de ventas', clave: 'costo_ventas' },
];
const GASTOS_OPERACIONALES_CONCEPTOS: FilaConcepto[] = [
  { label: 'Gastos de administración', clave: 'gastos_administracion' },
  { label: 'Gastos de ventas y distribución', clave: 'gastos_ventas_distribucion' },
];
const OTROS_INGRESOS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Ingreso financiero', clave: 'ingreso_financiero' },
  { label: 'Otros ingresos', clave: 'otros_ingresos' },
  { label: 'Ingresos método de participación', clave: 'ingresos_metodo_participacion' },
];
const OTROS_GASTOS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Gasto financiero', clave: 'gasto_financiero' },
  { label: 'Intereses', clave: 'intereses' },
  { label: 'Otros gastos', clave: 'otros_gastos' },
];
const IMPUESTOS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Impuesto a las ganancias', clave: 'impuesto_ganancias' },
  { label: 'Impuesto de renta', clave: 'impuesto_renta' },
];

type Tab = 'activo' | 'pasivo' | 'patrimonio' | 'utilidad_neta' | 'ori' | 'cartera' | 'resumen';

interface FilaRenderizable extends FilaConcepto {
  esCustom: boolean;
}

/**
 * SCRUM-162 — grupos que soportan filas custom ad-hoc (los mismos que ya
 * renderizan *ngFor sobre un array de FilaConcepto) y a qué pestaña/sección
 * JSON pertenece cada uno. `_custom` viaja dentro de la sección persistida
 * (`inputs[tab]._custom`) como lista de `{grupo, clave, label}` — el VALOR
 * de cada fila custom se guarda en `inputs[tab][clave][anio]`, igual que
 * cualquier concepto fijo (contrato definido en AnalisisFinancieroCalculoService).
 */
const GRUPOS_POR_TAB: Record<string, string[]> = {
  activo: ['activo_corriente', 'activo_no_corriente'],
  pasivo: ['pasivo_corriente', 'pasivo_no_corriente'],
  patrimonio: ['patrimonio'],
  cartera: ['cartera'],
  utilidad_neta: ['ingresos_ordinarios', 'costo_ventas', 'gastos_operacionales', 'otros_ingresos', 'otros_gastos', 'impuestos'],
};

const CONCEPTOS_FIJOS_POR_GRUPO: Record<string, FilaConcepto[]> = {
  activo_corriente: ACTIVO_CORRIENTE,
  activo_no_corriente: ACTIVO_NO_CORRIENTE,
  pasivo_corriente: PASIVO_CORRIENTE,
  pasivo_no_corriente: PASIVO_NO_CORRIENTE,
  patrimonio: PATRIMONIO_CONCEPTOS,
  cartera: CARTERA_CONCEPTOS,
  ingresos_ordinarios: INGRESOS_ORDINARIOS_CONCEPTOS,
  costo_ventas: COSTO_VENTAS_CONCEPTOS,
  gastos_operacionales: GASTOS_OPERACIONALES_CONCEPTOS,
  otros_ingresos: OTROS_INGRESOS_CONCEPTOS,
  otros_gastos: OTROS_GASTOS_CONCEPTOS,
  impuestos: IMPUESTOS_CONCEPTOS,
};

@Component({
  selector: 'app-analisis-financiero-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, MilesSeparatorDirective, BaseChartDirective],
  providers: [provideCharts(withDefaultRegisterables())],
  templateUrl: './analisis-financiero-detalle.component.html',
  styleUrls: ['./analisis-financiero-detalle.component.css']
})
export class AnalisisFinancieroDetalleComponent implements OnInit, OnDestroy {
  readonly activoCorriente = ACTIVO_CORRIENTE;
  readonly activoNoCorriente = ACTIVO_NO_CORRIENTE;
  readonly pasivoCorriente = PASIVO_CORRIENTE;
  readonly pasivoNoCorriente = PASIVO_NO_CORRIENTE;
  readonly patrimonioConceptos = PATRIMONIO_CONCEPTOS;
  readonly carteraConceptos = CARTERA_CONCEPTOS;

  creditoId!: number;
  credito: any = null;
  analisis: any = null;
  calculado: any = null;
  loading = false;
  activeRole = '';
  activeTab: Tab = 'activo';

  // Inputs crudos por pestaña — hidratados desde `analisis.*` (lo persistido).
  inputs: Record<string, any> = { activo: {}, pasivo: {}, patrimonio: {}, utilidad_neta: {}, ori: {}, cartera: {} };

  // SCRUM-162 — filas custom agregadas por el usuario, indexadas por grupo.
  customFilas: Record<string, FilaConcepto[]> = {
    activo_corriente: [], activo_no_corriente: [],
    pasivo_corriente: [], pasivo_no_corriente: [],
    patrimonio: [], cartera: [],
    ingresos_ordinarios: [], costo_ventas: [], gastos_operacionales: [],
    otros_ingresos: [], otros_gastos: [], impuestos: [],
  };

  observaciones = '';
  anioInicial: number | null = null;
  cantidadAnios: number = 2;
  subiendoAdjunto = false;

  carteraChartData: Record<number, ChartData<'doughnut'>> = {};
  carteraChartOptions = { plugins: { legend: { display: false } } };

  private autoguardadoSubject = new Subject<void>();
  private autoguardadoSub?: Subscription;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private http: HttpClient,
    public authService: AuthService
  ) {}

  ngOnInit(): void {
    this.activeRole = this.authService.getActiveRole() || '';
    this.creditoId = Number(this.route.snapshot.paramMap.get('creditoId'));
    this.cargar();

    this.autoguardadoSub = this.autoguardadoSubject.pipe(debounceTime(500)).subscribe(() => {
      if (this.puedeEditar) this.guardarBorrador(true);
    });
  }

  ngOnDestroy(): void {
    this.autoguardadoSub?.unsubscribe();
  }

  onCampoCambiado(): void {
    this.autoguardadoSubject.next();
  }

  cargar(): void {
    this.loading = true;
    this.http.get<any>(`${environment.apiUrl}/analisis-financiero/${this.creditoId}`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data.credito;
        this.analisis = data.analisis;
        this.calculado = data.calculado;
        this.hidratar();
        this.actualizarGraficoCartera();
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo cargar el análisis financiero.', 'error')
          .then(() => this.router.navigate(['/analisis-financiero']));
      }
    });
  }

  // PHP no distingue un objeto vacío de un array vacío: {} enviado al
  // backend vuelve como json_decode(..., true) === [] y se persiste/serializa
  // de vuelta como "[]", no "{}". Si se interpreta ese "[]" como ya-tiene-datos
  // (era truthy) los inputs quedaban sobre un Array real, y las claves de
  // concepto que se le asignan después se pierden en el próximo
  // JSON.stringify (Array solo serializa índices numéricos).
  private normalizarSeccion(valor: any): any {
    return valor && !Array.isArray(valor) ? valor : {};
  }

  private hidratar(): void {
    this.inputs = {
      activo: this.normalizarSeccion(this.analisis?.activo),
      pasivo: this.normalizarSeccion(this.analisis?.pasivo),
      patrimonio: this.normalizarSeccion(this.analisis?.patrimonio),
      utilidad_neta: this.normalizarSeccion(this.analisis?.utilidad_neta),
      ori: this.normalizarSeccion(this.analisis?.ori),
      cartera: this.normalizarSeccion(this.analisis?.cartera),
    };
    this.observaciones = this.analisis?.observaciones || '';
    this.anioInicial = this.analisis?.anio_inicial ?? (new Date().getFullYear() - 1);
    this.cantidadAnios = this.analisis?.cantidad_anios ?? 2;
    this.hidratarCustomFilas();
  }

  // SCRUM-162 — reconstruye `customFilas` (por grupo) a partir de la lista
  // `_custom` que viene dentro de cada sección persistida. Los valores en sí
  // ya quedaron en `this.inputs[tab][clave]` porque `_custom` vive DENTRO de
  // esa misma sección (no hace falta copiarlos aparte).
  private hidratarCustomFilas(): void {
    for (const grupo of Object.keys(this.customFilas)) {
      this.customFilas[grupo] = [];
    }
    for (const tab of Object.keys(GRUPOS_POR_TAB)) {
      const lista = this.inputs[tab]?.['_custom'];
      if (!Array.isArray(lista)) continue;
      for (const fila of lista) {
        if (!fila || typeof fila.clave !== 'string' || !this.customFilas[fila.grupo]) continue;
        this.customFilas[fila.grupo].push({ label: fila.label || fila.clave, clave: fila.clave });
      }
    }
  }

  // SCRUM-162 — abre un prompt para el label de la nueva fila, genera una
  // clave (slug) única, la agrega al grupo y sincroniza `_custom` en el
  // payload de la sección. Las filas custom son ad-hoc por informe (no
  // salen de un catálogo reutilizable — decisión de negocio confirmada).
  agregarFila(grupo: string, tab: string): void {
    if (!this.puedeEditar) return;

    Swal.fire({
      title: 'Agregar fila',
      input: 'text',
      inputPlaceholder: 'Nombre del concepto',
      showCancelButton: true,
      confirmButtonText: 'Agregar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8',
      inputValidator: (value) => (!value || !value.trim()) ? 'Escribe un nombre para el concepto' : undefined,
    }).then(result => {
      if (!result.isConfirmed) return;
      const label = (result.value as string).trim();
      const clave = this.generarClaveCustom(grupo, label);
      this.customFilas[grupo].push({ label, clave });
      this.sincronizarCustomEnInputs(tab);
      this.onCampoCambiado();
    });
  }

  eliminarFila(grupo: string, tab: string, clave: string): void {
    if (!this.puedeEditar) return;
    this.customFilas[grupo] = this.customFilas[grupo].filter(f => f.clave !== clave);
    if (this.inputs[tab]?.[clave]) delete this.inputs[tab][clave];
    this.sincronizarCustomEnInputs(tab);
    this.onCampoCambiado();
  }

  private generarClaveCustom(grupo: string, label: string): string {
    const slug = label
      .toLowerCase()
      .normalize('NFD').replace(/[^\x00-\x7f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '') || 'concepto';
    const sufijo = Date.now().toString(36);
    return `custom_${grupo}_${slug}_${sufijo}`.slice(0, 80);
  }

  // Reconstruye `inputs[tab]._custom` a partir de `customFilas` de TODOS los
  // grupos que pertenecen a esa pestaña (activo/pasivo tienen 2 grupos).
  private sincronizarCustomEnInputs(tab: string): void {
    const grupos = GRUPOS_POR_TAB[tab] || [];
    const lista: any[] = [];
    for (const grupo of grupos) {
      for (const fila of this.customFilas[grupo]) {
        lista.push({ grupo, clave: fila.clave, label: fila.label });
      }
    }
    this.inputs[tab]['_custom'] = lista;
  }

  // SCRUM-187 (segunda entrega) — "eliminar cualquier ítem, no solo los
  // agregados con Agregar fila": un concepto FIJO no se borra del catálogo
  // (`CONCEPTOS_FIJOS_POR_GRUPO`, compartido por todos los créditos), se
  // oculta solo en ESTE análisis vía `inputs[tab]._ocultos` (lista plana de
  // claves, decisión de Luis: por análisis, no catálogo global). Una fila
  // custom se sigue eliminando de verdad (ver `eliminarFila`).
  ocultarFila(tab: string, clave: string): void {
    if (!this.puedeEditar) return;
    const ocultos: string[] = this.inputs[tab]['_ocultos'] || [];
    if (!ocultos.includes(clave)) {
      this.inputs[tab]['_ocultos'] = [...ocultos, clave];
      this.onCampoCambiado();
    }
  }

  restaurarFila(tab: string, clave: string): void {
    if (!this.puedeEditar) return;
    const ocultos: string[] = this.inputs[tab]['_ocultos'] || [];
    this.inputs[tab]['_ocultos'] = ocultos.filter(c => c !== clave);
    this.onCampoCambiado();
  }

  // Conceptos (fijos de cualquier grupo de la pestaña + custom) ocultos en
  // ESTE análisis — para el link "Mostrar ocultos" de cada pestaña.
  filasOcultas(tab: string): FilaConcepto[] {
    const ocultos: string[] = this.inputs[tab]?.['_ocultos'] || [];
    if (!ocultos.length) return [];
    const grupos = GRUPOS_POR_TAB[tab] || [];
    const todas: FilaConcepto[] = [];
    for (const grupo of grupos) {
      todas.push(...(CONCEPTOS_FIJOS_POR_GRUPO[grupo] || []));
      todas.push(...(this.customFilas[grupo] || []));
    }
    return ocultos
      .map(clave => todas.find(f => f.clave === clave))
      .filter((f): f is FilaConcepto => !!f);
  }

  // SCRUM-187 (segunda entrega) — filas fijas + custom de un grupo, ya
  // filtradas por `_ocultos` y en el orden persistido (`inputs[tab]._orden`,
  // por grupo). Sin orden guardado (análisis previos a esta feature, o
  // grupo recién creado) cae al orden natural: fijas primero, custom al
  // final — el mismo comportamiento de siempre.
  filasOrdenadas(grupo: string, tab: string): FilaRenderizable[] {
    const fijas: FilaRenderizable[] = (CONCEPTOS_FIJOS_POR_GRUPO[grupo] || []).map(f => ({ ...f, esCustom: false }));
    const custom: FilaRenderizable[] = (this.customFilas[grupo] || []).map(f => ({ ...f, esCustom: true }));
    const ocultos: string[] = this.inputs[tab]?.['_ocultos'] || [];
    const todas = [...fijas, ...custom].filter(f => !ocultos.includes(f.clave));

    const orden: string[] | undefined = this.inputs[tab]?.['_orden']?.[grupo];
    if (!Array.isArray(orden) || orden.length === 0) return todas;

    const porClave = new Map(todas.map(f => [f.clave, f]));
    const resultado: FilaRenderizable[] = [];
    for (const clave of orden) {
      const f = porClave.get(clave);
      if (f) {
        resultado.push(f);
        porClave.delete(clave);
      }
    }
    // Fila nueva (agregada después de guardar el orden) o legacy sin orden: al final.
    for (const f of todas) {
      if (porClave.has(f.clave)) resultado.push(f);
    }
    return resultado;
  }

  // `filasOrdenadas` construye objetos nuevos en cada llamada (spread de
  // FilaConcepto) — sin trackBy, Angular ve identidades distintas en cada
  // ciclo de detección de cambios y destruye/recrea el DOM de la fila
  // completa (perdiendo el foco del input en cada tecla, y con potencial de
  // colgar la pestaña en tablas grandes). trackBy por clave lo evita.
  trackByClave(_index: number, fila: FilaConcepto): string {
    return fila.clave;
  }

  puedeMoverFila(grupo: string, tab: string, clave: string, direccion: -1 | 1): boolean {
    const lista = this.filasOrdenadas(grupo, tab);
    const idx = lista.findIndex(f => f.clave === clave);
    const nuevoIdx = idx + direccion;
    return idx >= 0 && nuevoIdx >= 0 && nuevoIdx < lista.length;
  }

  moverFila(grupo: string, tab: string, clave: string, direccion: -1 | 1): void {
    if (!this.puedeEditar) return;
    const claves = this.filasOrdenadas(grupo, tab).map(f => f.clave);
    const idx = claves.indexOf(clave);
    const nuevoIdx = idx + direccion;
    if (idx < 0 || nuevoIdx < 0 || nuevoIdx >= claves.length) return;

    [claves[idx], claves[nuevoIdx]] = [claves[nuevoIdx], claves[idx]];
    if (!this.inputs[tab]['_orden']) this.inputs[tab]['_orden'] = {};
    this.inputs[tab]['_orden'][grupo] = claves;
    this.onCampoCambiado();
  }

  get anios(): number[] {
    return this.calculado?.anios || [];
  }

  get esConfirmado(): boolean {
    return this.analisis?.estado === 'confirmado';
  }

  get puedeEditar(): boolean {
    if (!this.credito || this.esConfirmado) return false;
    return this.activeRole === 'coordinador_comercial' || this.activeRole === 'superadmin';
  }

  get puedeDescargar(): boolean {
    return !!this.analisis && this.calculado?.resumen?.total_activo > 0;
  }

  cambiarTab(tab: Tab): void {
    this.activeTab = tab;
  }

  campoInput(tab: string, clave: string, anio: number): number | null {
    return this.inputs[tab]?.[clave]?.[anio] ?? null;
  }

  setCampoInput(tab: string, clave: string, anio: number, valor: number | null): void {
    if (!this.inputs[tab][clave]) this.inputs[tab][clave] = {};
    this.inputs[tab][clave][anio] = valor;
  }

  estructural(seccion: string, clave: string, anio: number): number | null {
    return this.calculado?.[seccion]?.estructural?.[clave]?.[anio] ?? null;
  }

  variacion(seccion: string, clave: string, anio: number): number | null {
    return this.calculado?.[seccion]?.variacion?.[clave]?.[anio] ?? null;
  }

  valorCalculado(seccion: string, clave: string, anio: number): number {
    return this.calculado?.[seccion]?.[clave]?.[anio] ?? 0;
  }

  fmtPct(value: number | null): string {
    if (value === null || value === undefined) return 'N/A';
    return (value * 100).toFixed(2) + '%';
  }

  actualizarAnios(): void {
    if (!this.anioInicial || this.cantidadAnios < 2 || this.cantidadAnios > 3) return;
    this.guardarBorrador(true);
  }

  private actualizarGraficoCartera(): void {
    const composicion = this.calculado?.cartera?.composicion_grafico;
    if (!composicion) return;

    this.carteraChartData = {};
    for (const anio of this.anios) {
      const data: number[] = [];
      const labels: string[] = [];
      for (const fila of this.carteraConceptos) {
        if (fila.clave === 'provision') continue;
        labels.push(fila.label);
        data.push(this.calculado.cartera.valores[fila.clave]?.[anio] || 0);
      }
      this.carteraChartData[anio] = {
        labels,
        datasets: [{ data, backgroundColor: ['#3b82f6', '#ef4444', '#84cc16', '#a855f7', '#f59e0b'] }],
      };
    }
  }

  private payload(): any {
    return {
      anio_inicial: this.anioInicial,
      cantidad_anios: this.cantidadAnios,
      activo: this.inputs['activo'],
      pasivo: this.inputs['pasivo'],
      patrimonio: this.inputs['patrimonio'],
      utilidad_neta: this.inputs['utilidad_neta'],
      ori: this.inputs['ori'],
      cartera: this.inputs['cartera'],
      observaciones: this.observaciones,
    };
  }

  guardarBorrador(silencioso = false): void {
    this.http.put(`${environment.apiUrl}/analisis-financiero/${this.creditoId}/borrador`, this.payload(), {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data: any) => {
        // OJO: no llamar hidratar() acá. Esta respuesta puede llegar después
        // de que el usuario ya siguió escribiendo en otro campo (autoguardado
        // silencioso, sin esperar el roundtrip) — re-hidratar `inputs` desde
        // lo que el servidor tenía AL MOMENTO DE ESTE REQUEST borraría esos
        // cambios más nuevos. `this.inputs` (estado local) ya es la fuente de
        // verdad de lo que el usuario está viendo; solo se refresca lo que el
        // backend calcula (totales/porcentajes) y el estado del análisis.
        this.analisis.estado = data.analisis.estado;
        this.analisis.updated_at = data.analisis.updated_at;
        this.calculado = data.calculado;
        this.actualizarGraficoCartera();
        if (!silencioso) {
          Swal.fire('Guardado', 'El borrador del análisis financiero se guardó correctamente.', 'success');
        }
      },
      error: (err) => {
        if (!silencioso) {
          Swal.fire('Error', err.error?.message || 'No se pudo guardar el borrador.', 'error');
        }
      }
    });
  }

  confirmar(): void {
    Swal.fire({
      title: '¿Confirmar análisis financiero?',
      text: '¿Confirma que la información está completa y desea continuar el flujo hacia el Comité de Crédito?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, confirmar y continuar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.http.post(`${environment.apiUrl}/analisis-financiero/${this.creditoId}/confirmar`, this.payload(), {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: () => {
          Swal.fire('¡Confirmado!', 'El análisis financiero se confirmó correctamente.', 'success')
            .then(() => this.router.navigate(['/analisis-financiero']));
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'No se pudo confirmar el análisis financiero.', 'error');
        }
      });
    });
  }

  // SCRUM-175 — adjuntar/eliminar soportes libres en la pestaña Resumen.
  subirAdjunto(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('archivo', file);

    this.subiendoAdjunto = true;
    this.http.post(`${environment.apiUrl}/analisis-financiero/${this.creditoId}/adjuntos`, formData, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data: any) => {
        this.analisis = data.analisis;
        this.subiendoAdjunto = false;
        input.value = '';
      },
      error: (err) => {
        this.subiendoAdjunto = false;
        input.value = '';
        Swal.fire('Error', err.error?.message || 'No se pudo adjuntar el archivo.', 'error');
      }
    });
  }

  eliminarAdjunto(index: number): void {
    Swal.fire({
      title: '¿Eliminar adjunto?',
      text: 'Esta acción no se puede deshacer.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#dc2626'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.http.delete(`${environment.apiUrl}/analisis-financiero/${this.creditoId}/adjuntos/${index}`, {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: (data: any) => { this.analisis = data.analisis; },
        error: (err) => Swal.fire('Error', err.error?.message || 'No se pudo eliminar el adjunto.', 'error')
      });
    });
  }

  urlAdjunto(ruta: string): string {
    const baseUrl = environment.apiUrl.replace('/api', '');
    return `${baseUrl}/storage/${ruta}`;
  }

  descargar(formato: 'pdf' | 'excel'): void {
    this.http.get(`${environment.apiUrl}/analisis-financiero/${this.creditoId}/descargar`, {
      headers: { 'X-Active-Role': this.activeRole },
      params: { formato },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `analisis-financiero-${this.creditoId}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el análisis financiero.', 'error');
      }
    });
  }

  volver(): void {
    this.router.navigate(['/analisis-financiero']);
  }
}
