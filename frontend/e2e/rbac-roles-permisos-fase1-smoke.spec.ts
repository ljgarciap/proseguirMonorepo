import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import { loginAs } from './helpers/auth';

function tinker(php: string): string {
  return execFileSync('docker', ['exec', 'factoring_backend', 'php', 'artisan', 'tinker', '--execute', php]).toString();
}

/**
 * Smoke visual del motor paramétrico de Roles y Permisos, Fase 1 + Fase 2
 * (docs/specs/rbac-roles-permisos-parametrico.md,
 * docs/specs/rbac-fase2-enforcement.md). Cobertura funcional completa ya
 * está en PHPUnit (RoleControllerTest, CheckPermissionTest) — esto valida
 * lo que solo se ve en el navegador real: la pantalla ya está en el menú
 * (Fase 2 conectó el enforcement real), y el flujo de crear/eliminar un
 * rol.
 */
test('Gestión de Roles: está en el menú, crear y eliminar un rol', async ({ page }) => {
  await loginAs(page, '1234', '1234', 'superadmin');

  // Fase 2: ya tiene enlace en el menú (Configuración → Roles y Permisos).
  await page.getByText('Roles y Permisos').click();
  await expect(page.getByRole('heading', { name: 'Gestión de Roles y Permisos' })).toBeVisible({ timeout: 10000 });

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

/**
 * Fase 2: valida el gate de pantalla real (data.permission + roleGuard vía
 * AuthService.hasPermission) contra un usuario que NO es superadmin — el
 * usuario de fixture '1234' tiene superadmin entre sus roles, así que no
 * sirve para probar el camino "bloqueado" (el bypass de superadmin
 * siempre pasaría). Se crea un usuario dedicado solo-operativo.
 */
test('Gate de pantalla real: operativo entra a /upload, no a /users', async ({ page }) => {
  const numeroDocumento = '999888';
  tinker(`
    \\App\\Models\\User::where('numero_documento','${numeroDocumento}')->delete();
    \\App\\Models\\User::create([
      'name' => 'Operativo Solo Playwright',
      'numero_documento' => '${numeroDocumento}',
      'tipo_documento_id' => \\App\\Models\\DocumentType::first()->id,
      'password' => bcrypt('password123'),
      'roles' => ['operativo'],
    ]);
  `);

  await loginAs(page, numeroDocumento, 'password123', 'operativo');

  await page.goto('/upload');
  await expect(page).toHaveURL(/\/upload$/, { timeout: 10000 });

  await page.goto('/users');
  await expect(page).not.toHaveURL(/\/users$/, { timeout: 10000 });

  tinker(`\\App\\Models\\User::where('numero_documento','${numeroDocumento}')->forceDelete();`);
});
