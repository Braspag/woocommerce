# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: blocks-checkout/creditcard-blocks.spec.ts >> Blocks Checkout — Cartão de Crédito >> B1.4 — 3DS com Elo
- Location: tests/e2e/blocks-checkout/creditcard-blocks.spec.ts:60:9

# Error details

```
Test timeout of 30000ms exceeded while running "beforeEach" hook.
```

# Page snapshot

```yaml
- generic [ref=e1]:
  - generic [ref=e2]:
    - banner [ref=e3]:
      - generic [ref=e4]:
        - link "Pular para navegação" [ref=e5] [cursor=pointer]:
          - /url: "#site-navigation"
        - link "Pular para o conteúdo" [ref=e6] [cursor=pointer]:
          - /url: "#content"
        - link "Braspag DEV Local" [ref=e9] [cursor=pointer]:
          - /url: https://bp-lastest.ddev.site/
        - search [ref=e12]:
          - text: 
          - generic [ref=e13]: "Pesquisar por:"
          - searchbox "Pesquisar por:" [ref=e14]
          - button "Pesquisar" [ref=e15] [cursor=pointer]
      - generic [ref=e17]:
        - navigation "Navegação primária" [ref=e18]:
          - list [ref=e20]:
            - listitem [ref=e21]:
              - link "Início" [ref=e22] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/
            - listitem [ref=e23]:
              - link "Carrinho" [ref=e24] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/carrinho/
            - listitem [ref=e25]:
              - link "Finalização de compra" [ref=e26] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/finalizar-compra/
            - listitem [ref=e27]:
              - link "Minha conta" [ref=e28] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/minha-conta/
            - listitem [ref=e29]:
              - link "Página de exemplo" [ref=e30] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/pagina-exemplo/
          - generic:
            - list:
              - listitem [ref=e31]:
                - link "Início" [ref=e32] [cursor=pointer]:
                  - /url: https://bp-lastest.ddev.site/
              - listitem [ref=e33]:
                - link "Carrinho" [ref=e34] [cursor=pointer]:
                  - /url: https://bp-lastest.ddev.site/carrinho/
              - listitem [ref=e35]:
                - link "Finalização de compra" [ref=e36] [cursor=pointer]:
                  - /url: https://bp-lastest.ddev.site/finalizar-compra/
              - listitem [ref=e37]:
                - link "Minha conta" [ref=e38] [cursor=pointer]:
                  - /url: https://bp-lastest.ddev.site/minha-conta/
              - listitem [ref=e39]:
                - link "Página de exemplo" [ref=e40] [cursor=pointer]:
                  - /url: https://bp-lastest.ddev.site/pagina-exemplo/
        - list [ref=e41]:
          - listitem [ref=e42]:
            - link "R$ 18,00 1 item " [ref=e43] [cursor=pointer]:
              - /url: https://bp-lastest.ddev.site/carrinho/
              - generic [ref=e44]: R$ 18,00
              - text: 1 item 
          - listitem
    - navigation "caminho de navegação" [ref=e47]:
      - link " Início" [ref=e48] [cursor=pointer]:
        - /url: https://bp-lastest.ddev.site
      - generic [ref=e49]: / 
      - text: Finalização de compra
    - generic [ref=e51]:
      - main [ref=e53]:
        - article [ref=e54]:
          - heading "Finalização de compra" [level=1] [ref=e56]
          - generic [ref=e57]:
            - generic [ref=e59]:
              - generic [ref=e61]:
                - heading "Resumo do pedido" [level=2] [ref=e63]
                - generic [ref=e64]:
                  - generic [ref=e68]:
                    - generic [ref=e69]:
                      - generic [ref=e70]:
                        - generic [ref=e71]: "1"
                        - generic [ref=e72]: 1 item
                      - img "Camiseta com gola V" [ref=e73]
                    - generic [ref=e74]:
                      - heading "Camiseta com gola V" [level=3] [ref=e75]
                      - generic [ref=e77]: R$ 18,00
                      - paragraph [ref=e80]: Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Vestibulum tortor…
                    - generic [ref=e81]: "Preço total para 1 unidade de Camiseta com gola V: R$ 18,00"
                    - generic [ref=e84]: R$ 18,00
                  - heading "Adicionar cupons" [level=2] [ref=e86]:
                    - button "Adicionar cupons" [ref=e87] [cursor=pointer]:
                      - img [ref=e88]
                      - text: Adicionar cupons
                  - generic [ref=e90]:
                    - generic [ref=e92]:
                      - generic [ref=e93]: Subtotal
                      - generic [ref=e94]: R$ 18,00
                    - generic [ref=e97]:
                      - generic [ref=e98]: Entrega
                      - generic [ref=e99]: Insira o endereço para calcular
                  - generic [ref=e101]:
                    - generic [ref=e102]: Total
                    - generic [ref=e103]: R$ 18,00
                    - paragraph [ref=e105]:
                      - text: Incluindo
                      - generic [ref=e106]: R$ 2,48
                      - text: em impostos
              - form "Finalização de compra" [ref=e108]:
                - group "Informações de contato" [ref=e109]:
                  - generic [ref=e110]: Informações de contato
                  - generic [ref=e112]:
                    - heading "Informações de contato" [level=2] [ref=e113]
                    - link "Acessar" [ref=e114] [cursor=pointer]:
                      - /url: https://bp-lastest.ddev.site/minha-conta/?redirect_to=https%3A%2F%2Fbp-lastest.ddev.site%2Ffinalizar-compra%2F
                  - generic [ref=e116]:
                    - generic [ref=e117]:
                      - textbox "Endereço de e-mail" [ref=e118]: teste@braspag.com.br
                      - generic [ref=e119]: Endereço de e-mail
                    - paragraph [ref=e120]: Você está fazendo o check-out como convidado.
                    - generic [ref=e122] [cursor=pointer]:
                      - checkbox "Crie uma conta com Braspag DEV Local" [ref=e123]
                      - generic [ref=e124]: Crie uma conta com Braspag DEV Local
                - group "Endereço de entrega" [ref=e125]:
                  - generic [ref=e126]: Endereço de entrega
                  - heading "Endereço de entrega" [level=2] [ref=e129]
                  - generic [ref=e130]:
                    - generic [ref=e133]:
                      - generic [ref=e136]:
                        - generic [ref=e137]: País
                        - combobox "País" [ref=e138]:
                          - option "Selecione um país" [disabled]
                          - option "Afeganistão"
                          - option "África do Sul"
                          - option "Albânia"
                          - option "Alemanha"
                          - option "Andorra"
                          - option "Angola"
                          - option "Anguilla"
                          - option "Antártica"
                          - option "Antígua e Barbuda"
                          - option "Arábia Saudita"
                          - option "Argélia"
                          - option "Argentina"
                          - option "Armênia"
                          - option "Aruba"
                          - option "Austrália"
                          - option "Áustria"
                          - option "Azerbaijão"
                          - option "Bahamas"
                          - option "Bangladesh"
                          - option "Barbados"
                          - option "Barém"
                          - option "Belau"
                          - option "Bélgica"
                          - option "Belize"
                          - option "Benim"
                          - option "Bermudas"
                          - option "Bielorrússia"
                          - option "Bolívia"
                          - option "Bonaire, Santo Eustáquio e Saba"
                          - option "Bósnia e Herzegovina"
                          - option "Botsuana"
                          - option "Brasil" [selected]
                          - option "Brunei"
                          - option "Bulgária"
                          - option "Burquina Fasso"
                          - option "Burúndi"
                          - option "Butão"
                          - option "Cabo Verde"
                          - option "Camarões"
                          - option "Camboja"
                          - option "Canadá"
                          - option "Catar"
                          - option "Cazaquistão"
                          - option "Chade"
                          - option "Chile"
                          - option "China"
                          - option "Chipre"
                          - option "Cingapura"
                          - option "Colômbia"
                          - option "Comores"
                          - option "Comunidade das Ilhas Marianas Setentrionais"
                          - option "Congo (Brazzaville)"
                          - option "Congo (Kinshasa)"
                          - option "Coréia do Norte"
                          - option "Coréia do Sul"
                          - option "Costa do Marfim"
                          - option "Costa Rica"
                          - option "Croácia"
                          - option "Cuba"
                          - option "Curaçao"
                          - option "Dinamarca"
                          - option "Djibouti"
                          - option "Dominica"
                          - option "Egito"
                          - option "El Salvador"
                          - option "Emirados Árabes Unidos"
                          - option "Equador"
                          - option "Eritreia"
                          - option "Eslováquia"
                          - option "Eslovenia"
                          - option "Espanha"
                          - option "Estados Unidos"
                          - option "Estônia"
                          - option "Eswatini"
                          - option "Etiópia"
                          - option "Fiji"
                          - option "Filipinas"
                          - option "Finlândia"
                          - option "França"
                          - option "Gabão"
                          - option "Gâmbia"
                          - option "Gana"
                          - option "Geórgia"
                          - option "Gibraltar"
                          - option "Granada"
                          - option "Grécia"
                          - option "Groenlândia"
                          - option "Guadalupe"
                          - option "Guam"
                          - option "Guatemala"
                          - option "Guernsey"
                          - option "Guiana"
                          - option "Guiana Francesa"
                          - option "Guiné"
                          - option "Guiné Equatorial"
                          - option "Guiné-Bissau"
                          - option "Haiti"
                          - option "Holanda"
                          - option "Honduras"
                          - option "Hong Kong"
                          - option "Hungria"
                          - option "Iémen"
                          - option "Ilha Bouvet"
                          - option "Ilha Christmas"
                          - option "Ilha de Man"
                          - option "Ilha de São Martinho (República Francesa)"
                          - option "Ilha Heard e Ilhas McDonald"
                          - option "Ilha Norfolk"
                          - option "Ilhas Aland"
                          - option "Ilhas Cayman"
                          - option "Ilhas Cocos"
                          - option "Ilhas Cook"
                          - option "Ilhas Feroe"
                          - option "Ilhas Geórgia do Sul e Sandwich do Sul"
                          - option "Ilhas Malvinas"
                          - option "Ilhas Marshall"
                          - option "Ilhas Menores Distantes, Estados Unidos da América (EUA)"
                          - option "Ilhas Pitcairn"
                          - option "Ilhas Salomão"
                          - option "Ilhas Turcas e Caicos"
                          - option "Ilhas Virgens (EUA)"
                          - option "Ilhas Virgens Britânicas"
                          - option "Índia"
                          - option "Indonésia"
                          - option "Irã"
                          - option "Iraque"
                          - option "Irlanda"
                          - option "Islândia"
                          - option "Israel"
                          - option "Itália"
                          - option "Jamaica"
                          - option "Japão"
                          - option "Jersey"
                          - option "Jordânia"
                          - option "Kosovo"
                          - option "Kuweit"
                          - option "Laos"
                          - option "Látvia"
                          - option "Lesoto"
                          - option "Líbano"
                          - option "Libéria"
                          - option "Líbia"
                          - option "Liechtenstein"
                          - option "Lituânia"
                          - option "Luxemburgo"
                          - option "Macao"
                          - option "Macedônia do Norte"
                          - option "Madagascar"
                          - option "Malásia"
                          - option "Malawi"
                          - option "Maldivas"
                          - option "Mali"
                          - option "Malta"
                          - option "Marrocos"
                          - option "Martinica"
                          - option "Maurício"
                          - option "Mauritânia"
                          - option "Mayotte"
                          - option "México"
                          - option "Micronésia"
                          - option "Moçambique"
                          - option "Moldávia"
                          - option "Mônaco"
                          - option "Mongólia"
                          - option "Montenegro"
                          - option "Montserrat"
                          - option "Namíbia"
                          - option "Nauru"
                          - option "Nepal"
                          - option "Nicarágua"
                          - option "Níger"
                          - option "Nigéria"
                          - option "Niue"
                          - option "Noruega"
                          - option "Nova Caledónia"
                          - option "Nova Zelândia"
                          - option "Omã"
                          - option "Panamá"
                          - option "Papua-Nova Guiné"
                          - option "Paquistão"
                          - option "Paraguai"
                          - option "Peru"
                          - option "Polinésia Francesa"
                          - option "Polônia"
                          - option "Porto Rico"
                          - option "Portugal"
                          - option "Quênia"
                          - option "Quirguistão"
                          - option "Quiribáti"
                          - option "Reino Unido (UK)"
                          - option "República Checa"
                          - option "República da África Central"
                          - option "República da União de Myanmar"
                          - option "República Dominicana"
                          - option "Reunião"
                          - option "Romênia"
                          - option "Ruanda"
                          - option "Rússia"
                          - option "Saara Ocidental"
                          - option "Saint Martin (parte Holandesa)"
                          - option "Saint-Pierre e Miquelon"
                          - option "Samoa"
                          - option "Samoa Americana"
                          - option "San Marino"
                          - option "Santa Helena"
                          - option "Santa Lúcia"
                          - option "São Bartolomeu"
                          - option "São Cristóvão e Nevis"
                          - option "São Tomé e Príncipe"
                          - option "São Vicente e Granadinas"
                          - option "Senegal"
                          - option "Serra Leoa"
                          - option "Sérvia"
                          - option "Seychelles"
                          - option "Síria"
                          - option "Somália"
                          - option "Sri Lanka"
                          - option "Sudão"
                          - option "Sudão do Sul"
                          - option "Suécia"
                          - option "Suiça"
                          - option "Suriname"
                          - option "Svalbard e Jan Mayen"
                          - option "Tailândia"
                          - option "Taiwan"
                          - option "Tajiquistão"
                          - option "Tanzânia"
                          - option "Território Britânico do Oceano Índico"
                          - option "Território das Terras Austrais e Antárcticas Francesas"
                          - option "Território Palestino"
                          - option "Timor-Leste"
                          - option "Togo"
                          - option "Tokelau"
                          - option "Tonga"
                          - option "Trinidad e Tobago"
                          - option "Tunísia"
                          - option "Turcomenistão"
                          - option "Turquia"
                          - option "Tuvalu"
                          - option "Ucrânia"
                          - option "Uganda"
                          - option "Uruguai"
                          - option "Uzbequistão"
                          - option "Vanuatu"
                          - option "Vaticano"
                          - option "Venezuela"
                          - option "Vietnã"
                          - option "Wallis e Futuna"
                          - option "Zâmbia"
                          - option "Zimbábue"
                        - img
                      - generic [ref=e139]:
                        - textbox "Nome" [ref=e140]: Teste
                        - generic [ref=e141]: Nome
                      - generic [ref=e142]:
                        - textbox "Sobrenome" [ref=e143]: Braspag
                        - generic [ref=e144]: Sobrenome
                      - generic [ref=e145]:
                        - textbox "Endereço" [ref=e146]
                        - generic [ref=e147]: Endereço
                      - button "+ Adicionar complemento, apartamento, etc." [ref=e148] [cursor=pointer]
                      - textbox [ref=e149]
                      - generic [ref=e150]:
                        - textbox "Código postal" [ref=e151]
                        - generic [ref=e152]: Código postal
                      - generic [ref=e153]:
                        - textbox "Cidade" [ref=e154]
                        - generic [ref=e155]: Cidade
                      - generic [ref=e158]:
                        - generic [ref=e159]: Estado
                        - combobox "Estado" [ref=e160]:
                          - option "Selecione um estado" [disabled]
                          - option "Acre"
                          - option "Alagoas"
                          - option "Amapá"
                          - option "Amazonas"
                          - option "Bahia"
                          - option "Ceará"
                          - option "Distrito Federal"
                          - option "Espírito Santo"
                          - option "Goiás"
                          - option "Maranhão"
                          - option "Mato Grosso"
                          - option "Mato Grosso do Sul"
                          - option "Minas Gerais"
                          - option "Pará"
                          - option "Paraíba"
                          - option "Paraná"
                          - option "Pernambuco"
                          - option "Piauí"
                          - option "Rio de Janeiro"
                          - option "Rio Grande do Norte"
                          - option "Rio Grande do Sul"
                          - option "Rondônia"
                          - option "Roraima"
                          - option "Santa Catarina" [selected]
                          - option "São Paulo"
                          - option "Sergipe"
                          - option "Tocantins"
                        - img
                      - generic [ref=e161]:
                        - textbox "Telefone (opcional)" [active] [ref=e162]: "11999999999"
                        - generic [ref=e163]: Telefone (opcional)
                      - generic [ref=e164]:
                        - textbox "Número" [ref=e165]
                        - generic [ref=e166]: Número
                      - generic [ref=e167]:
                        - textbox "Bairro (opcional)" [ref=e168]
                        - generic [ref=e169]: Bairro (opcional)
                    - generic [ref=e171] [cursor=pointer]:
                      - checkbox "Usar o mesmo endereço para cobrança" [checked] [ref=e172]
                      - img
                      - generic [ref=e173]: Usar o mesmo endereço para cobrança
                - group "Opções de entrega" [ref=e174]:
                  - generic [ref=e175]: Opções de entrega
                  - heading "Opções de entrega" [level=2] [ref=e178]
                  - status [ref=e180]: Insira um endereço de entrega para ver as opções de entrega.
                - group "Opções de pagamento" [ref=e181]:
                  - generic [ref=e182]: Opções de pagamento
                  - heading "Opções de pagamento" [level=2] [ref=e185]
                  - generic [ref=e187]:
                    - generic [ref=e188]:
                      - generic [ref=e189] [cursor=pointer]:
                        - radio "Cartão de Crédito" [checked] [ref=e190]
                        - generic [ref=e193]: Cartão de Crédito
                      - generic [ref=e194]:
                        - generic [ref=e195]: teste
                        - generic [ref=e196]:
                          - paragraph [ref=e197]:
                            - generic [ref=e198]: Nome do Titular
                            - textbox "Nome do Titular" [ref=e199]
                          - paragraph [ref=e200]:
                            - generic [ref=e201]: Número do Cartão
                            - textbox "Número do Cartão" [ref=e202]:
                              - /placeholder: •••• •••• •••• ••••
                          - paragraph [ref=e203]:
                            - generic [ref=e204]: Data de Expiração (MM/YY)
                            - textbox "Data de Expiração (MM/YY)" [ref=e205]:
                              - /placeholder: MM/YY
                          - paragraph [ref=e206]:
                            - generic [ref=e207]: Código de Segurança
                            - textbox "Código de Segurança" [ref=e208]
                          - paragraph [ref=e209]:
                            - generic [ref=e210]: Parcelamento
                            - combobox "Parcelamento" [ref=e211]:
                              - option "1 x R$ 18,00" [selected]
                          - paragraph [ref=e212]:
                            - generic [ref=e213]:
                              - checkbox "Salvar cartão para próximas compras" [ref=e214]
                              - text: Salvar cartão para próximas compras
                    - generic [ref=e216] [cursor=pointer]:
                      - radio "Cartão de Débito" [ref=e217]
                      - generic [ref=e220]: Cartão de Débito
                    - generic [ref=e222] [cursor=pointer]:
                      - radio "Braspag - Boleto" [ref=e223]
                      - generic [ref=e226]: Braspag - Boleto
                    - generic [ref=e228] [cursor=pointer]:
                      - radio "Braspag - Pix" [ref=e229]
                      - generic [ref=e232]: Braspag - Pix
                - group "Informações adicionais do pedido" [ref=e233]:
                  - generic [ref=e234]: Informações adicionais do pedido
                  - heading "Informações adicionais do pedido" [level=2] [ref=e237]
                  - generic [ref=e242]:
                    - generic [ref=e243]: Tipo de Pessoa
                    - combobox "Tipo de Pessoa" [ref=e244]:
                      - option "Selecione um tipo de pessoa" [disabled] [selected]
                      - option "Pessoa Física"
                      - option "Pessoa Jurídica"
                    - img
                - generic [ref=e249] [cursor=pointer]:
                  - checkbox "Adicione uma nota ao seu pedido" [ref=e250]
                  - generic [ref=e251]: Adicione uma nota ao seu pedido
                - generic [ref=e252]: Ao prosseguir com sua compra você concorda com nosso Termos e condições e a Política de privacidade
                - generic [ref=e254]:
                  - link "Retornar ao carrinho" [ref=e255] [cursor=pointer]:
                    - /url: https://bp-lastest.ddev.site/carrinho/
                    - img [ref=e256]
                    - text: Retornar ao carrinho
                  - button "Finalizar pedido" [ref=e258] [cursor=pointer]:
                    - generic [ref=e260]: Finalizar pedido
            - paragraph
      - complementary [ref=e261]:
        - search [ref=e263]:
          - text: Pesquisar
          - generic [ref=e264]:
            - searchbox "Pesquisar" [ref=e265]
            - button "Pesquisar" [ref=e266] [cursor=pointer]
        - generic [ref=e269]:
          - heading "Posts recentes" [level=2] [ref=e270]
          - list [ref=e271]:
            - listitem [ref=e272]:
              - link "Olá, mundo!" [ref=e273] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/2024/11/05/ola-mundo/
        - generic [ref=e276]:
          - heading "Comentários" [level=2] [ref=e277]
          - list [ref=e278]:
            - listitem [ref=e279]:
              - article [ref=e280]:
                - generic [ref=e281]:
                  - link "Um comentarista do WordPress" [ref=e282] [cursor=pointer]:
                    - /url: https://br.wordpress.org/
                  - text: em
                  - link "Olá, mundo!" [ref=e283] [cursor=pointer]:
                    - /url: https://bp-lastest.ddev.site/2024/11/05/ola-mundo/#comment-1
        - generic [ref=e286]:
          - heading "Arquivos" [level=2] [ref=e287]
          - list [ref=e288]:
            - listitem [ref=e289]:
              - link "novembro 2024" [ref=e290] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/2024/11/
        - generic [ref=e293]:
          - heading "Categorias" [level=2] [ref=e294]
          - list [ref=e295]:
            - listitem [ref=e296]:
              - link "Sem categoria" [ref=e297] [cursor=pointer]:
                - /url: https://bp-lastest.ddev.site/category/sem-categoria/
    - contentinfo [ref=e298]:
      - generic [ref=e300]:
        - text: © Braspag DEV Local 2026
        - link "Built with WooCommerce" [ref=e301] [cursor=pointer]:
          - /url: https://woocommerce.com
        - text: .
  - paragraph [ref=e302]: Notificações
  - generic [ref=e303]: Não há métodos de pagamento disponíveis. Entre em contato conosco para obter ajuda na realização do seu pedido.
  - status [ref=e305]
```

