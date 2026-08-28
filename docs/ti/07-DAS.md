# DAS — Documento de Arquitetura de Software
**Produto:** Braspag for WooCommerce Oficial · **Versão:** 1.0 · **Data:** 2026-08-26

## 1. Introdução

Este DAS consolida a visão arquitetural do plugin sob a ótica de atributos de qualidade, visões arquiteturais (lógica, de processo, de implantação) e racional de decisão — complementando HLA (visão executiva) e C4 (visão em camadas de zoom).

## 2. Objetivos e Restrições Arquiteturais

- Integração de pagamento certificada com Braspag/Cielo dentro do ecossistema WordPress/WooCommerce.
- Restrição de plataforma: PHP 7.4+, sem namespaces, sem SQL direto (ver [02-ARD.md](02-ARD.md)).
- Restrição de segurança: aderência a PCI-DSS SAQ A/A-EP via SOP — o plugin nunca deve se tornar responsável por armazenar PAN.

## 3. Visão Lógica

Ver [03-SDD.md](03-SDD.md) §2 (mapa de componentes) e [06-C4-MODEL.md](06-C4-MODEL.md) (Nível 2/3). Resumo por camada:

1. Gateways (`WC_Gateway_Braspag_*`) — um por método de pagamento, todos herdam de `WC_Braspag_Payment_Gateway`.
2. Clientes de API (`WC_Braspag_*_API`) — um por serviço externo Braspag.
3. Serviços de domínio — tokens, logger, customer, order handler, exceptions.
4. Blocks — camada React que espelha os gateways clássicos via `get_payment_method_data()`.
5. Admin — telas de configuração via WooCommerce Settings API.

## 4. Visão de Processo (runtime)

| Processo | Síncrono/Assíncrono | Descrição |
|---|---|---|
| Checkout Cartão de Crédito/Débito/JustClick | Síncrono | Resposta da Braspag recebida na mesma requisição HTTP do checkout |
| Checkout PIX/Boleto | Assíncrono | Pedido fica `on-hold`; confirmação chega via webhook em requisição HTTP separada, iniciada pela Braspag |
| Autenticação 3DS 2.2 | Síncrono, com etapa client-side | JS carrega `bpmpi.js`, pode exibir modal de challenge antes do POST final do checkout |
| Renovação de OAuth token | Assíncrono/sob demanda | Disparado lazy, na primeira chamada MPI/Risk sem token cacheado válido, ou em resposta a 401 |
| Cancelamento de PIX expirado | Assíncrono, agendado | Cron job `PixCancelOrders` roda periodicamente via WP-Cron |

## 5. Visão de Implantação

```
Servidor de Hospedagem WordPress
  └── PHP-FPM / Apache/Nginx
        └── WordPress Core
              └── WooCommerce
                    └── Plugin Braspag for WooCommerce (este pacote)
                          ├── Chamadas HTTPS outbound → *.braspag.com.br (Pagador/MPI/Risk/OAuth)
                          └── Webhook inbound ← *.braspag.com.br (POST /wc-api/braspag_webhook)
Browser do Comprador
  └── JS de Checkout (Blocks ou clássico)
        ├── bpmpi.js (CDN Braspag) — 3DS
        └── SDKs de carteira (Apple Pay JS, Google Pay JS, Samsung Pay JS) — quando E-Wallets implementado
```

Não há infraestrutura própria adicional (filas, workers, bancos externos) — o plugin roda inteiramente dentro do processo PHP da requisição WordPress, exceto o cron de expiração de PIX (WP-Cron, dentro do mesmo processo/host).

## 6. Atributos de Qualidade e Táticas

| Atributo | Tática Arquitetural Aplicada |
|---|---|
| Segurança | SOP (dados de cartão nunca no servidor do lojista); mascaramento de log; nonce WP; HMAC no webhook |
| Extensibilidade | Builders via WordPress filters encadeados (ADR-001); troca de provider de antifraude sem alterar gateway |
| Confiabilidade | Webhook idempotente; retry com backoff exponencial só para erros transitórios (5xx/timeout) |
| Performance | Timeout de 30s; cache de token OAuth via `wp_cache`; nenhuma chamada síncrona bloqueante além do necessário |
| Manutenibilidade | Convenção de nomes rígida (`WC_Braspag_*`); um arquivo por classe; specs versionadas antes do código |
| Compatibilidade | HPOS via WC Payment Token API; coexistência Blocks + clássico; PHP 7.4+ sem recursos modernos incompatíveis |

## 7. Decisões Arquiteturais Relevantes (racional resumido)

Ver tabela completa de ADRs em [02-ARD.md](02-ARD.md) §9. Destaques:

- **ADR-001 (builders via filter):** evita que a lógica de 3DS/antifraude/wallet infle a classe de gateway com condicionais; qualquer combinação (SOP+3DS+AF) é composta, não hardcoded.
- **ADR-002 (sem namespace):** mantém compatibilidade retroativa com hosts em PHP 7.4 e padrões legados de plugins WordPress/WooCommerce amplamente usados por lojistas.
- **ADR-004 revogado (SOP+AF eram tidos como incompatíveis):** documentação oficial da Cielo comprovou compatibilidade — corrigido para permitir ambos simultaneamente, pois a análise de risco opera sobre o token, não o PAN.
- **ADR-008 (gateway único de E-Wallets):** evita triplicar código de gateway para Apple/Google/Samsung Pay quando a única diferença real está no token gerado pelo SDK — a carteira é apenas um parâmetro do payload.

## 8. Riscos e Dívidas Arquiteturais

Ver [02-ARD.md](02-ARD.md) §10 e [04-TDD.md](04-TDD.md) §8. Principal ponto de atenção atual: **E-Wallets tem arquitetura aprovada mas não implementada** — qualquer leitura deste DAS relativa a E-Wallets descreve o design-alvo, não o sistema em produção.
