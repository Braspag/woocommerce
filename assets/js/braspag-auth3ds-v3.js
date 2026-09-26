'use strict';

/**
 * Driver client-side do MPI v3 (Cardinal Commerce) para o checkout
 * clássico — substitui `braspag-auth3ds20.js` (v2).
 *
 * Contrato do SDK (Scripts/V3/mpi.js) -- respeitar à risca:
 *   - `MPI.load(config)` aceita UM argumento; todos os callbacks
 *     (`onLoadComplete`, `onReady`, `onValidationRequired`, `onError`) e o
 *     `cardNumberReader` vão DENTRO do config.
 *   - `MPI.init(referenceId, token)` e `MPI.updateCard()` NÃO aceitam
 *     callback -- os sinais de prontidão são `onLoadComplete` (script do
 *     Cardinal carregado) e `onReady` (`payments.setupComplete`).
 *   - `MPI.challenge(challengeData, order)`: o 2º parâmetro é o objeto
 *     `order`, não callbacks.
 *   - `MPI.load()` só dispara `onLoadComplete` na primeira carga.
 *
 * Fluxo:
 *   1. Ao marcar crédito/débito, chama o backend (AJAX `braspag_mpi_v3_init`)
 *      para obter `referenceId`+`token`, depois `MPI.load()` -> `MPI.init()`
 *      -> `MPI.updateCard()` (fala direto com a lib Cardinal, no browser).
 *   2. No submit do form, chama `braspag_mpi_v3_enroll` (AJAX), enviando
 *      também os dados do cartão já digitados no formulário (a Cielo exige
 *      o objeto `card` não-vazio em 3ds/enroll; o PAN já trafega por este
 *      mesmo backend na submissão normal do pedido).
 *      - status=1 (autenticado): o resultado final (Cavv/Xid/Eci/Version)
 *        já vem no próprio enroll -- preenche `.bpmpi_v3_*` direto, sem
 *        chamar validate.
 *      - status=2 (challenge): chama `MPI.challenge()`; só após o
 *        callback do challenge resolver, chama `braspag_mpi_v3_validate`
 *        (AJAX) com o `transactionId` do challenge para obter o resultado
 *        final.
 *      - status=0 (não autenticado/não enrolado): decide conforme
 *        `auth3ds20_mpi_authorize_on_*` (mesmo comportamento configurável
 *        do v2 — ver WC_Braspag_Auth3ds_V3_Gate no backend).
 *   3. Libera o submit do form só depois de `.bpmpi_v3_*` preenchidos (ou
 *      o failure_type setado) -- mesmo padrão de "esperar um evento antes
 *      de liberar o submit" do v2, orientado a Promises.
 */
var BraspagAuth3dsV3 = Class.create();

