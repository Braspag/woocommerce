# SDD: Plugin WooCommerce Braspag — Software Design Document
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-05-17 | **Autor:** agent-pm
**Tipo:** Documento Mestre de Design (top-level)
**Linkado a:** `plugin-braspag-prd.md`

---

## 1. Visão Geral da Arquitetura

O plugin é um **monólito PHP modular** sem namespaces (ADR-002), seguindo os padrões de plugin WordPress/WooCommerce. Toda a lógica é organizada em classes com prefixo `WC_Braspag_` divididas por responsabilidade.

```
wc-gateway-braspag.php (entry point)
    └── class-wc-gateway-braspag.php
            └── woocommerce_payment_gateways (filter)
                    ├── WC_Gateway_Braspag_CreditCard
                    ├── WC_Gateway_Braspag_DebitCard
                    ├── WC_Gateway_Braspag_Pix
                    └── WC_Gateway_Braspag_Boleto
```

---

## 2. Mapa de Componentes

### 2.1 Camada de Gateway (Payment Methods)

| Classe | Arquivo | Herda de |
|---|---|---|
| `WC_Braspag_Payment_Gateway` | `abstracts/abstract-wc-braspag-payment-gateway.php` | `WC_Payment_Gateway` |
| `WC_Gateway_Braspag_CreditCard` | `payment-methods/class-wc-gateway-braspag-creditcard.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_DebitCard` | `payment-methods/class-wc-gateway-braspag-debitcard.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_Pix` | `payment-methods/class-wc-gateway-braspag-pix.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_Boleto` | `payment-methods/class-wc-gateway-braspag-boleto.php` | `WC_Braspag_Payment_Gateway` |
| `WC_Gateway_Braspag_EWallet` | `payment-methods/class-wc-gateway-braspag-ewallet.php` | `WC_Braspag_Payment_Gateway` |

### 2.2 Camada de API (Integrações Braspag)

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Pagador_API` | `class-wc-braspag-pagador-api.php` | create/capture/void/refund/query |
| `WC_Braspag_Pagador_API_Query` | `class-wc-braspag-pagador-api-query.php` | Consultas de transações |
| `WC_Braspag_MPI_API` | `class-wc-braspag-mpi-api.php` | 3DS 2.0 via bpmpi.js |
| `WC_Braspag_Risk_API` | `class-wc-braspag-risk-api.php` | Antifraude separado do Pagador (CyberSource + ClearSale) |
| `WC_Braspag_OAuth_API` | `class-wc-braspag-oauth-api.php` | Bearer tokens para MPI e Risk |
| `WC_Braspag_Zero_Auth_API` | `class-wc-braspag-zero-auth-api.php` (a criar) | Validação de cartão antes de tokenizar (RF-13) |

### 2.3 Camada de Serviços

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Webhook_Handler` | `class-wc-braspag-webhook-handler.php` | Recebe e processa notificações |
| `WC_Braspag_Payment_Tokens` | `class-wc-braspag-payment-tokens.php` | Ciclo de vida de CardTokens |
| `WC_Braspag_Order_Handler` | `class-wc-braspag-order-handler.php` | Sincronização de status de pedido |
| `WC_Braspag_Customer` | `class-wc-braspag-customer.php` | Dados do comprador para API |
| `WC_Braspag_Logger` | `class-wc-braspag-logger.php` | Log com mascaramento de dados sensíveis |
| `WC_Braspag_Helper` | `class-wc-braspag-helper.php` | Utilitários gerais |
| `WC_Braspag_Exception` | `class-wc-braspag-exception.php` | Exceções customizadas |

### 2.4 Camada de Blocks (React/WooCommerce Blocks)

