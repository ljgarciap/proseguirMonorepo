import { defineConfig, devices } from '@playwright/test';

/**
 * Config de Playwright para Proseguir Factoring. No auto-arranca ng serve
 * ni el backend Docker — ambos deben estar corriendo antes de `npx
 * playwright test` (ver frontend/e2e/README.md). Válida contra el entorno
 * local de desarrollo (http://localhost:4200 + API en localhost:8000).
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost:4200',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
