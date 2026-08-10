import { Directive, ElementRef, HostListener, Input, forwardRef } from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

/**
 * Formatea un input de texto con separador de miles ("." para pesos
 * colombianos) mientras se escribe. Úsese junto a [(ngModel)] en inputs
 * type="text" (los inputs type="number" nativos no aceptan puntos).
 *
 * Por defecto el valor que viaja por ngModel es un number limpio (sin
 * puntos). Si el campo destino en el backend es un string (ej. un monto
 * estimado de texto libre), usar [tipoValorMiles]="'string'" para que el
 * valor formateado (con puntos) viaje tal cual como string.
 */
@Directive({
  selector: '[milesSeparator]',
  standalone: true,
  providers: [{
    provide: NG_VALUE_ACCESSOR,
    useExisting: forwardRef(() => MilesSeparatorDirective),
    multi: true,
  }],
})
export class MilesSeparatorDirective implements ControlValueAccessor {
  @Input() tipoValorMiles: 'number' | 'string' = 'number';

  private onChange: (value: number | string | null) => void = () => {};
  private onTouched: () => void = () => {};

  constructor(private el: ElementRef<HTMLInputElement>) {}

  writeValue(value: number | string | null): void {
    const numero = value === null || value === undefined || value === ''
      ? null
      : (typeof value === 'string' ? this.parsear(value) : value);

    this.el.nativeElement.value = numero === null ? '' : this.formatear(numero);
  }

  registerOnChange(fn: (value: number | string | null) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.el.nativeElement.disabled = isDisabled;
  }

  @HostListener('input', ['$event.target.value'])
  onInput(valorCrudo: string): void {
    // SCRUM-187: si el usuario recién tecleó el signo "-" (todavía sin
    // dígitos), `parsear` no tiene nada que convertir y devuelve null — sin
    // este caso especial, el bloque de abajo formatea null como '' y borra
    // el "-" del input antes de que el usuario alcance a escribir el
    // número, dejando la casilla en blanco en cada intento (imposible
    // escribir un negativo dígito a dígito, aunque pegar "-500000" de una
    // sola vez sí funcionaba). Se conserva el "-" en pantalla y se deja el
    // valor del modelo en null hasta que haya al menos un dígito.
    if (valorCrudo.trim() === '-') {
      this.el.nativeElement.value = '-';
      this.onChange(null);
      return;
    }

    const numero = this.parsear(valorCrudo);
    const formateado = numero === null ? '' : this.formatear(numero);
    this.el.nativeElement.value = formateado;
    this.onChange(this.tipoValorMiles === 'string' ? formateado : numero);
  }

  @HostListener('blur')
  onBlur(): void {
    this.onTouched();
  }

  private parsear(valor: string): number | null {
    const esNegativo = valor.trim().startsWith('-');
    const limpio = valor.replace(/\./g, '').replace(',', '.').replace(/[^\d.]/g, '');
    if (limpio === '' || limpio === '.') return null;
    const numero = parseFloat(limpio);
    if (isNaN(numero)) return null;
    return esNegativo ? -numero : numero;
  }

  private formatear(valor: number): string {
    const [entero, decimal] = valor.toString().split('.');
    const signo = entero.startsWith('-') ? '-' : '';
    const enteroAbs = signo ? entero.slice(1) : entero;
    const enteroFormateado = enteroAbs.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return decimal ? `${signo}${enteroFormateado},${decimal}` : `${signo}${enteroFormateado}`;
  }
}
