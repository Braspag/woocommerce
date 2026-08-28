# HLA — High-Level Architecture
**Produto:** Braspag for WooCommerce Oficial · **Versão:** 1.0 · **Data:** 2026-08-26

## 1. Panorama

```
                         ┌─────────────────────────────┐
                         │        Loja WooCommerce      │
                         │  (WordPress + WooCommerce)   │
                         │                               │
   Comprador ───checkout──▶  Plugin Braspag for WooCommerce
                         │   ├─ Gateways (Credit/Debit/  │
                         │   │  PIX/Boleto/JustClick)    │
                         │   ├─ Checkout Blocks (React)  │
                         │   ├─ Camada de API            │
                         │   ├─ Webhook Handler          │
                         │   └─ Admin Settings           │
                         └───────────┬───────────────────┘
                                     │ HTTPS/JSON
                     ┌───────────────┼────────────────────┬───────────────┐
                     ▼               ▼                    ▼               ▼
              Pagador API        MPI API (3DS)        Risk API      OAuth 2.0 Server
             (transações)        (bpmpi.js)        (CyberSource/       (tokens)
                                                       ClearSale)
                     │
                     ▼
           Webhook assíncrono ──▶ woocommerce_api_braspag_webhook ──▶ Order status update
```

## 2. Camadas Lógicas

| Camada | Responsabilidade | Componentes-chave |
|---|---|---|
| **Apresentação** | Checkout (clássico e Blocks), admin settings | Blocks/*, admin/* |
| **Aplicação (Gateways)** | Orquestra validação, montagem de payload, chamada de API, atualização de pedido | `WC_Gateway_Braspag_*` |
| **Domínio/Serviços** | Regras de negócio transversais: tokens, logger, cliente, exceptions | `WC_Braspag_Payment_Tokens`, `Order_Handler`, `Customer`, `Logger` |
| **Integração (API Clients)** | Comunicação HTTP com Braspag | `Pagador_API`, `MPI_API`, `Risk_API`, `OAuth_API`, `Zero_Auth_API` |
| **Infraestrutura WordPress** | Hooks, options, cache, cron | `woocommerce_payment_gateways`, `wp_cache`, `PixCancelOrders` (cron) |

## 3. Fluxos Principais (visão alto nível)

1. **Síncrono (Cartão de Crédito/Débito/JustClick):** checkout → validação → payload (builders) → Pagador API → resposta imediata → pedido atualizado na mesma requisição.
2. **Assíncrono (PIX/Boleto):** checkout → Pagador API → pedido `on-hold` → comprador paga fora do fluxo → Braspag envia webhook → pedido atualizado.
3. **Autenticação forte (3DS 2.2):** ocorre client-side via `bpmpi.js` antes/durante o `process_payment()`, resultado (`CAVV`/`ECI`) incorporado ao payload de venda.
4. **Antifraude:** integrado ao Pagador (mesmo payload) ou via Risk API separada, com sequência configurável (antes ou depois da autorização).
5. **OAuth interno:** MPI e Risk usam Bearer token, obtido/cacheado de forma transparente pela `OAuth_API`; Pagador usa credenciais diretas (não usa OAuth).

## 4. Características de Qualidade Priorizadas

| Atributo | Como é endereçado |
|---|---|
| Segurança (PCI-DSS) | SOP, mascaramento de log, nunca persistir PAN/CVV |
| Extensibilidade | Builders via WordPress filters (ADR-001) |
| Compatibilidade | Sem namespace, PHP 7.4+, HPOS, Blocks + clássico |
| Confiabilidade | Webhook idempotente, retry com backoff, cron de expiração PIX |
| Observabilidade | `WC_Braspag_Logger`/`Client_Logger` com níveis e mascaramento |

## 5. Limites do Sistema (fora do plugin)

- Não gerencia assinaturas recorrentes, split de marketplace, NF-e ou conciliação de extratos.
- Não implementa e-wallets em modo *decrypted card* (exigiria PCI-DSS do lojista).
- Depende de serviços externos Braspag/Cielo (Pagador, MPI, Risk, OAuth) e do CDN `bpmpi.js` — fora do controle do plugin.
