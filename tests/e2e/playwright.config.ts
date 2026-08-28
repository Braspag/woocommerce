import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E — WooCommerce Braspag Plugin
 *
 * Pré-requisitos:
 *   - WP sandbox rodando em WP_BASE_URL (padrão: http://localhost:8080)
 *   - Credenciais sandbox Cielo configuradas no WP Admin
 *   - ngrok ou similar para receber webhooks (opcional para testes básicos)
 *
 * Execução:
 *   npx playwright test
 *   npx playwright test tests/e2e/classic-checkout/
 *   npx playwright test tests/e2e/blocks-checkout/
 */
export default defineConfig({
    testDir: '.',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['html', { outputFolder: 'tests/e2e/reports' }],
        ['list'],
    ],

    use: {
        baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8080',
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'on-first-retry',
        locale: 'pt-BR',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
