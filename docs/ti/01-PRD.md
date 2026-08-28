# PRD — Product Requirements Document
**Produto:** Braspag for WooCommerce Oficial · **Versão do documento:** 1.0 (consolidado) · **Data:** 2026-08-26
**Fonte primária:** `docs/specs/plugin-braspag-prd.md`, `ewallet-prd.md`, `antifraude-prd.md`

## 1. Visão do Produto

Fornecer aos lojistas WooCommerce uma integração nativa e certificada com a plataforma Braspag (Cielo), cobrindo os principais métodos de pagamento do mercado brasileiro, com segurança PCI-DSS, autenticação forte (3DS 2.2), proteção antifraude e checkout moderno (Blocks e clássico).

## 2. Usuários-Alvo

| Ator | Descrição |
|---|---|
| Lojista | Configura e mantém os gateways de pagamento no admin WooCommerce |
| Comprador | Realiza o pagamento no checkout da loja |
| Operação | Monitora transações, reconcilia webhooks, gerencia chargebacks |

## 3. Métodos de Pagamento

| Método | ID WC | Blocks | Tokenização | 3DS | Antifraude | Status |
|---|---|:-:|:-:|:-:|:-:|---|
| Cartão de Crédito | `braspag_creditcard` | ✅ | ✅ | ✅ | ✅ | Implementado |
| Cartão de Débito | `braspag_debitcard` | ✅ | ❌ | ✅ | ❌ | Implementado |
| PIX | `braspag_pix` | ✅ | ❌ | ❌ | ❌ | Implementado |
| Boleto | `braspag_boleto` | ✅ | ❌ | ❌ | ❌ | Implementado |
| Crédito JustClick | `braspag_creditcard_justclick` | ❌ | ✅ (só token) | ✅ | ✅ | Implementado |
| E-Wallets (Apple/Google/Samsung Pay) | `braspag_ewallet` | ✅ (planejado) | ❌ | ❌ | ✅ | **Spec aprovada, implementação pendente** |

## 4. Requisitos Funcionais (RF)

> Numeração preservada do PRD mestre original.

- **RF-01 — Cartão de Crédito:** processar via Pagador API com SOP, 3DS 2.2, antifraude e tokenização; `validate_fields()` valida Luhn/CVV/validade/nome/nonce; pedido `processing` quando `Payment.Status = 2`, `failed` caso contrário; `CardToken` salvo em `wp_usermeta` quando `save_card=yes`.
- **RF-02 — Silent Order Post (SOP):** dados de cartão nunca transitam pelo servidor do lojista; backend recebe apenas `PaymentToken` temporário; `sop_tokenize=yes` gera `CardToken` permanente; compatível com 3DS e antifraude.
- **RF-03 — Autenticação 3DS 2.2:** bpmpi.js; bandeiras Visa/Mastercard/Elo (Amex sem 3DS); frictionless e challenge; comportamento em falha configurável (`failure_type` 0–3); 3DS Data Only (MasterCard Notify Only) suportado.
- **RF-04 — Antifraude:** modos "junto ao Pagador" e "API separada"; sequências `AuthorizeFirst`/`AnalyzeFirst`; device fingerprint via JS; `void_on_high_risk=yes`; providers CyberSource e ClearSale.
- **RF-05 — Tokenização de Cartões:** listagem em "Minha conta"; remoção pelo cliente; CVV opcional reconfirmável; compatível com 3DS/antifraude.
- **RF-06 — PIX:** QR Code via Pagador API; pedido `on-hold` até webhook; cron `PixCancelOrders` cancela expirados.
- **RF-07 — Boleto:** link/impressão na página de obrigado e e-mail; `on-hold` até webhook; múltiplos bancos via `available_types`.
- **RF-08 — Webhook Handler:** valida `PaymentId`/`ChangeType`; HMAC-SHA256 com `webhook_secret` (modo permissivo se ausente); idempotente; mapeia status; ChangeTypes 1,2,3,4,5,7,8,25 (reversão parcial).
- **RF-09 — Checkout Blocks (React):** 4 Blocks (CreditCard, DebitCard, PIX, Boleto); `get_payment_method_data()`; ponte ECFB para CPF/CNPJ; coexistência com clássico.
- **RF-10 — Captura Manual/Automática:** `authorize` vs `authorize_capture`; `void_sale()` automático em cancelamento pré-captura.
- **RF-11 — OAuth 2.0:** tokens cacheados via `wp_cache`; renovação em 401; Pagador não usa OAuth (credenciais diretas).
- **RF-12 — Configurações Admin:** settings globais (MerchantId, MerchantKey, ambiente, SOP, antifraude, webhook secret) e por gateway; avisos quando configuração crítica ausente.
- **RF-13 — Zero Auth:** valida cartão sem cobrar antes de tokenizar; bandeiras Visa/Mastercard/Elo; Amex → erro 57 tratado com fallback gracioso.
- **RF-14 — E-Wallets:** gateway único `braspag_ewallet`; payload `Payment.Wallet{}` por carteira; Apple Pay merchant validation via AJAX; `wallet_key` nunca logado em claro. *(spec aprovada — ver seção 5)*

