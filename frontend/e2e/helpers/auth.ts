import { Page, expect } from '@playwright/test';
import { getRoleLabel } from '../../src/app/shared/role-label.util';

/**
 * Login + selección de rol activo, reusable entre specs (notas operativas
 * documentadas en memory/ del proyecto):
 * - Login es por Número de Documento (no email) + contraseña.
 * - Si el usuario tiene >1 rol, tras el login aparece "Selecciona un
 *   Perfil": botones con el rol en Title Case (guiones bajos → espacios),
 *   no un dropdown.
 * - Una vez dentro, `.test-role-switcher select` permite cambiar de rol
 *   activo sin volver a loguearse; las opciones usan el valor crudo del
 *   rol (ej. 'coordinador_comercial').
 */
export async function loginAs(page: Page, numeroDocumento: string, password: string, rol: string) {
  await page.goto('/login');
  await page.locator('input[name="numero_documento"]').fill(numeroDocumento);
  await page.locator('input[name="password"]').fill(password);

  // Bug encontrado armando la spec de SCRUM-345 (rebote): getRoleLabel()
  // importado acá corre en el proceso Node de Playwright, no dentro del
  // navegador — la caché que SCRUM-346 llena en AuthService (desde
  // 'role_labels' de la respuesta de /login) nunca se puebla acá, así que
  // getRoleLabel() siempre caía al transform genérico del slug ("Director
  // de Crédito" -> nunca; devolvía "Coordinador Comercial" para
  // 'coordinador_comercial', el nombre viejo pre-SCRUM-346) y el picker de
  // perfil rompía con cualquier rol renombrado. Se intercepta la respuesta
  // real de /login (misma 'role_labels' que usa el frontend) en vez de
  // confiar en la caché estática.
  const [loginResponse] = await Promise.all([
    page.waitForResponse(resp => resp.url().includes('/api/login') && resp.request().method() === 'POST'),
    page.locator('button[type="submit"]').click(),
  ]);
  const roleLabels = await loginResponse.json().then(body => body.role_labels ?? {}).catch(() => ({}));
  const labelRol: string = roleLabels[rol] ?? getRoleLabel(rol);
  const selectorPerfil = page.getByRole('heading', { name: 'Selecciona un Perfil' });

  // NOTA: Locator.isVisible() no espera — consulta el DOM en el instante y
  // devuelve de inmediato, ignorando `timeout` (a diferencia de las
  // aserciones `expect(...).toBeVisible()`). Por eso acá se usa waitFor(),
  // que si hace polling hasta que aparece o vence el timeout.
  const aparecioPicker = await selectorPerfil.waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false);

  if (aparecioPicker) {
    await page.getByRole('button', { name: new RegExp(labelRol, 'i') }).click();
  }

  // El destino tras login varía por rol (roleGuard redirige cada rol a su
  // home por defecto) — el único invariante útil acá es "ya no está en
  // /login".
  await expect(page).not.toHaveURL(/\/login$/, { timeout: 10000 });

  // Por si el rol elegido no coincide con el activo (ej. el picker no
  // apareció porque el usuario tiene un solo rol distinto al pedido).
  const switcher = page.locator('.test-role-switcher select');
  const haySwitcher = await switcher.waitFor({ state: 'visible', timeout: 3000 }).then(() => true).catch(() => false);
  if (haySwitcher) {
    await switcher.selectOption(rol).catch(() => {});
  }
}
