import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../services/auth.service';
import { MilesSeparatorDirective } from '../../directives/miles-separator.directive';
import Swal from 'sweetalert2';

/**
 * Rol requerido por cada estado del flujo de Informe Técnico (SCRUM-120).
 * Debe reflejar App\Http\Controllers\InformeTecnicoController::ROL_POR_ESTADO.
 */
const ROL_POR_ESTADO: Record<string, string> = {
  informe_tecnico_ingeniero: 'ingeniero',
  informe_tecnico_coordinador: 'coordinador_comercial',
};

/**
 * SCRUM-120 Fase 2: shape itemizado real (fórmulas del Excel de referencia
 * CR-RO-09A v5). Estos son solo los INPUTS crudos que el frontend manda —
 * los totales/porcentajes SIEMPRE se calculan y devuelven desde el backend
 * (InformeTecnicoCalculoService), nunca se recalculan aquí, para que no
 * puedan divergir del valor persistido.
 */
export interface FilaConcepto {
  label: string;
  clave: string;
}

/**
 * SCRUM-162 — Ventas Totales Proyecto y Costos pasan de campos fijos
 * nombrados a array-driven (mismo patrón que Análisis Financiero), para
 * poder agregar filas custom ad-hoc por informe. `VentasInput`/`CostosInput`
 * quedan como `Record<clave, valor>` — los 7 campos fijos siguen usando las
 * mismas claves que antes (contrato con el backend sin cambios para ellos).
 */
const VENTAS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Casas', clave: 'casas' },
  { label: 'Apartamentos', clave: 'apartamentos' },
  { label: 'Parqueaderos', clave: 'parqueaderos' },
  { label: 'Conexión gas / arras desist.', clave: 'conexion_gas_arras' },
  { label: 'Local comercial', clave: 'local_comercial' },
  { label: 'Cuartos útiles', clave: 'cuartos_utiles' },
  { label: 'Otros', clave: 'otros' },
];

const COSTOS_CONCEPTOS: FilaConcepto[] = [
  { label: 'Lote', clave: 'lote' },
  { label: 'Directos', clave: 'directos' },
  { label: 'Directos Urbanismo', clave: 'directos_urbanismo' },
  { label: 'Indirectos', clave: 'indirectos' },
  { label: 'Honorarios', clave: 'honorarios' },
  { label: 'Incremento en costos', clave: 'incremento_costos' },
  { label: 'Financieros', clave: 'financieros' },
];

// Los únicos dos campos fijos de Costos que NO suman al total (regla del
// documento de control CR-RO-09A, ver InformeTecnicoCalculoService::calcularCostos()).
const COSTOS_CLAVES_NO_SUMAN = ['honorarios', 'financieros'];

type VentasInput = Record<string, number | null>;
type CostosInput = Record<string, number | null>;

interface InvertidoInput {
  lote: number | null;
  costos_directos: number | null;
  costos_indirectos: number | null;
  recursos_propios: number | null;
  cuotas_iniciales_ya_pagadas: number | null;
}

interface CreditoSolicitadoInput {
  credito_solicitado: number | null;
  aptos_vendidos: number | null;
  porcentaje_cuotas_iniciales_pendientes: number | null;
}

interface SaldosPorRecaudarInput {
  porcentaje_cuotas_iniciales: number | null;
  cuotas_iniciales_pendientes: number | null;
}

@Component({
  selector: 'app-informe-tecnico-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, MilesSeparatorDirective],
  templateUrl: './informe-tecnico-detalle.component.html',
  styleUrls: ['./informe-tecnico-detalle.component.css']
})
export class InformeTecnicoDetalleComponent implements OnInit, OnDestroy {
  creditoId!: number;
  credito: any = null;
  informe: any = null;
  loading = false;
  activeRole: string = '';

  // SCRUM-120 Fase 2 / SCRUM-162: config de filas fijas expuesta al template.
  readonly ventasConceptos = VENTAS_CONCEPTOS;
  readonly costosConceptos = COSTOS_CONCEPTOS;
  readonly costosClavesNoSuman = COSTOS_CLAVES_NO_SUMAN;

