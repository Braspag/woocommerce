# Tasks: E-Wallets (Apple Pay, Google Pay, Samsung Pay)
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-05-21
**Linkado a:** `ewallet-prd.md`, `ewallet-sdd.md`

---

## Convenções

- `[P]` = pode ser executado em paralelo com outras tarefas do mesmo bloco
- `[SEQ]` = deve ser executado em sequência (depende da anterior)
- Commits em PT-BR: `feat: adiciona gateway braspag_ewallet`
- Testar via: `ddev exec vendor/bin/phpunit --filter EWallet`

---

## Bloco 1 — Gateway PHP Base

### T-01 — Criar classe WC_Gateway_Braspag_EWallet
**Arquivo:** `includes/payment-methods/class-wc-gateway-braspag-ewallet.php`
**Dependência:** nenhuma
**Tipo:** [SEQ] (base para todas as tarefas PHP)

**O que fazer:**
- Criar classe `WC_Gateway_Braspag_EWallet extends WC_Gateway_Braspag`
- Definir `$this->id = 'braspag_ewallet'`
- Implementar `__construct()`: carregar settings, registrar actions, registrar filters de builders
- Implementar `init_form_fields()` com todos os campos do admin (ver SDD §8)
- Implementar `settings_extra_data()`: ler settings globais (test_mode, etc.)
- Implementar `payment_scripts()`: enqueue `ewallet.js` + localizar variáveis PHP→JS
- Registrar `wp_ajax_nopriv_braspag_ewallet_validate_merchant` e `wp_ajax_braspag_ewallet_validate_merchant`

**Critérios de conclusão:**
- [ ] Classe instanciável sem erros
- [ ] `init_form_fields()` retorna todos os campos do SDD §8
- [ ] Settings salvos e relidos corretamente (teste manual no admin)

---

### T-02 — Implementar builders de payload
**Arquivo:** `includes/payment-methods/class-wc-gateway-braspag-ewallet.php`
**Dependência:** T-01
**Tipo:** [SEQ]

**O que fazer:**
- Implementar `build_ewallet_payment_request(array $request, WC_Order $order): array`
  - Monta: `MerchantOrderId`, `Customer{}` via `WC_Braspag_Customer`, `Payment.Type='CreditCard'`, `Payment.Amount`, `Payment.Installments`, `Payment.Currency='BRL'`, `Payment.SoftDescriptor`, `Payment.Wallet.Type`, `Payment.Wallet.WalletKey`
  - Lê `wallet_type` e `wallet_key` de `$_POST['braspag_wallet_type']` e `$_POST['braspag_wallet_key']`
- Implementar `build_applepay_additional_data(array $request, WC_Order $order): array`
  - Adiciona `Payment.Wallet.AdditionalData.EphemeralPublicKey` e `Payment.Wallet.AdditionalData.Signature`
  - Lê de `$_POST['braspag_wallet_ephemeral_public_key']` e `$_POST['braspag_wallet_signature']`
- Implementar `build_googlepay_additional_data(array $request, WC_Order $order): array`
  - Adiciona `Payment.Wallet.AdditionalData.CaptureCode` se `$_POST['braspag_wallet_capture_code']` não vazio

**Critérios de conclusão:**
- [ ] Payload Apple Pay tem `Wallet.Type='ApplePay'` + `AdditionalData.EphemeralPublicKey` + `AdditionalData.Signature`
- [ ] Payload Google Pay tem `Wallet.Type='GooglePay'` + `AdditionalData.CaptureCode` (quando presente)
- [ ] Payload Samsung Pay tem `Wallet.Type='SamsungPay'` sem `AdditionalData`

---

### T-03 — Implementar validate_fields() e process_payment()
**Arquivo:** `includes/payment-methods/class-wc-gateway-braspag-ewallet.php`
**Dependência:** T-02
**Tipo:** [SEQ]

**O que fazer:**
- `validate_fields()`:
  - Verificar `$_POST['braspag_wallet_type']` não vazio e valor válido (`ApplePay|GooglePay|SamsungPay`)
  - Verificar `$_POST['braspag_wallet_key']` não vazio
  - Retornar `false` + `wc_add_notice()` em caso de erro
