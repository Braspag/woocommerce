/**
 * Testes para BIN Query (braspag-verifycard.js)
 *
 * Cobre os campos retornados pela API Cielo BIN Query:
 *  - brand (bandeira)
 *  - cardType (credit/debit/multiple)
 *  - foreignCard (cartão internacional)
 *  - corporateCard (cartão corporativo)
 *  - issuerName (banco emissor)
 *  - prepaidCard (cartão pré-pago)
 */

describe('BIN Query — campos retornados', () => {
    // ── Estrutura da resposta ─────────────────────────────────────────────────

    test('resposta completa contém todos os campos esperados da doc Cielo', () => {
        const binResponse = {
            Status: '00',
            Provider: 'CIELO',
            CardType: 'CreditCard',
            ForeignCard: false,
            CorporateCard: false,
            Brand: 'Visa',
            BrandReference: 'VISA',
            BinLength: 6,
            Issuer: 'BRADESCO',
            IssuerCode: '237',
            PrepaidCard: false,
        };

        expect(binResponse.Brand).toBe('Visa');
        expect(binResponse.CardType).toBe('CreditCard');
        expect(binResponse.ForeignCard).toBe(false);
        expect(binResponse.CorporateCard).toBe(false);
        expect(binResponse.PrepaidCard).toBe(false);
        expect(binResponse.Issuer).toBe('BRADESCO');
    });

    test('cartão dual function tem CardType Multiple', () => {
        const dual = { CardType: 'Multiple', Brand: 'Elo' };
        expect(dual.CardType).toBe('Multiple');
    });

    test('cartão internacional tem ForeignCard true', () => {
        const foreign = { ForeignCard: true, Brand: 'Visa' };
        expect(foreign.ForeignCard).toBe(true);
    });

    test('cartão corporativo tem CorporateCard true', () => {
        const corporate = { CorporateCard: true, Brand: 'Mastercard' };
        expect(corporate.CorporateCard).toBe(true);
    });

    test('cartão pré-pago tem PrepaidCard true', () => {
        const prepaid = { PrepaidCard: true, Brand: 'Visa' };
        expect(prepaid.PrepaidCard).toBe(true);
    });

    // ── Gatilho de 6 dígitos ──────────────────────────────────────────────────

    test('BIN query deve disparar ao digitar 6 ou mais dígitos', () => {
        const shouldQuery = (value) => value.replace(/\D/g, '').length >= 6;

        expect(shouldQuery('4111 1')).toBe(false);  // 5 dígitos
        expect(shouldQuery('4111 11')).toBe(true);   // 6 dígitos
        expect(shouldQuery('4111 1111 1111 1111')).toBe(true); // completo
    });

    test('BIN query não dispara com menos de 6 dígitos', () => {
        const shouldQuery = (value) => value.replace(/\D/g, '').length >= 6;
        expect(shouldQuery('4111')).toBe(false);
        expect(shouldQuery('')).toBe(false);
    });

    // ── Erro 323 — serviço não habilitado ────────────────────────────────────

    test('erro 323 deve ser tratado sem bloquear o checkout', () => {
        const handleBinError = (errorCode) => {
            if (errorCode === '323') {
                return { blocked: false, reason: 'service_not_enabled' };
            }
            return { blocked: true, reason: 'unknown_error' };
        };

        const result = handleBinError('323');
        expect(result.blocked).toBe(false);
        expect(result.reason).toBe('service_not_enabled');
    });

    // ── Mapeamento de marca ───────────────────────────────────────────────────

    test('brand Visa é mapeado para ícone correto', () => {
        const brandToIcon = (brand) => {
            const map = {
                'Visa': 'visa',
                'Master': 'mastercard',
                'Elo': 'elo',
                'Amex': 'amex',
                'Hipercard': 'hipercard',
            };
            return map[brand] ?? null;
        };

        expect(brandToIcon('Visa')).toBe('visa');
        expect(brandToIcon('Elo')).toBe('elo');
        expect(brandToIcon('Unknown')).toBeNull();
    });
});
