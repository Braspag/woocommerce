# SDD: Antifraude (CyberSource / ClearSale)
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-07-31 | **Autor:** agent-pm
**Tipo:** Software Design Document
**Linkado a:** `antifraude-prd.md`, `antifraude-ard.md`, `plugin-braspag-sdd.md`

---

## 1. Visão Geral

A funcionalidade adiciona um builder de payload (`..._antifraud_builder`) ao gateway `WC_Gateway_Braspag_CreditCard`, seguindo o mesmo padrão de builders encadeados via WordPress filters (ADR-001) já usado para SOP e 3DS 2.2. O nó `Payment.FraudAnalysis{}` é adicionado ao payload de `create_sale()` quando o Antifraude estiver habilitado nas configurações do gateway.

Não há endpoint HTTP adicional: a análise de fraude trafega na mesma requisição `POST /v2/sales/` do Pagador.

---

## 2. Novos Componentes

### 2.1 Builder (filter)

| Filter | Registrado em | Responsabilidade |
|---|---|---|
| `wc_gateway_braspag_pagador_creditcard_antifraud_builder` | `WC_Gateway_Braspag_CreditCard::__construct()` | Monta `Payment.FraudAnalysis{}` completo (Provider, Sequence, SequenceCriteria, TotalOrderAmount, CaptureOnLowRisk, VoidOnHighRisk, Browser, Cart, MerchantDefinedFields, Travel) |

### 2.2 JavaScript (Frontend — Fingerprint)

| Arquivo | Responsabilidade |
|---|---|
| `assets/js/frontend/antifraud-fingerprint.js` | Orchestrator: detecta provider configurado, carrega o script correspondente, coleta o fingerprint, injeta hidden field antes do submit |
| `assets/js/frontend/antifraud-cybersource.js` | Integração com Threatmetrix — monta `session_id` a partir de `ProviderMerchantId` + `ProviderIdentifier` (GUID gerado no client) |
| `assets/js/frontend/antifraud-clearsale.js` | Integração com o script de fingerprint da ClearSale — coleta `session_id` |

### 2.3 Hidden Field

```html
<input type="hidden" name="braspag_antifraud_fingerprint" value="{session_id ou ProviderIdentifier}" />
```

---

## 3. Contrato de API Cielo — Payload FraudAnalysis

### 3.1 Estrutura comum (ambos providers)

```json
{
  "Payment": {
    "FraudAnalysis": {
      "Provider": "Cybersource",
      "Sequence": "AuthorizeFirst",
      "SequenceCriteria": "OnSuccess",
      "TotalOrderAmount": 10000,
      "CaptureOnLowRisk": true,
      "VoidOnHighRisk": true,
      "Browser": {
        "BrowserFingerprint": "{session_id ou ProviderIdentifier}",
        "CookiesAccepted": true
      }
    }
  }
}
```

### 3.2 CyberSource — payload completo (com Cart, MDD, Travel)

```json
{
  "MerchantOrderId": "WC-{order_id}",
  "Customer": {
    "Name": "{billing_first_name} {billing_last_name}",
    "Identity": "{cpf_cnpj}",
    "IdentityType": "CPF",
    "Email": "{billing_email}",
    "Phone": "{billing_phone}",
    "Birthdate": "1991-01-10",
    "BillingAddress": { "Street": "...", "Number": "...", "ZipCode": "...", "Country": "BRA", "District": "..." }
  },
  "Payment": {
    "Type": "CreditCard",
    "Amount": 10000,
    "Installments": 1,
    "Currency": "BRL",
    "Country": "BRA",
    "Capture": false,
    "CreditCard": { "CardNumber": "{token_ou_pan}", "Holder": "...", "ExpirationDate": "...", "SecurityCode": "...", "Brand": "Visa" },
    "FraudAnalysis": {
      "Provider": "Cybersource",
      "Sequence": "AuthorizeFirst",
      "SequenceCriteria": "OnSuccess",
      "TotalOrderAmount": 10000,
      "CaptureOnLowRisk": true,
      "VoidOnHighRisk": true,
      "Browser": {
        "BrowserFingerprint": "{ProviderIdentifier}",
        "CookiesAccepted": true
      },
      "Cart": {
        "IsGift": false,
        "ReturnsAccepted": true,
        "Items": [
          { "Name": "Produto X", "Quantity": 1, "Sku": "SKU-001", "UnitPrice": 10000, "Type": "physical" }
        ]
      },
      "MerchantDefinedFields": [
        { "Id": 1, "Value": "{customer_login_id}" },
        { "Id": 4, "Value": "Web" },
        { "Id": 83, "Value": "{business_segment}" },
        { "Id": 84, "Value": "WooCommerce Braspag" }
      ]
    }
  }
}
```

