# PRD: Plugin WooCommerce Braspag
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-05-17 | **Autor:** agent-pm
**Tipo:** Documento Mestre de Produto (top-level)

---

## Objetivo

Fornecer aos lojistas WooCommerce uma integração nativa e certificada com a plataforma Braspag (Cielo), suportando os principais métodos de pagamento do mercado brasileiro, com segurança PCI-DSS, autenticação forte (3DS 2.0), proteção antifraude e experiência de checkout moderna (Blocks e clássico).

---

## Usuários-alvo

| Ator | Descrição |
|---|---|
| **Lojista** | Administrador WooCommerce que configura e mantém os gateways de pagamento |
| **Comprador** | Cliente final que realiza o pagamento no checkout |
| **Operação** | Equipe que monitora transações, reconcilia webhooks e gerencia chargebacks |

---

## Métodos de Pagamento Suportados

| Método | ID WC | Blocks | Tokenização | 3DS | Antifraude |
|---|---|:-:|:-:|:-:|:-:|
| Cartão de Crédito | `braspag_creditcard` | ✅ | ✅ | ✅ | ✅ |
| Cartão de Débito | `braspag_debitcard` | ✅ | ❌ | ✅ | ❌ |
| PIX | `braspag_pix` | ✅ | ❌ | ❌ | ❌ |
| Boleto | `braspag_boleto` | ✅ | ❌ | ❌ | ❌ |
| Crédito JustClick | `braspag_creditcard_justclick` | ❌ | ✅ (só token) | ✅ | ✅ |
| E-Wallets (Apple/Google/Samsung Pay) | `braspag_ewallet` | ✅ | ❌ | ❌ | ✅ |

---

## Requisitos Funcionais

### RF-01 — Processamento de Cartão de Crédito
**Descrição:** O plugin deve processar pagamentos com cartão de crédito via Pagador API, com suporte a SOP, 3DS 2.0, antifraude e tokenização.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] `validate_fields()` valida Luhn, CVV, validade, nome e nonce WP antes de submeter
- [ ] Payload montado via builders encadeados (base, 3DS, antifraude) sem acoplamento direto
- [ ] Pedido muda para `processing` quando `Payment.Status = 2`
- [ ] Pedido muda para `failed` quando negado ou erro de API
- [ ] CardToken salvo em `wp_usermeta` quando `save_card = 'yes'` e pagamento aprovado

### RF-02 — Silent Order Post (SOP)
**Descrição:** Dados do cartão devem ser enviados diretamente do browser para a Braspag, sem transitar pelo servidor do lojista.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Backend recebe apenas `PaymentToken` temporário, nunca PAN completo
- [ ] `sop_tokenize = 'yes'` gera `CardToken` permanente via SOP
- [ ] 3DS continua funcionando normalmente com SOP ativo (fluxos sequenciais)
- [ ] Antifraude funciona com SOP ativo (avalia PaymentToken, não o PAN)

### RF-03 — Autenticação 3DS 2.2
**Descrição:** Suporte à autenticação forte via protocolo 3DS 2.2 (bpmpi.js) para crédito e débito.
**Prioridade:** Alta
**Bandeiras suportadas:** Visa, Mastercard, Elo (crédito e débito). Amex: sem suporte 3DS (limitação da rede).
**Critérios de Aceitação:**
- [ ] Frictionless: `CAVV` e `ECI` retornados sem interação do comprador
- [ ] Challenge: modal exibido ao comprador, resposta processada via hidden fields
- [ ] Comportamento em falha configurável por `failure_type` (0=OK, 1=falha, 2=erro, 3=não inscrito)
- [ ] `authorize_on_failure/error/unenrolled` respeita configuração do lojista
- [ ] Elo suportado em 3DS 2.2 (cartões de teste: `6505290000002190`, `6505290000002000`)
- [ ] 3DS Data Only (MasterCard Notify Only) suportado

### RF-04 — Antifraude
**Descrição:** Análise de risco via Braspag Risk API ou integrada ao Pagador, com device fingerprinting. Spec completa: `antifraude-prd.md` / `antifraude-ard.md` / `antifraude-sdd.md`.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Dois modos: `junto ao Pagador` (FraudAnalysis no payload) e `API separada`
- [ ] Duas sequências: `AuthorizeFirst` e `AnalyzeFirst`
- [ ] Device fingerprint coletado via JS no checkout
- [ ] `void_on_high_risk = 'yes'` cancela transação automaticamente se risco alto
- [ ] Antifraude compatível com SOP ativo (avalia o token, não o PAN)
- [ ] Provider CyberSource: campos `FraudAnalysis`, `Cart`, `MerchantDefinedFields`
- [ ] Provider ClearSale: `Payment.FraudAnalysis.Provider = "ClearSale"` + `session_id`

