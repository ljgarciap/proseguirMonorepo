import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * SCRUM-189 (2026-08-13, 3ª vuelta — "las imágenes no se ven en el PDF").
 * Causa raíz real: pegar una imagen copiada de otra fuente (portapapeles con
 * HTML apuntando a una URL externa, sin bytes de archivo) insertaba un <img
 * src="https://..."> externo que el navegador sí carga (por eso se veía bien
 * en el editor) pero que DomPDF no puede resolver (enable_remote=false a
 * propósito) — sale como ícono de imagen rota en Previsualizar/PDF. Fix:
 * clipboard.addMatcher('IMG', ...) bloquea ese caso con una advertencia, y el
 * módulo 'uploader' de Quill se reconfigura para que pegar/soltar un archivo
 * real (bytes reales) pase por el mismo endpoint de subida que el botón de
 * la barra de herramientas (antes usaba el default de Quill: FileReader a
 * base64, sin pasar por el backend). Ver actas-comite-detalle.component.ts,
 * método onEditorCreated/quillModules.
 */
// Un solo test/una sola acta: el entorno bloquea "Generar acta pendiente"
// mientras exista otra acta pendiente/borrador sin registrar (ver
// e2e/README.md) — dos tests independientes que generan cada uno la suya se
// pisan entre sí si corren en la misma sesión de BD.
test('pegar imágenes en el acta: URL externa se bloquea, archivo real se sube al backend', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'coordinador_comercial');

  await page.goto('/actas-comite');
  await page.getByRole('button', { name: /Generar acta pendiente/i }).click();
  await page.getByRole('button', { name: 'OK' }).click(); // SweetAlert2 "Acta generada"
  await expect.poll(() => page.url(), { timeout: 10000 }).toMatch(/\/actas-comite\/\d+$/);

  await page.getByRole('button', { name: '1. Orden del día' }).click();
  const editor = page.locator('.ql-editor').first();
  await editor.click();

  // --- Caso 1: pegar HTML con un <img> externo suelto y SIN bytes de
  // archivo en el portapapeles — el caso real que Quill deja pasar tal cual
  // (ver quill/modules/clipboard.js: el atajo a quill.uploader.upload()
  // sólo dispara si además hay File(s) adjuntos en el portapapeles). ---
  await editor.evaluate((el) => {
    const dataTransfer = new DataTransfer();
    dataTransfer.setData('text/html', '<img src="https://ejemplo-externo.test/foto.png">');
    dataTransfer.setData('text/plain', '');
    const evento = new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: dataTransfer });
    el.dispatchEvent(evento);
  });

  await expect(page.locator('.swal2-popup')).toContainText('Imagen no admitida');
  await page.getByRole('button', { name: 'OK' }).click();

  // La imagen externa nunca debe quedar insertada en el HTML del acta.
  await expect(editor.locator('img[src*="ejemplo-externo.test"]')).toHaveCount(0);

  // --- Caso 2: pegar un archivo de imagen real (bytes, no HTML) — debe
  // subirse por el mismo endpoint que usa el botón de la barra de
  // herramientas, no quedar como data:...;base64 local (default de Quill). ---
  const respuestaSubida = page.waitForResponse((resp) => resp.url().includes('/imagenes') && resp.request().method() === 'POST');

  // Simula pegar un archivo de imagen real (bytes, no HTML) — 1x1 PNG.
  await editor.evaluate((el) => {
    const base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    const bytes = Uint8Array.from(atob(base64Png), (c) => c.charCodeAt(0));
    const file = new File([bytes], 'evidencia.png', { type: 'image/png' });
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    const evento = new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: dataTransfer });
    el.dispatchEvent(evento);
  });

  const resp = await respuestaSubida;
  expect(resp.status()).toBe(200);

  await expect(editor.locator('img[src*="/storage/"]')).toHaveCount(1);
});