# Test source

```ts
  1   | import { test, expect, Page } from '@playwright/test';
  2   | import { TEST_CARDS } from '../fixtures/test-cards';
  3   | 
  4   | const BASE = process.env.WP_BASE_URL ?? 'https://bp-lastest.ddev.site';
  5   | const CHECKOUT = `${BASE}/finalizar-compra/`;
  6   | const PRODUCT_ID = process.env.WP_PRODUCT_ID ?? '86';
  7   | 
  8   | async function addProductToCart(page: Page) {
  9   |     await page.goto(`${BASE}/?add-to-cart=${PRODUCT_ID}&quantity=1`);
  10  |     await page.goto(CHECKOUT);
  11  | }
  12  | 
  13  | async function fillBillingAddress(page: Page) {
  14  |     await page.locator('#email').fill('teste@braspag.com.br');
  15  |     await page.locator('#shipping-first_name').fill('Teste');
  16  |     await page.locator('#shipping-last_name').fill('Braspag');
  17  |     await page.locator('#shipping-phone').fill('11999999999');
  18  |     await page.locator('#order-braspag-wcbcf-cpf').fill('123.456.789-09').catch(() => {});
  19  | }
  20  | 
  21  | async function selectCreditCard(page: Page) {
  22  |     await page.locator('#radio-control-wc-payment-method-options-braspag_creditcard').click();
  23  | }
  24  | 
  25  | async function fillCardForm(page: Page, card: { number: string }, cvv = '123', expiry = '12/30') {
  26  |     await selectCreditCard(page);
  27  |     await page.locator('#braspag_creditcard-card-number').fill(card.number);
  28  |     await page.locator('#braspag_creditcard-card-holder').fill('TESTE BRASPAG');
  29  |     await page.locator('#braspag_creditcard-card-expiry').fill(expiry);
  30  |     await page.locator('#braspag_creditcard-card-cvc').fill(cvv);
  31  | }
  32  | 
  33  | async function placeOrder(page: Page) {
  34  |     await page.locator('.wc-block-components-checkout-place-order-button').click();
  35  | }
  36  | 
  37  | test.describe('Blocks Checkout — Cartão de Crédito', () => {
> 38  |     test.beforeEach(async ({ page }) => {
      |          ^ Test timeout of 30000ms exceeded while running "beforeEach" hook.
  39  |         await addProductToCart(page);
  40  |         await fillBillingAddress(page);
  41  |     });
  42  | 
  43  |     test('B1.1 — pagamento básico aprovado sem 3DS', async ({ page }) => {
  44  |         await fillCardForm(page, { number: '4111111111111111' });
  45  |         await placeOrder(page);
  46  |         await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
  47  |         await expect(page.locator('.woocommerce-order-received, .wc-block-order-confirmation')).toBeVisible();
  48  |     });
  49  | 
  50  |     test('B1.2 — 3DS frictionless Visa (sem modal)', async ({ page }) => {
  51  |         await fillCardForm(page, TEST_CARDS.visa3dsSemDesafioSucesso);
  52  |         await placeOrder(page);
  53  |         await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
  54  |     });
  55  | 
  56  |     test('B1.3 — 3DS challenge Visa (modal aparece)', async ({ page }) => {
  57  |         test.skip(true, 'Challenge modal requer interação com iframe do banco emissor');
  58  |     });
  59  | 
  60  |     test('B1.4 — 3DS com Elo', async ({ page }) => {
  61  |         await fillCardForm(page, TEST_CARDS.elo3dsSemDesafioSucesso);
  62  |         await placeOrder(page);
  63  |         await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
  64  |     });
  65  | 
  66  |     test('B1.5 — cartão recusado exibe mensagem de erro', async ({ page }) => {
  67  |         await fillCardForm(page, { number: '4000000000000002' });
  68  |         await placeOrder(page);
  69  |         const error = page.locator('.wc-block-components-notice--error, .wc-block-store-notice, .woocommerce-error');
  70  |         await expect(error.first()).toBeVisible({ timeout: 10000 });
  71  |         const errorText = await error.first().textContent() ?? '';
  72  |         expect(errorText).not.toContain('%s');
  73  |     });
  74  | 
  75  |     test('B1.6 — salvar cartão e usar cartão salvo', async ({ page }) => {
  76  |         await fillCardForm(page, { number: '4111111111111111' });
  77  |         await page.locator('#wc-braspag_creditcard-new-payment-method').check().catch(() => {});
  78  |         await placeOrder(page);
  79  |         await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
  80  | 
  81  |         await addProductToCart(page);
  82  |         await selectCreditCard(page);
  83  |         const savedCard = page.locator('.wc-block-components-payment-method-options input[type="radio"]:not([value="braspag_creditcard"])').first();
  84  |         if (await savedCard.count() > 0) {
  85  |             await savedCard.click();
  86  |             await placeOrder(page);
  87  |             await expect(page).toHaveURL(/order-received/, { timeout: 20000 });
  88  |         }
  89  |     });
  90  | 
  91  |     test('B1.7 — SOP: PAN não trafega pelo servidor da loja', async ({ page }) => {
  92  |         const requests: string[] = [];
  93  |         page.on('request', req => {
  94  |             if (req.url().includes(BASE) && req.method() === 'POST') {
  95  |                 requests.push(req.postData() ?? '');
  96  |             }
  97  |         });
  98  | 
  99  |         await fillCardForm(page, { number: '4111111111111111' });
  100 |         await placeOrder(page);
  101 | 
  102 |         const panInRequests = requests.some(body => body.includes('4111111111111111'));
  103 |         if (!panInRequests) {
  104 |             expect(panInRequests).toBe(false);
  105 |         }
  106 |     });
  107 | });
  108 | 
```