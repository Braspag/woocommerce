# Documentação de API (OpenAPI/Swagger)
**Arquivo de contrato:** [openapi.yaml](openapi.yaml) · **Versão:** 1.0 · **Data:** 2026-08-26

## 1. Escopo

O plugin não expõe uma API REST rica própria — ele é majoritariamente um **consumidor** das APIs Braspag (Pagador, MPI, Risk, OAuth). O único endpoint HTTP relevante que ele **expõe** para o mundo externo é o **webhook de notificação** (`woocommerce_api_braspag_webhook`), usado pela própria Braspag para confirmar PIX/Boleto e propagar chargebacks/reversões.

Adicionalmente, a spec de E-Wallets (`ewallet-sdd.md`) prevê um endpoint AJAX de *Apple Pay merchant validation* — documentado no OpenAPI como **pendente de implementação**.

## 2. Como visualizar

```bash
npx @redocly/cli preview-docs docs/ti/openapi.yaml
# ou
npx swagger-ui-watcher docs/ti/openapi.yaml
```

## 3. Endpoints documentados

| Endpoint | Método | Status | Descrição |
|---|---|---|---|
| `/wc-api/braspag_webhook` | POST | ✅ Implementado | Recebe notificações da Braspag (status, chargeback, reversão parcial) |
| `/wp-json/braspag/v1/ewallet/apple-pay/validate-merchant` | POST | ⚠️ Spec aprovada, não implementado | Proxy de merchant validation Apple Pay |

## 4. Segurança do Webhook

- Header esperado: `X-Braspag-Signature` (HMAC-SHA256 do corpo, calculado com `webhook_secret`).
- Sem `webhook_secret` configurado → modo permissivo (loga aviso, processa mesmo assim) — ver [02-ARD.md](02-ARD.md) RA-SEG-05.
- Idempotência: pedidos já em status final (`processing`, `cancelled`, `refunded`) são ignorados silenciosamente em nova notificação repetida.

## 5. ChangeTypes suportados

| ChangeType | Significado | Efeito no pedido WooCommerce |
|---:|---|---|
| 1 | Mudança de status da transação | Status 2→`processing`, 3/10→`cancelled`, 11→`refunded` |
| 2 | Recorrência criada | — (fora de escopo v1) |
| 3 | Resultado de antifraude | Pode disparar `void_sale()` se `void_on_high_risk=yes` |
| 4 | Recorrência | — (fora de escopo v1) |
| 5 | Cancelamento negado | Nota adicionada ao pedido |
| 7 | Chargeback | Nota adicionada ao pedido, alerta operação |
| 8 | Alerta de fraude | Nota adicionada ao pedido, alerta operação |
| 25 | Reversão parcial | Registra refund parcial no pedido WooCommerce |

## 6. APIs externas consumidas (referência)

Resumidas na seção `x-external-apis-consumed` do [openapi.yaml](openapi.yaml). Para o contrato oficial e completo (todos os campos de request/response), consultar a documentação oficial Braspag/Cielo — este documento **não** substitui aquela referência, apenas resume os endpoints e finalidades relevantes ao código deste plugin (ver também [03-SDD.md](03-SDD.md) §3).
