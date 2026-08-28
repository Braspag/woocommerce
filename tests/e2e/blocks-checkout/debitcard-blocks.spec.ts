import { test, expect, Page } from '@playwright/test';
import { TEST_CARDS } from '../fixtures/test-cards';

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
}

async function fillDebitCardForm(page: Page, card: { number: string }) {
    await page.locator('#radio-control-wc-payment-method-options-braspag_debitcard').click();
    await page.locator('#braspag_debitcard-card-number').fill(card.number);
    await page.locator('#braspag_debitcard-card-holder').fill('TESTE BRASPAG');
    await page.locator('#braspag_debitcard-card-expiry').fill('12/30');
    await page.locator('#braspag_debitcard-card-cvc').fill('123');
}

test.describe('Blocks Checkout — Cartão de Débito', () => {
    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('B2.1 — 3DS frictionless Visa obrigatório', async ({ page }) => {
        await fillDebitCardForm(page, TEST_CARDS.visa3dsSemDesafioSucesso);
        await page.locator('.wc-block-components-checkout-place-order-button').click();
        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
    });

    test('B2.2 — 3DS challenge (interação com modal)', async ({ page }) => {
        test.skip(true, 'Challenge modal requer interação com iframe do banco emissor');
    });

    test('B2.3 — débito sem 3DS deve ser bloqueado', async ({ page }) => {
        test.skip(true, 'Requer desativar 3DS nas configurações admin');
    });
});