BraspagAuth3dsV3.prototype = {

  initialize: function () {
    if (typeof braspag_auth3ds_v3_params === 'undefined') {
      return false;
    }

    this.params = braspag_auth3ds_v3_params;
    this.isBpmpiEnabledCC = !!this.params.isBpmpiEnabledCC;
    this.isBpmpiEnabledDC = !!this.params.isBpmpiEnabledDC;
    this.isTestEnvironment = !!this.params.isTestEnvironment;

    this.paymentType = '';
    this.sessionReady = false;
    this.sessionPromise = null;
    this.referenceId = '';
    this.orderNumber = '';
    this.mpiLoaded = false;
    this.dataOnly = false;

    // Deferreds dos callbacks do SDK: o MPI v3 não aceita callbacks por
    // chamada -- os eventos chegam pelos handlers registrados no config de
    // MPI.load(), então cada espera vira um deferred resolvido pelo handler.
    this.pendingLoad = null;
    this.pendingReady = null;
    this.pendingValidation = null;

    this.registerPaymentMethodEvents();
  },

  /** Timeout para os eventos de carga/setup do SDK. */
  SDK_TIMEOUT_MS: 30000,

  /**
   * Timeout funcional do challenge (DF-005 do BDD): se o comprador abandonar
   * o desafio e o Cardinal não emitir evento, o checkout não pode ficar
   * pendurado -- ao expirar, trata como não autenticado e a política da loja
   * decide (BDD-3DS-011).
   */
  CHALLENGE_TIMEOUT_MS: 5 * 60 * 1000,

  /**
   * @param {number} timeoutMs
   * @param {string} label
   */
  createDeferred: function (timeoutMs, label) {
    var deferred = {};

    deferred.promise = new Promise(function (resolve, reject) {
      deferred._resolve = resolve;
      deferred._reject = reject;
    });

    if (timeoutMs) {
      deferred._timer = setTimeout(function () {
        deferred._reject(new Error(label + ' timeout'));
      }, timeoutMs);
    }

    deferred.settle = function (value) {
      if (deferred._timer) {
        clearTimeout(deferred._timer);
      }
      deferred._resolve(value);
    };

    deferred.fail = function (error) {
      if (deferred._timer) {
        clearTimeout(deferred._timer);
      }
      deferred._reject(error);
    };

    return deferred;
  },

  /**
   * Config do MPI v3. O SDK (Scripts/V3/mpi.js) recebe TODOS os callbacks
   * aqui dentro -- `MPI.load()` aceita um único argumento, e
   * `MPI.init()`/`MPI.updateCard()`/`MPI.challenge()` não aceitam callback
   * nenhum. Passar callbacks posicionais para essas funções (como a versão
   * anterior deste driver fazia) faz o SDK ignorá-los silenciosamente.
   */
  mpiConfig: function () {
    var self = this;

    return {
      // O SDK usa "SDB"/"PRD" para escolher o script do Cardinal; qualquer
      // outra chave cai no default "PRD" (carregaria o Cardinal de produção
      // no sandbox).
      Environment: this.isTestEnvironment ? 'SDB' : 'PRD',
      Debug: this.isTestEnvironment,

      // MPI.updateCard() lê o cartão por aqui (não por parâmetro).
      cardNumberReader: function () {
        return self.collectCardData().cardNumber;
      },

      onLoadComplete: function () {
        self.mpiLoaded = true;
        self.log('MPI onLoadComplete');
        self.settlePending('pendingLoad', true);
      },

      onReady: function () {
        self.log('MPI onReady');
        self.settlePending('pendingReady', true);
      },

      // Dispara para SUCCESS, NOACTION e FAILURE -- receber o evento NÃO
      // significa autenticado, então sempre seguimos para o validate e o
      // backend decide (BDD-3DS-026/027).
      onValidationRequired: function (event) {
        self.log('MPI onValidationRequired', event);
        self.settlePending('pendingValidation', event && event.TransactionId ? event.TransactionId : '');
      },

      onError: function (error) {
        self.log('MPI onError', error);
        self.reportSdkError(error);

        var pendingError = new Error('MPI onError');
        self.failPending('pendingLoad', pendingError);
        self.failPending('pendingReady', pendingError);
        self.failPending('pendingValidation', pendingError);
      },
    };
  },

  /**
   * @param {string} key
   * @param {*} value
   */
  settlePending: function (key, value) {
    var deferred = this[key];

    if (deferred) {
      this[key] = null;
      deferred.settle(value);
    }
  },

  /**
   * @param {string} key
   * @param {Error} error
   */
  failPending: function (key, error) {
    var deferred = this[key];

    if (deferred) {
      this[key] = null;
      deferred.fail(error);
    }
  },

  /**
   * Reporta os códigos de erro do SDK (MPI901/MPI902 etc.) para o log do
   * servidor, sem expor detalhe técnico ao comprador (BDD-3DS-034/039).
   */
  reportSdkError: function (error) {
    if (!error || typeof console === 'undefined') {
      return;
    }

    console.error(
      '[BraspagAuth3dsV3] MPI error',
      error.ReturnCode || '',
      error.ReturnMessage || ''
    );
  },

  isBpmpiEnabled: function () {
    return this.isBpmpiEnabledCC || this.isBpmpiEnabledDC;
  },

  log: function () {
    if (this.isTestEnvironment && typeof console !== 'undefined') {
      console.log.apply(console, ['[BraspagAuth3dsV3]'].concat(Array.prototype.slice.call(arguments)));
    }
  },

  registerPaymentMethodEvents: function () {
    var self = this;
    var credit = document.querySelector('#payment_method_braspag_creditcard');
    var debit = document.querySelector('#payment_method_braspag_debitcard');

    [credit, debit].forEach(function (method) {
      if (!method) {
        return;
      }

      if (method.checked) {
        self.startSession();
      }

      method.addEventListener('change', function () {
        if (method.checked) {
          self.startSession();
        }
      });
    });
  },

  /**
   * Etapa 1: init (AJAX) -> MPI.load() -> MPI.init() -> MPI.updateCard().
   * Idempotente: só executa uma vez por página (mesmo padrão do v2 com
   * `transactionStarted`).
   *
   * O evento 'change' dos radios de método de pagamento pode disparar mais
   * de uma vez antes da primeira chamada terminar (ex.: WooCommerce
   * re-renderiza os métodos e reemite 'change' durante update_checkout) --
   * sem memoização da Promise em andamento, isso chama `braspag_mpi_v3_init`
   * duas vezes com o mesmo orderNumber (hash do carrinho) e a Cielo rejeita
   * a segunda com HTTP 409 (sessão já existe para esse pedido).
   */
  startSession: function () {
    var self = this;

    if (this.sessionReady) {
      return Promise.resolve(true);
    }

    if (!this.isBpmpiEnabled() || typeof MPI === 'undefined') {
      return Promise.resolve(false);
    }

    if (this.sessionPromise) {
      return this.sessionPromise;
    }

    this.sessionPromise = this.ajaxInit()
      .then(function (data) {
        self.referenceId = data.referenceId || '';
        self.orderNumber = data.orderNumber || '';

        // Sequência exigida pelo SDK: load (aguarda onLoadComplete) ->
        // init (dispara Cardinal.setup, aguarda onReady) -> updateCard.
        return self.loadSdk().then(function () {
          self.pendingReady = self.createDeferred(self.SDK_TIMEOUT_MS, 'MPI.onReady');
          MPI.init(data.referenceId, data.token);

          return self.pendingReady.promise;
        });
      })
      .then(function () {
        MPI.updateCard();
        self.sessionReady = true;
        self.log('sessão 3DS pronta', self.referenceId);

        return true;
      })
      .catch(function (error) {
        self.log('startSession failed', error);
        self.sessionReady = false;
        self.sessionPromise = null;
        return false;
      });

    return this.sessionPromise;
  },

  /**
   * `MPI.load()` só dispara `onLoadComplete` na PRIMEIRA carga (depois disso
   * faz early-return no flag interno `_loaded`), então numa retentativa não
   * podemos esperar o evento de novo -- só reaplicamos o config (para
   * re-registrar callbacks/cardNumberReader) e seguimos.
   */
  loadSdk: function () {
    if (this.mpiLoaded) {
      MPI.load(this.mpiConfig());

      return Promise.resolve(true);
    }

    this.pendingLoad = this.createDeferred(this.SDK_TIMEOUT_MS, 'MPI.onLoadComplete');
    MPI.load(this.mpiConfig());

    return this.pendingLoad.promise;
  },

  ajaxInit: function () {
    var self = this;

    return new Promise(function (resolve, reject) {
      jQuery.post(self.params.ajaxUrl, {
        action: 'braspag_mpi_v3_init',
        nonce: self.params.initNonce,
      })
        .done(function (response) {
          if (response && response.success) {
            resolve(response.data);
          } else {
            reject(response);
          }
        })
        .fail(reject);
    });
  },

  /**
   * A Cielo exige o objeto 'card' (com cardNumber) não-vazio em
   * 3ds/enroll -- lê os campos já presentes no formulário clássico do
   * checkout (o PAN já trafega por este mesmo backend na submissão do
   * pedido, então isso não amplia o escopo PCI já existente do plugin).
   */
  collectCardData: function () {
    var prefix = 'braspag_' + this.paymentType;
    var numberEl = document.querySelector('#' + prefix + '-card-number');
    var expiryEl = document.querySelector('#' + prefix + '-card-expiry');
    var brandEl = document.querySelector('#' + prefix + '-card-type');

    var expiryParts = (expiryEl && expiryEl.value ? expiryEl.value : '').split('/');
    var year = (expiryParts[1] || '').replace(/\D+/g, '');
    // Campo é MM/YY (2 dígitos); normaliza pro formato de 4 dígitos que a
    // Cielo espera (mesma normalização já usada no builder do Pagador,
    // class-wc-gateway-braspag-creditcard.php:577).
    if (year.length === 2) {
      year = '20' + year;
    }

    return {
      cardNumber: numberEl && numberEl.value ? numberEl.value.replace(/\D+/g, '') : '',
      cardExpirationMonth: (expiryParts[0] || '').replace(/\D+/g, ''),
      cardExpirationYear: year,
      // 'credit'/'debit' -- distingue cartões dual-function (documentado
      // como card.paymentMethod pela Cielo); NÃO é a bandeira do cartão.
      paymentMethod: this.paymentType === 'debitcard' ? 'debit' : 'credit',
      // Bandeira ('Visa', 'Master', 'Elo'...), preenchida por braspag.js no
      // campo oculto de card-type. Usada no backend só para decidir se o
      // Data Only se aplica (Mastercard/Visa).
      cardBrand: brandEl && brandEl.value ? brandEl.value : '',
    };
  },

  ajaxEnroll: function (browserInfo) {
    var self = this;
    var cardData = this.collectCardData();

    return new Promise(function (resolve, reject) {
      jQuery.post(self.params.ajaxUrl, {
        action: 'braspag_mpi_v3_enroll',
        nonce: self.params.enrollNonce,
        referenceId: self.referenceId,
        browserInfo: JSON.stringify(browserInfo || {}),
        cardNumber: cardData.cardNumber,
        cardExpirationMonth: cardData.cardExpirationMonth,
        cardExpirationYear: cardData.cardExpirationYear,
        cardPaymentMethod: cardData.paymentMethod,
        cardBrand: cardData.cardBrand,
      })
        .done(function (response) {
          if (response && response.success) {
            resolve(response.data);
          } else {
            reject(response);
          }
        })
        .fail(reject);
    });
  },

  /**
   * Só é chamado após o challenge (status=2 do enroll) ser resolvido --
   * quando o enroll já retorna status=1, o resultado final (Cavv/Xid/Eci/
   * Version) já vem no próprio enroll (ver runAuthentication()), sem
   * precisar deste endpoint. Exige o `transactionId` devolvido pelo
   * `Challenge` do enroll, não um `referenceId`.
   *
   * @param {string} transactionId
   */
  ajaxValidate: function (transactionId) {
    var self = this;
    var cardData = this.collectCardData();

    return new Promise(function (resolve, reject) {
      jQuery.post(self.params.ajaxUrl, {
        action: 'braspag_mpi_v3_validate',
        nonce: self.params.validateNonce,
        transactionId: transactionId,
        cardNumber: cardData.cardNumber,
        cardExpirationMonth: cardData.cardExpirationMonth,
        cardExpirationYear: cardData.cardExpirationYear,
      })
        .done(function (response) {
          if (response && response.success) {
            resolve(response.data);
          } else {
            reject(response);
          }
        })
        .fail(reject);
    });
  },

  /**
   * Etapa 2/3: chamado a partir de braspag.placeOrder() antes do submit.
   * Resolve quando os campos `.bpmpi_v3_*` já estão preenchidos e é seguro
   * liberar o submit do form; rejeita/reseta os campos com o failure_type
   * apropriado em caso de erro (o backend decide bloquear ou não via
   * WC_Braspag_Auth3ds_V3_Gate, a partir do failure_type enviado).
   */
  runAuthentication: function (paymentMethod) {
    var self = this;

    if (paymentMethod === 'braspag_creditcard') {
      this.paymentType = 'creditcard';
    } else if (paymentMethod === 'braspag_debitcard') {
      this.paymentType = 'debitcard';
    } else {
      return Promise.resolve(true);
    }

    if (!this.isBpmpiEnabled()) {
      return Promise.resolve(true);
    }

    return this.startSession()
      .then(function () {
        var browserInfo = (typeof MPIHelpers !== 'undefined') ? MPIHelpers.getBrowserInfo() : {};
        return self.ajaxEnroll(browserInfo);
      })
      .then(function (enrollData) {
        var status = String(enrollData.status);

        // O backend informa se a tentativa rodou em Data Only (a bandeira
        // precisa ser Mastercard/Visa) -- isso vai para o
        // ExternalAuthentication.DataOnly na autorização.
        self.dataOnly = !!enrollData.dataOnly;

        if (status === '2') {
          return self.handleChallenge(enrollData.challengeData);
        }

        if (status === '1') {
          // O resultado final (Cavv/Xid/Eci/Version) já vem no próprio
          // enroll quando status=1 -- o VALIDATE só existe para confirmar
          // a autenticação depois de um challenge (status=2), não precisa
          // ser chamado aqui.
          self.applyAuthenticationResult(enrollData);
          return true;
        }

        // Data Only também responde Status=0, mas com Reason.Code '100'
        // (Success) e sem Challenge: é um fluxo frictionless concluído, só
        // sem liability shift (RN-3DS-012) -- não é falha, então segue pelo
        // caminho de sucesso (o DataOnly=true na autorização é que comunica
        // a ausência de liability shift).
        if (self.dataOnly && String(enrollData.reasonCode) === '100') {
          self.applyAuthenticationResult(enrollData);
          return true;
        }

        // status 0 (não autenticado/não enrolado): a Cielo ainda devolve o
        // Eci (e a doc orienta avaliá-lo para decidir se prossegue), então
        // preenchemos os campos disponíveis e deixamos a decisão de
        // "autorizar mesmo assim" para o backend
        // (auth3ds20_mpi_authorize_on_unenrolled), aplicada no builder do
        // Pagador a partir do failure_type '2'.
        self.applyAuthenticationResult(enrollData);
        self.setFailureType('2');
        return true;
      })
      .catch(function (error) {
        self.log('runAuthentication failed', error);
        self.setFailureType('4');
        return true;
      });
  },

  /**
   * Exibe o challenge do emissor. O 2º parâmetro de `MPI.challenge()` é o
   * objeto `order` (repassado ao `Cardinal.continue`), NÃO um objeto de
   * callbacks -- o resultado chega pelo handler `onValidationRequired` do
   * config, e vale para sucesso e falha, então sempre seguimos para o
   * validate e o backend decide.
   *
   * @param {{acsUrl:string, payload:string, transactionId:string}} challengeData
   */
  handleChallenge: function (challengeData) {
    var self = this;

    // INV-003 / BDD-3DS-018: Data Only nunca abre challenge. Se vier um
    // challenge numa tentativa Data Only, é anomalia: registra e trata como
    // não autenticado, sem exibir desafio ao comprador.
    if (this.dataOnly) {
      this.log('challenge recebido em transação Data Only -- anomalia, desafio não exibido');
      this.setFailureType('1');

      return Promise.resolve(true);
    }

    if (typeof MPI === 'undefined' || !MPI.challenge || !challengeData || !challengeData.acsUrl) {
      this.setFailureType('1');

      return Promise.resolve(true);
    }

    this.pendingValidation = this.createDeferred(this.CHALLENGE_TIMEOUT_MS, 'MPI.onValidationRequired');

    try {
      MPI.challenge(challengeData, this.buildChallengeOrder());
    } catch (error) {
      this.failPending('pendingValidation', error);
      this.log('MPI.challenge falhou', error);
      this.setFailureType('4');

      return Promise.resolve(true);
    }

    return this.pendingValidation.promise
      .then(function (transactionId) {
        return self.runValidate(transactionId || challengeData.transactionId);
      })
      .catch(function (error) {
        // Timeout (comprador abandonou) ou onError do SDK.
        self.log('challenge não concluído', error);
        self.setFailureType('1');

        return true;
      });
  },

  /**
   * Objeto `order` exigido pelo 2º parâmetro de `MPI.challenge()`.
   *
   * Não usar `withCartItem()`: `MPIHelpers.getOrderBuilder()` devolve um
   * singleton compartilhado cujo array `Cart` acumula a cada push, então
   * chamadas repetidas contaminariam o pedido seguinte.
   */
  buildChallengeOrder: function () {
    if (typeof MPIHelpers === 'undefined') {
      return {};
    }

    var card = this.collectCardData();

    return MPIHelpers.getOrderBuilder()
      .withOrderDetails(
        this.orderNumber || '',
        MPIHelpers.Constants.getCurrencyISO().BRL,
        MPIHelpers.Constants.getOrderChannels().ECOMMERCE
      )
      .withCard(card.cardNumber, card.cardExpirationMonth, card.cardExpirationYear)
      .build();
  },

  /**
   * Chamado só após o challenge resolver -- pede a confirmação final
   * (`braspag_mpi_v3_validate`) usando o `transactionId` do challenge.
   *
   * @param {string} transactionId
   */
  runValidate: function (transactionId) {
    var self = this;

    return this.ajaxValidate(transactionId)
      .then(function (data) {
        self.applyAuthenticationResult(data);
        return true;
      })
      .catch(function (error) {
        self.log('runValidate failed', error);
        self.setFailureType('1');
        return true;
      });
  },

  /**
   * Preenche os campos `.bpmpi_v3_*` com o resultado final da
   * autenticação -- vem direto do enroll quando status=1, ou do validate
   * quando houve challenge (status=2).
   *
   * @param {{cavv:string, xid:string, eci:string, version:string}} data
   */
  applyAuthenticationResult: function (data) {
    jQuery('.bpmpi_v3_cavv').val(data.cavv || '');
    jQuery('.bpmpi_v3_xid').val(data.xid || '');
    jQuery('.bpmpi_v3_eci').val(data.eci || '');
    jQuery('.bpmpi_v3_version').val(data.version || '');
    jQuery('.bpmpi_v3_reference_id').val(this.referenceId || '');
    jQuery('.bpmpi_v3_data_only').val(this.dataOnly ? '1' : '');
    jQuery('.bpmpi_v3_failure_type').val('0');
  },

  setFailureType: function (failureType) {
    jQuery('.bpmpi_v3_failure_type').val(failureType);
  },

  /**
   * Chamado por `braspag.placeOrder()` (assets/js/braspag.js) no lugar do
   * antigo `bpmpi.placeOrder(form)` do v2 — mesmo nome de variável global
   * (`bpmpi`) e mesma assinatura, para não precisar tocar em braspag.js.
   * Só envia o form depois que os campos `.bpmpi_v3_*` estiverem
   * preenchidos (ou o failure_type setado), preservando o padrão de
   * "esperar um evento antes de liberar o submit" do driver v2.
   *
   * @param {jQuery} form
   */
  placeOrder: async function (form) {
    var paymentMethod = jQuery(form).find('input[name="payment_method"]:checked').val();

    await this.runAuthentication(paymentMethod);

    jQuery(form).submit();
    return true;
  },
};

var bpmpi = new BraspagAuth3dsV3;
