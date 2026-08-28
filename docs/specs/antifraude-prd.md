# PRD: Antifraude (CyberSource / ClearSale)
**Versão:** 1.0 | **Status:** Aprovado | **Data:** 2026-07-31 | **Autor:** agent-pm
**Tipo:** Especificação de Funcionalidade
**Linkado a:** `plugin-braspag-prd.md` (documento mestre, RF-04)

---

## Objetivo

Fornecer, dentro do plugin WooCommerce Braspag, a integração com o **Antifraude (solução Braspag)** oferecido pela API E-commerce Cielo, permitindo que o lojista analise o risco de fraude de transações de cartão de crédito antes e/ou depois da autorização, usando um dos dois provedores disponíveis: **CyberSource** ou **ClearSale**.

---

## Usuários-alvo

| Ator | Descrição |
|---|---|
| **Lojista** | Contrata o Antifraude, escolhe o provider e configura os parâmetros de sequência/captura/cancelamento no admin WooCommerce |
| **Comprador** | Tem seus dados transacionais e de dispositivo (fingerprint) analisados de forma transparente durante o checkout |
| **Operação** | Monitora resultados de análise (Accept/Reject/Review), acompanha pedidos em revisão manual e trata chargebacks |

---

## Contexto e Disclaimers Importantes

- O Antifraude é um serviço oferecido **mediante contratação adicional** na Cielo — não está disponível por padrão.
- A análise de fraude está disponível **apenas para transações de cartão de crédito** (não se aplica a débito, PIX ou Boleto).
- ⚠️ **A Cielo não realiza transações garantidas.** A análise de fraude avalia o risco de uma transação, mas o resultado **não vincula cobertura de chargeback** — o lojista permanece responsável pelo risco de chargeback mesmo com uma análise de baixo risco.
- A escolha do provider (CyberSource ou ClearSale) é feita na contratação e é uma configuração de loja, não uma decisão por transação.

---

## Escopo desta versão

**Incluído:**
- Provider CyberSource (com nós `FraudAnalysis`, `Cart`, `MerchantDefinedFields` e `Travel` opcional)
- Provider ClearSale (`FraudAnalysis.Provider = "ClearSale"`)
- Os 6 fluxos de negócio documentados pela Cielo (análise antes/depois da autorização, condicional, captura/cancelamento automático)
- Fingerprint (device identification) para ambos os providers
- Compatibilidade com SOP (Silent Order Post) e 3DS 2.2, já confirmada no doc mestre

**Fora do escopo desta versão:**
- Cartão de débito, PIX, Boleto (Antifraude não se aplica)
- Garantia de chargeback / venda garantida (não oferecida pela Cielo)
- Revisão manual como fluxo de UI própria no plugin (o plugin trata apenas o resultado — Accept/Reject/Review — recebido via resposta síncrona ou webhook)

---

## RF-04 — Antifraude (expande RF-04 do documento mestre)

### RF-04.1 — Seleção de Provider

**Descrição:** O lojista deve poder configurar, no admin WooCommerce, qual provider de Antifraude está contratado: CyberSource ou ClearSale. Essa escolha é enviada em `Payment.FraudAnalysis.Provider` em toda transação de cartão de crédito com análise habilitada.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] Campo `antifraud_provider` no admin com opções `Cybersource` | `ClearSale`
- [ ] `Payment.FraudAnalysis.Provider` preenchido corretamente conforme a configuração
- [ ] Troca de provider não exige alteração de código, apenas configuração

### RF-04.2 — Sequência de Análise (Sequence)

**Descrição:** O lojista escolhe se a análise de fraude ocorre **antes** (`AnalyseFirst`) ou **depois** (`AuthorizeFirst`) da autorização da transação.

**Prioridade:** Alta

**Fluxos suportados (conforme documentação oficial Cielo):**

| Fluxo | Parâmetro |
|---|---|
| Análise antes da autorização | `FraudAnalysis.Sequence = AnalyseFirst` |
| Análise após a autorização | `FraudAnalysis.Sequence = AuthorizeFirst` |
| Análise somente se autorizada | `FraudAnalysis.Sequence = AuthorizeFirst` + `SequenceCriteria = OnSuccess` |
| Análise em qualquer hipótese | `FraudAnalysis.Sequence = AuthorizeFirst` + `SequenceCriteria = Always` |
| Autorização em qualquer hipótese (ignora score) | `FraudAnalysis.Sequence = AnalyseFirst` + `SequenceCriteria = Always` |