| Classe | Arquivo | Responsabilidade |
|---|---|---|
| `WC_Braspag_Blocks` | `blocks/class-wc-braspag-blocks.php` | Registro dos 4 Blocks |
| `WC_Braspag_Blocks_Abstract` | `blocks/class-wc-braspag-blocks-abstract.php` | Base com `get_payment_method_data()` |
| `WC_Braspag_Blocks_CreditCard` | `blocks/payments/...creditcard.php` | Dados do bloco de crédito |
| `WC_Braspag_Blocks_DebitCard` | `blocks/payments/...debitcard.php` | Dados do bloco de débito |
| `WC_Braspag_Blocks_Pix` | `blocks/payments/...pix.php` | Dados do bloco PIX |
| `WC_Braspag_Blocks_Boleto` | `blocks/payments/...boleto.php` | Dados do bloco Boleto |
| `WC_Braspag_Blocks_EWallet` | `blocks/payments/class-wc-braspag-blocks-ewallet.php` | Dados do bloco E-Wallets |
| `WC_Braspag_Blocks_ECFB_Bridge` | `blocks/bridge/...ecfb-bridge.php` | Integração campos CPF/CNPJ |

---

## 3. Contratos de API Braspag

### 3.1 Endpoints por Ambiente

| API | Sandbox | Produção |
|---|---|---|
| Pagador (transações) | `https://apisandbox.braspag.com.br` | `https://api.braspag.com.br` |
| Pagador (consultas) | `https://apiquerysandbox.braspag.com.br` | `https://apiquery.braspag.com.br` |
| MPI (3DS) | `https://authsandbox.braspag.com.br` | `https://auth.braspag.com.br` |
| Risk (antifraude) | `https://risksandbox.braspag.com.br` | `https://risk.braspag.com.br` |
| OAuth | `https://authsandbox.braspag.com.br/oauth2/token` | `https://auth.braspag.com.br/oauth2/token` |

### 3.2 Headers Obrigatórios — Pagador API

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
| `query_sale()` | GET | `/v2/sales/{id}` | Consulta manual / webhook |

### 3.4 Mapeamento de Status

| Status Braspag | Descrição | Status WooCommerce |
|---|---|---|
| 1 | Pending | `on-hold` |
| 2 | PaymentConfirmed | `processing` |
| 3 | Denied | `cancelled` |
| 10 | Voided | `cancelled` |
| 11 | Refunded | `refunded` |
| 12 | Pending | `on-hold` |

---

## 4. Fluxos de Estado

### 4.1 Cartão de Crédito

```
validate_fields()
    └── [Builder 1] braspag_pagador_creditcard_payment_request_builder
           └── SOP ativo → PaymentToken | Token salvo → CardToken | Bruto → CardNumber
    └── [Builder 2] ..._auth3ds20_builder
           └── 3DS ativo → ExternalAuthentication{}
    └── [Builder 3] ..._antifraud_builder
           └── AF ativo + Pagador + SOP=off → FraudAnalysis{}
    └── create_sale() → POST /v2/sales
           ├── Status=2 → order 'processing' + save_token (se pedido)
           └── Erro/Status≠2 → order 'failed'
```

### 4.2 PIX / Boleto (assíncronos)

```
process_payment() → create_sale() → order 'on-hold'
                                          │
                              [Braspag envia webhook]
                                          │
                              POST /wc-api/braspag_webhook
                              is_valid_request() → valida HMAC
                                          │
                              Status=2 → order 'processing'
                              Status=3 → order 'cancelled'
```

### 4.3 OAuth 2.0 (transparente)

```
qualquer chamada MPI ou Risk
    └── token em wp_cache?
           ├── sim, válido → usa
           └── não/expirado → POST oauth2/token (client_credentials)
                  └── 401 recebido → invalida cache → repete
```

---

## 5. Hooks WordPress / WooCommerce

### 5.1 Actions Críticas

| Hook | Onde Registrado | Responsabilidade |
|---|---|---|
| `woocommerce_payment_gateways` | `wc-gateway-braspag.php` | Registra os 4 gateways |
| `woocommerce_api_braspag_webhook` | `WC_Braspag_Webhook_Handler` | Recebe webhook da Braspag |
| `woocommerce_scheduled_subscription_payment` | (futuro) | Pagamento recorrente |

### 5.2 Filters de Payload (Builders)

| Filter | Responsabilidade |
|---|---|
| `braspag_pagador_creditcard_payment_request_builder` | Monta `CreditCard{}` base |
| `braspag_pagador_creditcard_payment_request_auth3ds20_builder` | Adiciona `ExternalAuthentication{}` |
| `braspag_pagador_creditcard_payment_request_antifraud_builder` | Adiciona `FraudAnalysis{}` |