- `process_payment(int $order_id): array`:
  - Validar nonce `woocommerce-process_checkout`
  - Aplicar filter `braspag_pagador_ewallet_payment_request_builder`
  - Aplicar filter específico da carteira (`applepay_builder` ou `googlepay_builder`)
  - Chamar `$this->api->create_sale($payment_request)`
  - `Status=2` → `$order->payment_complete($payment_id)` + `$order->update_meta_data('_braspag_payment_id', ...)` → return `['result'=>'success', 'redirect'=>...]`
  - Outro → `$order->update_status('failed')` → return `['result'=>'failure']`
  - Logar `wallet_type` + `payment_id` (nunca logar `wallet_key` em claro)

**Critérios de conclusão:**
- [ ] `validate_fields()` retorna false quando `wallet_key` vazio
- [ ] `process_payment()` com mock Status=2 → order `processing`
- [ ] `process_payment()` com mock Status=3 → order `failed`

---

### T-04 — Implementar ajax_validate_merchant() (Apple Pay)
**Arquivo:** `includes/payment-methods/class-wc-gateway-braspag-ewallet.php`
**Dependência:** T-01
**Tipo:** [P] (paralelo com T-02)

**O que fazer:**
- Verificar nonce `braspag_ewallet_nonce` via `check_ajax_referer()`
- Pegar `validation_url` do POST (enviado pelo JS `ApplePaySession.onvalidatemerchant`)
- Fazer `wp_remote_post()` à `validation_url` com body:
  ```json
  {
    "merchantIdentifier": "{apple_pay_merchant_id}",
    "displayName": "{get_bloginfo('name')}",
    "initiative": "web",
    "initiativeContext": "{parse_url(get_site_url(), PHP_URL_HOST)}"
  }
  ```
- Retornar `merchantSession` JSON ao JS via `wp_send_json_success()`
- Em caso de erro: `wp_send_json_error(['message' => ...])` + log

**Critérios de conclusão:**
- [ ] AJAX action registrado e acessível
- [ ] Nonce validado (requisição sem nonce retorna 403)
- [ ] Requisição à Apple com campos corretos

---

### T-05 — Registrar gateway no plugin loader
**Arquivo:** `wc-gateway-braspag.php` (ou arquivo de loader equivalente)
**Dependência:** T-01
**Tipo:** [SEQ] (requer T-01 concluído)

**O que fazer:**
- Adicionar `require_once` para `class-wc-gateway-braspag-ewallet.php`
- Adicionar `'WC_Gateway_Braspag_EWallet'` ao array do filter `woocommerce_payment_gateways`

**Critérios de conclusão:**
- [ ] Gateway aparece na lista WooCommerce > Payments no admin

---

## Bloco 2 — Frontend JavaScript

### T-06 — Criar ewallet.js (orchestrator)
**Arquivo:** `assets/js/frontend/ewallet.js`
**Dependência:** T-05 (para ter dados localizados via `wp_localize_script`)
**Tipo:** [P]

**O que fazer:**
- Na inicialização: detectar quais carteiras estão disponíveis no dispositivo
  - Apple Pay: `window.ApplePaySession?.canMakePayments()`
  - Google Pay: `typeof google !== 'undefined' && google.payments`
  - Samsung Pay: verificar SDK Samsung
- Renderizar botões apenas das carteiras disponíveis E habilitadas pelo lojista (lista vem de `braspagEWallet.wallets`)
- Expor função `injectTokenAndSubmit(walletType, walletKey, additionalData)`:
  - Cria/atualiza hidden inputs no formulário de checkout
  - Chama `form.submit()`
- Se nenhuma carteira disponível: ocultar o container do gateway no checkout

**Critérios de conclusão:**
- [ ] Com Apple Pay disponível: botão Apple Pay renderizado
- [ ] Com Google Pay disponível: botão Google Pay renderizado
- [ ] Sem carteira: container oculto
- [ ] `injectTokenAndSubmit()` preenche hidden fields corretamente

