# ARD — Architecture Requirements Document
**Produto:** Braspag for WooCommerce Oficial · **Versão:** 1.0 · **Data:** 2026-08-26
**Fonte primária:** `docs/specs/antifraude-ard.md` + requisitos arquiteturais implícitos no PRD/SDD mestre

## 1. Objetivo

Definir os requisitos arquiteturais que qualquer solução dentro do plugin deve satisfazer, independente da feature — restrições técnicas, de segurança, integração e qualidade que orientam decisões de design (ADRs) e o SDD.

## 2. Restrições Tecnológicas

| Restrição | Descrição |
|---|---|
| Sem namespaces PHP | ADR-002 — compatibilidade com PHP 7.4 e padrões WordPress/WooCommerce legados. Todas as classes usam prefixo `WC_Braspag_` |
| Sem SQL direto | Proibido `$wpdb->query()`; usar `get_option()`, `WC_Order`, `get_posts()`, `wp_usermeta` |
| PHP mínimo | 7.4 |
| WordPress | 6.x (`Requires at least: 5.3.2`, `Tested up to: 6.9.5`) |
| WooCommerce | 10.x (`WC tested up to: 10.9.4`), HPOS compatível |
| Dependência obrigatória | `woocommerce`, `woocommerce-extra-checkout-fields-for-brazil` (ECFB, para CPF/CNPJ) |

## 3. Requisitos Arquiteturais de Segurança

- **RA-SEG-01:** Nenhum dado de cartão (PAN completo, CVV) pode ser persistido em banco de dados ou aparecer em log, sob qualquer circunstância (PCI-DSS SAQ A/A-EP).
- **RA-SEG-02:** Toda comunicação de dados de cartão do browser à Braspag deve usar Silent Order Post (SOP) — o servidor do lojista nunca recebe PAN bruto quando SOP está ativo.
- **RA-SEG-03:** Toda submissão de checkout deve validar nonce WordPress (`woocommerce-process_checkout`).
- **RA-SEG-04:** Segredos de integração (MerchantKey, wallet_key, webhook_secret) devem ser mascarados em log (`me*****y`) e nunca expostos ao cliente/JS.
- **RA-SEG-05:** Webhooks devem ser validados via HMAC-SHA256 (`webhook_secret`); ausência de secret cai em modo permissivo documentado (não bloqueia operação, mas reduz garantia de integridade).
- **RA-SEG-06:** Device fingerprinting (antifraude) deve ocorrer client-side sem coletar PII além do necessário aos providers (CyberSource/ClearSale).

## 4. Requisitos Arquiteturais de Integração

- **RA-INT-01:** Toda integração externa (Pagador, MPI, Risk, OAuth) deve ser isolada em classes de API dedicadas (`WC_Braspag_*_API`), nunca chamadas HTTP diretas dentro de gateways.
- **RA-INT-02:** Autenticação OAuth 2.0 (MPI/Risk) deve cachear o token via `wp_cache` pelo tempo de expiração retornado pela Braspag, com invalidação automática em HTTP 401.
- **RA-INT-03:** Chamadas HTTP externas devem ter timeout de 30s e retry com backoff exponencial (1s/2s/4s) apenas para erros 5xx/timeout — nunca para 4xx.
- **RA-INT-04:** A montagem de payloads deve ser extensível via WordPress filters encadeados (builders), sem exigir alteração das classes de gateway para adicionar comportamento (3DS, antifraude, wallet).
- **RA-INT-05:** Endpoints de sandbox e produção devem ser resolvidos por configuração de ambiente, nunca hardcoded fora de uma tabela central de endpoints.

## 5. Requisitos Arquiteturais de Extensibilidade

- **RA-EXT-01:** Novos métodos de pagamento devem poder ser adicionados como uma nova classe `WC_Gateway_Braspag_*` herdando de `WC_Braspag_Payment_Gateway`, sem modificar gateways existentes.
- **RA-EXT-02:** Novos providers de antifraude (além de CyberSource/ClearSale) devem se plugar via filter de builder de payload, não via `if/else` acumulativo na classe de Risk API.
- **RA-EXT-03:** Suporte a Checkout Blocks e clássico deve coexistir sem branch de código duplicado relevante — Blocks reaproveita a lógica de `process_payment()` do gateway clássico.