Os builders são encadeados — cada filter recebe o payload anterior e adiciona sua parte. Isso permite extensão sem modificar a classe principal.

---

## 6. Regras de Desenvolvimento (para Agentes)

### 6.1 Naming Conventions

| Elemento | Padrão | Exemplo |
|---|---|---|
| Classes | `WC_Braspag_[Component]_[Feature]` | `WC_Braspag_Pagador_API` |
| Métodos | `[action]_[subject]()` | `create_sale()`, `validate_fields()` |
| Constantes | `WC_BRASPAG_[FEATURE]_[NAME]` | `WC_BRASPAG_PLUGIN_VERSION` |
| Options WP | `woocommerce_braspag_[context]_[setting]` | `woocommerce_braspag_settings` |
| Arquivos | `class-wc-braspag-[feature].php` | `class-wc-braspag-pagador-api.php` |

### 6.2 Regras Inquebráveis

1. **Sem namespaces PHP** — ADR-002, compatibilidade com PHP 7.4
2. **Sem SQL direto** — usar `get_option()`, `WC_Order`, `get_posts()`, `wp_usermeta`
3. **Sem PAN/CVV em logs** — usar `WC_Braspag_Logger` com mascaramento
4. **Spec-first** — nenhum código sem spec aprovada em `docs/specs/`
5. **Commits em PT-BR** — `feat: adiciona suporte a PIX no Blocks`
6. **Testes antes de declarar concluído** — PHPUnit Unit + Integration devem passar

### 6.3 Arquivos de Alto Risco

Qualquer modificação nestes arquivos requer aprovação do `agent-tech-lead`:
- `wc-gateway-braspag.php` — entry point do plugin
- `abstracts/abstract-wc-braspag-payment-gateway.php` — base de todos os gateways
- `class-wc-braspag-pagador-api.php` — todas as transações financeiras
- `class-wc-braspag-webhook-handler.php` — confirmação de PIX e Boleto
- `class-wc-braspag-oauth-api.php` — autenticação com APIs externas

---

## 7. ADRs Vigentes

| ADR | Decisão | Status | Impacto |
|---|---|---|---|
| ADR-001 | Builders de payload via WordPress filters | Aceito | Extensibilidade sem modificar classes de gateway |
| ADR-002 | Sem namespaces PHP | Aceito | Todo código usa prefixo `WC_Braspag_` |
| ADR-003 | Elo/Amex sem suporte a 3DS | **REVOGADO** | Elo suporta 3DS 2.2 (doc Cielo confirmada). Remover bloqueio no código. Amex permanece sem 3DS. |
| ADR-004 | SOP + Antifraude (Pagador) incompatíveis | **REVOGADO** | Doc Cielo mostra que SOP e AF são compatíveis. Remover bloqueio no builder de AF. |
| ADR-005 | Zero Auth obrigatório antes de tokenizar | Aceito | Executar Zero Auth antes de criar CardToken. Fallback gracioso para Amex (erro 57) e serviço não habilitado. |
| ADR-007 | E-Wallets apenas no modo encrypted card | Aceito | Modo decrypted exige PCI DSS no lojista — fora do escopo. Ver `ewallet-sdd.md`. |
| ADR-008 | Um gateway único `braspag_ewallet` para as três carteiras | Aceito | Carteira detectada via JS (disponibilidade no dispositivo) e comunicada via hidden field. |

---

## 8. Estrutura de Testes

### 8.1 Suítes PHPUnit

```bash
# Unitários (sem WordPress real)
./vendor/bin/phpunit --testsuite Unit

# Integração (requer WP_TESTS_DIR)
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite Integration
```

### 8.2 Cobertura por Módulo

