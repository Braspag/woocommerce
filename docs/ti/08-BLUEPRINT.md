# Blueprint — WordPress Playground
**Versão:** 1.0 · **Data:** 2026-08-26 · **Arquivo:** [blueprint.json](blueprint.json)

## 1. Objetivo

Permitir subir, em segundos, um ambiente WordPress + WooCommerce descartável (navegador ou CLI, via WordPress Playground) já com as dependências do plugin instaladas (`woocommerce`, `woocommerce-extra-checkout-fields-for-brazil`) e o próprio plugin Braspag ativo em modo **sandbox**, para desenvolvimento e demonstração — sem tocar em ambiente de produção.

## 2. Como usar

O `pluginData` do passo `installPlugin` está com um placeholder (`REPLACE_WITH_URL_OR_USE_BUNDLED_ZIP_OF_woocommerce-braspag-dev`), pois este repositório é local e não há uma URL pública confirmada para buscá-lo automaticamente. Duas opções:

### Opção A — Bundle local (recomendado)
```bash
cd /home/williangringo/_work/braspag/bp-lastest/wp-content/plugins
zip -r woocommerce-braspag-dev.zip woocommerce-braspag-dev -x "*.git*" "node_modules/*" "vendor/*" "test-results/*"
```
Mova o zip para uma pasta de bundle junto com `blueprint.json` (mesmo diretório) e troque o resource por:
```json
{ "resource": "bundled", "path": "/woocommerce-braspag-dev.zip" }
```
Depois rode:
```bash
npx @wp-playground/cli server --blueprint=./docs/ti --blueprint-may-read-adjacent-files
```

### Opção B — URL remota
Se o plugin estiver hospedado (ex.: release do GitHub, S3 interno), substitua o placeholder por:
```json
{ "resource": "url", "url": "https://SEU_HOST/caminho/woocommerce-braspag-dev.zip" }
```

## 3. O que o blueprint configura

| Passo | Efeito |
|---|---|
| `preferredVersions` | PHP 7.4, WordPress 6.8 — alinhado ao mínimo suportado pelo plugin |
| `plugins` (shorthand) | Instala e ativa `woocommerce` e `woocommerce-extra-checkout-fields-for-brazil` (dependência declarada no cabeçalho do plugin) |
| `installPlugin` | Instala/ativa o plugin Braspag a partir do zip informado |
| `setSiteOptions` | Define moeda BRL, país padrão BR, nome da loja de demonstração |
| `runPHP` | Pré-popula `woocommerce_braspag_settings` com `environment=sandbox` e chaves de exemplo — **substitua `merchant_key` pelas credenciais de sandbox reais antes de testar transações** |
| `login: true` | Login automático como admin/password |
| `landingPage` | Abre direto na aba de métodos de pagamento do WooCommerce |

## 4. Teste rápido (inline, sem bundle)

Para um teste que não dependa do plugin em si (só a base WordPress+WooCommerce), minifique e abra:
```
https://playground.wordpress.net/#{"$schema":"https://playground.wordpress.net/blueprint-schema.json","preferredVersions":{"php":"7.4","wp":"6.8"},"login":true,"plugins":["woocommerce","woocommerce-extra-checkout-fields-for-brazil"]}
```

## 5. Limitações conhecidas

- Chamadas HTTPS reais à Braspag sandbox dependem de `features.networking: true` (já habilitado) e de credenciais de sandbox válidas — sem isso, os gateways aparecerão mas as transações falharão.
- Cron de expiração de PIX (`PixCancelOrders`) depende do WP-Cron real; o Playground simula isso apenas parcialmente.
