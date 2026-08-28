# PRD: E-Wallets (Apple Pay, Google Pay, Samsung Pay)
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-05-21 | **Autor:** agent-pm
**Tipo:** Especificação de Funcionalidade
**Linkado a:** `plugin-braspag-prd.md` (documento mestre)

---

## Objetivo

Adicionar suporte a pagamentos via carteiras digitais (Apple Pay, Google Pay, Samsung Pay) no plugin WooCommerce Braspag, utilizando a Cielo API E-commerce no modo **encrypted card** — o token criptografado gerado pelo SDK da carteira é enviado à Cielo, que realiza a descriptografia e processa a autorização. O lojista não manipula dados de cartão em nenhum momento.

---

## Usuários-alvo

| Ator | Descrição |
|---|---|
| **Lojista** | Configura quais carteiras habilitar no admin WooCommerce |
| **Comprador** | Usa Apple Pay (Safari/iOS), Google Pay (Android/Chrome) ou Samsung Pay (dispositivos Samsung) no checkout |
| **Operação** | Monitora transações e-wallet como qualquer outra transação de crédito |

---

## Escopo desta versão

**Incluído:**
- Modo encrypted card (lojista sem PCI DSS): token criptografado enviado à Cielo
- Apple Pay, Google Pay, Samsung Pay via Cielo 3.0
- Checkout clássico WooCommerce e WooCommerce Blocks
- Cartão de crédito (suporte a débito depende de disponibilidade por carteira — fora do escopo desta versão)
- Parcelamento configurável pelo lojista (restrito ao que cada SDK permite)

**Fora do escopo desta versão:**
- Modo decrypted card (exige PCI DSS — escopo futuro)
- Network Tokenization / DPAN
- Recorrência / assinaturas via e-wallet
- Samsung Pay em navegadores desktop (suporte limitado pelo próprio SDK)

---

## Pré-requisitos externos (responsabilidade do lojista)

| Carteira | Pré-requisito |
|---|---|
| Apple Pay | Apple Developer Program; Merchant Identifier configurado; domain verification no servidor; Apple Pay habilitado no portal Cielo |
| Google Pay | Cadastro Google Pay Business Console; Google Pay API aprovado para produção; Google Pay habilitado no portal Cielo |
| Samsung Pay | Cadastro Samsung Pay Business; Samsung Pay habilitado no portal Cielo |

---

## RF-14 — E-Wallets (Apple Pay, Google Pay, Samsung Pay)

### RF-14.1 — Gateway de Pagamento

**Descrição:** O plugin deve expor um gateway WooCommerce único `braspag_ewallet` que agrupa as três carteiras. A carteira usada é determinada pela disponibilidade no dispositivo/browser do comprador e pela configuração do lojista.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] Gateway `braspag_ewallet` visível na lista de gateways WooCommerce (admin > Payments)
- [ ] Gateway habilitado apenas quando pelo menos uma carteira estiver configurada e habilitada
- [ ] `validate_fields()` verifica que `wallet_type` e `wallet_key` não estão vazios antes de processar
- [ ] `process_payment()` monta o payload `Payment.Wallet{}` e chama `create_sale()` via `WC_Braspag_Pagador_API`
- [ ] Status 2 (PaymentConfirmed) → pedido `processing`
- [ ] Qualquer outro status ou erro de API → pedido `failed` + mensagem de erro ao comprador
- [ ] `PaymentId` salvo em `order_meta` (`_braspag_ewallet_payment_id`) para consultas e operações futuras
- [ ] Tipo da carteira salvo em `order_meta` (`_braspag_ewallet_wallet_type`) para exibição no admin

### RF-14.2 — Frontend: Detecção e Renderização dos Botões

**Descrição:** O JavaScript do plugin deve detectar qual carteira está disponível no dispositivo e renderizar o botão correspondente no checkout. Apenas botões de carteiras disponíveis no dispositivo atual são exibidos.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] Apple Pay: detectado via `window.ApplePaySession && ApplePaySession.canMakePayments()`; botão exibido apenas em Safari/iOS com Apple Pay configurado
- [ ] Google Pay: detectado via `google.payments.api.PaymentsClient`; botão exibido em Chrome/Android com Google Pay configurado
- [ ] Samsung Pay: detectado via Samsung Pay JS SDK; botão exibido apenas em dispositivos Samsung compatíveis
- [ ] Se nenhuma carteira disponível: formulário do gateway oculto (o checkout exibe apenas os outros gateways)
- [ ] Botões seguem as brand guidelines de cada carteira (Apple Pay Button, Google Pay Button, Samsung Pay Button)