### 3.3 CyberSource — nó Travel (segmento passagens aéreas, opcional)

```json
{
  "Travel": {
    "JourneyType": "OneWay",
    "DepartureTime": "2026-08-15T10:00:00",
    "Passengers": [
      {
        "Name": "{nome_passageiro}",
        "Identity": "{cpf_passageiro}",
        "TravelLegs": [
          { "Origin": "GRU", "Destination": "GIG", "DepartureDate": "2026-08-15" }
        ]
      }
    ]
  }
}
```

### 3.4 ClearSale — payload

```json
{
  "Payment": {
    "Type": "CreditCard",
    "Amount": 10000,
    "Installments": 1,
    "Capture": false,
    "CreditCard": { "CardNumber": "{token_ou_pan}", "Holder": "...", "ExpirationDate": "...", "SecurityCode": "...", "Brand": "Visa" },
    "FraudAnalysis": {
      "Provider": "ClearSale",
      "Sequence": "AuthorizeFirst",
      "SequenceCriteria": "OnSuccess",
      "TotalOrderAmount": 10000,
      "CaptureOnLowRisk": true,
      "VoidOnHighRisk": true,
      "Browser": {
        "BrowserFingerprint": "{session_id}"
      }
    }
  }
}
```

> ClearSale **não** utiliza `Cart`, `MerchantDefinedFields` nem `Travel` — o builder deve omitir esses nós quando `Provider = "ClearSale"`.

### 3.5 Resposta esperada (sucesso)

```json
{
  "Payment": {
    "PaymentId": "{uuid}",
    "Status": 2,
    "ReturnCode": "00",
    "ReturnMessage": "Successful",
    "FraudAnalysis": {
      "Id": "{uuid-antifraude}",
      "Status": 1,
      "StatusDescription": "Accept",
      "IsRetryTransaction": false,
      "ReplyData": {
        "ProviderTransactionId": "{id-no-provider}"
      }
    }
  }
}
```

**CyberSource — campos adicionais em `ReplyData`:** `AddressInfoCode`, `FactorCode`, `Score`, `BinCountry`, `CardIssuer`, `CardScheme`, `HostSeverity`, `InternetInfoCode`, `IpRoutingMethod`, `ScoreModelUsed`, `CasePriority`.

**ClearSale — campo adicional em `ReplyData`:** `ProviderTransactionId` (ID da transação na ClearSale).

---

## 4. Tabela de Status do Antifraude

| Código | Status | Significado |
|---|---|---|
| 0 | Unknown | Status indefinido |
| 1 | Accept | Baixo risco — transação aceita |
| 2 | Reject | Alto risco — transação rejeitada |
| 3 | Review | Necessita revisão manual |
| 4 | Aborted | Análise abortada |
| 5 | Unfinished | Análise não concluída |

---

## 5. Tabela de MDDs (resumo por grupo — CyberSource)

| Grupo | MDDs | Observação |
|---|---|---|
| Obrigatórios | 1, 4, 9, 83, 84 | Login do cliente, canal de venda, retirada em loja (varejo/cosméticos), segmento de negócio, nome da plataforma |
| Cliente | 1-2, 24-25, 42-43 | Login, tempo de conta, gênero, idade, faixa de renda |
| Transação | 3-8, 28-31 | Parcelas, tentativas de pagamento, comportamento de navegação, troca de cartão |
| Varejo | 9, 21-22, 37 | Retirada em loja, custo de frete, forma de entrega |
| Viagem/Aéreo | 10-20, 68-81 | Detalhes de voo, passageiros, categoria de hotel, frequent flyer |
| Fidelidade | 62-65 | Resgate de pontos, saldo, programa |
| Digital | 66-67 | Recarga de minutos e frequência |
| Customizados | 85-89 | Disponíveis para regras de negócio específicas |

