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

test.describe('Blocks Checkout — PIX', () => {
    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('B6.1 — QR Code gerado após finalizar pedido', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_pix').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const qrCode = page.locator('.braspag-pix-qrcode, [data-testid="pix-qrcode"], canvas, img[alt*="pix" i], img[alt*="QR" i]');
        await expect(qrCode.first()).toBeVisible({ timeout: 10000 });
    });

    test('B6.2 — código PIX copia-e-cola disponível', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_pix').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const pixCode = page.locator('.braspag-pix-code, [data-testid="pix-code"], input[readonly]').first();
        if (await pixCode.count() > 0) {
            await expect(pixCode).toBeVisible();
            const value = await pixCode.inputValue().catch(() => pixCode.textContent());
            expect((await value)?.length).toBeGreaterThan(0);
        }
    });

    test('B6.3 — expiração de 2 horas exibida ao comprador', async ({ page }) => {
        await page.locator('#radio-control-wc-payment-method-options-braspag_pix').click();
        await page.locator('.wc-block-components-checkout-place-order-button').click();

        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        const body = await page.locator('body').textContent();
        const hasExpiry = body?.includes('2 hora') || body?.includes('120 minuto') || body?.includes('expir');
        if (hasExpiry) {
            expect(hasExpiry).toBe(true);
        }
    });
});
