'use strict';

var BraspagAuth3ds20 = Class.create();

BraspagAuth3ds20.prototype = {

  initialize: function () {

    if (typeof braspag_auth3ds20_params == "undefined") {
      return false;
    }

    this.bpmpiRenderer = new BpmpiRenderer();
    // O script MPI vendorizado lê a classe/campo `bpmpi_accesstoken` de
    // forma síncrona assim que carrega (ele é impresso no <head>, antes do
    // nosso próprio driver rodar) — por isso o token vem embutido no HTML
    // via wp_localize_script, e não buscado por AJAX (buscar de forma
    // assíncrona causa corrida: o script tenta inicializar com o campo
    // ainda vazio e falha com 401/MPI900 "Invalid Access Token").
    this.bpmpiToken = braspag_auth3ds20_params.bpmpiToken;
    this.isBpmpiEnabledCC = braspag_auth3ds20_params.isBpmpiEnabledCC;
    this.isBpmpiEnabledDC = braspag_auth3ds20_params.isBpmpiEnabledDC;
    this.isBpmpiMasterCardNotifyOnlyEnabledCC = braspag_auth3ds20_params.isBpmpiMasterCardNotifyOnlyEnabledCC;
    this.isBpmpiMasterCardNotifyOnlyEnabledDC = braspag_auth3ds20_params.isBpmpiMasterCardNotifyOnlyEnabledDC;
    this.isTestEnvironment = braspag_auth3ds20_params.isTestEnvironment;
    this.isSopEnabled = !!braspag_auth3ds20_params.isSopEnabled;
    this.paymentType = '';
    this.transactionStarted = false;

    this.registerPaymentMethodEvents();
  },

  // Busca sob demanda o token de autenticação MPI/3DS via AJAX, em vez de
  // recebê-lo embutido no HTML (evita exposição do token em texto puro).
  fetchAuthTokens: function () {
    if (this._authTokensPromise) {
      return this._authTokensPromise;
    }

    var self = this;

    this._authTokensPromise = fetch(braspag_auth3ds20_params.authTokensAjaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        action: braspag_auth3ds20_params.authTokensAction,
        nonce: braspag_auth3ds20_params.authTokensNonce,
      }),
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (json && json.success && json.data) {
          self.bpmpiToken = json.data.bpmpiToken || null;
        }
        return json;
      })
      .catch(function (error) {
        console.error('Erro ao obter token de autenticação 3DS:', error);
      });

    return this._authTokensPromise;
  },

  startTransaction: async function () {
    var self = this;

    if (this.transactionStarted || !this.isBpmpiEnabled()) {
      return true
    }

    this.transactionStarted = true;

    let checkout_payment_element = jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table');

    braspag.blockElement(checkout_payment_element);

    try {
      await self.fetchAuthTokens();

      self.bpmpiRenderer.renderBpmpiData('bpmpi_auth', false, self.isBpmpiEnabled());
      self.bpmpiRenderer.renderBpmpiData('bpmpi_accesstoken', false, self.bpmpiToken);

      await bpmpi_load();
    } finally {
      braspag.unBlockElement(checkout_payment_element);
    }


    return true;
  },

  getAuthenticateData: async function () {

    // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo)
    console.log('[BP-DEBUG] getAuthenticateData: antes de bpmpi_authenticate()', Date.now());

    await bpmpi_authenticate();

    // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo)
    console.log('[BP-DEBUG] getAuthenticateData: depois de bpmpi_authenticate()', Date.now());

    var returnData = {
      'bpmpiAuthFailureType': jQuery('.bpmpi_auth_failure_type').val(),
      'bpmpiAuthCavv': jQuery('.bpmpi_auth_cavv').val(),
      'bpmpiAuthXid': jQuery('.bpmpi_auth_xid').val(),
      'bpmpiAuthEci': jQuery('.bpmpi_auth_eci').val(),
      'bpmpiAuthVersion': jQuery('.bpmpi_auth_version').val(),
      'bpmpiAuthReferenceId': jQuery('.bpmpi_auth_reference_id').val()
    };

    if (this.isTestEnvironment) {
      console.log('[BP-DEBUG] getAuthenticateData: returnData', returnData);
    }

    return returnData;
  },

  isBpmpiEnabled: function () {
    return this.isBpmpiEnabledCC || this.isBpmpiEnabledDC;
  },

  disableBpmpi: function () {
    this.isBpmpiEnabledCC = false;
    this.isBpmpiEnabledDC = false;

    if (this.isTestEnvironment) {
      console.log("[BP-DEBUG] 'Bpmpi' disabled.");
    }

    return;
  },

  registerPaymentMethodEvents: function () {
    const self = this;
    const credit = document.querySelector('#payment_method_braspag_creditcard');
    const debit = document.querySelector('#payment_method_braspag_debitcard');

    const methods = [credit, debit];

    methods.forEach(function (method) {
      if (method) {
        if (method.checked) {
          self.startTransaction();
        }

        method.addEventListener('change', function () {
          if (method.checked) {
            self.startTransaction();
          }
        });
      }
    });
  },

  placeOrder: async function (form) {
    try {
      var self = this;
      let paymentForm = jQuery(form);

      jQuery('.bpmpi_auth_failure_type').change(function () {
        // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo): este e o
        // handler que efetivamente dispara o submit do checkout assim que bpmpi_auth_failure_type
        // recebe valor. Registramos aqui o estado de TODOS os campos bpmpi_auth_* nesse instante,
        // para confirmar se cavv/xid/eci/version/reference_id ja estao preenchidos quando o submit ocorre.
        console.log('[BP-DEBUG] change:bpmpi_auth_failure_type disparado', Date.now(), {
          failureType: jQuery('.bpmpi_auth_failure_type').val(),
          cavv: jQuery('.bpmpi_auth_cavv').val(),
          xid: jQuery('.bpmpi_auth_xid').val(),
          eci: jQuery('.bpmpi_auth_eci').val(),
          version: jQuery('.bpmpi_auth_version').val(),
          referenceId: jQuery('.bpmpi_auth_reference_id').val()
        });

        if (self.isBpmpiEnabled()) {
          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_failure_type', 'authentication_failure_type', ''
          );

          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_cavv', 'authentication_cavv', ''
          );

          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_xid', 'authentication_xid', ''
          );

          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_eci', 'authentication_eci', ''
          );

          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_version', 'authentication_version', ''
          );

          self.bpmpiRenderer.createInputHiddenElement(
            paymentForm, 'payment_authentication_reference_id', 'authentication_reference_id', ''
          );

          jQuery('.authentication_failure_type').val(jQuery('.bpmpi_auth_failure_type').val());
          jQuery('.authentication_cavv').val(jQuery('.bpmpi_auth_cavv').val());
          jQuery('.authentication_xid').val(jQuery('.bpmpi_auth_xid').val());
          jQuery('.authentication_eci').val(jQuery('.bpmpi_auth_eci').val());
          jQuery('.authentication_version').val(jQuery('.bpmpi_auth_version').val());
          jQuery('.authentication_reference_id').val(jQuery('.bpmpi_auth_reference_id').val());
        }

        // Ordem documentada pela Cielo: 3DS autentica primeiro (dados acima já
        // preenchidos em ExternalAuthentication), SOP tokeniza o cartão depois
        // e só então o form é enviado — com os dois conjuntos de dados juntos.
        if (self.isSopEnabled) {
          // SOP está habilitado nas configurações: o cartão SÓ pode ser enviado
          // como PaymentToken/CardToken tokenizado pelo SOP. Se o objeto `sop`
          // não estiver disponível (ex.: braspag-authsop.js não carregou porque
          // Prototype.js veio depois dele), submeter agora mandaria a transação
          // sem dado de cartão e a Braspag responderia 127. Aborta o submit e
          // deixa o erro visível em vez de falhar silenciosamente.
          if (typeof sop === 'undefined' || typeof sop.isSopEnabled !== 'function' || !sop.isSopEnabled()) {
            console.error('[BP-DEBUG] SOP habilitado mas indisponível — submit abortado para evitar transação sem cartão (Braspag 127).', Date.now());
            paymentForm.removeClass('processing').unblock();
            jQuery(document.body).trigger('checkout_error');
            jQuery('.woocommerce-notices-wrapper:first').html(
              '<ul class="woocommerce-error" role="alert"><li>' +
              'Não foi possível preparar os dados do cartão. Recarregue a página e tente novamente.' +
              '</li></ul>'
            );
            return false;
          }

          // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo)
          console.log('[BP-DEBUG] 3DS concluido, acionando SOP antes do submit', Date.now());

          sop.processSop(paymentForm);
          return true;
        }

        // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo)
        console.log('[BP-DEBUG] paymentForm.submit() chamado', Date.now());

        paymentForm.submit();
        return true;
      });

      let paymentMethod = paymentForm.find('input[name="payment_method"]:checked').val();

      if (paymentMethod == 'braspag_creditcard') {
        this.paymentType = 'creditcard';
      } else if (paymentMethod == 'braspag_debitcard') {
        this.paymentType = 'debitcard';
      } else {
        this.disableBpmpi();
        return true;
      }

      // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo)
      console.log('[BP-DEBUG] placeOrder: inicio da cadeia MPI', Date.now(), self.paymentType);

      await self.startTransaction();
      await self.renderData();
      await self.getAuthenticateData();

      // TEMP DEBUG (remover apos investigacao do caso ExternalAuthentication nulo): se este log
      // aparecer DEPOIS do "paymentForm.submit() chamado" acima, o submit disparou antes do fim
      // da cadeia MPI (confirma a hipotese de corrida). Se aparecer ANTES, o submit ja aguardou
      // a cadeia completa e o problema esta na propria gravacao dos campos pelo SDK bpmpi.
      console.log('[BP-DEBUG] placeOrder: fim da cadeia MPI (getAuthenticateData resolvido)', Date.now());

    } catch (e) {
      if (self.isTestEnvironment) {
        console.log('[BP-DEBUG] placeOrder: erro na cadeia MPI', e);
      }

      self.disableBpmpi();
      return false;
    }

    // return true;
  },

  validateAuthenticate: async function () {
    var self = this;

    if (self.paymentType == 'creditcard') {
      if (!this.isBpmpiEnabledCC) {
        return false;
      }
    }

    if (self.paymentType == 'debitcard') {
      if (!this.isBpmpiEnabledDC) {
        return false;
      }
    }

    return true;
  },

  renderData: async function () {
    var self = this;

    let mpiValidation = await this.validateAuthenticate();

    if (!mpiValidation) {
      self.disableBpmpi();
      return true;
    }

    this.bpmpiRenderer.renderBpmpiData('bpmpi_auth', false, mpiValidation);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_transaction_mode', false, 'S');

    if (self.paymentType == 'creditcard') {
      this.renderCredicardData();
    }
    if (self.paymentType == 'debitcard') {
      this.renderDebitcardData();
    }

    self.renderAddressData();

    return true;
  },

  renderCredicardData: function () {
    this.bpmpiRenderer.renderBpmpiData('bpmpi_paymentmethod', '', 'Credit');
    this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledCC);

    let creditcardExpiration = jQuery('#braspag_creditcard-card-expiry').val().split('/');

    let creditcardExpirationMonth = '';
    if (creditcardExpiration[0]) {
      creditcardExpirationMonth = creditcardExpiration[0].replace(/\s/g, '');
    }

    let creditcardExpirationYear = '';
    if (creditcardExpiration[1]) {
      creditcardExpirationYear = creditcardExpiration[1].replace(/\s/g, '');
    }

    if (creditcardExpirationYear.length === 2) {
      creditcardExpirationYear = '20' + creditcardExpirationYear;
    }

    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardnumber', false, jQuery('#braspag_creditcard-card-number').val().replace(/\s/g, ''));
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_contactname', false, jQuery('#braspag_creditcard-card-holder').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationmonth', false, creditcardExpirationMonth);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationyear', false, creditcardExpirationYear);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_installments', false, jQuery('#braspag_creditcard-card-installments').val());
  },

  renderDebitcardData: function () {
    this.bpmpiRenderer.renderBpmpiData('bpmpi_paymentmethod', '', 'Debit');
    this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledDC);

    let debitcardExpiration = jQuery('#braspag_debitcard-card-expiry').val().split('/');

    let debitcardExpirationMonth = '';
    if (debitcardExpiration[0]) {
      debitcardExpirationMonth = debitcardExpiration[0].replace(/\s/g, '');
    }

    let debitcardExpirationYear = '';
    if (debitcardExpiration[1]) {
      debitcardExpirationYear = debitcardExpiration[1].replace(/\s/g, '');
    }

    if (debitcardExpirationYear.length === 2) {
      debitcardExpirationYear = '20' + debitcardExpirationYear;
    }

    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardnumber', false, jQuery('#braspag_debitcard-card-number').val().replace(/\s/g, ''));
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_contactname', false, jQuery('#braspag_debitcard-card-holder').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationmonth', false, debitcardExpirationMonth);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationyear', false, debitcardExpirationYear);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_installments', false, 1);
  },

  renderAddressData: function () {
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_phonenumber', false, jQuery('#billing_phone').val().replace(/[^a-zA-Z 0-9]+/g, '').replace(/\s/g, ''));
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_email', false, jQuery('#billing_email').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_street1', false, jQuery('#billing_address_1').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_street2', false, jQuery('#billing_number').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_city', false, jQuery('#billing_city').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_state', false, jQuery('#billing_state').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_zipcode', false, jQuery('#billing_postcode').val().replace(/[^a-zA-Z 0-9]+/g, ''));
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_country', false, jQuery('#billing_country').val());

    let shippingAddressValue = jQuery('#shipping_address_1').val();

    this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_sameasbillto', false, typeof shippingAddressValue == "undefined" ? true : false);

    if (typeof shippingAddressValue != "undefined") {
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_sameasbillto', false, jQuery('#shipping_address_1').val() == '' ? true : false);
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_addressee', false, jQuery('#shipping_first_name').val() + ' ' + jQuery('#shipping_last_name').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_phonenumber', false, jQuery('#shipping_phone').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_email', false, jQuery('#shipping_email').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_street1', false, jQuery('#shipping_address_1').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_street2', false, jQuery('#shipping_number').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_city', false, jQuery('#shipping_city').val());
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_state', false, jQuery('#shipping_state').val());

      let shipping_postcode = jQuery('#shipping_postcode').val();
      if (typeof shipping_postcode != "undefined") {
        shipping_postcode = shipping_postcode.replace(/[^a-zA-Z 0-9]+/g, '');
      }

      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_zipcode', false, shipping_postcode);
      this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_country', false, jQuery('#shipping_country').val());
    }
  },
};

var bpmpi = new BraspagAuth3ds20;