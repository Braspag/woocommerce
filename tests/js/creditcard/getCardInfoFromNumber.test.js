const { getCardInfoFromNumber } = require('../helpers/creditcard-utils');

describe('getCardInfoFromNumber', () => {
    // ── Visa ──────────────────────────────────────────────────────────────────
    test('detecta Visa pelo prefixo 4', () => {
        expect(getCardInfoFromNumber('4111111111111111').typeName).toBe('Visa');
    });

    test('detecta Visa com 13 dígitos', () => {
        expect(getCardInfoFromNumber('4111111111111').typeName).toBe('Visa');
    });

    // ── Mastercard ────────────────────────────────────────────────────────────
    test('detecta Master pelo prefixo 51', () => {
        expect(getCardInfoFromNumber('5500000000000004').typeName).toBe('Master');
    });

    test('detecta Master pelo prefixo 55', () => {
        expect(getCardInfoFromNumber('5555555555554444').typeName).toBe('Master');
    });

    test('detecta Master pela faixa 2221-2720 (prefixo 22)', () => {
        expect(getCardInfoFromNumber('2221000000000000').typeName).toBe('Master');
    });

    // ── Amex ──────────────────────────────────────────────────────────────────
    test('detecta Amex pelo prefixo 34', () => {
        expect(getCardInfoFromNumber('378282246310005').typeName).toBe('Amex');
    });

    test('detecta Amex pelo prefixo 37', () => {
        expect(getCardInfoFromNumber('371449635398431').typeName).toBe('Amex');
    });

    // ── Diners ────────────────────────────────────────────────────────────────
    test('detecta Diners pelo prefixo 36', () => {
        expect(getCardInfoFromNumber('36227206271667').typeName).toBe('Diners');
    });

    // ── Casos inválidos ───────────────────────────────────────────────────────
    test('retorna null para número inválido', () => {
        expect(getCardInfoFromNumber('9999999999999999')).toBeNull();
    });

    test('retorna null para string vazia', () => {
        expect(getCardInfoFromNumber('')).toBeNull();
    });

    test('retorna null para undefined', () => {
        expect(getCardInfoFromNumber(undefined)).toBeNull();
    });

    test('retorna null para null', () => {
        expect(getCardInfoFromNumber(null)).toBeNull();
    });

    // ── Formatação do número ──────────────────────────────────────────────────
    test('ignora espaços e hífens no número', () => {
        expect(getCardInfoFromNumber('4111 1111 1111 1111').typeName).toBe('Visa');
    });

    test('ignora formatação de número real', () => {
        expect(getCardInfoFromNumber('5500-0000-0000-0004').typeName).toBe('Master');
    });

    // ── Propriedades do objeto retornado ──────────────────────────────────────
    test('objeto retornado tem propriedades esperadas', () => {
        const card = getCardInfoFromNumber('4111111111111111');
        expect(card).toHaveProperty('type');
        expect(card).toHaveProperty('typeName');
        expect(card).toHaveProperty('length');
        expect(card).toHaveProperty('logo');
        expect(card).toHaveProperty('format');
    });

    test('Visa tem length [13, 16]', () => {
        const card = getCardInfoFromNumber('4111111111111111');
        expect(card.length).toEqual([13, 16]);
    });

    test('Amex tem length [15]', () => {
        const card = getCardInfoFromNumber('378282246310005');
        expect(card.length).toEqual([15]);
    });
});
