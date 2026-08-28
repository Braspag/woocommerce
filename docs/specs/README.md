# Specs de Funcionalidades

> **Local:** esta pasta (`docs/specs/`) — migrada de `docs/specs/`. Specs técnicas por domínio (payment-methods, integrations, features, architecture) também vivem aqui, em subpastas (ex.: `docs/specs/integrations/mpi-v3-migration.spec.md`).
> Documentação de TI consolidada (PRD/ARD/SDD/TDD/HLA/C4/DAS/Blueprint/OpenAPI): [../ti/00-INDEX.md](../ti/00-INDEX.md)

Cada spec vive nesta pasta como `[slug-da-feature].md`.
Metodologia completa: @SPEC_DRIVEN_DEVELOPMENT.md

## Estrutura de uma Spec

```markdown
# Spec: [Nome da Funcionalidade]
**Versão:** 1.0 | **Status:** Rascunho | **Data:** YYYY-MM-DD

## Objetivo
Uma frase descrevendo o que a funcionalidade faz.

## Requisitos Funcionais
- RF-01: ...
- RF-02: ...

## Requisitos Não-Funcionais
- RNF-01: Performance < 200ms
- RNF-02: ...

## Cenários de Teste
- CT-01 (sucesso): ...
- CT-02 (falha): ...

## Arquivos Impactados
- `includes/payment-methods/class-wc-gateway-braspag-[method].php`
- `includes/blocks/class-wc-braspag-blocks-[method].php`

## Decisões Arquiteturais
Link para ADR relevante em `memory/decisions.md` se houver.
```

## Documentos Mestre (entry points para agentes)

| Documento | Arquivo | Descrição |
|---|---|---|
| PRD | [plugin-braspag-prd.md](plugin-braspag-prd.md) | Requisitos de produto, RF/RNF, critérios de aceitação |
| SDD | [plugin-braspag-sdd.md](plugin-braspag-sdd.md) | Design de software, componentes, contratos de API, workflow de agentes |

## Specs de Features

| Feature | PRD | ARD | SDD |
|---|---|---|---|
| E-Wallets (Apple/Google/Samsung Pay) | [ewallet-prd.md](ewallet-prd.md) | — | [ewallet-sdd.md](ewallet-sdd.md) |
| Antifraude (CyberSource/ClearSale) | [antifraude-prd.md](antifraude-prd.md) | [antifraude-ard.md](antifraude-ard.md) | [antifraude-sdd.md](antifraude-sdd.md) |

_(criar `[slug]-ard.md`, `[slug]-prd.md`, `[slug]-sdd.md` antes de implementar qualquer feature)_