## 6. Requisitos Arquiteturais de Confiabilidade

- **RA-REL-01:** Fluxos assíncronos (PIX, Boleto) dependem exclusivamente do webhook handler para confirmação — o handler deve ser idempotente (pedidos em status final são ignorados silenciosamente).
- **RA-REL-02:** Pedidos PIX expirados devem ser cancelados por um cron job (`PixCancelOrders`), não por polling síncrono no checkout.
- **RA-REL-03:** Falhas de rede transitórias com a Braspag não devem corromper o estado do pedido — o pedido só muda de status após confirmação explícita de sucesso/falha da API.

## 7. Requisitos Arquiteturais Específicos — Antifraude

(Detalhe consolidado de `antifraude-ard.md`)

- **RA-AF-01:** O antifraude deve suportar dois modos de operação mutuamente exclusivos por transação: **integrado ao Pagador** (campo `FraudAnalysis` dentro do payload de `create_sale()`) e **API separada** (`WC_Braspag_Risk_API`, chamada antes ou depois da autorização conforme a sequência configurada).
- **RA-AF-02:** Duas sequências suportadas: `AuthorizeFirst` (autoriza e depois analisa risco) e `AnalyzeFirst` (analisa risco antes de autorizar).
- **RA-AF-03:** O antifraude deve operar corretamente com SOP ativo, avaliando o `PaymentToken` — nunca precisa do PAN bruto (ADR-004 revogado: SOP+AF são compatíveis).
- **RA-AF-04:** `void_on_high_risk=yes` deve disparar `void_sale()` automaticamente quando o provider retornar risco alto, antes de expor qualquer confirmação ao comprador.
- **RA-AF-05:** O provider deve ser abstraído — a classe de gateway não deve saber se está falando com CyberSource ou ClearSale; a diferença fica isolada na Risk API/builders.

## 8. Requisitos Arquiteturais Específicos — E-Wallets (spec aprovada, pendente de implementação)

- **RA-EW-01:** Um único gateway WooCommerce (`braspag_ewallet`) deve representar as três carteiras (Apple/Google/Samsung Pay); a carteira específica é detectada em JS por feature-detection e comunicada ao backend via hidden field — evita 3 gateways redundantes.
- **RA-EW-02:** Apenas o modo *encrypted card* é suportado — o payload transporta o token criptografado do SDK da carteira; a descriptografia ocorre no lado Cielo, nunca no plugin.
- **RA-EW-03:** Apple Pay Merchant Validation deve ocorrer via endpoint AJAX autenticado do próprio plugin, nunca expondo credenciais de merchant validation ao browser.

## 9. Trade-offs e Decisões Registradas (ADRs)

| ADR | Decisão | Status |
|---|---|---|
| ADR-001 | Builders de payload via WordPress filters encadeados | Aceito |
| ADR-002 | Sem namespaces PHP | Aceito |
| ADR-003 | Elo/Amex sem 3DS | **Revogado** — Elo suporta 3DS 2.2; Amex permanece sem suporte (limitação de rede) |
| ADR-004 | SOP + Antifraude incompatíveis | **Revogado** — comprovadamente compatíveis |
| ADR-005 | Zero Auth obrigatório antes de tokenizar | Aceito, com fallback gracioso (Amex erro 57, serviço não habilitado) |
| ADR-007 | E-Wallets apenas modo encrypted card | Aceito |
| ADR-008 | Gateway único `braspag_ewallet` para as 3 carteiras | Aceito |

## 10. Riscos Arquiteturais Conhecidos

| Risco | Impacto | Mitigação |
|---|---|---|
| Webhook sem `webhook_secret` configurado | Integridade reduzida, risco de spoofing | Modo permissivo documentado; recomendar fortemente a configuração em produção |
| Divergência entre lógica Blocks (JS) e clássica (PHP) | Comportamento inconsistente entre checkouts | `get_payment_method_data()` centraliza dados; `process_payment()` único reaproveitado |
| Dependência de biblioteca externa `bpmpi.js` (CDN Braspag) | Indisponibilidade externa quebra 3DS | Sem mitigação local — risco aceito, monitorar disponibilidade do CDN |
| E-Wallets: spec aprovada sem código correspondente | Divergência entre documentação e produto real | Sinalizado em todo este pacote; tratar como backlog até merge do código |
