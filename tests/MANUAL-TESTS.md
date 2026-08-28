# Manual de Testes — WooCommerce Braspag Plugin

**Versão do Plugin:** 2.3.5.43+  
**Data:** 2026-05-17  
**Ambiente:** Sandbox Cielo

---

## Pré-requisitos

| Requisito | Detalhe |
|-----------|---------|
| Credenciais Cielo Sandbox | MerchantId + MerchantKey do portal Cielo |
| Plugin Extra Checkout Fields for Brazil | Instalado e ativo |
| Produto de teste no WooCommerce | Qualquer produto simples, preço R$ 10,00 |
| WP_DEBUG ativo | `define('WP_DEBUG', true)` no wp-config.php |
| WC Logs visíveis | WooCommerce → Status → Logs → braspag |
| ngrok ou Serveo | Para receber webhooks no sandbox |

---

## Ambiente de Configuração

1. Acesse **WooCommerce → Configurações → Pagamentos → Braspag**
2. Configure:
   - Modo Teste: **Sim**
   - MerchantId Sandbox: (fornecido pela Cielo)
   - MerchantKey Sandbox: (fornecido pela Cielo)
3. Para webhooks: configure a URL `https://SEU_NGROK/wc-api/braspag_webhook` no portal Cielo

---

## Cartões de Teste Cielo (3DS 2.2)

| Número | Bandeira | Cenário |
|--------|----------|---------|
| `4000000000002503` | Visa | Challenge — sucesso |
| `4000000000002370` | Visa | Challenge — falha |
| `4000000000002701` | Visa | Frictionless — sucesso |
| `4000000000002024` | Visa | Data Only (sem challenge) |
| `5200000000002151` | Mastercard | Challenge — sucesso |
| `5200000000002490` | Mastercard | Challenge — falha |
| `5200000000002235` | Mastercard | Frictionless — sucesso |
| `5200000000002805` | Mastercard | Data Only (sem challenge) |
| `6505290000002190` | Elo | Challenge — sucesso |
| `6505290000002000` | Elo | Frictionless — sucesso |

**CVV:** 123 | **Validade:** 12/30 | **Nome:** TESTE BRASPAG

---

## 1. Cartão de Crédito — Classic Checkout

### 1.1 Pagamento básico sem 3DS

- [ ] Adicionar produto ao carrinho → ir ao checkout clássico
- [ ] Selecionar "Cartão de Crédito Braspag"
- [ ] Preencher com qualquer número válido (Luhn), CVV 123, validade 12/30
- [ ] Confirmar pedido → status deve ir para `processing`
- [ ] Verificar log: `PaymentId` presente, `Status=2`

### 1.2 3DS Frictionless (Visa `4000000000002701`)

- [ ] Usar cartão `4000000000002701`
- [ ] Não deve aparecer nenhum modal de challenge
- [ ] Pedido finalizado → status `processing`
- [ ] Log: `ExternalAuthentication.ECI` preenchido

### 1.3 3DS Challenge (Visa `4000000000002503`)

- [ ] Usar cartão `4000000000002503`
- [ ] Modal de autenticação deve aparecer
- [ ] Completar o desafio no modal
- [ ] Pedido finalizado → status `processing`

### 1.4 3DS Challenge — Falha (Visa `4000000000002370`)

- [ ] Usar cartão `4000000000002370`
- [ ] Modal aparece, autenticação falha
- [ ] Comportamento conforme configuração `authorize_on_failure`:
  - `yes`: pedido prossegue sem 3DS
  - `no`: pedido rejeitado com mensagem de erro

### 1.5 3DS com Elo (após revogação ADR-003)

- [ ] Usar cartão Elo `6505290000002190`
- [ ] 3DS deve ser executado (Elo agora suporta 3DS 2.2)
- [ ] Modal de challenge deve aparecer se aplicável
- [ ] Pedido finalizado com sucesso

### 1.6 Cartão recusado

- [ ] Usar número inválido ou cartão sem saldo
- [ ] Mensagem de erro exibida ao comprador (sem `%s` literal)
- [ ] Pedido vai para status `failed`
- [ ] Log: `ProviderReturnCode` e `ProviderReturnMessage` registrados

### 1.7 Salvar cartão + usar cartão salvo

- [ ] Marcar "Salvar cartão" → finalizar pedido
- [ ] Ir em **Minha Conta → Métodos de Pagamento** → cartão listado
- [ ] Novo pedido → selecionar cartão salvo → finalizar
- [ ] CVV solicitado novamente (se `cvv_required_for_saved_card = yes`)

### 1.8 SOP ativado

- [ ] Ativar SOP em **Configurações → Braspag → Silent Order Post**
- [ ] Abrir DevTools → Network
- [ ] Preencher cartão no checkout
- [ ] Verificar: request para o servidor da loja NÃO contém PAN completo
- [ ] Payload do pedido contém `PaymentToken` (temporário)