### RF-14 — E-Wallets (Apple Pay, Google Pay, Samsung Pay)
**Descrição:** O plugin deve processar pagamentos via carteiras digitais Apple Pay, Google Pay e Samsung Pay, no modo encrypted card (token criptografado gerado pelo SDK da carteira, descriptografado pela Cielo). Spec completa: `ewallet-prd.md`.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Gateway `braspag_ewallet` visível e configurável no admin WooCommerce
- [ ] Botões das carteiras renderizados apenas quando disponíveis no dispositivo do comprador
- [ ] Payload `Payment.Wallet{}` montado corretamente para cada carteira (Apple, Google, Samsung)
- [ ] Apple Pay merchant validation via AJAX endpoint seguro
- [ ] Status=2 → pedido `processing`; erro → pedido `failed`
- [ ] `wallet_key` nunca logado em claro
- [ ] Compatível com WC Blocks e checkout clássico

---

### RF-13 — Zero Auth (Validação de Cartão)
**Descrição:** Validar cartão antes de tokenizar, sem cobrar o comprador e sem afetar o limite.
**Prioridade:** Alta
**Bandeiras suportadas:** Visa, Mastercard, Elo. Amex retorna erro 57 — tratar gracefully.
**Critérios de Aceitação:**
- [ ] Zero Auth executado antes de criar CardToken permanente (`save_card = 'yes'`)
- [ ] Cartão aprovado pelo Zero Auth → tokenizar normalmente
- [ ] Cartão recusado pelo Zero Auth → não tokenizar, exibir mensagem ao comprador
- [ ] Amex → erro 57 tratado: tokenizar sem Zero Auth (comportamento degradado documentado)
- [ ] Zero Auth com `CardToken` também suportado (para re-validação de tokens existentes)
- [ ] Serviço não habilitado na Cielo → fallback gracioso, tokenização prossegue sem validação

### RF-05 — Tokenização de Cartões
**Descrição:** Lojistas e compradores podem salvar cartões para uso futuro.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Token listado no checkout e em "Minha conta > Métodos de pagamento"
- [ ] Comprador pode remover tokens pela área do cliente
- [ ] CVV pode ser solicitado novamente para tokens salvos (configurável)
- [ ] 3DS e antifraude funcionam normalmente com `CardToken`

### RF-06 — PIX
**Descrição:** Geração de QR Code PIX via Pagador API; confirmação assíncrona via webhook.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] QR Code exibido ao comprador após criação do pedido
- [ ] Pedido em `on-hold` até webhook confirmar (`Status=2 → processing`)
- [ ] Cron job `PixCancelOrders` cancela pedidos PIX expirados

### RF-07 — Boleto
**Descrição:** Geração de boleto bancário via Pagador API; confirmação assíncrona via webhook.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Link/impressão do boleto disponível na página de obrigado e e-mail
- [ ] Pedido em `on-hold` até webhook confirmar
- [ ] Suporte a múltiplos bancos via `available_types`

### RF-08 — Webhook Handler
**Descrição:** Receber e processar notificações assíncronas da Braspag via HTTP POST.
**Prioridade:** Alta — PIX e Boleto dependem disso para confirmação
**Critérios de Aceitação:**
- [ ] Validação de `PaymentId` e `ChangeType` obrigatórios no body
- [ ] Validação HMAC-SHA256 com `webhook_secret` configurado (modo permissivo quando ausente)
- [ ] Idempotência: pedidos em status final ignorados silenciosamente
- [ ] Mapeamento: Status 2 → `processing`, Status 3/10 → `cancelled`, Status 11 → `refunded`
- [ ] ChangeTypes suportados: 1 (status), 2 (recorrência criada), 3 (antifraude), 4 (recorrência), 5 (cancelamento negado), 7 (chargeback), 8 (fraude alert), **25 (reversão parcial)**
- [ ] ChangeType 25 → registra refund parcial no pedido WooCommerce

### RF-09 — Checkout Blocks (React)
**Descrição:** Todos os gateways compatíveis com o novo sistema WooCommerce Blocks.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Quatro Blocks registrados: CreditCard, DebitCard, PIX, Boleto
- [ ] `get_payment_method_data()` expõe configurações do PHP para o React
- [ ] Ponte ECFB integra campos extras (CPF/CNPJ) nos Blocks
- [ ] Checkout Blocks e clássico coexistem sem conflito

### RF-10 — Captura Manual e Automática
**Descrição:** Lojista escolhe entre captura imediata (`authorize_capture`) ou diferida (`authorize`).
**Prioridade:** Média
**Critérios de Aceitação:**
- [ ] `payment_action = 'authorize'`: apenas autoriza, captura quando pedido vai para `processing`
- [ ] `payment_action = 'authorize_capture'`: autoriza e captura na mesma transação
- [ ] `void_sale()` chamado automaticamente quando pedido cancelado antes da captura

### RF-11 — OAuth 2.0 para APIs Internas
**Descrição:** Gerenciamento automático de Bearer tokens para MPI e Risk API.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Token cacheado via `wp_cache` pelo tempo de expiração da Braspag
- [ ] HTTP 401 invalida cache e solicita novo token antes de retornar erro
- [ ] Pagador usa credenciais diretas no header (sem OAuth)