### RF-14.3 — Fluxo Apple Pay

**Descrição:** Ao clicar no botão Apple Pay, o comprador aprova o pagamento via Face ID/Touch ID. O JS captura o `paymentToken` e injeta os dados nos hidden fields antes do submit do checkout.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] `ApplePaySession` criado com `merchantCapabilities`, `supportedNetworks` (Visa, Mastercard, Elo), `countryCode`, `currencyCode`, `total`
- [ ] `onvalidatemerchant` dispara chamada AJAX ao backend (`braspag_ewallet_validate_merchant`) que retorna a `merchantSession` da Apple
- [ ] Após aprovação: `wallet_type=ApplePay`, `wallet_key=paymentData.data` (base64), `wallet_ephemeral_public_key=paymentData.header.ephemeralPublicKey`, `wallet_signature=paymentData.signature` injetados como hidden fields
- [ ] Submit do formulário de checkout disparado automaticamente após preenchimento dos hidden fields

### RF-14.4 — Fluxo Google Pay

**Descrição:** Ao clicar no botão Google Pay, o SDK abre o sheet nativo. Após aprovação, o `paymentToken` é injetado nos hidden fields.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] `PaymentDataRequest` configurado com `gateway: 'cielo'`, `gatewayMerchantId` (MerchantId Cielo), `allowedCardNetworks`, `allowedAuthMethods`
- [ ] Após aprovação: `wallet_type=GooglePay`, `wallet_key=paymentData.paymentMethodData.tokenizationData.token` (JSON string), `wallet_capture_code` (se presente) injetados como hidden fields
- [ ] Submit automático após preenchimento

### RF-14.5 — Fluxo Samsung Pay

**Descrição:** Samsung Pay usa seu próprio SDK mobile; o `ref_id` retornado é enviado como `wallet_key`.

**Prioridade:** Média (suporte mais restrito de dispositivos)

**Critérios de Aceitação:**
- [ ] SDK Samsung Pay inicializado com `serviceId` configurado pelo lojista
- [ ] Após aprovação: `wallet_type=SamsungPay`, `wallet_key=ref_id` injetados como hidden fields
- [ ] Submit automático após preenchimento

### RF-14.6 — Admin Settings

**Descrição:** Painel de configurações completo no WooCommerce para o gateway `braspag_ewallet`.

**Prioridade:** Alta

**Campos de configuração:**

| Campo | Tipo | Descrição |
|---|---|---|
| `enabled` | checkbox | Habilitar/desabilitar o gateway |
| `title` | text | Título exibido no checkout |
| `description` | textarea | Descrição exibida no checkout |
| `apple_pay_enabled` | checkbox | Habilitar Apple Pay |
| `apple_pay_merchant_id` | text | Apple Merchant Identifier (ex: `merchant.com.loja`) |
| `apple_pay_cert_path` | text | Caminho absoluto do certificado merchant identity Apple Pay no servidor (.pem/.crt) |
| `apple_pay_key_path` | text | Caminho absoluto da chave privada do certificado Apple Pay no servidor (.key/.pem) |
| `google_pay_enabled` | checkbox | Habilitar Google Pay |
| `google_pay_merchant_id` | text | Google Merchant ID (do Google Business Console) |
| `samsung_pay_enabled` | checkbox | Habilitar Samsung Pay |
| `samsung_pay_service_id` | text | Samsung Pay Service ID |
| `installments` | select | Número máximo de parcelas (1–12) |
| `soft_descriptor` | text | Descritor no extrato (máx 13 chars) |

**Critérios de Aceitação:**
- [ ] Aviso admin exibido quando `apple_pay_enabled=yes` mas `apple_pay_merchant_id` está vazio
- [ ] Configurações salvas via `woocommerce_braspag_ewallet_settings` option
- [ ] Configurações lidas corretamente em `__construct()`

