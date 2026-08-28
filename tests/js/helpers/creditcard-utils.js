/**
 * Funções puras extraídas de assets/js/blocks/braspag-creditcard.js para testes.
 *
 * Como o source é um IIFE (não é um módulo ES), este arquivo re-exporta as
 * funções como CommonJS para que Jest possa importá-las diretamente.
 * Mantenha sincronizado com o arquivo source quando as funções forem alteradas.
 */

const braspagCards = [
    { type: 'visa',      typeName: 'Visa',      patterns: [4],                                          regex_include: '^(4)',            regex_exclude: '', format: /\d{1,4}/g,                             length: [13, 16], logo: 'visa' },
    { type: 'mastercard',typeName: 'Master',    patterns: [51, 52, 53, 54, 55, 22, 23, 24, 25, 26, 27], regex_include: '^(5[1-5]|2[2-7])',regex_exclude: '', format: /\d{1,4}/g,                             length: [16],     logo: 'mastercard' },
    { type: 'amex',      typeName: 'Amex',      patterns: [34, 37],                                     regex_include: '^(34|37)',        regex_exclude: '', format: /(\d{1,4})(\d{1,6})?(\d{1,5})?/,     length: [15],     logo: 'amex' },
    { type: 'elo',       typeName: 'Elo',       patterns: [6363, 4389, 5041, 4514, 6362, 5067, 4576, 4011], regex_include: '',             regex_exclude: '', format: /\d{1,4}/g,                             length: [16],     logo: 'elo' },
    { type: 'hipercard', typeName: 'Hipercard', patterns: [38, 60, 6062, 6370, 6375, 6376],             regex_include: '',               regex_exclude: '', format: /\d{1,4}/g,                             length: [16],     logo: 'hipercard' },
    { type: 'diners',    typeName: 'Diners',    patterns: [30, 36, 38, 39],                             regex_include: '^(36)',           regex_exclude: '', format: /(\d{1,4})(\d{1,6})?(\d{1,4})?/,     length: [14],     logo: 'diners' },
    { type: 'discover',  typeName: 'Discover',  patterns: [6011, 622, 64, 65],                          regex_include: '',               regex_exclude: '', format: /\d{1,4}/g,                             length: [16],     logo: 'discover' },
];

function getCardInfoFromNumber(num) {
    const sanitized = String(num || '').replace(/\D/g, '');

    for (let index = 0; index < braspagCards.length; index++) {
        const card = braspagCards[index];

        if (card.regex_include && new RegExp(card.regex_include).test(sanitized)) {
            return card;
        }

        for (let patternIndex = 0; patternIndex < card.patterns.length; patternIndex++) {
            const pattern = String(card.patterns[patternIndex]);
            if (sanitized.substr(0, pattern.length) === pattern) {
                return card;
            }
        }
    }

    return null;
}

function formatCardNumber(num) {
    let sanitized = String(num || '').replace(/\D/g, '');
    const card = getCardInfoFromNumber(sanitized);

    if (!card) {
        return sanitized.replace(/(.{4})/g, '$1 ').trim();
    }

    sanitized = sanitized.slice(0, card.length[card.length.length - 1]);

    if (card.format.global) {
        return (sanitized.match(card.format) || []).join(' ');
    }

    const groups = card.format.exec(sanitized);
    if (!groups) {
        return sanitized;
    }

    groups.shift();
    return groups.filter(Boolean).join(' ');
}

function formatExpiry(value) {
    const sanitized = String(value || '').replace(/\D/g, '').slice(0, 6);

    if (sanitized.length <= 2) {
        return sanitized;
    }

    return sanitized.slice(0, 2) + '/' + sanitized.slice(2);
}

function normalizeExpiry(value) {
    const formatted = String(value || '').replace(/\s+/g, '');
    const match = formatted.match(/^(\d{2})\/(\d{2}|\d{4})$/);

    if (!match) {
        return formatted;
    }

    let year = match[2];
    if (year.length === 4) {
        year = year.slice(2);
    }

    return match[1] + '/' + year;
}

/**
 * Retorna mensagem de erro de validação dos campos do form, ou '' se válido.
 * Lê do DOM (requer jsdom com os inputs criados).
 */
function validateFields() {
    function getInputValue(id) {
        const input = document.getElementById(id);
        return input ? String(input.value || '') : '';
    }

    const holderName  = getInputValue('braspag_creditcard-card-holder').trim();
    const cardNumber  = getInputValue('braspag_creditcard-card-number').replace(/\s+/g, '');
    const expiryRaw   = getInputValue('braspag_creditcard-card-expiry');
    const expiry      = normalizeExpiry(expiryRaw);
    const cvc         = getInputValue('braspag_creditcard-card-cvc').replace(/\s+/g, '');
    const brand       = getInputValue('braspag_creditcard-card-type');

    if (!holderName)                           return 'Informe o nome do titular.';
    if (!cardNumber || cardNumber.length < 13) return 'Informe um número de cartão válido.';
    if (!/^\d{2}\/\d{2}$/.test(expiry))       return 'Informe uma data de expiração válida.';
    if (!cvc || cvc.length < 3)               return 'Informe o código de segurança.';
    if (!brand)                               return 'Não foi possível identificar a bandeira do cartão.';

    return '';
}

/**
 * Constrói o objeto paymentMethodData lido do DOM.
 */
function buildPaymentMethodData() {
    function getInputValue(id, fallback = '') {
        const input = document.getElementById(id);
        return input ? String(input.value || '') : fallback;
    }

    function normalizeExpiryLocal(value) {
        return normalizeExpiry(value);
    }

    return {
        payment_method: 'braspag_creditcard',
        'braspag_creditcard-card-holder':       getInputValue('braspag_creditcard-card-holder'),
        'braspag_creditcard-card-number':       getInputValue('braspag_creditcard-card-number').replace(/\s+/g, ''),
        'braspag_creditcard-card-expiry':       normalizeExpiryLocal(getInputValue('braspag_creditcard-card-expiry')),
        'braspag_creditcard-card-cvc':          getInputValue('braspag_creditcard-card-cvc'),
        'braspag_creditcard-card-type':         getInputValue('braspag_creditcard-card-type'),
        'braspag_creditcard-card-installments': getInputValue('braspag_creditcard-card-installments', '1'),
        'wc-braspag_creditcard-new-payment-method': document.getElementById('wc-braspag_creditcard-new-payment-method')?.checked ? 'true' : 'false',
        'braspag_creditcard-card-paymenttoken': getInputValue('braspag_creditcard-card-paymenttoken'),
        'braspag_creditcard-card-cardtoken':    getInputValue('braspag_creditcard-card-cardtoken'),
        bpmpi_auth_cavv:           getInputValue('bpmpi_auth_cavv'),
        bpmpi_auth_xid:            getInputValue('bpmpi_auth_xid'),
        bpmpi_auth_eci:            getInputValue('bpmpi_auth_eci'),
        bpmpi_auth_version:        getInputValue('bpmpi_auth_version'),
        bpmpi_auth_reference_id:   getInputValue('bpmpi_auth_reference_id'),
        bpmpi_auth_failure_type:   getInputValue('bpmpi_auth_failure_type', '0'),
    };
}

module.exports = {
    braspagCards,
    getCardInfoFromNumber,
    formatCardNumber,
    formatExpiry,
    normalizeExpiry,
    validateFields,
    buildPaymentMethodData,
};
