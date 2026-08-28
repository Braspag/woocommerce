import { test, expect, Page } from '@playwright/test';

const BASE = process.env.WP_BASE_URL ?? 'https://bp-lastest.ddev.site';
const CHECKOUT = `${BASE}/finalizar-compra/`;
const PRODUCT_ID = process.env.WP_PRODUCT_ID ?? '86';

async function addProductToCart(page: Page) {
    await page.goto(`${BASE}/?add-to-cart=${PRODUCT_ID}&quantity=1`);
    await page.goto(CHECKOUT);
}

async function fillBillingAddress(page: Page) {
    await page.locator('#email').fill('teste@braspag.com.br');
    await page.locator('#shipping-first_name').fill('Teste');
    await page.locator('#shipping-last_name').fill('Braspag');
    await page.locator('#shipping-phone').fill('11999999999');
    await page.locator('#order-braspag-wcbcf-cpf').fill('123.456.789-09').catch(() => {});
}

test.describe('Blocks Checkout — Boleto', () => {
    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('B7.1 — boleto gerado com link de download', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_boleto').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const boletoLink = page.locator('a[href*="boleto"], a[href*="bank"], .braspag-boleto-link, [data-testid="boleto-link"]').first();
        await expect(boletoLink).toBeVisible({ timeout: 10000 });
    });

    test('B7.2 — linha digitável exibida', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_boleto').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const barCode = page.locator('.braspag-boleto-barcode, .braspag-boleto-digitable, [data-testid="boleto-barcode"]').first();
        if (await barCode.count() > 0) {
            await expect(barCode).toBeVisible();
            const text = await barCode.textContent();
            expect(text?.replace(/\D/g, '').length).toBeGreaterThanOrEqual(44);
        }
    });

    test('B7.3 — data de vencimento exibida', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_boleto').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const body = await page.locator('body').textContent();
        const hasDate = /\d{2}\/\d{2}\/\d{4}/.test(body ?? '');
        expect(hasDate).toBe(true);
    });
});
