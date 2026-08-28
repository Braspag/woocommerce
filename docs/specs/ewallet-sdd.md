# SDD: E-Wallets (Apple Pay, Google Pay, Samsung Pay)
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-05-21 | **Autor:** agent-pm
**Tipo:** Software Design Document
**Linkado a:** `ewallet-prd.md`, `plugin-braspag-sdd.md`

---

## 1. Visão Geral

O gateway `braspag_ewallet` é um novo payment method WooCommerce que agrupa Apple Pay, Google Pay e Samsung Pay. Segue o mesmo padrão dos gateways existentes: herda `WC_Gateway_Braspag`, usa builders via WordPress filters (ADR-001), e chama `WC_Braspag_Pagador_API::create_sale()`.

A diferença central: **não há campos de cartão**. O payload `Payment.CreditCard{}` é substituído por `Payment.Wallet{}` com o token criptografado vindo do SDK da carteira. O `Payment.Type` permanece `"CreditCard"`.

---

## 2. Novos Componentes

### 2.1 Gateway PHP

| Classe | Arquivo | Herda de |
|---|---|---|
| `WC_Gateway_Braspag_EWallet` | `includes/payment-methods/class-wc-gateway-braspag-ewallet.php` | `WC_Gateway_Braspag` |

### 2.2 Blocks

| Classe | Arquivo | Herda de |
|---|---|---|
| `WC_Braspag_Blocks_EWallet` | `includes/blocks/payments/class-wc-braspag-blocks-ewallet.php` | `WC_Braspag_Blocks_Abstract` |

### 2.3 JavaScript (Frontend)

| Arquivo | Responsabilidade |
|---|---|
| `assets/js/frontend/ewallet.js` | Orchestrator: detecta carteiras disponíveis, renderiza botões, coleta token, injeta hidden fields, dispara submit |
| `assets/js/frontend/apple-pay.js` | `ApplePaySession` API — merchant validation, payment request, token extraction |
| `assets/js/frontend/google-pay.js` | `google.payments.api.PaymentsClient` — payment request, token extraction |
| `assets/js/frontend/samsung-pay.js` | Samsung Pay JS SDK — payment request, ref_id extraction |

### 2.4 AJAX Handler (Apple Pay Merchant Validation)

Registrado em `WC_Gateway_Braspag_EWallet::__construct()`:
- `wp_ajax_nopriv_braspag_ewallet_validate_merchant`
- `wp_ajax_braspag_ewallet_validate_merchant`
- Handler: `WC_Gateway_Braspag_EWallet::ajax_validate_merchant()`

---

## 3. Contrato de API Cielo — Payload Wallet

### 3.1 Apple Pay (encrypted)

```json
{
  "MerchantOrderId": "WC-{order_id}",
  "Customer": {
    "Name": "{billing_first_name} {billing_last_name}",
    "Identity": "{cpf_cnpj}",
    "IdentityType": "CPF"
  },
  "Payment": {
    "Type": "CreditCard",
    "Amount": 10000,
    "Installments": 1,
    "Currency": "BRL",
    "SoftDescriptor": "{soft_descriptor}",
    "Wallet": {
      "Type": "ApplePay",
      "WalletKey": "{paymentData.data}",
      "AdditionalData": {
        "EphemeralPublicKey": "{paymentData.header.ephemeralPublicKey}",
        "Signature": "{paymentData.signature}"
      }
    }
  }
}
```

### 3.2 Google Pay / AndroidPay (encrypted)

```json
{
  "Payment": {
    "Type": "CreditCard",
    "Amount": 10000,
    "Installments": 1,
    "Wallet": {
      "Type": "AndroidPay",
      "WalletKey": "{token.signedMessage}",
      "AdditionalData": {
        "Signature": "{token.signature}"
      }
    }
  }
}
```

> `token` = `JSON.parse(paymentMethodData.tokenizationData.token)`. O campo `Type` usa `"AndroidPay"` conforme documentação Cielo — verificar no sandbox se `"GooglePay"` também é aceito.

### 3.3 Samsung Pay (encrypted)

```json
{
  "Payment": {
    "Type": "CreditCard",
    "Amount": 10000,
    "Installments": 1,
    "Wallet": {
      "Type": "SamsungPay",
      "WalletKey": "{ref_id}"
    }
  }
}
```

