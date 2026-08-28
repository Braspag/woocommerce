# SDD — Software Design Document
**Produto:** Braspag for WooCommerce Oficial · **Versão:** 1.0 · **Data:** 2026-08-26
**Fonte primária:** `docs/specs/plugin-braspag-sdd.md`, `ewallet-sdd.md`, `antifraude-sdd.md`, leitura de `includes/`

## 1. Visão Geral da Arquitetura

Monólito PHP modular **sem namespaces** (ADR-002), organizado por responsabilidade sob o prefixo `WC_Braspag_`. Ponto de entrada: `wc-gateway-braspag.php`.

```
wc-gateway-braspag.php (entry point)
  └── includes/class-wc-gateway-braspag.php
        └── filter: woocommerce_payment_gateways
              ├── WC_Gateway_Braspag_CreditCard
              ├── WC_Gateway_Braspag_DebitCard
              ├── WC_Gateway_Braspag_Pix
              ├── WC_Gateway_Braspag_Boleto
              ├── WC_Gateway_Braspag_CreditCard_JustClick
              └── WC_Gateway_Braspag_EWallet   (spec — não presente em includes/payment-methods/ hoje)
```

## 2. Mapa de Componentes (verificado em `includes/`)

### 2.1 Camada de Gateway

| Classe | Arquivo | Herda de |
|---|---|---|
| `WC_Braspag_Payment_Gateway` | `abstracts/abstract-wc-braspag-payment-gateway.php` | `WC_Payment_Gateway` |
| `WC_Gateway_Braspag_CreditCard` | `payment-methods/class-wc-gateway-braspag-creditcard.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_DebitCard` | `payment-methods/class-wc-gateway-braspag-debitcard.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_Pix` | `payment-methods/class-wc-gateway-braspag-pix.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_Boleto` | `payment-methods/class-wc-gateway-braspag-boleto.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_CreditCard_JustClick` | `payment-methods/class-wc-gateway-braspag-creditcard-justclick.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_EWallet` | *(spec `ewallet-sdd.md`, arquivo não encontrado no código atual)* | — |

### 2.2 Camada de API (Integrações Braspag)

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Pagador_API` | `class-wc-braspag-pagador-api.php` | create/capture/void/refund |
| `WC_Braspag_Pagador_API_Query` | `class-wc-braspag-pagador-api-query.php` | Consulta de transações |
| `WC_Braspag_MPI_API` | `class-wc-braspag-mpi-api.php` | 3DS 2.2 via bpmpi.js |
| `WC_Braspag_Risk_API` | `class-wc-braspag-risk-api.php` | Antifraude separado do Pagador (CyberSource/ClearSale) |
| `WC_Braspag_OAuth_API` | `class-wc-braspag-oauth-api.php` | Bearer tokens (MPI/Risk) |
| `WC_Braspag_Zero_Auth_API` | `class-wc-braspag-zero-auth-api.php` | Validação de cartão pré-tokenização |
| `WC_Braspag_Auth_Tokens_Ajax` | `class-wc-braspag-auth-tokens-ajax.php` | Endpoint AJAX de tokens de autenticação |
| `WC_Braspag_3DS_Return_Codes` | `class-wc-braspag-3ds-return-codes.php` | Mapeamento de códigos de retorno 3DS |

### 2.3 Camada de Serviços

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Webhook_Handler` | `class-wc-braspag-webhook-handler.php` | Recebe/processa notificações assíncronas |
| `WC_Braspag_Payment_Tokens` | `class-wc-braspag-payment-tokens.php` | Ciclo de vida de CardTokens (WC Payment Token API) |
| `WC_Braspag_Order_Handler` | `class-wc-braspag-order-handler.php` | Sincronização de status de pedido |
| `WC_Braspag_Customer` | `class-wc-braspag-customer.php` | Dados do comprador para payload da API |
| `WC_Braspag_Logger` | `class-wc-braspag-logger.php` | Log com mascaramento de dados sensíveis |
| `WC_Braspag_Client_Logger` | `class-wc-braspag-client-logger.php` | Log client-side (JS → PHP) |
| `WC_Braspag_Helper` | `class-wc-braspag-helper.php` | Utilitários gerais |
| `WC_Braspag_Exception` | `class-wc-braspag-exception.php` | Exceções customizadas |

### 2.4 Camada Admin

