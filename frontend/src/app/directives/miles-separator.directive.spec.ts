import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { By } from '@angular/platform-browser';
import { MilesSeparatorDirective } from './miles-separator.directive';

@Component({
  standalone: true,
  imports: [FormsModule, MilesSeparatorDirective],
  template: `<input type="text" [(ngModel)]="valor" milesSeparator [tipoValorMiles]="tipo">`,
})
class HostComponent {
  valor: number | string | null = null;
  tipo: 'number' | 'string' = 'number';
}

describe('MilesSeparatorDirective', () => {
  let fixture: ComponentFixture<HostComponent>;
  let host: HostComponent;
  let inputEl: HTMLInputElement;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(HostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
    inputEl = fixture.debugElement.query(By.css('input')).nativeElement;
  });

  function escribir(texto: string) {
    inputEl.value = texto;
    inputEl.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  }

  it('formatea miles mientras se escribe y expone un number limpio en el ngModel', () => {
    escribir('1000000');

    expect(inputEl.value).toBe('1.000.000');
    expect(host.valor).toBe(1000000);
  });

  it('conserva la parte decimal separada por coma', () => {
    escribir('1234567,89');

    expect(inputEl.value).toBe('1.234.567,89');
    expect(host.valor).toBe(1234567.89);
  });

  it('soporta valores negativos', () => {
    escribir('-500000');

    expect(inputEl.value).toBe('-500.000');
    expect(host.valor).toBe(-500000);
  });

  it('ignora caracteres no numéricos', () => {
    escribir('abc123.456xyz');

    expect(inputEl.value).toBe('123.456');
    expect(host.valor).toBe(123456);
  });

  it('vacío resulta en valor null', () => {
    escribir('1000');
    escribir('');

    expect(inputEl.value).toBe('');
    expect(host.valor).toBeNull();
  });

  it('writeValue formatea un número asignado programáticamente', async () => {
    host.valor = 2500000;
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(inputEl.value).toBe('2.500.000');
  });

  it('con tipoValorMiles=string expone el valor formateado como string (no number)', () => {
    host.tipo = 'string';
    fixture.detectChanges();

    escribir('100000000');

    expect(inputEl.value).toBe('100.000.000');
    expect(host.valor).toBe('100.000.000');
    expect(typeof host.valor).toBe('string');
  });
});
