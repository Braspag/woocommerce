# Modelo C4 — Braspag for WooCommerce
**Versão:** 1.0 · **Data:** 2026-08-26

## Nível 1 — Contexto do Sistema

```mermaid
C4Context
title Contexto — Braspag for WooCommerce

Person(comprador, "Comprador", "Cliente final da loja")
Person(lojista, "Lojista", "Administra a loja e o gateway")
Person(operacao, "Operação", "Monitora transações e chargebacks")

System(loja, "Loja WooCommerce", "WordPress + WooCommerce + Plugin Braspag")

System_Ext(pagador, "Braspag Pagador API", "Autoriza, captura, cancela e consulta transações")
System_Ext(mpi, "Braspag MPI (3DS)", "Autenticação forte 3DS 2.2 via bpmpi.js")
System_Ext(risk, "Braspag Risk API", "Antifraude — CyberSource / ClearSale")
System_Ext(oauth, "Braspag OAuth 2.0", "Emite Bearer tokens para MPI e Risk")
System_Ext(wallets, "SDKs de Carteira", "Apple Pay / Google Pay / Samsung Pay (client-side)")

Rel(comprador, loja, "Compra e paga", "HTTPS")
Rel(lojista, loja, "Configura gateways", "Admin WP")
Rel(operacao, loja, "Consulta pedidos/logs")

Rel(loja, pagador, "Cria/captura/cancela venda", "HTTPS/JSON")
Rel(loja, mpi, "Solicita autenticação 3DS", "HTTPS/JSON")
Rel(loja, risk, "Solicita análise de risco", "HTTPS/JSON")
Rel(loja, oauth, "Obtém Bearer token", "HTTPS/Form")
Rel(pagador, loja, "Notifica via webhook", "HTTPS POST")
Rel(comprador, wallets, "Autentica e gera token de pagamento", "SDK nativo")
Rel(wallets, loja, "Envia token criptografado", "JS")
```

## Nível 2 — Contêineres

```mermaid
C4Container
title Contêineres — Plugin Braspag for WooCommerce

Person(comprador, "Comprador")
Person(lojista, "Lojista")

System_Boundary(plugin, "Plugin Braspag for WooCommerce") {
  Container(gateways, "Gateways de Pagamento", "PHP (WC_Payment_Gateway)", "Crédito, Débito, PIX, Boleto, JustClick, E-Wallets*")
  Container(blocks, "Checkout Blocks", "React/JS", "UI de checkout moderna (WooCommerce Blocks)")
  Container(apiclients, "Camada de API", "PHP", "Clientes HTTP: Pagador, MPI, Risk, OAuth, Zero Auth")
  Container(services, "Serviços de Domínio", "PHP", "Tokens, Order Handler, Customer, Logger, Exceptions")
  Container(webhook, "Webhook Handler", "PHP (REST/wc-api)", "Recebe confirmações assíncronas")
  Container(admin, "Admin Settings", "PHP (WC Settings API)", "Configuração de merchant, ambiente, chaves")
}

ContainerDb(wpdb, "WordPress DB", "MySQL", "Options, usermeta, pedidos (via WC_Order/APIs WP)")
System_Ext(braspag, "APIs Braspag", "Pagador / MPI / Risk / OAuth")

Rel(comprador, blocks, "Preenche checkout", "HTTPS")
Rel(comprador, gateways, "Checkout clássico", "HTTPS")
Rel(lojista, admin, "Configura", "Admin WP")
Rel(blocks, gateways, "Reaproveita process_payment()")
Rel(gateways, services, "Usa")
Rel(gateways, apiclients, "Chama")
Rel(apiclients, braspag, "HTTPS/JSON")
Rel(braspag, webhook, "POST assíncrono")
Rel(webhook, services, "Atualiza pedido via Order_Handler")
Rel(services, wpdb, "get_option/usermeta/WC_Order")
Rel(admin, wpdb, "Salva settings")
```

*E-Wallets: contêiner de spec aprovada, código ainda não presente em `includes/`.*

## Nível 3 — Componentes (Gateway de Cartão de Crédito)

```mermaid
C4Component
title Componentes — WC_Gateway_Braspag_CreditCard (process_payment)

Container_Boundary(gw, "WC_Gateway_Braspag_CreditCard") {
  Component(validate, "validate_fields()", "PHP", "Luhn, CVV, validade, nonce")
  Component(builder1, "Builder Base", "WP filter", "braspag_pagador_creditcard_payment_request_builder")
  Component(builder2, "Builder 3DS", "WP filter", "..._auth3ds20_builder")
  Component(builder3, "Builder Antifraude", "WP filter", "..._antifraud_builder")
  Component(process, "process_payment()", "PHP", "Orquestra chamada final")
}

Component(pagadorapi, "WC_Braspag_Pagador_API", "PHP", "create_sale()")
Component(mpiapi, "WC_Braspag_MPI_API", "PHP", "3DS 2.2")
Component(riskapi, "WC_Braspag_Risk_API", "PHP", "Antifraude")
Component(tokens, "WC_Braspag_Payment_Tokens", "PHP", "Salva CardToken")
Component(logger, "WC_Braspag_Logger", "PHP", "Log mascarado")

Rel(validate, builder1, "payload inicial")
Rel(builder1, builder2, "payload + CreditCard{}")
Rel(builder2, builder3, "payload + ExternalAuthentication{}")
Rel(builder3, process, "payload + FraudAnalysis{}")
Rel(process, pagadorapi, "create_sale(payload)")
Rel(process, mpiapi, "consulta resultado 3DS")
Rel(process, riskapi, "consulta risco (se API separada)")
Rel(process, tokens, "salva token se save_card=yes")
Rel(process, logger, "loga resultado (mascarado)")
```

## Nível 4 — Código (opcional, referência)

Ver classes e assinaturas detalhadas em [03-SDD.md](03-SDD.md) §2 e §6 (convenções de nomes). Não repetido aqui para evitar duplicação — nível de código deve ser lido diretamente do PHPDoc de cada classe em `includes/`.