| Classe/arquivo | Responsabilidade |
|---|---|
| `admin/braspag-settings.php` | Settings globais |
| `admin/braspag-creditcard-settings.php`, `-debitcard-`, `-pix-`, `-boleto-`, `-creditcard-justclick-` | Settings por gateway |
| `class-wc-braspag-admin-notices.php` | Avisos admin (configuração ausente) |
| `class-wc-braspag-customer-seller-attributes.php` | Atributos extras do cliente/vendedor |
| `class-wc-braspag-privacy.php` | Exportação/eliminação de dados (LGPD/GDPR) |

### 2.5 Camada de Blocks (React/WooCommerce Blocks)

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Blocks` | `blocks/class-wc-braspag-blocks.php` | Registro dos Blocks |
| `WC_Braspag_Blocks_Abstract` | `blocks/class-wc-braspag-blocks-abstract.php` | Base com `get_payment_method_data()` |
| `WC_Braspag_Blocks_CreditCard` | `blocks/payments/class-wc-braspag-blocks-creditcard.php` | Dados do bloco Crédito |
| `WC_Braspag_Blocks_DebitCard` | `blocks/payments/class-wc-braspag-blocks-debitcard.php` | Dados do bloco Débito |
| `WC_Braspag_Blocks_Pix` | `blocks/payments/class-wc-braspag-blocks-pix.php` | Dados do bloco PIX |
| `WC_Braspag_Blocks_Boleto` | `blocks/payments/class-wc-braspag-blocks-boleto.php` | Dados do bloco Boleto |
| `WC_Braspag_Blocks_Main` | `blocks/payments/class-wc-braspag-blocks-main.php` | Orquestração/registro comum |
| `WC_Braspag_Blocks_ECFB_Bridge` | `blocks/bridge/class-wc-braspag-blocks-ecfb-bridge.php` | Ponte de campos CPF/CNPJ (ECFB) |
| `WC_Braspag_Blocks_EWallet` | *(spec, arquivo não encontrado)* | Dados do bloco E-Wallets |

## 3. Contratos de API Braspag

### 3.1 Endpoints por Ambiente

| API | Sandbox | Produção |
|---|---|---|
| Pagador (transações) | `apisandbox.braspag.com.br` | `api.braspag.com.br` |
| Pagador (consultas) | `apiquerysandbox.braspag.com.br` | `apiquery.braspag.com.br` |
| MPI (3DS) | `authsandbox.braspag.com.br` | `auth.braspag.com.br` |
| Risk (antifraude) | `risksandbox.braspag.com.br` | `risk.braspag.com.br` |
| OAuth | `authsandbox.braspag.com.br/oauth2/token` | `auth.braspag.com.br/oauth2/token` |

### 3.2 Headers — Pagador API

```
MerchantId: {merchant_id}
MerchantKey: {merchant_key}
RequestId: {uuid-v4}
Content-Type: application/json
```

### 3.3 Operações Pagador

| Método PHP | HTTP | Endpoint | Trigger |
|---|---|---|---|
| `create_sale()` | POST | `/v2/sales` | `process_payment()` |
| `capture_sale()` | PUT | `/v2/sales/{id}/capture` | Pedido → `processing` |
| `void_sale()` | PUT | `/v2/sales/{id}/void` | Pedido → `cancelled` |
| `refund_sale()` | PUT | `/v2/sales/{id}/void` | Pedido → `refunded` |
| `query_sale()` | GET | `/v2/sales/{id}` | Consulta manual/webhook |

### 3.4 Mapeamento de Status

| Status Braspag | Descrição | Status WooCommerce |
|---:|---|---|
| 1 | Pending | `on-hold` |
| 2 | PaymentConfirmed | `processing` |
| 3 | Denied | `cancelled` |
| 10 | Voided | `cancelled` |
| 11 | Refunded | `refunded` |
| 12 | Pending | `on-hold` |

## 4. Fluxos de Estado

### 4.1 Cartão de Crédito

```
validate_fields()
  └─ [Builder 1] braspag_pagador_creditcard_payment_request_builder
        └─ SOP ativo → PaymentToken | Token salvo → CardToken | Bruto → CardNumber
  └─ [Builder 2] ..._auth3ds20_builder
        └─ 3DS ativo → ExternalAuthentication{}
  └─ [Builder 3] ..._antifraud_builder
        └─ AF ativo → FraudAnalysis{}
  └─ create_sale() → POST /v2/sales
        ├─ Status=2 → order 'processing' (+ salva token se aplicável)
        └─ Erro/Status≠2 → order 'failed'
```

### 4.2 PIX / Boleto (assíncronos)

```
process_payment() → create_sale() → order 'on-hold'
        │
   [Braspag envia webhook]
        │
   POST /wc-api/braspag_webhook → is_valid_request() (HMAC)
        │
   Status=2 → 'processing'   |   Status=3 → 'cancelled'