### RF-12 — Configurações Admin
**Descrição:** Painel de administração completo no WooCommerce para configurar todos os gateways.
**Prioridade:** Alta
**Critérios de Aceitação:**
- [ ] Settings globais: MerchantId, MerchantKey, ambiente (sandbox/produção), SOP, antifraude, webhook secret
- [ ] Settings por gateway: enabled, title, 3DS, captura, parcelamento, cartão salvo
- [ ] Avisos admin quando configurações críticas estão ausentes

---

## Requisitos Não-Funcionais

### RNF-01 — Segurança PCI-DSS
- Nunca armazenar PAN (número completo do cartão) em banco de dados ou logs
- Nunca armazenar CVV em qualquer forma
- Mascarar dados sensíveis nos logs: `MerchantKey → me*****y`, `CardNumber → 41****1111`
- Todos os inputs de cartão: `sanitize_text_field()` + remoção de não-dígitos
- Nonce WP (`woocommerce-process_checkout`) validado em todo `process_payment()`

### RNF-02 — Performance
- Chamada à API Braspag: timeout máximo de 30s
- Retry com backoff exponencial: 1s → 2s → 4s (3 tentativas)
- Erros 4xx permanentes: sem retry
- Erros 5xx/timeout: com retry
- Token OAuth cacheado para evitar chamadas repetidas de autenticação

### RNF-03 — Compatibilidade
- **PHP:** 7.4+
- **WordPress:** 6.x
- **WooCommerce:** 10.x
- **HPOS:** compatível via WC Payment Token API
- **Checkout Blocks e Clássico:** coexistência sem conflito

### RNF-04 — Internacionalização
- Todas as strings com `__()` e `_e()` usando textdomain `woocommerce-braspag`
- Arquivo `.pot` atualizado a cada release

### RNF-05 — Código sem Namespaces PHP
- Seguir ADR-002: sem `namespace` PHP (compatibilidade com PHP 7.4 e padrões WooCommerce legados)
- Todas as classes com prefixo `WC_Braspag_`

### RNF-06 — Sem SQL Direto
- Usar exclusivamente APIs WordPress: `get_option()`, `WC_Order`, `get_posts()`, `wp_usermeta`
- Proibido `$wpdb->query()` direto

### RNF-07 — Logs
- Logs via `WC_Braspag_Logger` com nível configurável
- Nunca logar: CVV, PAN completo, MerchantKey completa
- Sempre logar: PaymentId, Status, erros de API com código HTTP

---

## Compatibilidade de Funcionalidades

| Combinação | Compatível? | Observação |
|---|:-:|---|
| SOP + 3DS | ✅ | PaymentToken e ExternalAuthentication coexistem |
| SOP + Antifraude (Pagador) | ✅ | Antifraude avalia PaymentToken (não o PAN) |
| SOP + Tokenização (CardToken) | ✅ | Via `sop_tokenize = 'yes'` |
| 3DS + Antifraude | ✅ | Ambos coexistem no payload de `create_sale()` |
| WC Blocks + Checkout Clássico | ✅ | Tema decide qual exibir |
| HPOS + Tokenização | ✅ | Tokens via WC Payment Token API |

---

## Adquirentes Suportados

Cielo (1.0, 3.0, Sitef), Rede (Rede2, Sitef), Getnet, Stone, GlobalPayments, FirstData, Sub1, Banorte, Credibanco, Transbank, Santander, Safra2.

---

## Fora de Escopo (versão atual)

- Gestão de assinaturas recorrentes (Braspag Recorrência)
- Pagamentos split (disponível no módulo Magento, não no WooCommerce)
- Split de marketplace
- Emissão de nota fiscal eletrônica
- Conciliação automática de extratos

## Features Futuras (planejadas para versões seguintes)

| Feature | Justificativa |
|---------|--------------|
| E-wallets modo decrypted card | Exige PCI DSS no lojista; fora do escopo v1 (encrypted card já suportado via RF-14) |
| Network Tokenization / DPAN | Visa Network Token habilitada automaticamente para todos clientes API; melhora aprovação |

---

## Métricas de Sucesso

| Métrica | Target |
|---|---|
| Taxa de aprovação (Crédito) | ≥ 85% em produção |
| Latência média da API Braspag | < 2s (p95) |
| Cobertura de testes (PHPUnit) | ≥ 80% dos métodos críticos |
| Webhooks processados com sucesso | ≥ 99% (sem duplicidade) |
| Specs cobrindo funcionalidades | 100% antes de qualquer implementação |

---

## Critérios Globais de Aceitação

- [ ] Todos os RFs implementados e verificáveis via teste automatizado
- [ ] Nenhuma credencial sensível (PAN, CVV, MerchantKey) em logs ou banco de dados
- [ ] PHPUnit Unit + Integration passando sem falhas
- [ ] PHPCS/PHPStan sem erros críticos
- [ ] Compatível com WC Blocks e checkout clássico simultaneamente
- [ ] Webhook handler processa PIX e Boleto corretamente em modo sandbox

---

*Documento mestre — atualizar ao adicionar novos métodos de pagamento ou integrar novas APIs Braspag.*