---

### T-07 — Criar apple-pay.js
**Arquivo:** `assets/js/frontend/apple-pay.js`
**Dependência:** T-06
**Tipo:** [P]

**O que fazer:**
- Inicializar `ApplePaySession` com:
  - `merchantCapabilities: ['supports3DS']`
  - `supportedNetworks: ['visa', 'masterCard', 'elo']`
  - `countryCode: 'BR'`, `currencyCode: 'BRL'`
  - `total: { label: storeName, amount: orderTotal }`
- `session.onvalidatemerchant`: fetch AJAX `braspag_ewallet_validate_merchant` → `completeMerchantValidation(merchantSession)`
- `session.onpaymentauthorized`: extrair `payment.token.paymentData` → chamar `injectTokenAndSubmit('ApplePay', data, { ephemeralPublicKey, signature })` → `session.completePayment(ApplePaySession.STATUS_SUCCESS)`
- `session.oncancel`: limpar estado

**Critérios de conclusão:**
- [ ] `onvalidatemerchant` chama AJAX corretamente
- [ ] Hidden fields `braspag_wallet_type`, `braspag_wallet_key`, `braspag_wallet_ephemeral_public_key`, `braspag_wallet_signature` preenchidos

---

### T-08 — Criar google-pay.js
**Arquivo:** `assets/js/frontend/google-pay.js`
**Dependência:** T-06
**Tipo:** [P]

**O que fazer:**
- Criar `PaymentsClient` com `environment: braspagEWallet.isSandbox ? 'TEST' : 'PRODUCTION'`
- `PaymentDataRequest` com:
  - `gateway: 'cielo'`, `gatewayMerchantId: braspagEWallet.googleMerchantId`
  - `allowedCardNetworks: ['MASTERCARD', 'VISA', 'ELO']`
  - `allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS']`
  - `transactionInfo: { currencyCode: 'BRL', totalPrice: orderTotal, totalPriceStatus: 'FINAL' }`
- Após aprovação: extrair `paymentData.paymentMethodData.tokenizationData.token` → `injectTokenAndSubmit('GooglePay', token, { captureCode })`

**Critérios de conclusão:**
- [ ] `PaymentDataRequest` configurado com gateway Cielo
- [ ] Hidden fields preenchidos após aprovação

---

### T-09 — Criar samsung-pay.js
**Arquivo:** `assets/js/frontend/samsung-pay.js`
**Dependência:** T-06
**Tipo:** [P]

**O que fazer:**
- Inicializar Samsung Pay SDK com `serviceId: braspagEWallet.samsungServiceId`
- Configurar `paymentRequest` com amount, currency, merchant info
- Após aprovação: extrair `ref_id` → `injectTokenAndSubmit('SamsungPay', ref_id, {})`

**Critérios de conclusão:**
- [ ] Hidden fields `braspag_wallet_type=SamsungPay` e `braspag_wallet_key=ref_id` preenchidos

---

## Bloco 3 — WooCommerce Blocks

### T-10 — Criar WC_Braspag_Blocks_EWallet
**Arquivo:** `includes/blocks/payments/class-wc-braspag-blocks-ewallet.php`
**Dependência:** T-01
**Tipo:** [P]

**O que fazer:**
- Criar classe `WC_Braspag_Blocks_EWallet extends WC_Braspag_Blocks_Abstract`
- `$name = 'braspag_ewallet'`
- Implementar `get_payment_method_data()` retornando: `wallets`, `appleMerchantId`, `googleMerchantId`, `samsungServiceId`, `installments`, `ajaxUrl`, `nonce`, `isSandbox`
- Registrar o Block no loader de blocks existente

**Critérios de conclusão:**
- [ ] Block registrado sem erros
- [ ] `get_payment_method_data()` retorna todas as keys esperadas

---

## Bloco 4 — Testes

### T-11 — Criar EWalletPayloadBuilderTest
**Arquivo:** `tests/unit/EWalletPayloadBuilderTest.php`
**Dependência:** T-02
**Tipo:** [P]

