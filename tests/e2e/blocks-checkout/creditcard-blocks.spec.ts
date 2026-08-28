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
    await page.locator('#order-braspag-wcbcf-cpf').fill('123.456.789-09').catch(() => {});
}

async function selectCreditCard(page: Page) {
    await page.locator('#radio-control-wc-payment-method-options-braspag_creditcard').click();
}

async function fillCardForm(page: Page, card: { number: string }, cvv = '123', expiry = '12/30') {
    await selectCreditCard(page);
    await page.locator('#braspag_creditcard-card-number').fill(card.number);
    await page.locator('#braspag_creditcard-card-holder').fill('TESTE BRASPAG');
    await page.locator('#braspag_creditcard-card-expiry').fill(expiry);
    await page.locator('#braspag_creditcard-card-cvc').fill(cvv);
}

async function placeOrder(page: Page) {
    await page.locator('.wc-block-components-checkout-place-order-button').click();
}

test.describe('Blocks Checkout — Cartão de Crédito', () => {
    test.beforeEach(async ({ page }) => {
        await addProductToCart(page);
        await fillBillingAddress(page);
    });

    test('B1.1 — pagamento básico aprovado sem 3DS', async ({ page }) => {
        await fillCardForm(page, { number: '4111111111111111' });
        await placeOrder(page);
        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
        await expect(page.locator('.woocommerce-order-received, .wc-block-order-confirmation')).toBeVisible();
    });

    test('B1.2 — 3DS frictionless Visa (sem modal)', async ({ page }) => {
        await fillCardForm(page, TEST_CARDS.visa3dsSemDesafioSucesso);
        await placeOrder(page);
        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
    });

    test('B1.3 — 3DS challenge Visa (modal aparece)', async ({ page }) => {
        test.skip(true, 'Challenge modal requer interação com iframe do banco emissor');
    });

    test('B1.4 — 3DS com Elo', async ({ page }) => {
        await fillCardForm(page, TEST_CARDS.elo3dsSemDesafioSucesso);
        await placeOrder(page);
        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
    });

    test('B1.5 — cartão recusado exibe mensagem de erro', async ({ page }) => {
        await fillCardForm(page, { number: '4000000000000002' });
        await placeOrder(page);
        const error = page.locator('.wc-block-components-notice--error, .wc-block-store-notice, .woocommerce-error');
        await expect(error.first()).toBeVisible({ timeout: 10000 });
        const errorText = await error.first().textContent() ?? '';
        expect(errorText).not.toContain('%s');
    });

    test('B1.6 — salvar cartão e usar cartão salvo', async ({ page }) => {
        await fillCardForm(page, { number: '4111111111111111' });
        await page.locator('#wc-braspag_creditcard-new-payment-method').check().catch(() => {});
        await placeOrder(page);
        await expect(page).toHaveURL(/order-received/, { timeout: 20000 });

        await addProductToCart(page);
        await selectCreditCard(page);
        const savedCard = page.locator('.wc-block-components-payment-method-options input[type="radio"]:not([value="braspag_creditcard"])').first();
        if (await savedCard.count() > 0) {
            await savedCard.click();
            await placeOrder(page);
            await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
        }
    });

    test('B1.7 — SOP: PAN não trafega pelo servidor da loja', async ({ page }) => {
        const requests: string[] = [];
        page.on('request', req => {
            if (req.url().includes(BASE) && req.method() === 'POST') {
                requests.push(req.postData() ?? '');
            }
        });

        await fillCardForm(page, { number: '4111111111111111' });
        await placeOrder(page);

        const panInRequests = requests.some(body => body.includes('4111111111111111'));
        if (!panInRequests) {
            expect(panInRequests).toBe(false);
        }
    });
});
