# TDD — Documento de Projeto Técnico
**Produto:** Braspag for WooCommerce Oficial · **Versão:** 1.0 · **Data:** 2026-08-26

Este documento traduz PRD/ARD/SDD em decisões de implementação concretas — o "como" para quem vai codar.

## 1. Ambiente de Desenvolvimento

| Item | Valor |
|---|---|
| PHP | 7.4+ (testar também em 8.x se disponível no host) |
| WordPress | 6.x local (recomendado `wp-env` ou Playground — ver [08-BLUEPRINT.md](08-BLUEPRINT.md)) |
| WooCommerce | 10.x |
| Dependência obrigatória | `woocommerce-extra-checkout-fields-for-brazil` (ECFB) ativo |
| Gerenciador JS | `npm` (Jest para testes, `@playwright/test` para E2E) |
| Testes PHP | PHPUnit (suites `Unit` e `Integration`) |

## 2. Convenções de Código

- **PHP:** sem namespaces; prefixo `WC_Braspag_`; PHPCS com padrão WordPress (`vendor/bin/phpcs`); PHPStan para análise estática.
- **JS (Blocks):** arquivos em `assets/js/blocks/*.js`; cobertura Jest configurada em `package.json` (`collectCoverageFrom`).
- **Commits:** mensagens em PT-BR, formato Conventional Commits (`feat:`, `fix:`, `refactor:`), ex.: `feat: adiciona suporte a PIX no Blocks`.
- **Hooks de commit:** `.captainhook/` presente — validar hooks configurados antes de commitar.

## 3. Ciclo de Implementação de uma Feature

1. **Spec-first:** criar/atualizar `[slug]-ard.md` (se houver decisão arquitetural nova), `[slug]-prd.md`, `[slug]-sdd.md` em `docs/specs/`.
2. **Task breakdown:** `[slug]-tasks.md` com tarefas atômicas, marcando paralelizáveis com `[P]`.
3. **Implementação:** nova classe segue convenção de nomes (seção 6 do SDD); builders adicionados via filter, nunca `if/else` acumulado.
4. **Testes:** escrever/atualizar teste PHPUnit (Unit e, se tocar fluxo de pedido, Integration) e teste Jest se houver JS de Blocks.
5. **Validação de segurança:** revisar que nenhum PAN/CVV/MerchantKey é logado; nonce validado em `process_payment()`.
6. **Code review:** aprovação obrigatória para os "arquivos de alto risco" listados no SDD (§6).
7. **Atualizar `.claude/memory/progress.md`** com o estado da feature.

## 4. Padrão de Implementação — Novo Gateway de Pagamento

```php
class WC_Gateway_Braspag_NovoMetodo extends WC_Braspag_Payment_Gateway {
    public function __construct() {
        $this->id = 'braspag_novometodo';
        // título, ícone, form_fields via includes/admin/braspag-novometodo-settings.php
    }

    public function validate_fields() {
        // sanitize_text_field() + checagens de negócio + nonce
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $payload = apply_filters( 'braspag_pagador_novometodo_payment_request_builder', [...], $order );
        $response = $this->api->create_sale( $payload );
        // Status=2 -> processing ; senão -> failed
    }
}
```

Registrar em `class-wc-gateway-braspag.php` via filter `woocommerce_payment_gateways`, e criar o Block correspondente em `includes/blocks/payments/` herdando `WC_Braspag_Blocks_Abstract`.

## 5. Padrão de Implementação — Builder de Payload (extensão via filter)

```php
add_filter( 'braspag_pagador_creditcard_payment_request_builder', function( $payload, $order ) {
    // adiciona/ajusta campos específicos, sem tocar na classe do gateway
    return $payload;
}, 20, 2 );
```

Regra: builders devem ser puros (não fazem I/O), recebem o payload parcial e devolvem o payload aumentado.

## 6. Tratamento de Erros e Retry

| Cenário | Comportamento |
|---|---|
| HTTP 4xx da Braspag | Sem retry; erro repassado a `WC_Braspag_Exception`; pedido → `failed` com nota explicativa |
| HTTP 5xx / timeout | Retry com backoff 1s → 2s → 4s (3 tentativas); se todas falharem, `failed` |
| OAuth 401 (MPI/Risk) | Invalida cache do token, solicita novo, repete a chamada original uma vez |
| Amex + Zero Auth (erro 57) | Fallback gracioso: tokeniza sem Zero Auth, log informativo (não erro) |
| Webhook sem `webhook_secret` | Loga aviso, processa em modo permissivo (não bloqueia) |

## 7. Checklist de Definição de Pronto (DoD)

- [ ] Spec correspondente aprovada em `docs/specs/`
- [ ] Código sem namespace, sem SQL direto, com prefixo `WC_Braspag_`
- [ ] PHPUnit Unit + Integration passam
- [ ] Jest (se Blocks/JS) passa
- [ ] PHPCS/PHPStan sem erros críticos
- [ ] Nenhum dado sensível (PAN/CVV/MerchantKey) em log de commit ou runtime
- [ ] `.pot` atualizado se novas strings de UI foram adicionadas
- [ ] `.claude/memory/progress.md` atualizado

## 8. Débitos Técnicos e Pendências Conhecidas

| Item | Descrição | Prioridade |
|---|---|---|
| E-Wallets | Spec aprovada (`ewallet-prd.md`/`ewallet-sdd.md`) mas classes `WC_Gateway_Braspag_EWallet` / `WC_Braspag_Blocks_EWallet` ainda não existem em `includes/` | Alta (bloqueia RF-14) |
| Webhook ChangeType 25 (reversão parcial) | Testes `WebhookChangeType25Test` marcados "a criar" na SDD mestre | Média |
| DebitCard payload builder test | `DebitCardPayloadBuilderTest` "a criar" | Média |
| PIX payload builder test (provider Cielo2) | `PixPayloadBuilderTest` "a criar" | Baixa |
