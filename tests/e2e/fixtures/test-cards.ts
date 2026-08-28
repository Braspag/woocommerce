/**
 * Cartões de teste oficiais Cielo/Braspag para 3DS 2.2
 * Fonte: https://docs.cielo.com.br/ecommerce-cielo/docs/3ds-cartoes-teste
 */
export const TEST_CARDS = {
    // ── Visa — Com Challenge ──────────────────────────────────────────────────
    visa3dsChallengeSucesso:  { number: '4000000000002503', brand: 'Visa', scenario: 'Challenge — sucesso' },
    visa3dsChallenhaFalha:    { number: '4000000000002370', brand: 'Visa', scenario: 'Challenge — falha' },

    // ── Visa — Sem Challenge (Frictionless) ───────────────────────────────────
    visa3dsSemDesafioSucesso: { number: '4000000000002701', brand: 'Visa', scenario: 'Frictionless — sucesso' },

    // ── Visa — Data Only ──────────────────────────────────────────────────────
    visa3dsDataOnly:          { number: '4000000000002024', brand: 'Visa', scenario: 'Data Only (sem challenge)' },

    // ── Mastercard — Com Challenge ────────────────────────────────────────────
    mc3dsChallengeSucesso:    { number: '5200000000002151', brand: 'Master', scenario: 'Challenge — sucesso' },
    mc3dsChallenhaFalha:      { number: '5200000000002490', brand: 'Master', scenario: 'Challenge — falha' },

    // ── Mastercard — Sem Challenge ────────────────────────────────────────────
    mc3dsSemDesafioSucesso:   { number: '5200000000002235', brand: 'Master', scenario: 'Frictionless — sucesso' },

    // ── Mastercard — Data Only ────────────────────────────────────────────────
    mc3dsDataOnly:            { number: '5200000000002805', brand: 'Master', scenario: 'Data Only (sem challenge)' },

    // ── Elo — Com Challenge (ADR-003 revogado: Elo suporta 3DS 2.2) ──────────
    elo3dsChallengeSucesso:   { number: '6505290000002190', brand: 'Elo', scenario: 'Challenge — sucesso' },

    // ── Elo — Sem Challenge ───────────────────────────────────────────────────
    elo3dsSemDesafioSucesso:  { number: '6505290000002000', brand: 'Elo', scenario: 'Frictionless — sucesso' },
} as const;

export const TEST_EXPIRY = '12/30';
export const TEST_CVV    = '123';
export const TEST_HOLDER = 'TESTE BRASPAG';

export type TestCard = typeof TEST_CARDS[keyof typeof TEST_CARDS];
