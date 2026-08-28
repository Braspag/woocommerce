const { normalizeExpiry, formatExpiry } = require('../helpers/creditcard-utils');

describe('normalizeExpiry', () => {
    // ── Formato MM/YY (já correto) ────────────────────────────────────────────
    test('mantém MM/YY sem alteração', () => {
        expect(normalizeExpiry('12/28')).toBe('12/28');
    });

    test('mantém 01/29 sem alteração', () => {
        expect(normalizeExpiry('01/29')).toBe('01/29');
    });

    // ── Formato MM/YYYY → normaliza para MM/YY ────────────────────────────────
    test('converte MM/YYYY para MM/YY', () => {
        expect(normalizeExpiry('12/2028')).toBe('12/28');
    });

    test('converte 01/2030 para 01/30', () => {
        expect(normalizeExpiry('01/2030')).toBe('01/30');
    });

    // ── Formatos inválidos → retorna como está ────────────────────────────────
    test('retorna string sem separador como está', () => {
        expect(normalizeExpiry('1228')).toBe('1228');
    });

    test('retorna string vazia para entrada vazia', () => {
        expect(normalizeExpiry('')).toBe('');
    });

    test('retorna string vazia para null (null || "" = "")', () => {
        expect(normalizeExpiry(null)).toBe('');
    });

    test('retorna formato parcial como está', () => {
        expect(normalizeExpiry('12')).toBe('12');
    });

    // ── Remove espaços antes de processar ────────────────────────────────────
    test('processa mesmo com espaços', () => {
        expect(normalizeExpiry('12 / 28')).toBe('12/28');
    });
});

describe('formatExpiry', () => {
    test('formata 4 dígitos como MM/YY', () => {
        expect(formatExpiry('1228')).toBe('12/28');
    });

    test('formata 2 dígitos retorna apenas MM', () => {
        expect(formatExpiry('12')).toBe('12');
    });

    test('retorna string vazia para entrada vazia', () => {
        expect(formatExpiry('')).toBe('');
    });

    test('remove caracteres não numéricos', () => {
        expect(formatExpiry('12/28')).toBe('12/28');
    });

    test('trunca em 6 dígitos (MM/YYYY)', () => {
        const result = formatExpiry('122028');
        expect(result.replace(/\D/g, '').length).toBeLessThanOrEqual(6);
    });

    test('formata digitação parcial de 1 dígito', () => {
        expect(formatExpiry('1')).toBe('1');
    });
});