  // Inputs editables — hidratados desde `informe` al cargar/guardar.
  ventasInput: VentasInput = {
    casas: null, apartamentos: null, parqueaderos: null, conexion_gas_arras: null,
    local_comercial: null, cuartos_utiles: null, otros: null
  };
  costosInput: CostosInput = {
    lote: null, directos: null, directos_urbanismo: null, indirectos: null,
    honorarios: null, incremento_costos: null, financieros: null
  };

  // SCRUM-162 — filas custom ad-hoc de Ventas Totales Proyecto/Costos (no
  // salen de un catálogo reutilizable, se escriben libres en el formulario).
  ventasCustomFilas: FilaConcepto[] = [];
  costosCustomFilas: FilaConcepto[] = [];

  invertidoInput: InvertidoInput = {
    lote: null, costos_directos: null, costos_indirectos: null,
    recursos_propios: null, cuotas_iniciales_ya_pagadas: null
  };
  observacionesIngeniero = '';

  creditoSolicitadoInput: CreditoSolicitadoInput = {
    credito_solicitado: null, aptos_vendidos: null, porcentaje_cuotas_iniciales_pendientes: 0.30
  };
  saldosPorRecaudarInput: SaldosPorRecaudarInput = {
    porcentaje_cuotas_iniciales: 0.10, cuotas_iniciales_pendientes: null
  };
  observacionesCoordinador = '';

  // SCRUM-154: autoguardado silencioso — el backend es la única fuente de
  // cálculo de totales/porcentajes (SCRUM-120), así que para que se vean
  // "en vivo" mientras se diligencia hace falta disparar guardarBorrador()
  // en segundo plano, sin esperar al click explícito en "Guardar Borrador".
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

  // Enganchado a (input) en el contenedor de cada sección editable: dispara
  // el autoguardado silencioso con debounce ante cualquier cambio de campo.
  onCampoCambiado(): void {
    this.autoguardadoSubject.next();
  }

