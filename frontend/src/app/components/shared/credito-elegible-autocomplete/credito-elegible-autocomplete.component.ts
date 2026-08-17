import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

// SCRUM-198: buscador de créditos ya existentes y elegibles para comité
// (estado comite_evaluacion), para el alta manual del Acta — reemplaza el
// formulario de texto libre que permitía crear un crédito desde cero.
// A diferencia de ClienteAutocompleteComponent, no implementa
// ControlValueAccessor: no hay un valor de formulario que mantener, el
// padre solo necesita el objeto crédito completo al seleccionar.
@Component({
  selector: 'app-credito-elegible-autocomplete',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="credito-elegible-autocomplete">
      <input type="text"
             class="pro-input"
             [(ngModel)]="query"
             (ngModelChange)="onQueryChange()"
             (focus)="onFocus()"
             (blur)="onBlur()"
             [disabled]="disabled"
             [placeholder]="placeholder"
             autocomplete="off" />
      <ul class="credito-elegible-autocomplete-suggestions" *ngIf="showSuggestions && suggestions.length > 0">
        <li *ngFor="let c of suggestions" (mousedown)="select(c)">
          {{ c.cliente_nombre }} ({{ c.cliente_identificacion || 'sin identificación' }}) — {{ c.tipo_solicitud || 'Tipo N/A' }} — $ {{ c.monto | number:'1.0-0':'es-CO' }}
        </li>
      </ul>
      <p class="credito-elegible-autocomplete-hint" *ngIf="showSuggestions && query.trim().length >= minChars && !loading && suggestions.length === 0">
        Sin créditos elegibles que coincidan.
      </p>
    </div>
  `,
  styles: [`
    .credito-elegible-autocomplete { position: relative; }
    .credito-elegible-autocomplete-suggestions {
      position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
      max-height: 220px; overflow-y: auto; margin: 4px 0 0; padding: 4px;
      list-style: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .credito-elegible-autocomplete-suggestions li {
      padding: 8px 10px; cursor: pointer; border-radius: 6px; font-size: 0.9rem;
    }
    .credito-elegible-autocomplete-suggestions li:hover { background: #f1f5f9; }
    .credito-elegible-autocomplete-hint {
      position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
      margin: 4px 0 0; padding: 8px 10px; font-size: 0.85rem; color: #64748b;
    }
  `]
})
export class CreditoElegibleAutocompleteComponent {
  @Input() actaId!: number;
  @Input() activeRole = '';
  @Input() placeholder = 'Buscar crédito elegible (cliente o identificación)...';
  @Input() disabled = false;
  @Output() creditoSeleccionado = new EventEmitter<any>();

  query = '';
  suggestions: any[] = [];
  showSuggestions = false;
  loading = false;
  minChars = 3;

  private searchTimeout: any;

  constructor(private http: HttpClient) {}

  onQueryChange() {
    clearTimeout(this.searchTimeout);
    if (this.query.trim().length < this.minChars) {
      this.suggestions = [];
      return;
    }
    this.searchTimeout = setTimeout(() => this.search(), 300);
  }

  search() {
    this.loading = true;
    const url = `${environment.apiUrl}/actas-comite/${this.actaId}/creditos-elegibles?q=${encodeURIComponent(this.query.trim())}`;
    this.http.get<any[]>(url, { headers: { 'X-Active-Role': this.activeRole } }).subscribe({
      next: data => { this.suggestions = data; this.loading = false; },
      error: () => { this.suggestions = []; this.loading = false; }
    });
  }

  onFocus() {
    this.showSuggestions = true;
  }

  onBlur() {
    setTimeout(() => this.showSuggestions = false, 150);
  }

  select(credito: any) {
    this.query = '';
    this.suggestions = [];
    this.showSuggestions = false;
    this.creditoSeleccionado.emit(credito);
  }
}