**Cenários obrigatórios:**
- [ ] Apple Pay: `Wallet.Type=ApplePay`, `Wallet.WalletKey` presente, `AdditionalData.EphemeralPublicKey` e `AdditionalData.Signature` presentes
- [ ] Google Pay com CaptureCode: `AdditionalData.CaptureCode` presente
- [ ] Google Pay sem CaptureCode: `AdditionalData` vazio ou ausente
- [ ] Samsung Pay: sem `AdditionalData`
- [ ] `wallet_key` mascarado no log (nunca valor completo)

---

### T-12 — Criar EWalletGatewayTest
**Arquivo:** `tests/unit/EWalletGatewayTest.php`
**Dependência:** T-03
**Tipo:** [P]

**Cenários obrigatórios:**
- [ ] `validate_fields()` ok: wallet_type e wallet_key presentes → true
- [ ] `validate_fields()` fail: wallet_key vazio → false + wc_notice
- [ ] `validate_fields()` fail: wallet_type inválido → false + wc_notice
- [ ] `process_payment()` Status=2 → order status `processing`
- [ ] `process_payment()` Status=3 → order status `failed`
- [ ] `process_payment()` API timeout → order status `failed`

---

### T-13 — Criar EWalletIntegrationTest
**Arquivo:** `tests/integration/EWalletIntegrationTest.php`
**Dependência:** T-03, T-04
**Tipo:** [SEQ]

**Cenários obrigatórios:**
- [ ] POST Apple Pay completo (mock Cielo → Status=2) → order `processing`
- [ ] POST Google Pay completo → order `processing`
- [ ] POST Samsung Pay completo → order `processing`
- [ ] Erro 500 Cielo → order `failed`

---

## Bloco 5 — Atualização de Documentos Mestres

### T-14 — Atualizar plugin-braspag-prd.md
**Arquivo:** `docs/specs/plugin-braspag-prd.md`
**Dependência:** nenhuma
**Tipo:** [P]

**O que fazer:**
- Adicionar `braspag_ewallet` na tabela "Métodos de Pagamento Suportados"
- Adicionar RF-14 (resumido, referenciando `ewallet-prd.md`)
- Mover E-wallets de "Features Futuras" para "Métodos Suportados"

---

### T-15 — Atualizar plugin-braspag-sdd.md
**Arquivo:** `docs/specs/plugin-braspag-sdd.md`
**Dependência:** nenhuma
**Tipo:** [P]

**O que fazer:**
- Adicionar `WC_Gateway_Braspag_EWallet` ao mapa de componentes (§2.1)
- Adicionar `WC_Braspag_Blocks_EWallet` ao mapa de Blocks (§2.4)
- Adicionar ADR-007 e ADR-008 à tabela de ADRs (§7)
- Adicionar fluxo de estado E-Wallet (§4)

---

## Ordem de execução recomendada

```
T-14, T-15 (paralelo) — atualizar docs mestres
T-01 (base PHP) → T-02, T-04 (paralelo) → T-03 → T-05
T-06 → T-07, T-08, T-09 (paralelo)
T-10 (paralelo com JS)
T-11, T-12 (paralelo após T-02/T-03) → T-13
```

---

## Checklist Final de Entrega

- [ ] `WC_Gateway_Braspag_EWallet` registrado e visível no admin WooCommerce
- [ ] Payload correto para Apple Pay, Google Pay e Samsung Pay (sandbox Cielo)
- [ ] Admin settings salvos e lidos corretamente
- [ ] Merchant validation Apple Pay funcionando (AJAX)
- [ ] `EWalletPayloadBuilderTest` — todos os cenários passando
- [ ] `EWalletGatewayTest` — todos os cenários passando
- [ ] `EWalletIntegrationTest` — todos os cenários passando
- [ ] `ddev exec vendor/bin/phpunit` passando sem falhas
- [ ] Nenhum `wallet_key` logado em claro
- [ ] Blocks: `get_payment_method_data()` retorna dados corretos
- [ ] `.claude/memory/progress.md` atualizado
