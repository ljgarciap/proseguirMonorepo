import { Component, EventEmitter, Input, Output, forwardRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

// SCRUM-134: reemplaza el <select> gigante de Cliente por un autocompletar
// (search-as-you-type contra /api/clientes?q=). Implementa ControlValueAccessor
// para poder usarse con [(ngModel)]="form.cliente_id" igual que un <select> normal.
@Component({
  selector: 'app-cliente-autocomplete',
  standalone: true,
  imports: [CommonModule, FormsModule],
  providers: [{
    provide: NG_VALUE_ACCESSOR,
    useExisting: forwardRef(() => ClienteAutocompleteComponent),
    multi: true
  }],
  template: `
    <div class="cliente-autocomplete">
      <input type="text"
             class="pro-input"
             [(ngModel)]="query"
             (ngModelChange)="onQueryChange()"
             (focus)="onFocus()"
             (blur)="onBlur()"
             [disabled]="disabled"
             [placeholder]="placeholder"
             autocomplete="off" />
      <ul class="cliente-autocomplete-suggestions" *ngIf="showSuggestions && suggestions.length > 0">
        <li *ngFor="let c of suggestions" (mousedown)="select(c)">
          {{ c.nombre }} ({{ c.tipo_persona?.nombre || 'Tipo N/A' }} - {{ c.numero_documento }})
        </li>
      </ul>
      <p class="cliente-autocomplete-hint" *ngIf="showSuggestions && query.trim().length >= minChars && !loading && suggestions.length === 0">
        Sin coincidencias.
      </p>
    </div>
  `,
  styles: [`
    .cliente-autocomplete { position: relative; }
    .cliente-autocomplete-suggestions {
      position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
      max-height: 220px; overflow-y: auto; margin: 4px 0 0; padding: 4px;
      list-style: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .cliente-autocomplete-suggestions li {
      padding: 8px 10px; cursor: pointer; border-radius: 6px; font-size: 0.9rem;
    }
    .cliente-autocomplete-suggestions li:hover { background: #f1f5f9; }
    .cliente-autocomplete-hint {
      position: absolute; z-index: 20; top: 100%; left: 0; right: 0;
      background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
      margin: 4px 0 0; padding: 8px 10px; font-size: 0.85rem; color: #64748b;
    }
  `]
})
export class ClienteAutocompleteComponent implements ControlValueAccessor {
  @Input() placeholder = 'Escriba al menos 3 letras del nombre o el documento...';
  @Input() disabled = false;
  @Input() activosSolo = true;
  // Lista ya cargada por el padre (ej. activeClientes) — se usa para mostrar
  // el nombre del cliente ya seleccionado sin pegarle al backend de nuevo.
  @Input() clientesPreCargados: any[] | null = null;
  @Output() clienteSeleccionado = new EventEmitter<any>();

  query = '';
  suggestions: any[] = [];
  showSuggestions = false;
  loading = false;
  minChars = 3;

  private searchTimeout: any;
  private onChange: (value: any) => void = () => {};
  private onTouched: () => void = () => {};

  constructor(private http: HttpClient) {}

  writeValue(value: any): void {
    if (!value) {
      this.query = '';
      return;
    }
    const preCargado = this.clientesPreCargados?.find(c => c.id === Number(value));
    if (preCargado) {
      this.query = `${preCargado.nombre} (${preCargado.numero_documento})`;
    }
  }

  registerOnChange(fn: any): void { this.onChange = fn; }
  registerOnTouched(fn: any): void { this.onTouched = fn; }
  setDisabledState(isDisabled: boolean): void { this.disabled = isDisabled; }

  onQueryChange() {
    this.onChange('');
    clearTimeout(this.searchTimeout);
    if (this.query.trim().length < this.minChars) {
      this.suggestions = [];
      return;
    }
    this.searchTimeout = setTimeout(() => this.search(), 300);
  }

  search() {
    this.loading = true;
    let url = `${environment.apiUrl}/clientes?q=${encodeURIComponent(this.query.trim())}`;
    if (this.activosSolo) {
      url += '&activo=true';
    }
    this.http.get<any[]>(url).subscribe({
      next: data => { this.suggestions = data; this.loading = false; },
      error: () => { this.suggestions = []; this.loading = false; }
    });
  }

  onFocus() {
    this.showSuggestions = true;
  }

  onBlur() {
    this.onTouched();
    // Delay para permitir que el (mousedown) de la sugerencia se dispare antes de cerrar la lista.
    setTimeout(() => this.showSuggestions = false, 150);
  }

  select(cliente: any) {
    this.query = `${cliente.nombre} (${cliente.numero_documento})`;
    this.suggestions = [];
    this.showSuggestions = false;
    this.onChange(cliente.id);
    this.clienteSeleccionado.emit(cliente);
  }
}