**Critérios de Aceitação:**
- [ ] Campo `antifraud_sequence` no admin: `AnalyseFirst` | `AuthorizeFirst`
- [ ] Quando `AnalyseFirst`: transações de alto risco não são enviadas para autorização
- [ ] Quando `AuthorizeFirst`: transação é autorizada antes de ser avaliada pelo Antifraude

### RF-04.3 — Critério de Sequência (SequenceCriteria)

**Descrição:** Controla quando o Antifraude é acionado em relação ao resultado da autorização, evitando custo de análise em transações não autorizadas.

**Prioridade:** Alta

**Critérios de Aceitação:**
- [ ] Campo `antifraud_sequence_criteria` no admin: `OnSuccess` | `Always`
- [ ] `OnSuccess`: Antifraude só é acionado se a transação foi autorizada
- [ ] `Always`: Antifraude é sempre acionado, independente do status de autorização

### RF-04.4 — Captura Automática em Baixo Risco (CaptureOnLowRisk)

**Descrição:** Após a análise de fraude, captura automaticamente uma transação já autorizada quando o resultado for baixo risco. Aplica-se também ao fluxo de revisão manual: quando a Cielo notificar mudança de status para "aceita", a transação é capturada automaticamente.

**Prioridade:** Média

**Pré-requisitos:** `Sequence = AuthorizeFirst` e `Payment.Capture = false`

**Critérios de Aceitação:**
- [ ] Campo `antifraud_capture_on_low_risk` no admin (checkbox), habilitado apenas quando `Sequence = AuthorizeFirst`
- [ ] Quando ativo, `Payment.Capture` é forçado para `false` no payload
- [ ] Captura automática disparada ao receber resultado de baixo risco (síncrono ou via webhook de revisão manual)

### RF-04.5 — Cancelamento Automático em Alto Risco (VoidOnHighRisk)

**Descrição:** Caso a análise de fraude retorne alto risco para uma transação já autorizada ou capturada, ela é imediatamente cancelada ou estornada. Aplica-se também à revisão manual: notificação de status "rejeitada" cancela a transação automaticamente.

**Prioridade:** Média

**Pré-requisitos:** `Sequence = AuthorizeFirst` e `Payment.Capture = false`

**Critérios de Aceitação:**
- [ ] Campo `antifraud_void_on_high_risk` no admin (checkbox), habilitado apenas quando `Sequence = AuthorizeFirst`
- [ ] Cancelamento/estorno automático disparado ao receber resultado de alto risco
- [ ] Pedido WooCommerce atualizado para `cancelled` quando o void é executado

### RF-04.6 — Fingerprint CyberSource (Threatmetrix)

**Descrição:** Identificação digital do dispositivo do comprador, coletada via script Threatmetrix na página de checkout antes da submissão do pagamento.

**Prioridade:** Alta (obrigatório para CyberSource)

**Critérios de Aceitação:**
- [ ] Script Threatmetrix carregado no checkout com `org_id` (sandbox: `1snn5n9w`; produção: `k8vif92e`) e `ProviderMerchantId` (`braspag_<nome-da-loja>`)
- [ ] `ProviderIdentifier` (GUID único por sessão) gerado no frontend
- [ ] Valor enviado em `Payment.FraudAnalysis.Browser.BrowserFingerprint`
- [ ] LGPD: disclosure de coleta de dados do dispositivo incluído na política de cookies do lojista (responsabilidade documentada, não implementação de código)

### RF-04.7 — Fingerprint ClearSale (session_id)

**Descrição:** Identificação digital do dispositivo do comprador, coletada via script próprio da ClearSale na página de checkout.

**Prioridade:** Alta (obrigatório para ClearSale)

**Critérios de Aceitação:**
- [ ] Script de fingerprint da ClearSale executado no checkout, gerando `session_id`
- [ ] Valor do `session_id` enviado em `Payment.FraudAnalysis.Browser.BrowserFingerprint`
- [ ] LGPD: mesmo disclosure de RF-04.6

