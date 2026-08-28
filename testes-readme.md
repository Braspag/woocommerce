Como funcionam os testes
O plugin usa PHPUnit com dois tipos de suíte:

Testes Unitários (tests/unit/) — sem WordPress
Executam isoladamente com stubs do WooCommerce. Não precisam de banco de dados.

Arquivo	O que testa
CreditCardPayloadBuilderTest	Monta o gateway com disableOriginalConstructor() e testa os 3 builders de payload: SOP (PaymentToken vs CardNumber), 3DS (ExternalAuthentication) e Antifraude (FraudAnalysis). Valida que cada combinação de flags gera o JSON correto para a API Braspag.
WebhookValidationTest	Testa is_valid_request(): rejeita payloads sem PaymentId/ChangeType, valida os 8 ChangeType aceitos, e verifica HMAC em formato hex e base64. Também testa idempotência: pedidos em completed/cancelled não têm status alterado.
BlocksCreditCardTest	Testa WC_Braspag_Blocks_CreditCard em isolamento via $GLOBALS['_braspag_test_options']. Valida is_active(), todas as chaves obrigatórias de get_payment_method_data(), flags de SOP/3DS/antifraude e o script handle wc-braspag-blocks-creditcard.
BlocksBoletoTest / BlocksDebitCardTest / BlocksPixTest	Mesma lógica dos Blocks para Boleto, Débito e PIX: is_active() e dados de configuração.
HelperTest	Guardas básicas de WC_Braspag_Helper: retorna false para parâmetros vazios/nulos.
Testes de Integração (tests/integration/) — requerem WordPress + WooCommerce
Estendem WP_UnitTestCase. Precisam do ambiente WordPress completo.

Arquivo	O que testa
CreditCardProcessPaymentTest	Testa process_payment() com pedido WooCommerce real. Usa pre_http_request para mockar a API Braspag. Verifica que SOP não envia PAN, que 3DS inclui ExternalAuthentication, que 3DS+antifraude coexistem, e que API 400 marca pedido como failed.
WebhookNotificationTest	Cria pedidos reais e processa webhooks. Verifica: PIX pago → processing, boleto liquidado → processing, idempotência para status duplicados, cancelled para pagamento negado (Status=3), e rejeição de assinatura inválida.
BlocksRegistrationTest	Verifica que os 4 métodos (braspag_creditcard, braspag_debitcard, braspag_pix, braspag_boleto) estão registrados no PaymentMethodRegistry do WC Blocks, e que o checkout clássico continua disponível em paralelo.

Explicação dos Testes
7 testes unitários (sem WordPress, rápidos):

CreditCardPayloadBuilderTest — valida os 3 builders de payload: SOP vs CardNumber, 3DS ExternalAuthentication, FraudAnalysis
WebhookValidationTest — validação estrutural do webhook + HMAC-SHA256 (hex e base64) + idempotência
BlocksCreditCardTest — dados de configuração expostos pelo Block React
BlocksBoletoTest / BlocksDebitCardTest / BlocksPixTest — idem para os outros métodos
HelperTest — guardas básicas de null/vazio
3 testes de integração (precisam do WP completo):

CreditCardProcessPaymentTest — process_payment() real com mock HTTP
WebhookNotificationTest — webhooks com pedidos WooCommerce reais
BlocksRegistrationTest — registro no PaymentMethodRegistry do WC Blocks

---

## Diagnóstico do erro 400 — loja.impa.br

Os logs do NotificationSender2 mostram `Status Code 400 - BadRequest` ao notificar `https://loja.impa.br/?wc-api=wc_braspag`. Use os curls abaixo para isolar a causa.

O plugin retorna 400 em dois casos:
- `is_valid_request()` retorna `false` → problema de assinatura/header
- `process_webhook()` lança exceção → pedido não encontrado para o `PaymentId`

### Cenário 1 — Payload exato dos logs (sem header de assinatura)

```bash
curl -v -X POST "https://loja.impa.br/?wc-api=wc_braspag" \
  -H "Content-Type: application/json" \
  -d '{"PaymentId":"2f24e77c-0c8f-419e-9326-644ba6d4cb76","ChangeType":1,"MerchantOrderId":"2283"}'
```

> Replica exatamente o body dos logs da Braspag. Se retornar 400 → avance para o cenário 3.

### Cenário 2 — ChangeType como string (descartar diferença de tipo JSON)

```bash
curl -v -X POST "https://loja.impa.br/?wc-api=wc_braspag" \
  -H "Content-Type: application/json" \
  -d '{"PaymentId":"2f24e77c-0c8f-419e-9326-644ba6d4cb76","ChangeType":"1","MerchantOrderId":"2283"}'
```

> O plugin faz cast para string então não deve fazer diferença, mas confirma.

### Cenário 3 — Com header de assinatura (caso `webhook_secret` ainda esteja preenchido no WP admin)

```bash
# Substitua SEU_SECRET pelo valor que estava configurado em WooCommerce → Pagamentos → Braspag → Webhook Header Value
curl -v -X POST "https://loja.impa.br/?wc-api=wc_braspag" \
  -H "Content-Type: application/json" \
  -H "X-BRASPAG-SIGNATURE: SEU_SECRET_AQUI" \
  -d '{"PaymentId":"2f24e77c-0c8f-419e-9326-644ba6d4cb76","ChangeType":"1","MerchantOrderId":"2283"}'
```

> **Se esse retornar 200** → `webhook_secret` ainda está configurado no WP mesmo após "remover". Acesse WooCommerce → Pagamentos → Braspag e limpe o campo **Webhook Header Value**.

### Cenário 4 — PaymentId fictício para isolar "Order not found"

```bash
curl -v -X POST "https://loja.impa.br/?wc-api=wc_braspag" \
  -H "Content-Type: application/json" \
  -d '{"PaymentId":"00000000-0000-0000-0000-000000000001","ChangeType":"1"}'
```

> Se o cenário 1 retornar 400 mas este também retornar 400 **com a mesma mensagem de log** → o problema é que o `PaymentId` não está associado a nenhum pedido WooCommerce.

### Interpretação dos logs

Após executar os curls, verifique:

```bash
tail -f wp-content/uploads/wc-logs/braspag-*.log
```

| Mensagem no log | Causa |
|----------------|-------|
| `Incoming webhook validation Error` | Assinatura inválida ou payload malformado |
| `Order not found` | `PaymentId` não está salvo em nenhum pedido WooCommerce |

---

# PHP Unit
./vendor/bin/phpunit --testsuite Unit

# PHP Integration (requer WP_TESTS_DIR)
WP_TESTS_DIR=/tmp/wordpress-tests-lib ./vendor/bin/phpunit --testsuite Integration

# JS
npm run test:js
npm run test:js:coverage