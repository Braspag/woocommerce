const fs = require('fs');
const path = require('path');

/**
 * assets/js/braspag-authsop.js é um script legado no formato Class.create()/
 * `var Sop = ...`, carregado via <script> no browser (não é módulo CommonJS).
 * Para testar sem alterar sua estrutura, executamos o código dentro de uma
 * Function com o `document`/globals do jsdom já em escopo (mesmo efeito de
 * um <script> na página) e devolvemos a instância `sop` criada no final do
 * arquivo (`var sop = new Sop();`).
 */
function loadAuthSopScript() {
    // Prototype.js Class.create(): retorna um construtor que chama initialize()
    // quando instanciado.
    const Class = {
        create() {
            return function (...args) {
                if (typeof this.initialize === 'function') {
                    this.initialize(...args);
                }
            };
        },
    };

    const scriptPath = path.join(__dirname, '..', '..', '..', 'assets', 'js', 'braspag-authsop.js');
    const code = fs.readFileSync(scriptPath, 'utf8');

    // eslint-disable-next-line no-new-func
    const run = new Function(
        'Class',
        'document',
        'window',
        'braspag_authsop_params',
        'bpSop_silentOrderPost',
        code + '\nreturn sop;'
    );

    return run(Class, document, window, global.braspag_authsop_params, global.bpSop_silentOrderPost);
}

describe('braspag-authsop.js — bpInit() no contexto Blocks (sem radios clássicos)', () => {
    let sop;

    beforeEach(() => {
        document.body.innerHTML = '';

        // Checkout Blocks não renderiza #payment_method_braspag_creditcard /
        // #payment_method_braspag_debitcard (são radios do checkout clássico).
        global.braspag_authsop_params = {
            bpEnvironment: 'sandbox',
            bpAccessToken: 'token-abc',
            bpMerchantId: 'merchant-1',
            enable: true,
            language: 'pt',
            testMode: false,
            cvvrequired: 'true',
            provider: 'Cielo30',
        };

        global.bpSop_silentOrderPost = jest.fn();

        sop = loadAuthSopScript();
    });

    afterEach(() => {
        delete global.braspag_authsop_params;
        delete global.bpSop_silentOrderPost;
        document.body.innerHTML = '';
    });

    test('initialize() não encontra os radios do checkout clássico no Blocks', async () => {
        await sop.initialize();

        expect(sop.creditCardMethod).toBeNull();
        expect(sop.debitCardMethod).toBeNull();
    });

    test('bpInit() não lança ReferenceError quando os radios clássicos não existem (bug this.debitCardMethod)', async () => {
        await sop.initialize();

        expect(() => sop.bpInit({ submit: jest.fn() })).not.toThrow();
    });

    test('bpInit() usa cardType "creditCard" como fallback e chama o SDK do SOP', async () => {
        await sop.initialize();

        sop.bpInit({ submit: jest.fn() });

        expect(sop.cardType).toBe('creditCard');
        expect(global.bpSop_silentOrderPost).toHaveBeenCalledTimes(1);
    });

    test('checkout clássico continua funcionando: usa o radio marcado quando presente', async () => {
        const creditRadio = document.createElement('input');
        creditRadio.type = 'radio';
        creditRadio.id = 'payment_method_braspag_creditcard';
        creditRadio.checked = true;
        document.body.appendChild(creditRadio);

        await sop.initialize();
        sop.bpInit({ submit: jest.fn() });

        expect(sop.cardType).toBe('creditCard');
        expect(global.bpSop_silentOrderPost).toHaveBeenCalledTimes(1);
    });
});
