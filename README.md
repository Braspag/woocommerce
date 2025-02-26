# WooCommerce

> Módulo para integração da plataforma WooCommerce com o Gateway da Braspag.

![GitHub release (with filter)](https://img.shields.io/github/v/release/Braspag/woocommerce) ![GitHub last commit (branch)](https://img.shields.io/github/last-commit/Braspag/woocommerce/main) ![GitHub issues](https://img.shields.io/github/issues/Braspag/woocommerce)

## Documentação

Para acessar a nossa documentação completa do Módulo WooCommerce, [clique aqui](https://braspag.github.io//tutorial/modulo-woo-commerce)

## Instalação

**ATENÇÃO**: Siga os passos da nossa documentação para uma boa implementação no seu projeto.

### Requisitos mínimos obrigatórios

- Conta criada na Braspag com um Token gerado
- Serviços habilitados na sua conta Braspag
- **Wordpress** versão 5.3.2 ou maior
- **Woocomerce** versão 4.0.0 ou maior
- **Plugin Requerido Brazilian Market on WooCommerce**

> É necessária a instalação desse plugin no WordPress, para adicionar recursos de preenchimento de informações pessoais do cliente. [clique aqui](https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil)

### Versões Suportadas

#### versão mínima suportada

- Wordpress: versão 5.3.2 | WooCommerce: versão 4.0.0

#### versão testadas

- Wordpress: versão 5.7 | WooCommerce: versão 5.1.0
- Wordpress: versão 6.2.2 | WooCommerce: versão 7.9.0

#### Beta (Em testes)
- Wordpress: versão 6.7.2 | WooCommerce: versão 9.7.0

### Pré-instalação

#### 1º - Download do Plug-in

Primeiramente deve-se acessar [este endereço](https://github.com/Braspag/woocommerce/tags) e fazer o donwload do plugin.

#### 2º - Instalação do Plug-in

Siga o passo a passo em nossa documentação para instalar nosso plug-in. [clique aqui](https://braspag.github.io//tutorial/modulo-woo-commerce#instala%C3%A7%C3%A3o-no-wordpress)

#### 3º - Ativação do Plug-in

Siga o passo a passo em nossa documentação para instalar nosso plug-in. [clique aqui](https://braspag.github.io//tutorial/modulo-woo-commerce#instala%C3%A7%C3%A3o-no-wordpress)

#### 4º - Configuração do Plug-in

Siga nossas intruções para realizar as configurações gerais do módulo da Braspag. [clique aqui](https://braspag.github.io//tutorial/modulo-woo-commerce#configura%C3%A7%C3%A3o)

## Funcionalidades

### Métodos de pagamento disponíveis

#### Credit Card

- Cielo: Visa, Master, Amex, Elo, Aura, JCB, Diners, Discover
- Cielo 3.0: Visa, Master, Amex, Elo, Aura, JCB, Diners, Discover, Hipercard, Hiper
- Redecard: Visa, Master, Hipercard, Hiper, Diners
- Rede2: Visa, Master, Hipercard, Hiper, Diners, Elo, Amex
- Getnet: Visa, Master, Elo, Amex
- GlobalPayments: Visa, Master
- Stone: Visa, Master, Hipercard, Elo
- FirstData: Visa, Master, Cabal
- Sub1: Visa, Master, Diners, Amex, Discover, Cabal, Naranja e Nevada
- Banorte: Visa, Master, Carnet
- Credibanco: Visa, Master, Diners, Amex, Credential
- Transbank: Visa, Master, Diners, Amex
- Rede Sitef: Visa, Master, Hipercard, Diners
- Cielo Sitef: Visa, Master, Amex, Elo, Aura, JCB, Diners, Discover
- Santander: Visa, Master
- Safra2: Visa, Master, Hipercard, Elo, Amex

_Funcionalidades_

- Checkout Card View
- Authorize Only
- Authorize and Capture
- Authentication 3DS 2.0
- Anti Fraud
- Credit Card Token

#### Credit Card JustClick

A função “JustClick” funcionará apenas com a contratação do serviço do Cartão Protegido da Braspag. Entre em contato conosco através do email para mais detalhes.

_Funcionalidades_

- Authorize Only
- Authorize and Capture

#### Debit Card

- Cielo: Visa, Master
- Cielo 3.0: Visa, Master
- FirstData: Visa, Master
- GlobalPayments: Visa, Master

_Funcionalidades_

- Authenticate 3DS 2.0
- Checkout Card View

#### Boleto

- Braspag
- Bradesco
- Banco do Brasil
- Itau Shopline
- Itau
- Santander
- Caixa
- Citi Bank
- Bank Of America

_Funcionalidades_

- Instruções para o consumidor
- Instruções para o Banco
- Dias para expiração

## Suporte

Se você não conseguiu encontrar sua resposta em nossa documentação, [entre em contato conosco.](https://github.com/Braspag)

## Autores

- [@braspag](https://github.com/Braspag)

## Licença

> Este plug-in pode ser usado por todos desde que siga as normas da licença GNU General Public License v2.
[GPLv2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