⚠️ Nunca enviar `MerchantDefinedFields[]` com `Value` vazio — omitir o campo do array quando não houver dado disponível. Tabela completa: [Tabela de MDDs](https://docs.cielo.com.br/gateway/reference/tabela-de-mdds).

---

## 6. Fluxos de Estado

### 6.1 AnalyseFirst (análise antes da autorização)

```
process_payment()
    └── builder monta FraudAnalysis{ Sequence: AnalyseFirst }
    └── create_sale() → POST /v2/sales
           └── Cielo aciona Provider ANTES de autorizar
                  ├── Accept/Review → segue para autorização
                  │      ├── Status=2 → order 'processing'
                  │      └── Erro/negada → order 'failed'
                  └── Reject → transação não autorizada → order 'failed'
```

### 6.2 AuthorizeFirst + CaptureOnLowRisk

```
process_payment()
    └── builder monta FraudAnalysis{ Sequence: AuthorizeFirst, CaptureOnLowRisk: true }
    └── Payment.Capture forçado para false
    └── create_sale() → autoriza primeiro
           └── Cielo aciona Provider depois (conforme SequenceCriteria)
                  ├── FraudAnalysis.Status = Accept → captura automática → order 'processing'
                  ├── FraudAnalysis.Status = Review → order 'on-hold' (aguarda revisão manual)
                  │      └── webhook notifica "aceita" → captura automática → order 'processing'
                  └── FraudAnalysis.Status = Reject → order 'failed' (nenhuma captura)
```

### 6.3 AuthorizeFirst + VoidOnHighRisk

```
process_payment()
    └── builder monta FraudAnalysis{ Sequence: AuthorizeFirst, VoidOnHighRisk: true }
    └── Payment.Capture forçado para false
    └── create_sale() → autoriza primeiro
           └── Cielo aciona Provider depois
                  ├── FraudAnalysis.Status = Accept → order 'processing' (aguarda captura manual/RF-10)
                  ├── FraudAnalysis.Status = Review → order 'on-hold'
                  │      └── webhook notifica "rejeitada" → void automático → order 'cancelled'
                  └── FraudAnalysis.Status = Reject → void automático imediato → order 'cancelled'
```

---

## 7. Estrutura do Builder

```php
// Registrado em WC_Gateway_Braspag_CreditCard::__construct()
add_filter('wc_gateway_braspag_pagador_creditcard_antifraud_builder', array($this, 'build_antifraud_payload'), 10, 3);

public function build_antifraud_payload(array $payment, WC_Order $order, string $fingerprint): array
{
    if ('yes' !== $this->antifraud_enabled) {
        return $payment;
    }

    $payment['FraudAnalysis'] = array(
        'Provider'          => $this->antifraud_provider,       // 'Cybersource' | 'ClearSale'
        'Sequence'          => $this->antifraud_sequence,       // 'AnalyseFirst' | 'AuthorizeFirst'
        'SequenceCriteria'  => $this->antifraud_sequence_criteria, // 'OnSuccess' | 'Always'
        'TotalOrderAmount'  => $order->get_total() * 100,
        'CaptureOnLowRisk'  => 'yes' === $this->antifraud_capture_on_low_risk,
        'VoidOnHighRisk'    => 'yes' === $this->antifraud_void_on_high_risk,
        'Browser'           => array(
            'BrowserFingerprint' => $fingerprint,
            'CookiesAccepted'    => true,
        ),
    );

    if ('Cybersource' === $this->antifraud_provider) {
        $payment['FraudAnalysis']['Cart'] = $this->build_cart_node($order);
        $payment['FraudAnalysis']['MerchantDefinedFields'] = $this->build_mdd_fields($order);

        if ($this->is_travel_segment()) {
            $payment['FraudAnalysis']['Travel'] = $this->build_travel_node($order);
        }
    }

    // CaptureOnLowRisk / VoidOnHighRisk exigem captura diferida
    if ($payment['FraudAnalysis']['CaptureOnLowRisk'] || $payment['FraudAnalysis']['VoidOnHighRisk']) {
        $payment['Capture'] = false;
    }

    return $payment;
}

private function build_mdd_fields(WC_Order $order): array
{
    $fields = array();
    $candidates = array(
        1  => $order->get_customer_id(),
        4  => 'Web',
        83 => $this->business_segment,
        84 => 'WooCommerce Braspag',
    );

    foreach ($candidates as $id => $value) {
        if ('' !== (string) $value) { // nunca enviar campo vazio
            $fields[] = array('Id' => $id, 'Value' => (string) $value);
        }
    }

    return $fields;
}
```

---

## 8. Segurança

- `Payment.FraudAnalysis.Browser.BrowserFingerprint` mascarado nos logs via `WC_Braspag_Logger`: `{primeiros_6}****{últimos_4}`
- Valores de `MerchantDefinedFields[]` que contenham dado pessoal (ex.: login do cliente) mascarados no log conforme `WC_Braspag_Logger`
- `FraudAnalysis.Id` e `ProviderTransactionId` da resposta salvos em `order_meta` (`_braspag_antifraud_id`, `_braspag_antifraud_provider_transaction_id`) para consulta/suporte, sem dado sensível
- Nonce WP padrão herdado do gateway base (`process_payment()`)

---

## 9. Testes

### 9.1 Unitários

| Teste | Arquivo | Cenários |
|---|---|---|
| `AntifraudPayloadBuilderTest` | `tests/unit/AntifraudPayloadBuilderTest.php` | CyberSource: payload com Cart+MDD; MDD vazio omitido; Travel incluído quando segmento=viagem; ClearSale: payload sem Cart/MDD/Travel; `CaptureOnLowRisk` força `Capture=false`; fingerprint mascarado no log |
| `AntifraudSequenceMatrixTest` | `tests/unit/AntifraudSequenceMatrixTest.php` | Todas as combinações de `Sequence`/`SequenceCriteria` geram payload correto |
| `AntifraudAdminSettingsTest` | `tests/unit/AntifraudAdminSettingsTest.php` | Campos salvos/lidos corretamente para ambos providers; `CaptureOnLowRisk`/`VoidOnHighRisk` desabilitados no admin quando `Sequence != AuthorizeFirst` |

### 9.2 Integração

| Teste | Arquivo | Cenários |
|---|---|---|
| `AntifraudIntegrationTest` | `tests/integration/AntifraudIntegrationTest.php` | POST completo CyberSource com `FraudAnalysis.Status=1` (Accept) → order processing; `Status=2` (Reject) → order failed; `Status=3` (Review) → order on-hold; ClearSale equivalente; captura automática via webhook após Review→Accept; void automático via webhook após Review→Reject |

### 9.3 Estratégia de Mock

- `pre_http_request` filter intercepta chamadas à Cielo API (mesmo padrão do `plugin-braspag-sdd.md` §8.3)
- `$GLOBALS['_braspag_test_options']` simula settings de Antifraude do lojista
- Execução: `ddev exec vendor/bin/phpunit --filter Antifraud`

---

## 10. Dependências de Arquivos Existentes (não modificar sem aprovação tech-lead)

| Arquivo | Motivo da dependência |
|---|---|
| `class-wc-gateway-braspag-creditcard.php` | Ponto de registro do novo filter `..._antifraud_builder` |
| `class-wc-braspag-pagador-api.php` | `create_sale()` reutilizado sem modificações |
| `class-wc-braspag-webhook-handler.php` | ChangeType 3 (antifraude) já mapeado no doc mestre RF-08 — usado para captura/void automáticos via revisão manual |
| `class-wc-braspag-logger.php` | Mascaramento de `BrowserFingerprint` e MDDs |
| `class-wc-braspag-order-handler.php` | Sincronização de status de pedido após resultado do Antifraude |

---

## 11. Referências

- [Análise de Fraude — Visão Geral](https://docs.cielo.com.br/ecommerce-cielo/docs/analise-de-fraude)
- [Antifraude CyberSource — Referência API](https://docs.cielo.com.br/ecommerce-cielo/reference/af-cybersource)
- [Antifraude ClearSale — Referência API](https://docs.cielo.com.br/ecommerce-cielo/reference/af-clearsale)
- [Fingerprint CyberSource](https://docs.cielo.com.br/risco/docs/fingerprint-cybersource)
- [Fingerprint ClearSale](https://docs.cielo.com.br/risco/docs/fingerprint-clearsale)
- [Tabela de MDDs](https://docs.cielo.com.br/gateway/reference/tabela-de-mdds)
- [Lista de Status do Antifraude](https://docs.cielo.com.br/ecommerce-cielo/reference/lista-de-status-do-antifraude)

---

*Atualizar ao implementar: adicionar número de linhas, referências de código, e resultados de testes.*