### RF-14.7 — Merchant Validation (Apple Pay)

**Descrição:** Apple Pay requer que o servidor do lojista valide o merchant junto à Apple antes de cada sessão de pagamento. O plugin deve expor um endpoint AJAX para esta validação.

**Prioridade:** Alta (obrigatório para Apple Pay)

**Critérios de Aceitação:**
- [ ] Action AJAX `wp_ajax_nopriv_braspag_ewallet_validate_merchant` registrado
- [ ] Backend faz requisição POST à URL de validação da Apple com o `merchantIdentifier`, `displayName`, `initiative: 'web'`, `initiativeContext` (domínio do lojista)
- [ ] Certificado Apple Pay configurado (ou delegado ao lojista via instrução no admin)
- [ ] Resposta `merchantSession` retornada ao JS para completar `completeMerchantValidation()`
- [ ] Erros de validação logados via `WC_Braspag_Logger` sem expor dados sensíveis

### RF-14.8 — WooCommerce Blocks

**Descrição:** O gateway e-wallet deve funcionar no checkout WooCommerce Blocks (React).

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] Block `braspag_ewallet` registrado via `WC_Braspag_Blocks_EWallet`
- [ ] `get_payment_method_data()` expõe: lista de wallets habilitadas, merchant IDs, ambiente (sandbox/prod), total do pedido
- [ ] Componente React renderiza botões corretos conforme disponibilidade
- [ ] Hidden fields preenchidos pelo JS antes do submit do Block

---

## Compatibilidade de Funcionalidades

| Combinação | Compatível? | Observação |
|---|:-:|---|
| E-Wallet + 3DS | ❌ | A autenticação é feita pelo SDK da carteira; não se aplica 3DS adicional |
| E-Wallet + Antifraude | ✅ | Payload Cielo aceita `FraudAnalysis` junto com `Wallet` (verificar suporte por carteira) |
| E-Wallet + Tokenização (CardToken) | ❌ | Não aplicável no modo encrypted; a Cielo não retorna CardToken para e-wallets |
| E-Wallet + SOP | ❌ | SOP e e-wallets são mutuamente exclusivos — dados vêm do SDK, não de campos de cartão |
| E-Wallet + Checkout Clássico | ✅ | |
| E-Wallet + WC Blocks | ✅ | |

---

## Fluxo de Estado do Pedido

```
Comprador clica no botão da carteira
    └── SDK autentica (Face ID / biometria / PIN)
    └── JS captura token → injeta hidden fields → submit
          └── validate_fields() → ok
          └── process_payment()
                └── builder monta Payment.Wallet{}
                └── create_sale() → POST /v2/sales
                       ├── Status=2 → pedido 'processing'
                       └── Outro / erro → pedido 'failed'
```

---

## Critérios Globais de Aceitação

- [ ] RF-14.1 a RF-14.8 implementados e verificáveis por testes automatizados
- [ ] Nenhum token de carteira (`wallet_key`) logado em claro — mascarar via `WC_Braspag_Logger`
- [ ] PHPUnit Unit + Integration passando sem falhas (`ddev exec vendor/bin/phpunit`)
- [ ] Sandbox Cielo: transação Apple Pay, Google Pay e Samsung Pay completando com `Status=2`
- [ ] Compatível com WC Blocks e checkout clássico simultaneamente
- [ ] Admin settings salvos e lidos corretamente
- [ ] Botões de carteira seguem brand guidelines das respectivas plataformas

---

## Referências

- [Cielo E-Wallets Overview](https://docs.cielo.com.br/ecommerce-cielo/docs/e-wallets)
- [Cielo Apple Pay](https://docs.cielo.com.br/ecommerce-cielo/docs/apple-pay)
- [Cielo Google Pay](https://docs.cielo.com.br/ecommerce-cielo/docs/google-pay)
- [Cielo Samsung Pay](https://docs.cielo.com.br/ecommerce-cielo/docs/samsung-pay)
- [Apple Pay JS API](https://developer.apple.com/documentation/apple_pay_on_the_web)
- [Google Pay Web API](https://developers.google.com/pay/api/web)