```

### 4.3 OAuth 2.0 (transparente, MPI/Risk)

```
chamada MPI ou Risk
  └─ token em wp_cache?
        ├─ sim, válido → usa
        └─ não/expirado → POST oauth2/token (client_credentials)
              └─ 401 recebido → invalida cache → repete uma vez
```

### 4.4 Antifraude — sequências (spec `antifraude-sdd.md`)

```
AuthorizeFirst:  create_sale() → risco avaliado (Pagador ou Risk API) → void_sale() se alto risco
AnalyzeFirst:    Risk_API.analyze() → aprovado? → create_sale() : bloqueia antes de autorizar
```

### 4.5 E-Wallets — fluxo de design (spec `ewallet-sdd.md`, pendente de implementação)

```
JS: feature-detection (Apple/Google/Samsung Pay disponível?)
  └─ exibe botão da carteira disponível
  └─ SDK da carteira gera token criptografado
  └─ hidden field envia { wallet_type, encrypted_token } no checkout
process_payment() [WC_Gateway_Braspag_EWallet]
  └─ monta Payment.Wallet{} conforme wallet_type
  └─ create_sale() → Status=2 → 'processing' | erro → 'failed'
```

## 5. Hooks WordPress/WooCommerce

### 5.1 Actions Críticas

| Hook | Registrado em | Responsabilidade |
|---|---|---|
| `woocommerce_payment_gateways` | `wc-gateway-braspag.php` | Registra os gateways |
| `woocommerce_api_braspag_webhook` | `WC_Braspag_Webhook_Handler` | Recebe webhook |
| `init` | `wc-gateway-braspag.php` | `load_plugin_textdomain()` |

### 5.2 Filters de Payload (Builders — ADR-001)

| Filter | Responsabilidade |
|---|---|
| `braspag_pagador_creditcard_payment_request_builder` | Monta `CreditCard{}` base |
| `braspag_pagador_creditcard_payment_request_auth3ds20_builder` | Adiciona `ExternalAuthentication{}` |
| `braspag_pagador_creditcard_payment_request_antifraud_builder` | Adiciona `FraudAnalysis{}` |

Builders são encadeados: cada filter recebe o payload do anterior e adiciona sua parte — extensível sem alterar a classe de gateway.

## 6. Regras de Desenvolvimento

| Elemento | Padrão | Exemplo |
|---|---|---|
| Classes | `WC_Braspag_[Component]_[Feature]` | `WC_Braspag_Pagador_API` |
| Métodos | `[action]_[subject]()` | `create_sale()`, `validate_fields()` |
| Constantes | `WC_BRASPAG_[FEATURE]_[NAME]` | `WC_BRASPAG_PLUGIN_VERSION` |
| Options WP | `woocommerce_braspag_[context]_[setting]` | `woocommerce_braspag_settings` |
| Arquivos | `class-wc-braspag-[feature].php` | `class-wc-braspag-pagador-api.php` |

Regras inquebráveis: sem namespaces PHP; sem SQL direto; sem PAN/CVV em log; spec-first (`docs/specs/`); commits em PT-BR; testes obrigatórios antes de "concluído".

Arquivos de alto risco (exigem aprovação técnica para alterar): `wc-gateway-braspag.php`, `abstracts/abstract-wc-braspag-payment-gateway.php`, `class-wc-braspag-pagador-api.php`, `class-wc-braspag-webhook-handler.php`, `class-wc-braspag-oauth-api.php`.

## 7. Estrutura de Testes

```bash
./vendor/bin/phpunit --testsuite Unit
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite Integration
npm run test:js
```

Estratégia de mock: unitários usam `disableOriginalConstructor()` + `$GLOBALS['_braspag_test_options']`; integração usa filter `pre_http_request` para interceptar chamadas HTTP e `WC_Helper_Order::create_order()`.

## 8. Dependências Externas

| Sistema | URL Sandbox | Protocolo | Auth |
|---|---|---|---|
| Pagador API | `apisandbox.braspag.com.br` | HTTPS/JSON | MerchantId+MerchantKey |
| MPI (3DS) | `authsandbox.braspag.com.br` | HTTPS/JSON | Bearer (OAuth) |
| Risk API | `risksandbox.braspag.com.br` | HTTPS/JSON | Bearer (OAuth) |
| OAuth 2.0 | `authsandbox.braspag.com.br/oauth2/token` | HTTPS/Form | client_credentials |
| bpmpi.js | CDN Braspag | `<script>` externo | nenhuma |
