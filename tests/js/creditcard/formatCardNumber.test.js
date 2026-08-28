const { formatCardNumber } = require('../helpers/creditcard-utils');

describe('formatCardNumber', () => {
    // ── Visa: grupos de 4 ─────────────────────────────────────────────────────
    test('formata Visa 16 dígitos em grupos de 4', () => {
        expect(formatCardNumber('4111111111111111')).toBe('4111 1111 1111 1111');
    });

    test('formata Visa 13 dígitos corretamente', () => {
        const result = formatCardNumber('4111111111111');
        expect(result).toMatch(/^\d{4} \d{4} \d{4} \d{1}$/);
    });

    // ── Mastercard: grupos de 4 ───────────────────────────────────────────────
    test('formata Master 16 dígitos em grupos de 4', () => {
        expect(formatCardNumber('5500000000000004')).toBe('5500 0000 0000 0004');
    });

    // ── Amex: formato 4-6-5 ───────────────────────────────────────────────────
    test('formata Amex 15 dígitos no padrão 4-6-5', () => {
        const result = formatCardNumber('378282246310005');
        // Amex: grupos não uniformes, mas deve ter 2 espaços (3 grupos)
        const parts = result.split(' ');
        expect(parts.length).toBe(3);
        expect(parts[0].length).toBe(4);
        expect(parts[1].length).toBe(6);
        expect(parts[2].length).toBe(5);
    });

    // ── Sem bandeira detectada ────────────────────────────────────────────────
    test('formata número desconhecido em grupos de 4', () => {
        expect(formatCardNumber('9999999999999999')).toBe('9999 9999 9999 9999');
    });

    test('formata número curto sem bandeira', () => {
        expect(formatCardNumber('1234')).toBe('1234');
    });

    // ── Truncamento no max length da bandeira ─────────────────────────────────
    test('trunca Visa no máximo de 16 dígitos', () => {
        const result = formatCardNumber('41111111111111119999');
        const digits = result.replace(/\s/g, '');
        expect(digits.length).toBeLessThanOrEqual(16);
    });

    // ── Entrada já formatada ──────────────────────────────────────────────────
    test('re-formata número com espaços já inseridos', () => {
        expect(formatCardNumber('4111 1111 1111 1111')).toBe('4111 1111 1111 1111');
    });

    // ── Entrada vazia ─────────────────────────────────────────────────────────
    test('retorna string vazia para entrada vazia', () => {
        expect(formatCardNumber('')).toBe('');
    });

    test('retorna string vazia para null', () => {
        expect(formatCardNumber(null)).toBe('');
    });

    test('retorna string vazia para undefined', () => {
        expect(formatCardNumber(undefined)).toBe('');
    });

    // ── Apenas letras → trata como desconhecido ───────────────────────────────
    test('ignora letras e processa apenas dígitos', () => {
        const result = formatCardNumber('4111abc1111def1111');
        expect(result.replace(/\s/g, '')).toMatch(/^\d+$/);
    });
});
