# Documentação de TI — Plugin Braspag for WooCommerce

**Versão do plugin:** 2.3.6.3 (BETA) · **Gerado em:** 2026-08-26 · **Atualizado em:** 2026-08-26 (migração de specs)
**Fonte:** consolidado a partir de `docs/specs/*.md` (antes em `docs/specs/`) e leitura da árvore `includes/`

Este diretório reúne a documentação técnica completa do módulo `woocommerce-braspag-dev`, organizada nos padrões de engenharia de software solicitados. Os documentos de especificação por feature (PRD/ARD/SDD detalhados de E-Wallets e Antifraude) continuam sendo a fonte primária em [`../specs/`](../specs/README.md) — os documentos aqui **consolidam e formalizam** essa informação em um pacote de TI único.

> **Local das specs:** `docs/specs/` (índice em [`docs/specs/README.md`](../specs/README.md)) — inclui também specs técnicas por domínio em subpastas (`docs/specs/integrations/`, etc.).
> **Local desta documentação de TI:** `docs/ti/` (este diretório).

| # | Documento | Arquivo |
|---|---|---|
| 1 | PRD — Product Requirements Document | [01-PRD.md](01-PRD.md) |
| 2 | ARD — Architecture Requirements Document | [02-ARD.md](02-ARD.md) |
| 3 | SDD — Software Design Document | [03-SDD.md](03-SDD.md) |
| 4 | TDD — Documento de Projeto Técnico | [04-TDD.md](04-TDD.md) |
| 5 | HLA — High-Level Architecture | [05-HLA.md](05-HLA.md) |
| 6 | Modelo C4 (Contexto/Contêiner/Componente) | [06-C4-MODEL.md](06-C4-MODEL.md) |
| 7 | DAS — Documento de Arquitetura de Software | [07-DAS.md](07-DAS.md) |
| 8 | Blueprint (WordPress Playground) | [08-BLUEPRINT.md](08-BLUEPRINT.md) + `blueprint.json` |
| 9 | Documentação de API (OpenAPI/Swagger) | [09-API-SWAGGER.md](09-API-SWAGGER.md) + `openapi.yaml` |
| 10 | CONTRIBUTING.md | [../../CONTRIBUTING.md](../../CONTRIBUTING.md) |

## Status de implementação por feature (importante)

| Feature | Status no código (`includes/`) | Status na spec |
|---|---|---|
| Cartão de Crédito | ✅ Implementado (`class-wc-gateway-braspag-creditcard.php`) | Aprovado |
| Cartão de Débito | ✅ Implementado | Aprovado |
| PIX | ✅ Implementado | Aprovado |
| Boleto | ✅ Implementado | Aprovado |
| Crédito JustClick | ✅ Implementado | Aprovado |
| Zero Auth | ✅ Implementado (`class-wc-braspag-zero-auth-api.php`) | Aprovado |
| Antifraude (Risk API) | ✅ Implementado (`class-wc-braspag-risk-api.php`) | Aprovado |
| MPI / 3DS 2.2 | ✅ Implementado (`class-wc-braspag-mpi-api.php`) | Aprovado |
| **E-Wallets (Apple/Google/Samsung Pay)** | ⚠️ **Não encontrada classe de gateway/block no código-fonte atual** (`class-wc-gateway-braspag-ewallet.php` não existe em `includes/payment-methods/`) | Spec aprovada (`ewallet-prd.md`, `ewallet-sdd.md`), pendente de implementação |

Onde este pacote descreve E-Wallets, isso reflete o **design aprovado (spec)**, não necessariamente código já mesclado — sinalizado explicitamente em cada documento.
