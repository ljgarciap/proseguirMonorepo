import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

/**
 * Smoke visual de la Fase 1 del motor paramétrico de Roles y Permisos
 * (ver docs/specs/rbac-roles-permisos-parametrico.md). Cobertura
 * funcional completa ya está en PHPUnit (RoleControllerTest) — esto
 * valida lo que solo se ve en el navegador real: el banner de aviso, que
 * la pantalla no está en el menú, y el flujo de crear/eliminar un rol.
 */
test('Gestión de Roles: banner de aviso, no está en el menú, crear y eliminar un rol', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');

  // No hay enlace en ningún menú (decisión de Luis, Fase 1) — se entra
  // solo escribiendo la URL.
  await expect(page.locator('nav, .sidebar, .menu').getByText('Gestión de Roles', { exact: false })).toHaveCount(0);

  await page.goto('/roles');
  await expect(page.getByRole('heading', { name: 'Gestión de Roles y Permisos' })).toBeVisible({ timeout: 10000 });

  // Banner de "esto no aplica todavía".
  await expect(page.getByText(/no controlan el acceso real/i)).toBeVisible();

  // Los 10 roles semilla están listados.
  await expect(page.getByText('operativo', { exact: true })).toBeVisible();
  await expect(page.getByText('Sistema', { exact: true }).first()).toBeVisible();

  // Crear un rol nuevo.
  await page.getByRole('button', { name: 'Nuevo Rol' }).click();
  await page.locator('#swal-nombre').fill('Auditor Playwright');
  await page.locator('#swal-slug').fill('auditor_playwright_e2e');
  await page.locator('.swal-permiso').first().check();
  await page.getByRole('button', { name: 'Crear Rol' }).click();
  await expect(page.getByText('¡Éxito!')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();

  await expect(page.getByText('Auditor Playwright')).toBeVisible();
  await expect(page.getByText('auditor_playwright_e2e')).toBeVisible();

  // Limpieza: eliminar el rol recién creado (sin usuarios asignados). El
  // botón es icon-only (Material Symbols) — su nombre accesible real es
  // la ligadura del ícono ("delete"), el title="Eliminar" es solo tooltip.
  const fila = page.locator('tr', { hasText: 'Auditor Playwright' });
  await fila.locator('button[title="Eliminar"]').click();
  await page.getByRole('button', { name: 'Sí, eliminar' }).click();
  await expect(page.getByRole('heading', { name: 'Eliminado' })).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'OK' }).click();
  await expect(page.getByText('Auditor Playwright')).toHaveCount(0);
});