### 1.9 SOP + 3DS combinados (após revogação ADR-004)

- [ ] SOP ativo + 3DS ativo
- [ ] Usar Visa `4000000000002701` (frictionless)
- [ ] Pedido deve ter `PaymentToken` E `ExternalAuthentication`
- [ ] Finalizar com sucesso

### 1.10 SOP + Antifraude (após revogação ADR-004)

- [ ] SOP ativo + Antifraude ativo (Cybersource)
- [ ] Finalizar pedido
- [ ] `FraudAnalysis` deve aparecer no payload (verificar log)
- [ ] Pedido aprovado com baixo risco

---

## 2. Cartão de Débito — Classic Checkout

### 2.1 3DS obrigatório (Visa `4000000000002701`)

- [ ] Selecionar "Cartão de Débito Braspag"
- [ ] 3DS deve ser executado automaticamente (obrigatório)
- [ ] Frictionless: pedido finalizado diretamente

### 2.2 3DS Challenge

- [ ] Usar Mastercard `5200000000002151`
- [ ] Modal de desafio deve aparecer
- [ ] Completar → pedido finalizado

### 2.3 Tentativa sem 3DS

- [ ] Desativar 3DS temporariamente nas configurações
- [ ] Tentar débito → deve ser bloqueado (3DS é obrigatório para débito)

---

## 3. BIN Query

### 3.1 Detecção automática de bandeira

- [ ] No checkout, digitar 6 primeiros dígitos do cartão
- [ ] Bandeira deve ser detectada e ícone exibido automaticamente
- [ ] Testar com: Visa (4111), Mastercard (5200), Elo (6505), Hipercard (6062)

### 3.2 Cartão dual (crédito + débito)

- [ ] Digitar BIN de cartão dual
- [ ] Aviso deve ser exibido: "Este cartão pode ser usado como crédito ou débito"

### 3.3 Cartão internacional

- [ ] Digitar BIN de cartão internacional (ForeignCard=true)
- [ ] Aviso deve ser exibido se configurado

### 3.4 Serviço não habilitado (erro 323)

- [ ] BIN Query não habilitada na Cielo
- [ ] Checkout deve funcionar normalmente (sem bloquear)
- [ ] Log: erro 323 registrado como warning, não como falha crítica

---

## 4. Velocity

### 4.1 Transação normal

- [ ] Finalizar pedido normalmente
- [ ] Resposta da Braspag sem campo `VelocityAnalysis` → sem erro
- [ ] Mensagem de erro não contém `%s` literal (BUG-V2 corrigido)

### 4.2 Transação rejeitada por Velocity

- [ ] (Requer conta com Velocity ativo e regras configuradas no portal Cielo)
- [ ] Transação rejeitada por regra de Velocity
- [ ] Mensagem de erro: "Payment processing failed [VelocityAnalysis]: ..." (sem `%s`)
- [ ] Pedido vai para `failed`, nota de pedido registrada

---

## 5. Antifraude — Cybersource

### 5.1 Pedido com risco baixo → aprovado automaticamente

- [ ] Configurar `capture_on_low_risk = yes`
- [ ] Finalizar pedido normal
- [ ] Pedido deve ir direto para `processing` (captura automática)

### 5.2 Pedido suspenso (risco médio)

- [ ] Pedido vai para status configurado em `antifraud_review_order_status`
- [ ] Aguarda revisão manual no portal Cielo
- [ ] Após aprovação → webhook atualiza para `processing`

### 5.3 Pedido de alto risco → void automático

- [ ] Configurar `void_on_high_risk = yes`
- [ ] Pedido de alto risco: autorizado e depois anulado automaticamente
- [ ] Status final: `cancelled`
- [ ] Nota de pedido registrada com motivo

### 5.4 Antifraude + 3DS simultâneo

- [ ] Antifraude ativo + 3DS ativo
- [ ] Finalizar com Visa `4000000000002701`
- [ ] Ambos executados sem conflito
- [ ] Log: `ExternalAuthentication` + `FraudAnalysis` no mesmo payload

---

## 6. PIX

### 6.1 Geração de QR Code

- [ ] Selecionar PIX → finalizar pedido
- [ ] QR Code exibido na página de obrigado
- [ ] Código PIX copia-e-cola disponível
- [ ] Expiração de 2 horas exibida ao comprador

### 6.2 Confirmação via Webhook (simular com curl)

```bash
curl -X POST http://localhost:8080/wc-api/braspag_webhook \
  -H "Content-Type: application/json" \
  -d '{"PaymentId":"SEU_PAYMENT_ID","ChangeType":"1"}'
```