### 3.4 Resposta esperada (sucesso)

```json
{
  "Payment": {
    "PaymentId": "{uuid}",
    "Status": 2,
    "AuthorizationCode": "123456",
    "Tid": "...",
    "ProofOfSale": "...",
    "ReturnCode": "00",
    "ReturnMessage": "Successful",
    "ECI": "7"
  }
}
```

---

## 4. Arquitetura de Builders (ADR-001)

Seguindo o padrão estabelecido, o payload é construído via WordPress filters encadeados:

### Filter principal

```
wc_gateway_braspag_pagador_braspag_ewallet_request_payment_builder
```

Responsabilidade: montar o payload base com `Payment.Type`, `Payment.Amount`, `Payment.Installments`, `Payment.Currency`, `Payment.SoftDescriptor`, e `Payment.Wallet.Type` + `Payment.Wallet.WalletKey`.

### Filters por carteira

```
wc_gateway_braspag_pagador_ewallet_applepay_builder
```
Adiciona `Payment.Wallet.AdditionalData.EphemeralPublicKey` e `Payment.Wallet.AdditionalData.Signature`.

```
wc_gateway_braspag_pagador_ewallet_googlepay_builder
```
Adiciona `Payment.Wallet.AdditionalData.CaptureCode` (se presente).

Samsung Pay não tem `AdditionalData` — sem filter adicional necessário.

### Registro dos filters

```php
// Em WC_Gateway_Braspag_EWallet::__construct()
add_filter("wc_gateway_braspag_pagador_{$this->id}_request_payment_builder", array($this, 'build_ewallet_payment_request'), 10, 4);
add_filter('wc_gateway_braspag_pagador_ewallet_applepay_builder',            array($this, 'build_applepay_additional_data'), 10, 2);
add_filter('wc_gateway_braspag_pagador_ewallet_googlepay_builder',           array($this, 'build_googlepay_additional_data'), 10, 2);
```

### Encadeamento em process_payment()

```php
$request_builder = $this->braspag_pagador_request_builder($this->id, $order, $default_request_params);

if ('ApplePay' === $wallet_type) {
    $request_builder['Payment'] = apply_filters('wc_gateway_braspag_pagador_ewallet_applepay_builder', $request_builder['Payment'], $order);
} elseif ('GooglePay' === $wallet_type) {
    $request_builder['Payment'] = apply_filters('wc_gateway_braspag_pagador_ewallet_googlepay_builder', $request_builder['Payment'], $order);
}

$response = $this->braspag_pagador_request($request_builder, 'v2/sales/', $default_request_params);
```

---

## 5. Fluxo de Estado Detalhado

### 5.1 Checkout Clássico

```
1. Comprador no checkout → JS ewallet.js inicializa
2. Detecta carteiras disponíveis no dispositivo
3. Renderiza botão(ões) da(s) carteira(s) disponível(eis)
4. Comprador clica no botão
5. SDK da carteira abre sheet nativo (autenticação biométrica)
6. Após aprovação: JS coleta token e injeta hidden fields:
   - braspag_wallet_type  = 'ApplePay' | 'GooglePay' | 'SamsungPay'
   - braspag_wallet_key   = {token_criptografado}
   - braspag_wallet_ephemeral_public_key (Apple only)
   - braspag_wallet_signature (Apple only)
   - braspag_wallet_capture_code (Google, se presente)
7. JS dispara submit() do formulário de checkout
8. PHP: validate_fields() → verifica campos obrigatórios
9. PHP: process_payment() → aplica builders → create_sale()
10. Cielo responde:
    - Status=2 → WC order 'processing' → redirect thank-you
    - Outro   → WC order 'failed' → mensagem de erro
```

### 5.2 Apple Pay — Merchant Validation (passo adicional)

```
5a. ApplePaySession.onvalidatemerchant dispara
5b. JS faz fetch() para wp-admin/admin-ajax.php?action=braspag_ewallet_validate_merchant
5c. PHP faz POST à URL de validação Apple com:
    - merchantIdentifier, displayName, initiative='web', initiativeContext=domain
5d. PHP retorna merchantSession JSON ao JS
5e. JS chama session.completeMerchantValidation(merchantSession)
5f. Apple exibe sheet de pagamento ao comprador
```