### RF-04.8 — MDDs (Merchant Defined Data) — exclusivo CyberSource

**Descrição:** Campos numerados (0 a N) usados para armazenar informações adicionais específicas da loja, enviados no nó `MerchantDefinedFields[]`. Ver tabela completa de MDDs.

**Prioridade:** Média

**Critérios de Aceitação:**
- [ ] `MerchantDefinedFields[]` populado com `Id` (número do MDD) e `Value` (dado da loja)
- [ ] MDDs obrigatórios sempre enviados quando aplicável: MDD 1 (login do cliente), MDD 4 (canal de venda), MDD 9 (retirada em loja — segmento varejo/cosméticos), MDD 83 (segmento de negócio), MDD 84 (nome da plataforma integrada)
- [ ] Nenhum MDD enviado com valor vazio (regra da documentação oficial: "não faça o envio de campos vazios")
- [ ] Não aplicável ao provider ClearSale

### RF-04.9 — Nó Travel (venda de passagens aéreas) — exclusivo CyberSource

**Descrição:** Para lojas do segmento de viagens/passagens aéreas, dados adicionais de passageiros e trechos devem ser enviados no nó `Travel`.

**Prioridade:** Baixa (aplicável apenas a lojistas do segmento de viagens)

**Critérios de Aceitação:**
- [ ] Nó `Travel.Passengers[]` populado com `Name`, `Identity`, `TravelLegs[]` (origem, destino, data de partida) quando o segmento da loja for viagens
- [ ] Nó omitido para lojas de outros segmentos
- [ ] Não aplicável ao provider ClearSale

---

## Compatibilidade de Funcionalidades

| Combinação | Compatível? | Observação |
|---|:-:|---|
| Antifraude + SOP | ✅ | Antifraude avalia o `PaymentToken`, não o PAN (ADR-004 revogado no doc mestre confirma compatibilidade) |
| Antifraude + 3DS 2.2 | ✅ | Ambos coexistem no mesmo payload de `create_sale()` |
| Antifraude + Tokenização (CardToken) | ✅ | Funciona normalmente com cartão salvo |
| Antifraude + E-Wallets | ✅ | Payload aceita `FraudAnalysis` junto com `Wallet` (verificar suporte por carteira — ver `ewallet-prd.md`) |
| Antifraude + Débito/PIX/Boleto | ❌ | Análise de fraude disponível apenas para cartão de crédito |
| MDDs / Travel + ClearSale | ❌ | Exclusivos do provider CyberSource |

---

## Critérios Globais de Aceitação

- [ ] RF-04.1 a RF-04.9 implementados e verificáveis por testes automatizados
- [ ] Nenhum valor de `BrowserFingerprint` ou MDD logado sem mascaramento
- [ ] PHPUnit Unit + Integration passando sem falhas
- [ ] Sandbox Cielo: transação de crédito com Antifraude CyberSource e ClearSale retornando `FraudAnalysis.Status` corretamente para os 5 fluxos de negócio documentados
- [ ] Admin settings salvos e lidos corretamente para ambos os providers
- [ ] Disclaimer de "análise de risco não garante cobertura de chargeback" documentado na configuração admin

---

## Referências

- [Análise de Fraude — Visão Geral](https://docs.cielo.com.br/ecommerce-cielo/docs/analise-de-fraude)
- [Antifraude ClearSale — Referência API](https://docs.cielo.com.br/ecommerce-cielo/reference/af-clearsale)
- [Antifraude CyberSource — Referência API](https://docs.cielo.com.br/ecommerce-cielo/reference/af-cybersource)
- [Fingerprint ClearSale](https://docs.cielo.com.br/risco/docs/fingerprint-clearsale)
- [Fingerprint CyberSource](https://docs.cielo.com.br/risco/docs/fingerprint-cybersource)
- [Tabela de MDDs](https://docs.cielo.com.br/gateway/reference/tabela-de-mdds)
- [Lista de Status do Antifraude](https://docs.cielo.com.br/ecommerce-cielo/reference/lista-de-status-do-antifraude)
