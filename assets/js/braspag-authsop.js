var Sop = Class.create();

Sop.prototype = {
  initialize: async function () {
    if (typeof braspag_authsop_params == "undefined") {
      return false;
    }

    this.bpEnvironment = braspag_authsop_params.bpEnvironment;
    this.bpOauthToken = braspag_authsop_params.bpOauthToken;
    this.bpMerchantId = braspag_authsop_params.bpMerchantId;
    this.bpMerchantIdSOP = braspag_authsop_params.bpMerchantIdSOP;
    this.bpAccessToken = braspag_authsop_params.bpAccessToken;

    if (["yes", true].includes(braspag_authsop_params.verifyCard)) {
      this.enableVerifyCardCheck = true;
    }

    if (["yes", true].includes(braspag_authsop_params.binQuery)) {
      this.enableBinQueryCheck = true;
    }

    if (["yes", true].includes(braspag_authsop_params.tokenize)) {
      this.enableTokenizeCheck = true;
    }

    this.language = braspag_authsop_params.language;
    this.testMode = braspag_authsop_params.testMode;
    this.cvvrequired = braspag_authsop_params.cvvrequired;
    this.cardType = '';
    this.options = {};
    this.provider = braspag_authsop_params.provider;

    this.creditCardMethod = document.querySelector("#payment_method_braspag_creditcard");
    this.debitCardMethod = document.querySelector("#payment_method_braspag_debitcard");
    this.sop_enable = braspag_authsop_params.enable || false;
  },

  bpInit: function (form) {
    if (this.creditCardMethod && this.creditCardMethod.checked) {
      this.cardType = 'creditCard';
    } else if (debitCardMethod && debitCardMethod.checked) {
      this.cardType = 'debitCard';
    } else {
      this.cardType = "creditCard";
    }

    const errorContainer = document.querySelector('.woocommerce-notices-wrapper');

    let options = {
      accessToken: this.bpAccessToken,
      onSuccess: function (response) {
        console.log('raw-message: ' + JSON.stringify(response, null, '\t'));
        let element;
        if (response.PaymentToken != undefined) {
          element = document.getElementById('braspag_creditcard-card-paymenttoken');
          element.value = response.PaymentToken;
          console.log('PaymentToken: ' + response.PaymentToken.toLowerCase());
        } else {
          element = document.getElementById('braspag_creditcard-card-cardtoken');
          element.value = response.CardToken;
          console.log('CardToken: ' + response.CardToken).toLowerCase();
        }

        // enviar o payment token para ser usado no lugar do cartão
        // Após a obtenção do PaymentToken através do script, execute o processo de autorização, enviando o PaymentToken no lugar de dados do cartão.

        // Para submeter uma transação de crédito, envie o parâmetro Payment.CreditCard.PaymentToken em vez de Payment.CreditCard. Saiba mais no manual do Gateway de pagamento;
        // Para submeter uma transação de débito, envie o parâmetro Payment.DebitCard.PaymentToken em vez de Payment.DebitCard. Saiba mais no manual do Gateway de pagamento.
        var paymentTokenField = document.getElementById('braspag_creditcard-card-paymenttoken');
        var cardTokenField = document.getElementById('braspag_creditcard-card-cardtoken');

        if ((paymentTokenField && paymentTokenField.value) || (cardTokenField && cardTokenField.value)) {
          console.log('Os campos possuem conteúdo. Submetendo o formulário.');
          form.submit();
          return true;
        } else {
          console.warn('Os campos estão vazios. O formulário não será submetido.');
        }
        //   {
        //     "ForeignCard": true,
        //     "BinQueryReturnCode": "00",
        //     "BinQueryReturnMessage": "Analise autorizada",
        //     "Brand": "Master",
        //     "VerifyCardStatus": 0,
        //     "VerifyCardReturnCode": "11",
        //     "VerifyCardReturnMessage": "Autorizacao negada",
        //     "CardBin": "523353",
        //     "CardLast4Digits": "7811",
        //     "CardToken": "1e7ab8e0-c0a0-4e19-80c4-2d43bb15430b",
        //     "CardType": "Multiple",
        //     "Issuer": "Bradesco",
        //     "IssuerCode": "237",
        //     "Prepaid": false
        // }
        return true;
      },
      onError: function (response) {
        if (!errorContainer) {
          console.error("Elemento com a classe 'woocommerce-error' não encontrado.");
          return;
        }

        const errorItem = document.createElement('li');
        errorItem.setAttribute('data-id', 'payment');

        const errorLink = document.createElement('a');
        errorLink.setAttribute('href', '#payment');
        errorLink.innerHTML = `
                    error: HTTP ${response.Code} - ${response.Message}
                `;

        errorItem.appendChild(errorLink);

        const errorDiv = document.createElement('div');
        errorItem.setAttribute('class', 'woocommerce-message');
        errorItem.setAttribute('role', 'alert');
        errorItem.setAttribute('tabindex', '-1');
        errorDiv.appendChild(errorItem);

        errorContainer.appendChild(errorDiv);
        errorContainer.style.display = "block";
      },
      onInvalid: function (validationResults) {
        validationResults.forEach(function (result) {
          console.log(`field: ${result.Field} = ${JSON.stringify(result)} | message: ${result.Message}`);

          // Obter o campo com base no ID ou classe do resultado
          const fieldElement = document.getElementById(result.Field) || document.querySelector(`.${result.Field}`);

          if (fieldElement) {
            // Adicionar classes faltantes
            if (!fieldElement.classList.contains('bp-sop-cardnumber')) {
              //fieldElement.classList.add('form-row', 'bp-sop-cardnumber');
            }

            if (!fieldElement.classList.contains('input-text')) {
              //fieldElement.classList.add('bp-sop-cardtype');
            }

            // Exibir erro no console caso necessário
            //console.warn(`Classe(s) adicionada(s) ao campo: ${result.Field}`);
          } else {
            console.error(`Campo não encontrado: ${result.Field}`);
          }
        });
      },
      environment: this.bpEnvironment,
      language: this.language,
      enableBinQuery: this.enableBinQueryCheck,
      enableVerifyCard: this.enableVerifyCardCheck,
      enableTokenize: this.enableTokenizeCheck,
      cvvrequired: this.cvvrequired,
      provider: this.provider,
    };

    bpSop_silentOrderPost(options);
  },
  logger: function () {
    console.log('bpEnvironment: ' + braspag_authsop_params.bpEnvironment);
    console.log('bpOauthToken: ' + braspag_authsop_params.bpOauthToken);
    console.log('bpMerchantId: ' + braspag_authsop_params.bpMerchantId);
    console.log('bpMerchantIdSOP: ' + braspag_authsop_params.bpMerchantIdSOP);
    console.log('bpClientId: ' + braspag_authsop_params.bpClientId);
    console.log('bpAccessToken: ' + braspag_authsop_params.bpAccessToken);
    console.log('enable: ' + braspag_authsop_params.enable);
    console.log('verifyCard: ' + braspag_authsop_params.verifyCard);
    console.log('binQuery: ' + braspag_authsop_params.binQuery);
    console.log('tokenize: ' + braspag_authsop_params.tokenize);
    console.log('language: ' + braspag_authsop_params.language);
    console.log('cvvrequired: ' + braspag_authsop_params.cvvrequired);
  },
  isSopEnabled: function () {
    return this.sop_enable;
  },
  registerPaymentMethodEvents: function () {
    const self = this;
    const paymentMethods = [
      "#payment_method_braspag_creditcard",
      "#payment_method_braspag_debitcard",
    ];
    paymentMethods.forEach(function (methodSelector) {
      const methodElement = document.querySelector(methodSelector);
      if (methodElement) {
        methodElement.addEventListener("change", function () {
          self.bpInit();
        });
      }
    });
  },
  FormDataSop: function () {
    if (typeof value === 'string') {
      return value.replace(/\s+/g, '');
    }

    return value;
  },
  registerCardNumberSync: function () {
    let cardNumberField = document.querySelector('.wc-credit-card-form-braspag-card-number');
    let formatValue = cardNumberField.value.replace(/\s+/g, '');
    let bpSopCardNumberField = document.querySelector('#bp-sop-cardnumber');
    bpSopCardNumberField.value = formatValue;

    cardNumberField.addEventListener("change", () => {
      bpSopCardNumberField.value = formatValue;
    });

    cardNumberField.addEventListener("input", () => {
      bpSopCardNumberField.value = formatValue;
    });

    cardNumberField.addEventListener("blur", () => {
      bpSopCardNumberField.value = formatValue;
    });
  },
  registerCardTypeSync: function () {
    let cardTypeField = document.querySelector('.wc-credit-card-form-card-type-card');
    let bpSopCardTypeField = document.querySelector('#bp-sop-cardtype');

    cardTypeField.addEventListener("change", () => {
      bpSopCardTypeField.value = cardTypeField.value;
    });

    cardTypeField.addEventListener("input", () => {
      bpSopCardTypeField.value = cardTypeField.value;
    });

    cardTypeField.addEventListener("blur", () => {
      bpSopCardTypeField.value = cardTypeField.value;
    });
  },
  registerCardExpirySync: function () {
    let cardExpirationDateField = document.querySelector('.wc-credit-card-form-card-expiry');
    let bpSopCardExpirationDateField = document.querySelector('#bp-sop-cardexpirationdate');
    let formatValue = cardExpirationDateField.value.replace(/\s+/g, '');
    bpSopCardExpirationDateField.value = formatValue;

    cardExpirationDateField.addEventListener("change", () => {
      bpSopCardExpirationDateField.value = formatValue;
    });

    cardExpirationDateField.addEventListener("input", () => {
      bpSopCardExpirationDateField.value = formatValue;
    });

    cardExpirationDateField.addEventListener("blur", () => {
      bpSopCardExpirationDateField.value = formatValue;
    });
  }
};

var sop = new Sop();