---

## 6. Estrutura da Classe WC_Gateway_Braspag_EWallet

```php
class WC_Gateway_Braspag_EWallet extends WC_Gateway_Braspag
{
    protected $apple_pay_enabled;
    protected $apple_pay_merchant_id;
    protected $google_pay_enabled;
    protected $google_pay_merchant_id;
    protected $samsung_pay_enabled;
    protected $samsung_pay_service_id;
    protected $installments;
    protected $soft_descriptor;

    public function __construct() { ... }
    public function init_form_fields() { ... }    // campos admin
    public function settings_extra_data() { ... } // lê settings globais

    // Builders
    public function build_ewallet_payment_request(array $request, WC_Order $order): array { ... }
    public function build_applepay_additional_data(array $request, WC_Order $order): array { ... }
    public function build_googlepay_additional_data(array $request, WC_Order $order): array { ... }

    // Validação e pagamento
    public function validate_fields(): bool { ... }
    public function process_payment(int $order_id): array { ... }

    // AJAX: Apple Pay merchant validation
    public function ajax_validate_merchant(): void { ... }

    // Assets
    public function payment_scripts(): void { ... }
}
```

---

## 7. Segurança e PCI

- `wallet_key` (token criptografado) **nunca** é logado em claro — mascarar via `WC_Braspag_Logger`: `{wallet_type}:****{últimos_4_chars}`
- `apple_pay_merchant_id`, `google_pay_merchant_id`, `samsung_pay_service_id` armazenados em `wp_options` (não em banco de dados de forma direta, nem em logs)
- Nonce WP (`woocommerce-process_checkout`) validado em `process_payment()` — padrão herdado do gateway base
- AJAX `braspag_ewallet_validate_merchant`: nonce separado `braspag_ewallet_nonce` gerado e verificado com `check_ajax_referer()`
- Certificado Apple Pay: o lojista é responsável por configurar no servidor; o plugin não armazena chaves privadas

---

## 8. Admin Settings — Form Fields

```php
public function init_form_fields()
{
    $this->form_fields = array(
        'enabled'                => array('type' => 'checkbox', 'title' => __('Enable/Disable')),
        'title'                  => array('type' => 'text',     'default' => __('Pague com sua carteira digital')),
        'description'            => array('type' => 'textarea'),

        // Apple Pay
        'apple_pay_enabled'      => array('type' => 'checkbox', 'title' => __('Habilitar Apple Pay')),
        'apple_pay_merchant_id'  => array('type' => 'text',     'title' => __('Apple Merchant Identifier')),

        // Google Pay
        'google_pay_enabled'     => array('type' => 'checkbox', 'title' => __('Habilitar Google Pay')),
        'google_pay_merchant_id' => array('type' => 'text',     'title' => __('Google Merchant ID')),

        // Samsung Pay
        'samsung_pay_enabled'    => array('type' => 'checkbox', 'title' => __('Habilitar Samsung Pay')),
        'samsung_pay_service_id' => array('type' => 'text',     'title' => __('Samsung Pay Service ID')),

        // Comum
        'installments'           => array('type' => 'select', 'options' => range(1, 12)),
        'soft_descriptor'        => array('type' => 'text', 'description' => __('Máx. 13 caracteres')),
    );
}
```

---

## 9. Blocks — WC_Braspag_Blocks_EWallet

```php
class WC_Braspag_Blocks_EWallet extends WC_Braspag_Blocks_Abstract
{
    public $name = 'braspag_ewallet';

    public function get_payment_method_data(): array
    {
        return array(
            'wallets'              => $this->gateway->get_enabled_wallets(), // ['ApplePay', 'GooglePay']
            'appleMerchantId'      => $this->gateway->apple_pay_merchant_id,
            'googleMerchantId'     => $this->gateway->google_pay_merchant_id,
            'samsungServiceId'     => $this->gateway->samsung_pay_service_id,
            'installments'         => $this->gateway->installments,
            'ajaxUrl'              => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('braspag_ewallet_nonce'),
            'isSandbox'            => $this->gateway->test_mode,
        );
    }
}
```

---

## 10. Testes

### 10.1 Unitários

