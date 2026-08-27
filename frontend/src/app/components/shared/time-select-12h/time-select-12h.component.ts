import { Component, Input, forwardRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

// SCRUM-277: reemplaza el <input type="time"> nativo. El picker que el
// navegador/SO dibuja al abrirlo (rueda de Safari/macOS) no es CSS nuestro —
// deja columnas de alturas distintas (12 horas vs 2 periodos) con espacio en
// blanco que Juan Andrés reportó como confuso. 3 <select> propios, mismo
// look "pro-input" del resto del formulario, eliminan el picker nativo de
// raíz. Sigue produciendo/consumiendo "HH:mm" 24h (mismo formato que el
// input nativo) para no tocar el contrato con el backend (columna `time` en
// BD) — solo cambia la presentación a 12h con a. m./p. m.
@Component({
  selector: 'app-time-select-12h',
  standalone: true,
  imports: [CommonModule, FormsModule],
  providers: [{
    provide: NG_VALUE_ACCESSOR,
    useExisting: forwardRef(() => TimeSelect12hComponent),
    multi: true
  }],
  template: `
    <div class="time-select-12h">
      <select class="pro-input" [(ngModel)]="hora" (ngModelChange)="emit()" [disabled]="disabled">
        <option value="" disabled>HH</option>
        <option *ngFor="let h of horas" [value]="h">{{ h }}</option>
      </select>
      <span class="time-sep">:</span>
      <select class="pro-input" [(ngModel)]="minuto" (ngModelChange)="emit()" [disabled]="disabled">
        <option value="" disabled>MM</option>
        <option *ngFor="let m of minutos" [value]="m">{{ m }}</option>
      </select>
      <select class="pro-input" [(ngModel)]="periodo" (ngModelChange)="emit()" [disabled]="disabled">
        <option value="" disabled>--</option>
        <option value="a.m.">a. m.</option>
        <option value="p.m.">p. m.</option>
      </select>
    </div>
  `,
  styles: [`
    .time-select-12h { display: flex; align-items: center; gap: 4px; }
    .time-select-12h select { padding: 10px 6px; min-width: 0; }
    .time-sep { font-weight: 600; color: #64748b; }
  `]
})
export class TimeSelect12hComponent implements ControlValueAccessor {
  @Input() disabled = false;

  readonly horas = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));
  readonly minutos = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));

  hora = '';
  minuto = '';
  periodo: '' | 'a.m.' | 'p.m.' = '';

  private onChange: (value: string | null) => void = () => {};
  private onTouched: () => void = () => {};

  // Recibe/escribe siempre "HH:mm" en 24h (o null/'' para vacío).
  writeValue(value: string | null): void {
    if (!value) {
      this.hora = '';
      this.minuto = '';
      this.periodo = '';
      return;
    }
    const [hStr, mStr] = value.split(':');
    let h = parseInt(hStr, 10);
    if (isNaN(h)) {
      this.hora = '';
      this.minuto = '';
      this.periodo = '';
      return;
    }
    this.periodo = h >= 12 ? 'p.m.' : 'a.m.';
    h = h % 12;
    if (h === 0) h = 12;
    this.hora = String(h).padStart(2, '0');
    this.minuto = (mStr || '00').padStart(2, '0');
  }

  registerOnChange(fn: any): void { this.onChange = fn; }
  registerOnTouched(fn: any): void { this.onTouched = fn; }
  setDisabledState(isDisabled: boolean): void { this.disabled = isDisabled; }

  emit(): void {
    this.onTouched();
    // Mientras el usuario arma un valor NUEVO (los 3 <select> arrancan
    // vacíos), no propagar nada hasta tener los 3 — el padre autoguarda con
    // (ngModelChange), un onChange(null) por cada selección intermedia
    // dispara su propio PUT inmediato; 3 PUT casi simultáneos a la misma
    // acta pueden resolverse fuera de orden en el backend y el último en
    // llegar (no el último en salir) es el que persiste, pudiendo pisar la
    // hora completa con null si el PUT del 3er select llega antes que el
    // del 1º/2º. No hay opción de "limpiar" el campo una vez elegido un
    // valor (las 3 <option> placeholder son `disabled`), así que no perder
    // la capacidad de vaciarlo — solo se evita el emit a medio llenar.
    if (!this.hora || !this.minuto || !this.periodo) {
      return;
    }
    let h = parseInt(this.hora, 10) % 12;
    if (this.periodo === 'p.m.') h += 12;
    this.onChange(`${String(h).padStart(2, '0')}:${this.minuto}`);
  }
}