- [ ] Pedido muda de `on-hold` para `processing`

### 6.3 Expiração do PIX

- [ ] Aguardar o cron de expiração (`PixCancelOrders`)
- [ ] Pedido expirado muda para `cancelled`

---

## 7. Boleto

### 7.1 Geração de Boleto

- [ ] Selecionar Boleto → finalizar pedido
- [ ] Link do boleto na página de obrigado + e-mail
- [ ] Número de barras válido

### 7.2 Confirmação via Webhook

```bash
curl -X POST http://localhost:8080/wc-api/braspag_webhook \
  -H "Content-Type: application/json" \
  -d '{"PaymentId":"SEU_PAYMENT_ID","ChangeType":"1"}'
```

- [ ] Pedido muda de `on-hold` para `processing`

---

## 8. Webhook — Todos os ChangeTypes

### Simular via curl

```bash
# Base: substitua SEU_PAYMENT_ID e a URL
WEBHOOK_URL="http://localhost:8080/wc-api/braspag_webhook"
PID="SEU_PAYMENT_ID"

# ChangeType 1 — Mudança de status
curl -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" \
  -d "{\"PaymentId\":\"$PID\",\"ChangeType\":\"1\"}"

# ChangeType 3 — Mudança de status de antifraude
curl -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" \
  -d "{\"PaymentId\":\"$PID\",\"ChangeType\":\"3\"}"

# ChangeType 7 — Notificação de chargeback
curl -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" \
  -d "{\"PaymentId\":\"$PID\",\"ChangeType\":\"7\"}"

# ChangeType 8 — Alerta de fraude
curl -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" \
  -d "{\"PaymentId\":\"$PID\",\"ChangeType\":\"8\"}"

# ChangeType 25 — Reversão parcial (NOVO)
curl -X POST "$WEBHOOK_URL" -H "Content-Type: application/json" \
  -d "{\"PaymentId\":\"$PID\",\"ChangeType\":\"25\"}"
```

### Checklist

| ChangeType | Evento | Resultado esperado |
|-----------|--------|--------------------|
| 1 | Mudança de status | Order atualizada via query |
| 3 | Antifraude | Order atualizada |
| 7 | Chargeback | Nota adicionada ao pedido |
| 8 | Alerta de fraude | Nota adicionada ao pedido |
| 25 | Reversão parcial | Refund parcial registrado |

- [ ] HMAC inválido → rejeitado com 401
- [ ] JSON inválido → rejeitado com 400
- [ ] Webhook duplicado → ignorado (idempotência)
- [ ] ChangeType inválido (ex: 99) → rejeitado

---

## 9. Checkout Blocks

Repetir os mesmos cenários do **Classic Checkout** nos Blocks:

- [ ] Acessar checkout com tema que usa WooCommerce Blocks (ex: Storefront + Blocks)
- [ ] Cartão de Crédito no Blocks — pagamento básico
- [ ] Cartão de Crédito no Blocks — 3DS frictionless
- [ ] Cartão de Crédito no Blocks — SOP
- [ ] Cartão de Débito no Blocks — 3DS obrigatório
- [ ] PIX no Blocks — QR Code
- [ ] Boleto no Blocks — geração
- [ ] CPF/CNPJ: Pessoa Física (CPF obrigatório)
- [ ] CPF/CNPJ: Pessoa Jurídica (CNPJ + Razão Social obrigatórios)

---

## 10. O que NÃO é possível automatizar

| Cenário | Motivo |
|---------|--------|
| 3DS challenge real | Interação com modal do banco emissor (iframe externo) |
| Redirecionamento bancário de débito em produção | Requer banco real |
| Scan de QR Code PIX | Requer app de banco real |
| Aprovação manual de antifraude | Requer acesso ao portal Cielo |
| Pagamento em produção | Requer cartão real e dinheiro real |

---

## 11. Configuração de Ambiente para PHPUnit

As extensões PHP necessárias para rodar os testes não estão instaladas por padrão no WSL:

```bash
# Ubuntu/Debian
sudo apt-get install php-xml php-mbstring php-dom

# Verificar extensões
php -m | grep -E "dom|mbstring|xml"

# Rodar os testes
cd wp-content/plugins/woocommerce-braspag-dev
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

## 12. Configuração para Jest (JS)

```bash
cd wp-content/plugins/woocommerce-braspag-dev
npm install
npm run test:js
```

## 13. Configuração para Playwright (E2E)

```bash
cd wp-content/plugins/woocommerce-braspag-dev
npm install @playwright/test
npx playwright install

# Configurar variáveis
export WP_BASE_URL=http://localhost:8080
export WP_ADMIN_USER=admin
export WP_ADMIN_PASSWORD=admin

# Rodar
npx playwright test
npx playwright test tests/e2e/classic-checkout/
```
