import { Locator } from '@playwright/test';

/**
 * SCRUM-277: selecciona una hora en <app-time-select-12h> (3 <select>:
 * hora 1-12, minuto 00-59, a. m./p. m.) a partir de un string "HH:mm" 24h —
 * mismo formato que aceptaba el <input type="time"> nativo que reemplazó,
 * para no tener que tocar cada spec que ya arma la hora en 24h.
 *
 * `contenedor` es el Locator que envuelve el componente (ej. un
 * `.form-field` con el label), no el `app-time-select-12h` en sí.
 */
export async function seleccionarHora12h(contenedor: Locator, hora24h: string): Promise<void> {
  const [hStr, mStr] = hora24h.split(':');
  let h = parseInt(hStr, 10);
  const periodo = h >= 12 ? 'p.m.' : 'a.m.';
  h = h % 12;
  if (h === 0) h = 12;
  const hora12 = String(h).padStart(2, '0');
  const minuto = (mStr || '00').padStart(2, '0');

  const selects = contenedor.locator('app-time-select-12h select');
  await selects.nth(0).selectOption(hora12);
  await selects.nth(1).selectOption(minuto);
  await selects.nth(2).selectOption(periodo);
}