| Teste | Arquivo | Cenários |
|---|---|---|
| `EWalletPayloadBuilderTest` | `tests/unit/EWalletPayloadBuilderTest.php` | Apple Pay: payload com AdditionalData correto; Google Pay: com CaptureCode; sem CaptureCode; Samsung Pay: sem AdditionalData; wallet_key mascarado no log |
| `EWalletGatewayTest` | `tests/unit/EWalletGatewayTest.php` | `validate_fields()` com campos ok; com wallet_key vazio; com wallet_type inválido; `process_payment()` Status=2→processing; Status≠2→failed; settings salvos |
| `EWalletAdminSettingsTest` | `tests/unit/EWalletAdminSettingsTest.php` | Aviso admin quando apple_pay_merchant_id vazio; campos carregados corretamente |

### 10.2 Integração

| Teste | Arquivo | Cenários |
|---|---|---|
| `EWalletIntegrationTest` | `tests/integration/EWalletIntegrationTest.php` | POST completo Apple Pay (mock pre_http_request → Status=2); POST Google Pay → Status=2; POST Samsung Pay → Status=2; Erro 500 Cielo → order failed |

### 10.3 Estratégia de Mock

- `pre_http_request` filter intercepta chamadas à Cielo API
- `$GLOBALS['_braspag_test_options']` simula settings do lojista
- `WC_Helper_Order::create_order()` cria pedidos reais para testes de integração
- Execução: `ddev exec vendor/bin/phpunit --filter EWallet`

---

## 11. Registro do Gateway

Em `wc-gateway-braspag.php` (ou no loader principal), adicionar à lista de gateways:

```php
// Em: add_filter('woocommerce_payment_gateways', 'braspag_add_gateway_class')
$methods[] = 'WC_Gateway_Braspag_EWallet';
```

E incluir o arquivo:
```php
include_once dirname(__FILE__) . '/includes/payment-methods/class-wc-gateway-braspag-ewallet.php';
```

---

## 12. ADRs

### ADR-007: E-Wallets no modo encrypted card apenas

**Data:** 2026-05-21
**Status:** Aceito

**Contexto:** A Cielo suporta dois modos para e-wallets: encrypted (Cielo descriptografa o token) e decrypted (lojista descriptografa, exige PCI DSS). O modo decrypted exige infraestrutura adicional de segurança no lado do lojista.

**Decisão:** Implementar apenas o modo encrypted card nesta versão. O payload enviado à Cielo contém sempre o token criptografado gerado pelo SDK da carteira.

**Consequências:**
- (+) Lojista não precisa de PCI DSS adicional além do que já possui
- (+) Menor superfície de ataque — dados de cartão nunca passam pelo servidor
- (-) Modo decrypted (para lojistas PCI DSS) não suportado nesta versão

### ADR-008: Um gateway único para as três carteiras

**Data:** 2026-05-21
**Status:** Aceito

**Contexto:** Poderíamos criar um gateway separado por carteira (`braspag_applepay`, `braspag_googlepay`, `braspag_samsungpay`) ou um gateway único `braspag_ewallet`.

**Decisão:** Um único gateway `braspag_ewallet`. A carteira usada é determinada pelo JS no frontend (disponibilidade no dispositivo) e comunicada ao backend via `braspag_wallet_type` hidden field.

**Consequências:**
- (+) Menos classes e configurações no admin
- (+) Um único `process_payment()` para manter
- (-) Configuração de wallets individuais dentro de um único painel (mais complexo)

---

## 13. Dependências de Arquivos Existentes (não modificar sem aprovação tech-lead)

| Arquivo | Motivo da dependência |
|---|---|
| `abstracts/abstract-wc-braspag-payment-gateway.php` | Classe base herdada — `WC_Gateway_Braspag` |
| `class-wc-braspag-pagador-api.php` | `create_sale()` reutilizado sem modificações |
| `class-wc-braspag-logger.php` | `WC_Braspag_Logger::log()` para mascaramento |
| `class-wc-braspag-customer.php` | `WC_Braspag_Customer` para montar `Customer{}` |
| `class-wc-braspag-helper.php` | Utilitários gerais |
| `wc-gateway-braspag.php` | **Modificar**: adicionar `require` + registrar gateway na lista |

---

*Atualizar ao implementar: adicionar número de linhas, referências de código, e resultados de testes.*