### 4.1 Detalhe — E-Wallets (RF-14, spec `ewallet-prd.md`)

- Modo suportado: **encrypted card** (token criptografado do SDK da carteira, descriptografado pela Cielo). Modo *decrypted card* fica fora de escopo (exige PCI-DSS do lojista).
- Botões das carteiras só renderizam quando disponíveis no dispositivo/browser do comprador (feature detection via JS).
- `Status=2` → `processing`; erro → `failed`.
- Compatível com WC Blocks e checkout clássico.

### 4.2 Detalhe — Antifraude (RF-04, spec `antifraude-prd.md` / `antifraude-ard.md`)

- Dois providers: **CyberSource** (`FraudAnalysis`, `Cart`, `MerchantDefinedFields`) e **ClearSale** (`Payment.FraudAnalysis.Provider = "ClearSale"` + `session_id`).
- Compatível com SOP (avalia o `PaymentToken`, nunca o PAN).

## 5. Requisitos Não Funcionais (RNF)

| ID | Requisito |
|---|---|
| RNF-01 | Segurança PCI-DSS: nunca persistir PAN/CVV; mascarar dados sensíveis em log; sanitização de inputs; nonce WP obrigatório |
| RNF-02 | Performance: timeout 30s por chamada; retry exponencial (1s/2s/4s) só para 5xx/timeout; cache de token OAuth |
| RNF-03 | Compatibilidade: PHP 7.4+, WordPress 6.x, WooCommerce 10.x, HPOS, Blocks + clássico |
| RNF-04 | i18n: `__()`/`_e()` com textdomain `woocommerce-braspag`; `.pot` atualizado a cada release |
| RNF-05 | Sem namespaces PHP (ADR-002); prefixo `WC_Braspag_` |
| RNF-06 | Sem SQL direto — apenas APIs WordPress |
| RNF-07 | Logs via `WC_Braspag_Logger` com mascaramento; nunca logar CVV/PAN/MerchantKey completos |

## 6. Compatibilidade de Funcionalidades

| Combinação | Compatível | Observação |
|---|:-:|---|
| SOP + 3DS | ✅ | `PaymentToken` e `ExternalAuthentication` coexistem |
| SOP + Antifraude (Pagador) | ✅ | Avalia `PaymentToken`, não o PAN |
| SOP + Tokenização | ✅ | `sop_tokenize=yes` |
| 3DS + Antifraude | ✅ | Coexistem no payload de `create_sale()` |
| WC Blocks + Checkout Clássico | ✅ | Tema decide |
| HPOS + Tokenização | ✅ | Via WC Payment Token API |

## 7. Adquirentes Suportados

Cielo (1.0, 3.0, Sitef), Rede (Rede2, Sitef), Getnet, Stone, GlobalPayments, FirstData, Sub1, Banorte, Credibanco, Transbank, Santander, Safra2.

## 8. Fora de Escopo (v atual)

Assinaturas recorrentes, split de pagamento/marketplace, emissão de NF-e, conciliação automática de extratos, e-wallets em modo *decrypted card*.

## 9. Métricas de Sucesso

| Métrica | Meta |
|---|---|
| Taxa de aprovação (Crédito) | ≥ 85% em produção |
| Latência p95 API Braspag | < 2s |
| Cobertura PHPUnit | ≥ 80% dos métodos críticos |
| Webhooks processados com sucesso | ≥ 99%, sem duplicidade |

## 10. Critérios Globais de Aceitação

- Todos os RFs verificáveis por teste automatizado.
- Nenhuma credencial sensível em log/BD.
- PHPUnit (Unit + Integration) e PHPCS/PHPStan sem falhas/erros críticos.
- Blocks e clássico funcionando simultaneamente.
- Webhook processa PIX/Boleto corretamente em sandbox.