| Módulo | Arquivo de Teste | Tipo |
|---|---|---|
| Builders de payload (SOP/3DS/AF) | `CreditCardPayloadBuilderTest` | Unitário |
| Validação webhook + HMAC | `WebhookValidationTest` | Unitário |
| Blocks CreditCard | `BlocksCreditCardTest` | Unitário |
| MPI API | `MpiApiTest` | Unitário |
| Risk API | `RiskApiTest` | Unitário |
| OAuth API | `OAuthApiTest` | Unitário |
| Zero Auth API | `ZeroAuthApiTest` (a criar) | Unitário |
| Velocity response handling | `VelocityResponseTest` (a criar) | Unitário |
| Webhook ChangeType 25 | `WebhookChangeType25Test` (a criar) | Unitário |
| DebitCard payload builder | `DebitCardPayloadBuilderTest` (a criar) | Unitário |
| PIX payload (provider Cielo2) | `PixPayloadBuilderTest` (a criar) | Unitário |
| process_payment() completo | `CreditCardProcessPaymentTest` | Integração |
| DebitCard process_payment() | `DebitCardProcessPaymentTest` (a criar) | Integração |
| Webhooks com pedidos reais | `WebhookNotificationTest` | Integração |
| Webhook todos ChangeTypes | `WebhookAllChangeTypesTest` (a criar) | Integração |
| Registro nos WC Blocks | `BlocksRegistrationTest` | Integração |
| CreditCard Blocks fluxo completo | `CreditCardBlocksProcessPaymentTest` (a criar) | Integração |

### 8.3 Estratégia de Mock

- **Unitários:** `disableOriginalConstructor()` + `$GLOBALS['_braspag_test_options']` para simular `get_option()`
- **Integração:** filter `pre_http_request` para interceptar chamadas HTTP à Braspag; `WC_Helper_Order::create_order()` para pedidos reais

---

## 9. Workflow dos Sub-Agents

### 9.1 Quando Usar Cada Agente

| Situação | Agente a Acionar |
|---|---|
| Nova funcionalidade ou mudança de comportamento | `@agent-pm` — cria ARD+PRD+SDD |
| Quebrar spec em tarefas e delegar | `@agent-po` — produz tasks.md e delega |
| Revisão técnica, contratos de API, aprovação | `@agent-tech-lead` — aprova ou bloqueia |
| Implementação PHP/JS, testes | `@agent-dev-backend` — executa e commita |

### 9.2 Fluxo de Trabalho

```
Pedido do usuário (PT-BR)
    │
    ▼
agent-pm → docs/specs/[slug]-ard.md
         → docs/specs/[slug]-prd.md
         → docs/specs/[slug]-sdd.md
    │
    ▼
agent-po → docs/specs/[slug]-tasks.md
         → delega via Task tool
    │
    ├──────────────────────────┐
    ▼                          ▼
agent-tech-lead          agent-dev-backend
(spec técnica,           (implementa PHP/JS,
 code review,             roda testes,
 aprovação)               commita em PT-BR)
    │                          │
    └──────── ciclo ───────────┘
              │
              ▼
    .claude/memory/progress.md atualizado
```

### 9.3 Entradas e Saídas por Agente

| Agente | Entradas | Saídas |
|---|---|---|
| `agent-pm` | Pedido em PT-BR, CLAUDE.md, decisions.md, specs existentes | ARD + PRD + SDD em `docs/specs/` |
| `agent-po` | Specs prontas, progress.md | tasks.md com tarefas atômicas e `[P]` paralelas |
| `agent-tech-lead` | Spec + código do dev | tech.md, code review, ADR atualizado |
| `agent-dev-backend` | tasks.md aprovado pela tech-lead | Código PHP/JS + testes + commit |

---

## 10. Dependências Externas

| Sistema | URL Sandbox | Protocolo | Auth |
|---|---|---|---|
| Pagador API | `apisandbox.braspag.com.br` | HTTPS/JSON | MerchantId + MerchantKey no header |
| MPI (3DS) | `authsandbox.braspag.com.br` | HTTPS/JSON | Bearer (OAuth) |
| Risk API | `risksandbox.braspag.com.br` | HTTPS/JSON | Bearer (OAuth) |
| OAuth 2.0 | `authsandbox.braspag.com.br/oauth2/token` | HTTPS/Form | client_credentials |
| bpmpi.js | CDN Braspag | `<script>` externo | nenhuma |

---

*Documento mestre de design — atualizar ao adicionar novos componentes, adquirentes ou integrações externas.*
