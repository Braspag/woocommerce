import { test, expect, Page } from '@playwright/test';
import { TEST_CARDS } from '../fixtures/test-cards';

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
}

async function fillDebitCardForm(page: Page, card: { number: string }) {
    await page.locator('#payment_method_braspag_debitcard').click();
    await page.locator('#braspag_debitcard-card-number').fill(card.number);
    await page.locator('#braspag_debitcard-card-holder').fill('TESTE BRASPAG');
    await page.locator('#braspag_debitcard-card-expiry').fill('12/30');
    await page.locator('#braspag_debitcard-card-cvc').fill('123');
}

test.describe('Classic Checkout — Cartão de Débito', () => {
    test.use({ storageState: 'tests/e2e/.auth/admin.json' });

    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('2.1 — 3DS frictionless Visa obrigatório', async ({ page }) => {
        await fillDebitCardForm(page, TEST_CARDS.visa3dsSemDesafioSucesso);
        await page.locator('#place_order').click();
        // Débito requer 3DS — deve processar sem modal em frictionless
        await expect(page).toHaveURL(/order-received/, { timeout: 15000 });
    });

    test('2.2 — 3DS challenge (interação com modal)', async ({ page }) => {
        test.skip(true, 'Challenge modal requer interação com iframe do banco emissor');
    });

    test('2.3 — débito sem 3DS deve ser bloqueado', async ({ page }) => {
        // Se 3DS estiver desativado, o débito não pode ser processado
        // Este cenário requer configuração específica no admin — teste informativo
        test.skip(true, 'Requer desativar 3DS nas configurações admin');
    });
});
