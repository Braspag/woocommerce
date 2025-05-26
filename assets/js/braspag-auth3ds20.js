'use strict';

var BraspagAuth3ds20 = Class.create();

BraspagAuth3ds20.prototype = {

  initialize: async function () {

    if (typeof braspag_auth3ds20_params == "undefined") {
      return false;
    }

    this.bpmpiRenderer = new BpmpiRenderer();
    this.bpmpiToken = braspag_auth3ds20_params.bpmpiToken;
    this.isBpmpiEnabledCC = braspag_auth3ds20_params.isBpmpiEnabledCC;
    this.isBpmpiEnabledDC = braspag_auth3ds20_params.isBpmpiEnabledDC;
    this.isBpmpiMasterCardNotifyOnlyEnabledCC = braspag_auth3ds20_params.isBpmpiMasterCardNotifyOnlyEnabledCC;
    this.isBpmpiMasterCardNotifyOnlyEnabledDC = braspag_auth3ds20_params.isBpmpiMasterCardNotifyOnlyEnabledDC;
    this.isTestEnvironment = braspag_auth3ds20_params.isTestEnvironment;
    this.paymentType = '';

    jQuery('.bpmpi_accesstoken').val(this.bpmpiToken);

    await this.startTransaction();
  },

  startTransaction: async function () {
    var self = this;

    if (this.isBpmpiEnabled()) {

      let checkout_payment_element = jQuery('.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table');

      braspag.blockElement(checkout_payment_element);

      self.bpmpiRenderer.renderBpmpiData('bpmpi_auth', false, self.isBpmpiEnabled());
      self.bpmpiRenderer.renderBpmpiData('bpmpi_accesstoken', false, self.bpmpiToken);

      await bpmpi_load();

      braspag.unBlockElement(checkout_payment_element);
    }

    return true;
  },

  getAuthenticateData: async function () {

    await bpmpi_authenticate();

    var returnData = {
      'bpmpiAuthFailureType': jQuery('.bpmpi_auth_failure_type').val(),
      'bpmpiAuthCavv': jQuery('.bpmpi_auth_cavv').val(),
      'bpmpiAuthXid': jQuery('.bpmpi_auth_xid').val(),
      'bpmpiAuthEci': jQuery('.bpmpi_auth_eci').val(),
      'bpmpiAuthVersion': jQuery('.bpmpi_auth_version').val(),
      'bpmpiAuthReferenceId': jQuery('.bpmpi_auth_reference_id').val()
    };

    console.log(returnData);

    return returnData;
  },

  isBpmpiEnabled: function () {
    return this.isBpmpiEnabledCC || this.isBpmpiEnabledDC;
  },

  disableBpmpi: function () {
    this.isBpmpiEnabledCC = false;
    this.isBpmpiEnabledDC = false;

    if (this.isTestEnvironment) {
      console.log("'Bpmpi' disabled.");
    }

    return;
  },

  placeOrder: async function (form) {

    try {
      var self = this;
      let paymentForm = jQuery(form);

      jQuery('.bpmpi_auth_failure_type').change(function () {

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

      await self.renderData();
      await self.getAuthenticateData();

    } catch (e) {
      if (self.isTestEnvironment) {
        console.log(e);
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
    //this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledCC);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledCC == 1 ? 'true' : 'false');

    let creditcardExpiration = jQuery('#braspag_creditcard-card-expiry').val().split('/');

    let creditcardExpirationMonth = '';
    if (creditcardExpiration[0]) {
      creditcardExpirationMonth = creditcardExpiration[0].replace(/\s/g, '');
    }

    let creditcardExpirationYear = '';
    if (creditcardExpiration[1]) {
      creditcardExpirationYear = creditcardExpiration[1].replace(/\s/g, '');
    }

    if (creditcardExpirationYear.length == 2) {
      creditcardExpirationYear += '20';
    }

    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardnumber', false, jQuery('#braspag_creditcard-card-number').val().replace(/\s/g, ''));
    this.bpmpiRenderer.renderBpmpiData('bpmpi_billto_contactname', false, jQuery('#braspag_creditcard-card-holder').val());
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationmonth', false, creditcardExpirationMonth);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardexpirationyear', false, creditcardExpirationYear);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_installments', false, jQuery('#braspag_creditcard-card-installments').val());
  },

  renderDebitcardData: function () {

    this.bpmpiRenderer.renderBpmpiData('bpmpi_paymentmethod', '', 'Debit');
    //this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledDC);
    this.bpmpiRenderer.renderBpmpiData('bpmpi_auth_notifyonly', false, this.isBpmpiMasterCardNotifyOnlyEnabledDC == 1 ? 'true' : 'false');

    let debitcardExpiration = jQuery('#braspag_debitcard-card-expiry').val().split('/');

    let debitcardExpirationMonth = '';
    if (debitcardExpiration[0]) {
      debitcardExpirationMonth = debitcardExpiration[0].replace(/\s/g, '');
    }

    let debitcardExpirationYear = '';
    if (debitcardExpiration[1]) {
      debitcardExpirationYear = debitcardExpiration[1].replace(/\s/g, '');
    }

    if (debitcardExpirationYear.length == 2) {
      debitcardExpirationYear += '20';
    }

    this.bpmpiRenderer.renderBpmpiData('bpmpi_cardnumber', false, jQuery('#braspag_debitcard-card-number').val());
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

    this.bpmpiRenderer.renderBpmpiData('bpmpi_shipto_sameasbillto', false, typeof shippingAddress == "undefined" ? true : false);

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