  cargar(): void {
    this.loading = true;
    this.http.get<any>(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}`, {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (data) => {
        this.credito = data.credito;
        this.informe = data.informe;
        this.hidratarFormularios();
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        Swal.fire('Error', err.error?.message || 'No se pudo cargar el informe técnico.', 'error')
          .then(() => this.router.navigate(['/informes-tecnicos']));
      }
    });
  }

  private hidratarFormularios(): void {
    if (!this.informe) return;

    const v = this.informe.ventas_totales_proyecto || {};
    this.ventasInput = {
      casas: v.casas ?? null, apartamentos: v.apartamentos ?? null, parqueaderos: v.parqueaderos ?? null,
      conexion_gas_arras: v.conexion_gas_arras ?? null, local_comercial: v.local_comercial ?? null,
      cuartos_utiles: v.cuartos_utiles ?? null, otros: v.otros ?? null
    };
    this.ventasCustomFilas = [];
    for (const fila of (v.custom || [])) {
      if (!fila?.clave) continue;
      this.ventasCustomFilas.push({ label: fila.label || fila.clave, clave: fila.clave });
      this.ventasInput[fila.clave] = fila.valor ?? null;
    }

    const c = this.informe.costos || {};
    this.costosInput = {
      lote: c.lote ?? null, directos: c.directos ?? null, directos_urbanismo: c.directos_urbanismo ?? null,
      indirectos: c.indirectos ?? null, honorarios: c.honorarios ?? null,
      incremento_costos: c.incremento_costos ?? null, financieros: c.financieros ?? null
    };
    this.costosCustomFilas = [];
    for (const fila of (c.custom || [])) {
      if (!fila?.clave) continue;
      this.costosCustomFilas.push({ label: fila.label || fila.clave, clave: fila.clave });
      this.costosInput[fila.clave] = fila.valor ?? null;
    }

    const i = this.informe.invertido || {};
    this.invertidoInput = {
      lote: i.lote ?? null, costos_directos: i.costos_directos ?? null, costos_indirectos: i.costos_indirectos ?? null,
      recursos_propios: i.recursos_propios ?? null, cuotas_iniciales_ya_pagadas: i.cuotas_iniciales_ya_pagadas ?? null
    };

    this.observacionesIngeniero = this.informe.observaciones_ingeniero || '';

    const cs = this.informe.credito_solicitado || {};
    this.creditoSolicitadoInput = {
      credito_solicitado: cs.credito_solicitado ?? null,
      aptos_vendidos: cs.aptos_vendidos ?? null,
      porcentaje_cuotas_iniciales_pendientes: cs.porcentaje_cuotas_iniciales_pendientes ?? 0.30
    };

    const s = this.informe.saldos_por_recaudar_contraentrega || {};
    this.saldosPorRecaudarInput = {
      porcentaje_cuotas_iniciales: s.porcentaje_cuotas_iniciales ?? 0.10,
      cuotas_iniciales_pendientes: s.cuotas_iniciales_pendientes ?? null
    };

    this.observacionesCoordinador = this.informe.observaciones_coordinador || '';
  }

  // SCRUM-154: desde SCRUM-128 el CreditoOrdinario encadena automáticamente
  // a 'sarlaft_control_interno' apenas se registra el informe — el estado
  // 'informe_tecnico_finalizado' nunca llega a observarse en el frontend.
  // InformeTecnico.estado === 'registrado' es la señal real de que ya quedó
  // completo (mismo criterio que ya usa el backend en autorizarVisualizacion()).
  get esFinalizado(): boolean {
    return this.credito?.estado === 'informe_tecnico_finalizado' || this.informe?.estado === 'registrado';
  }

  get puedeEditar(): boolean {
    if (!this.credito || this.esFinalizado) return false;
    if (this.activeRole === 'superadmin') return true;
    return ROL_POR_ESTADO[this.credito.estado] === this.activeRole;
  }

  get seccionIngenieroVisible(): boolean {
    return true;
  }

  get seccionIngenieroEditable(): boolean {
    return this.puedeEditar && this.credito?.estado === 'informe_tecnico_ingeniero';
  }

  get seccionCoordinadorVisible(): boolean {
    return this.credito?.estado === 'informe_tecnico_coordinador'
      || this.credito?.estado === 'informe_tecnico_finalizado'
      || this.informe?.estado === 'registrado';
  }

  get seccionCoordinadorEditable(): boolean {
    return this.puedeEditar && this.credito?.estado === 'informe_tecnico_coordinador';
  }

  // Habilitado apenas exista algún dato guardado, aunque sea borrador
  // (así lo pide el prototipo — no solo cuando el informe está finalizado).
  get puedeDescargar(): boolean {
    if (!this.informe) return false;
    const v = this.informe.ventas_totales_proyecto;
    return !!(v && (v.total_ventas || 0) > 0) || !!this.informe.observaciones_ingeniero;
  }

  // SCRUM-162 — construye el payload de una sección con filas custom: los
  // campos fijos van tal cual (mismas claves de siempre) y las filas custom
  // viajan aparte en `_custom` como `{clave, label, valor}` (el valor sale
  // de `inputs[clave]`, donde también vive para el binding del formulario).
  private construirPayloadConCustom(conceptos: FilaConcepto[], inputs: Record<string, any>, customFilas: FilaConcepto[]): any {
    const payload: Record<string, any> = {};
    for (const fila of conceptos) {
      payload[fila.clave] = inputs[fila.clave] ?? null;
    }
    payload['_custom'] = customFilas.map(f => ({ clave: f.clave, label: f.label, valor: inputs[f.clave] ?? null }));
    return payload;
  }

  // SCRUM-162 — agrega una fila custom ad-hoc (nombre libre, no sale de un
  // catálogo reutilizable) a Ventas Totales Proyecto o Costos.
  agregarFilaCustom(tipo: 'ventas' | 'costos'): void {
    if (!this.seccionIngenieroEditable) return;

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
      const clave = this.generarClaveCustom(tipo, label);
      if (tipo === 'ventas') {
        this.ventasCustomFilas.push({ label, clave });
        this.ventasInput[clave] = null;
      } else {
        this.costosCustomFilas.push({ label, clave });
        this.costosInput[clave] = null;
      }
      this.onCampoCambiado();
    });
  }

  eliminarFilaCustom(tipo: 'ventas' | 'costos', clave: string): void {
    if (!this.seccionIngenieroEditable) return;
    if (tipo === 'ventas') {
      this.ventasCustomFilas = this.ventasCustomFilas.filter(f => f.clave !== clave);
      delete this.ventasInput[clave];
    } else {
      this.costosCustomFilas = this.costosCustomFilas.filter(f => f.clave !== clave);
      delete this.costosInput[clave];
    }
    this.onCampoCambiado();
  }

  private generarClaveCustom(tipo: string, label: string): string {
    const slug = label
      .toLowerCase()
      .normalize('NFD').replace(/[^\x00-\x7f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '') || 'concepto';
    const sufijo = Date.now().toString(36);
    return `custom_${tipo}_${slug}_${sufijo}`.slice(0, 80);
  }

  private payloadSegunEstado(): any {
    if (this.credito.estado === 'informe_tecnico_ingeniero') {
      return {
        ventas_totales_proyecto: this.construirPayloadConCustom(this.ventasConceptos, this.ventasInput, this.ventasCustomFilas),
        costos: this.construirPayloadConCustom(this.costosConceptos, this.costosInput, this.costosCustomFilas),
        invertido: this.invertidoInput,
        observaciones_ingeniero: this.observacionesIngeniero
      };
    }
    if (this.credito.estado === 'informe_tecnico_coordinador') {
      return {
        credito_solicitado: this.creditoSolicitadoInput,
        saldos_por_recaudar_contraentrega: this.saldosPorRecaudarInput,
        observaciones_coordinador: this.observacionesCoordinador
      };
    }
    return {};
  }

  guardarBorrador(silencioso = false): void {
    this.http.put(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/borrador`, this.payloadSegunEstado(), {
      headers: { 'X-Active-Role': this.activeRole }
    }).subscribe({
      next: (informe) => {
        this.informe = informe;
        this.hidratarFormularios();
        if (!silencioso) {
          Swal.fire('Guardado', 'El borrador del informe técnico se guardó correctamente.', 'success');
        }
      },
      error: (err) => {
        if (!silencioso) {
          Swal.fire('Error', err.error?.message || 'No se pudo guardar el borrador.', 'error');
        }
      }
    });
  }

  registrar(): void {
    const esIngeniero = this.credito.estado === 'informe_tecnico_ingeniero';
    Swal.fire({
      title: esIngeniero ? '¿Registrar informe del Ingeniero?' : '¿Registrar informe del Coordinador Comercial?',
      text: esIngeniero
        ? 'Tu sección quedará bloqueada para edición y pasará a Coordinador Comercial.'
        : 'El informe técnico quedará finalizado y no podrá editarse más.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, registrar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#1d4ed8'
    }).then(result => {
      if (!result.isConfirmed) return;

      this.http.post(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/registrar`, this.payloadSegunEstado(), {
        headers: { 'X-Active-Role': this.activeRole }
      }).subscribe({
        next: () => {
          Swal.fire('¡Registrado!', 'El informe técnico se registró correctamente.', 'success')
            .then(() => this.router.navigate(['/informes-tecnicos']));
        },
        error: (err) => {
          Swal.fire('Error', err.error?.message || 'No se pudo registrar el informe técnico.', 'error');
        }
      });
    });
  }

  descargar(formato: 'pdf' | 'excel'): void {
    this.http.get(`${environment.apiUrl}/informes-tecnicos/${this.creditoId}/descargar`, {
      headers: { 'X-Active-Role': this.activeRole },
      params: { formato },
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `informe-tecnico-${this.creditoId}.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        Swal.fire('Error', 'No se pudo descargar el informe técnico.', 'error');
      }
    });
  }

  volver(): void {
    this.router.navigate(['/informes-tecnicos']);
  }
}
