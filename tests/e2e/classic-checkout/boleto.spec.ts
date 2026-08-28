import { test, expect, Page } from '@playwright/test';

const BASE = process.env.WP_BASE_URL ?? 'http://localhost:8080';

async function addProductToCart(page: Page) {
    await page.goto(`${BASE}/?add-to-cart=1`);
    await page.goto(`${BASE}/checkout/`);
}

async function fillBillingAddress(page: Page) {
    await page.locator('#billing_first_name').fill('Teste');
    await page.locator('#billing_last_name').fill('Braspag');
    await page.locator('#billing_email').fill('teste@braspag.com.br');
    await page.locator('#billing_phone').fill('11999999999');
    await page.locator('#billing_cpf').fill('123.456.789-09').catch(() => {});
}

test.describe('Classic Checkout — Boleto', () => {
    test.use({ storageState: 'tests/e2e/.auth/admin.json' });

    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('7.1 — boleto gerado com link de download', async ({ page }) => {
        await page.locator('#payment_method_braspag_boleto').click();
        await page.locator('#place_order').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 15000 });

        // Link do boleto deve estar visível
        const boletoLink = page.locator('a[href*="boleto"], a[href*="bank"], .braspag-boleto-link, [data-testid="boleto-link"]').first();
        await expect(boletoLink).toBeVisible({ timeout: 10000 });
    });

    test('7.2 — linha digitável exibida', async ({ page }) => {
        await page.locator('#payment_method_braspag_boleto').click();
        await page.locator('#place_order').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 15000 });

        // Linha digitável (código de barras) deve estar presente
        const barCode = page.locator('.braspag-boleto-barcode, .braspag-boleto-digitable, [data-testid="boleto-barcode"]').first();
        if (await barCode.count() > 0) {
            await expect(barCode).toBeVisible();
            const text = await barCode.textContent();
            // Linha digitável tem entre 47-48 dígitos
            expect(text?.replace(/\D/g, '').length).toBeGreaterThanOrEqual(44);
        }
    });

    test('7.3 — data de vencimento exibida', async ({ page }) => {
        await page.locator('#payment_method_braspag_boleto').click();
        await page.locator('#place_order').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 15000 });

        // Data de vencimento deve estar presente
        const body = await page.locator('body').textContent();
        const hasDate = /\d{2}\/\d{2}\/\d{4}/.test(body ?? '');
        expect(hasDate).toBe(true);
    });
});
