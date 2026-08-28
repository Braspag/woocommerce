const { buildPaymentMethodData } = require('../helpers/creditcard-utils');

/**
 * Helper: cria todos os inputs que buildPaymentMethodData() lê do DOM.
 */
function setupDOM(values = {}) {
    document.body.innerHTML = '';

    const fields = {
        'braspag_creditcard-card-holder':       values.holder        ?? 'JOAO SILVA',
        'braspag_creditcard-card-number':       values.number        ?? '4111111111111111',
        'braspag_creditcard-card-expiry':       values.expiry        ?? '12/28',
        'braspag_creditcard-card-cvc':          values.cvc           ?? '123',
        'braspag_creditcard-card-type':         values.brand         ?? 'Visa',
        'braspag_creditcard-card-installments': values.installments  ?? '1',
        'braspag_creditcard-card-paymenttoken': values.paymentToken  ?? '',
        'braspag_creditcard-card-cardtoken':    values.cardToken     ?? '',
        'bpmpi_auth_cavv':                      values.cavv          ?? '',
        'bpmpi_auth_xid':                       values.xid           ?? '',
        'bpmpi_auth_eci':                       values.eci           ?? '',
        'bpmpi_auth_version':                   values.version3ds    ?? '',
        'bpmpi_auth_reference_id':              values.referenceId   ?? '',
        'bpmpi_auth_failure_type':              values.failureType   ?? '0',
    };

    for (const [id, val] of Object.entries(fields)) {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.id    = id;
        input.value = val;
        document.body.appendChild(input);
    }

    // Checkbox save card
    if (values.saveCard !== undefined) {
        const cb = document.createElement('input');
        cb.type    = 'checkbox';
        cb.id      = 'wc-braspag_creditcard-new-payment-method';
        cb.checked = values.saveCard;
        document.body.appendChild(cb);
    }
}

describe('buildPaymentMethodData', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    // ── Estrutura base ────────────────────────────────────────────────────────
    test('sempre retorna payment_method = braspag_creditcard', () => {
        setupDOM();
        expect(buildPaymentMethodData()['payment_method']).toBe('braspag_creditcard');
    });

    test('retorna todos os campos esperados', () => {
        setupDOM();
        const data = buildPaymentMethodData();

        const expectedKeys = [
            'payment_method',
            'braspag_creditcard-card-holder',
            'braspag_creditcard-card-number',
            'braspag_creditcard-card-expiry',
            'braspag_creditcard-card-cvc',
            'braspag_creditcard-card-type',
            'braspag_creditcard-card-installments',
            'wc-braspag_creditcard-new-payment-method',
            'braspag_creditcard-card-paymenttoken',
            'braspag_creditcard-card-cardtoken',
            'bpmpi_auth_cavv',
            'bpmpi_auth_xid',
            'bpmpi_auth_eci',
            'bpmpi_auth_version',
            'bpmpi_auth_reference_id',
            'bpmpi_auth_failure_type',
        ];

        for (const key of expectedKeys) {
            expect(data).toHaveProperty(key);
        }
    });

    // ── Dados do cartão ───────────────────────────────────────────────────────
    test('lê holder do campo correto', () => {
        setupDOM({ holder: 'MARIA SOUZA' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-holder']).toBe('MARIA SOUZA');
    });

    test('remove espaços do número do cartão', () => {
        setupDOM({ number: '4111 1111 1111 1111' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-number']).toBe('4111111111111111');
    });

    test('normaliza expiry MM/YYYY para MM/YY', () => {
        setupDOM({ expiry: '12/2028' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-expiry']).toBe('12/28');
    });

    test('mantém expiry MM/YY sem alteração', () => {
        setupDOM({ expiry: '06/30' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-expiry']).toBe('06/30');
    });

    // ── SOP: tokens ──────────────────────────────────────────────────────────
    test('inclui paymentToken quando presente', () => {
        setupDOM({ paymentToken: 'tok-sop-xyz' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-paymenttoken']).toBe('tok-sop-xyz');
    });

    test('inclui cardToken quando presente', () => {
        setupDOM({ cardToken: 'card-tok-abc' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-cardtoken']).toBe('card-tok-abc');
    });

    // ── 3DS: dados de autenticação ────────────────────────────────────────────
    test('inclui dados de 3DS quando presentes', () => {
        setupDOM({
            cavv:        'cavv-test',
            xid:         'xid-test',
            eci:         '05',
            version3ds:  '2.2.0',
            referenceId: 'ref-001',
        });

        const data = buildPaymentMethodData();
        expect(data['bpmpi_auth_cavv']).toBe('cavv-test');
        expect(data['bpmpi_auth_xid']).toBe('xid-test');
        expect(data['bpmpi_auth_eci']).toBe('05');
        expect(data['bpmpi_auth_version']).toBe('2.2.0');
        expect(data['bpmpi_auth_reference_id']).toBe('ref-001');
    });

    test('bpmpi_auth_failure_type usa "0" como fallback quando campo ausente', () => {
        document.body.innerHTML = '';
        const data = buildPaymentMethodData();
        expect(data['bpmpi_auth_failure_type']).toBe('0');
    });

    // ── Checkbox salvar cartão ────────────────────────────────────────────────
    test('save card retorna "true" quando checkbox marcado', () => {
        setupDOM({ saveCard: true });
        expect(buildPaymentMethodData()['wc-braspag_creditcard-new-payment-method']).toBe('true');
    });

    test('save card retorna "false" quando checkbox desmarcado', () => {
        setupDOM({ saveCard: false });
        expect(buildPaymentMethodData()['wc-braspag_creditcard-new-payment-method']).toBe('false');
    });

    test('save card retorna "false" quando checkbox ausente no DOM', () => {
        setupDOM();
        expect(buildPaymentMethodData()['wc-braspag_creditcard-new-payment-method']).toBe('false');
    });

    // ── Parcelamento ──────────────────────────────────────────────────────────
    test('usa "1" como fallback quando campo installments ausente', () => {
        document.body.innerHTML = '';
        expect(buildPaymentMethodData()['braspag_creditcard-card-installments']).toBe('1');
    });

    test('lê installments do campo quando presente', () => {
        setupDOM({ installments: '3' });
        expect(buildPaymentMethodData()['braspag_creditcard-card-installments']).toBe('3');
    });
});
