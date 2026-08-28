const { validateFields } = require('../helpers/creditcard-utils');

/**
 * Helper: cria inputs no DOM do jsdom com os ids esperados pelo validateFields().
 */
function setupDOM(values = {}) {
    document.body.innerHTML = '';

    const fields = {
        'braspag_creditcard-card-holder':      values.holder      ?? '',
        'braspag_creditcard-card-number':      values.number      ?? '',
        'braspag_creditcard-card-expiry':      values.expiry      ?? '',
        'braspag_creditcard-card-cvc':         values.cvc         ?? '',
        'braspag_creditcard-card-type':        values.brand       ?? '',
    };

    for (const [id, val] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.id    = id;
        input.value = val;
        document.body.appendChild(input);
    }
}

function validData() {
    return {
        holder: 'JOAO SILVA',
        number: '4111 1111 1111 1111',
        expiry: '12/28',
        cvc:    '123',
        brand:  'Visa',
    };
}

describe('validateFields', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    // ── Campos válidos ────────────────────────────────────────────────────────
    test('retorna string vazia quando todos os campos estão corretos', () => {
        setupDOM(validData());
        expect(validateFields()).toBe('');
    });

    // ── Nome do titular ───────────────────────────────────────────────────────
    test('retorna erro quando holder está vazio', () => {
        setupDOM({ ...validData(), holder: '' });
        expect(validateFields()).toMatch(/titular/i);
    });

    test('retorna erro quando holder é só espaços', () => {
        setupDOM({ ...validData(), holder: '   ' });
        expect(validateFields()).toMatch(/titular/i);
    });

    // ── Número do cartão ──────────────────────────────────────────────────────
    test('retorna erro quando número está vazio', () => {
        setupDOM({ ...validData(), number: '' });
        expect(validateFields()).toMatch(/cartão/i);
    });

    test('retorna erro quando número tem menos de 13 dígitos', () => {
        setupDOM({ ...validData(), number: '411111111111' }); // 12 dígitos
        expect(validateFields()).toMatch(/cartão/i);
    });

    test('aceita número com exatamente 13 dígitos', () => {
        setupDOM({ ...validData(), number: '4111111111111' }); // 13 dígitos Visa
        expect(validateFields()).toBe('');
    });

    test('aceita número com 16 dígitos formatado com espaços', () => {
        setupDOM({ ...validData(), number: '4111 1111 1111 1111' });
        expect(validateFields()).toBe('');
    });

    // ── Data de expiração ─────────────────────────────────────────────────────
    test('retorna erro quando expiry está vazio', () => {
        setupDOM({ ...validData(), expiry: '' });
        expect(validateFields()).toMatch(/expira|vencimento|válida/i);
    });

    test('retorna erro quando expiry tem formato inválido', () => {
        setupDOM({ ...validData(), expiry: '1228' });
        expect(validateFields()).toMatch(/expira|vencimento|válida/i);
    });

    test('aceita expiry no formato MM/YY', () => {
        setupDOM({ ...validData(), expiry: '12/28' });
        expect(validateFields()).toBe('');
    });

    test('aceita expiry MM/YYYY (normalizado antes da validação)', () => {
        // O campo recebe MM/YYYY mas normalizeExpiry converte para MM/YY
        setupDOM({ ...validData(), expiry: '12/2028' });
        expect(validateFields()).toBe('');
    });

    // ── CVV / CVC ─────────────────────────────────────────────────────────────
    test('retorna erro quando CVV está vazio', () => {
        setupDOM({ ...validData(), cvc: '' });
        expect(validateFields()).toMatch(/segurança|cvc|cvv/i);
    });

    test('retorna erro quando CVV tem menos de 3 dígitos', () => {
        setupDOM({ ...validData(), cvc: '12' });
        expect(validateFields()).toMatch(/segurança|cvc|cvv/i);
    });

    test('aceita CVV com 3 dígitos', () => {
        setupDOM({ ...validData(), cvc: '123' });
        expect(validateFields()).toBe('');
    });

    test('aceita CVV com 4 dígitos (Amex)', () => {
        setupDOM({ ...validData(), cvc: '1234' });
        expect(validateFields()).toBe('');
    });

    // ── Bandeira ──────────────────────────────────────────────────────────────
    test('retorna erro quando brand está vazio', () => {
        setupDOM({ ...validData(), brand: '' });
        expect(validateFields()).toMatch(/bandeira/i);
    });

    test('aceita qualquer string não vazia como brand', () => {
        setupDOM({ ...validData(), brand: 'Elo' });
        expect(validateFields()).toBe('');
    });
});
