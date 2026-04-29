/* global wc, sop, bpmpi, verify, braspag, jQuery, bpSop_silentOrderPost */
(function () {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { __ } = wp.i18n;
    const { createElement: el, Fragment, useEffect, useRef, useState } = wp.element;

    const settings = getSetting('braspag_creditcard_data', {});

    const braspagCards = [
        { type: 'visa', typeName: 'Visa', patterns: [4], regex_include: '^(4)', regex_exclude: '', format: /\d{1,4}/g, length: [13, 16], logo: 'visa' },
        { type: 'mastercard', typeName: 'Master', patterns: [51, 52, 53, 54, 55, 22, 23, 24, 25, 26, 27], regex_include: '^(5[1-5]|2[2-7])', regex_exclude: '', format: /\d{1,4}/g, length: [16], logo: 'mastercard' },
        { type: 'amex', typeName: 'Amex', patterns: [34, 37], regex_include: '^(34|37)', regex_exclude: '', format: /(\d{1,4})(\d{1,6})?(\d{1,5})?/, length: [15], logo: 'amex' },
        { type: 'elo', typeName: 'Elo', patterns: [6363, 4389, 5041, 4514, 6362, 5067, 4576, 4011], regex_include: '', regex_exclude: '', format: /\d{1,4}/g, length: [16], logo: 'elo' },
        { type: 'hipercard', typeName: 'Hipercard', patterns: [38, 60, 6062, 6370, 6375, 6376], regex_include: '', regex_exclude: '', format: /\d{1,4}/g, length: [16], logo: 'hipercard' },
        { type: 'diners', typeName: 'Diners', patterns: [30, 36, 38, 39], regex_include: '^(36)', regex_exclude: '', format: /(\d{1,4})(\d{1,6})?(\d{1,4})?/, length: [14], logo: 'diners' },
        { type: 'discover', typeName: 'Discover', patterns: [6011, 622, 64, 65], regex_include: '', regex_exclude: '', format: /\d{1,4}/g, length: [16], logo: 'discover' },
    ];

    function getCardInfoFromNumber(num) {
        const sanitized = String(num || '').replace(/\D/g, '');

        for (let index = 0; index < braspagCards.length; index++) {
            const card = braspagCards[index];

            if (card.regex_include && new RegExp(card.regex_include).test(sanitized)) {
                return card;
            }

            for (let patternIndex = 0; patternIndex < card.patterns.length; patternIndex++) {
                const pattern = String(card.patterns[patternIndex]);
                if (sanitized.substr(0, pattern.length) === pattern) {
                    return card;
                }
            }
        }

        return null;
    }

    function formatCardNumber(num) {
        let sanitized = String(num || '').replace(/\D/g, '');
        const card = getCardInfoFromNumber(sanitized);

        if (!card) {
            return sanitized.replace(/(.{4})/g, '$1 ').trim();
        }

        sanitized = sanitized.slice(0, card.length[card.length.length - 1]);

        if (card.format.global) {
            return (sanitized.match(card.format) || []).join(' ');
        }

        const groups = card.format.exec(sanitized);
        if (!groups) {
            return sanitized;
        }

        groups.shift();
        return groups.filter(Boolean).join(' ');
    }

    function formatExpiry(value) {
        const sanitized = String(value || '').replace(/\D/g, '').slice(0, 6);

        if (sanitized.length <= 2) {
            return sanitized;
        }

        return sanitized.slice(0, 2) + '/' + sanitized.slice(2);
    }

    function normalizeExpiry(value) {
        const formatted = String(value || '').replace(/\s+/g, '');
        const match = formatted.match(/^(\d{2})\/(\d{2}|\d{4})$/);

        if (!match) {
            return formatted;
        }

        let year = match[2];
        if (year.length === 4) {
            year = year.slice(2);
        }

        return match[1] + '/' + year;
    }

    function getCardLogo(card) {
        if (!card) {
            return null;
        }

        return (settings.assets_url || '') + card.logo + '.svg';
    }

    function buildInstallments() {
        const installments = settings.installments || {};

        if (!installments || typeof installments !== 'object') {
            return [{ value: '1', label: __('1x', 'woocommerce-braspag') }];
        }

        const entries = Object.keys(installments).map((key) => ({
            value: String(key),
            label: String(installments[key]),
        }));

        return entries.length ? entries : [{ value: '1', label: __('1x', 'woocommerce-braspag') }];
    }

    function getInputValue(id, fallback = '') {
        const input = document.getElementById(id);
        return input ? String(input.value || '') : fallback;
    }

    function getErrorResponse(emitResponse, message) {
        return {
            type: emitResponse.responseTypes.ERROR,
            messageContext: emitResponse.noticeContexts.PAYMENTS,
            message,
        };
    }

    function buildPaymentMethodData() {
        return {
            payment_method: 'braspag_creditcard',
            'braspag_creditcard-card-holder': getInputValue('braspag_creditcard-card-holder'),
            'braspag_creditcard-card-number': getInputValue('braspag_creditcard-card-number').replace(/\s+/g, ''),
            'braspag_creditcard-card-expiry': normalizeExpiry(getInputValue('braspag_creditcard-card-expiry')),
            'braspag_creditcard-card-cvc': getInputValue('braspag_creditcard-card-cvc'),
            'braspag_creditcard-card-type': getInputValue('braspag_creditcard-card-type'),
            'braspag_creditcard-card-installments': getInputValue('braspag_creditcard-card-installments', '1'),
            'wc-braspag_creditcard-new-payment-method': document.getElementById('wc-braspag_creditcard-new-payment-method')?.checked ? 'true' : 'false',
            'braspag_creditcard-card-paymenttoken': getInputValue('braspag_creditcard-card-paymenttoken'),
            'braspag_creditcard-card-cardtoken': getInputValue('braspag_creditcard-card-cardtoken'),
            bpmpi_auth_cavv: getInputValue('bpmpi_auth_cavv'),
            bpmpi_auth_xid: getInputValue('bpmpi_auth_xid'),
            bpmpi_auth_eci: getInputValue('bpmpi_auth_eci'),
            bpmpi_auth_version: getInputValue('bpmpi_auth_version'),
            bpmpi_auth_reference_id: getInputValue('bpmpi_auth_reference_id'),
            bpmpi_auth_failure_type: getInputValue('bpmpi_auth_failure_type', '0'),
        };
    }

    function buildVerifyPayload(cardDetails) {
        return {
            Card: {
                CardNumber: cardDetails.cardNumber,
                Holder: cardDetails.holderName,
                ExpirationDate: cardDetails.expirationDate,
                SecurityCode: cardDetails.securityCode,
                Brand: cardDetails.brand,
                Type: cardDetails.cardType,
            },
            Provider: 'Simulado',
        };
    }

    async function runVerifyProcess(cardDetails) {
        if (!settings.verify_enabled) {
            return true;
        }

        if (typeof verify === 'undefined' || !verify.isVerifyEnabled()) {
            return true;
        }

        await verify.verify(buildVerifyPayload(cardDetails));
        return true;
    }

    async function run3dsProcess() {
        if (!settings.auth3ds20_enabled) {
            return true;
        }

        if (typeof bpmpi === 'undefined' || !bpmpi.isBpmpiEnabled()) {
            return true;
        }

        bpmpi.paymentType = 'creditcard';
        bpmpi.transactionStarted = false;

        await bpmpi.startTransaction();
        await bpmpi.renderData();
        await bpmpi.getAuthenticateData();

        return true;
    }

    async function runSopProcess() {
        if (!settings.sop_enabled) {
            return true;
        }

        if (typeof sop === 'undefined' || !sop.isSopEnabled()) {
            return true;
        }

        if (typeof bpSop_silentOrderPost === 'undefined') {
            throw new Error(__('Biblioteca do SOP não foi carregada.', 'woocommerce-braspag'));
        }

        const paymentTokenField = document.getElementById('braspag_creditcard-card-paymenttoken');
        const cardTokenField = document.getElementById('braspag_creditcard-card-cardtoken');

        if (paymentTokenField) {
            paymentTokenField.value = '';
        }

        if (cardTokenField) {
            cardTokenField.value = '';
        }

        return new Promise((resolve, reject) => {
            const timeout = window.setTimeout(() => {
                reject(new Error(__('Não foi possível tokenizar o cartão com o SOP.', 'woocommerce-braspag')));
            }, 15000);

            const fakeForm = {
                submit: function () {
                    window.clearTimeout(timeout);
                    resolve(true);
                },
            };

            try {
                sop.processSop(fakeForm);
            } catch (error) {
                window.clearTimeout(timeout);
                reject(error);
            }
        });
    }

    function HiddenInteropFields(props) {
        const cartTotal = props?.billing?.cartTotal?.value || '';

        return el(
            Fragment,
            null,
            el('input', { type: 'hidden', id: 'braspag_creditcard-card-type-card', className: 'wc-credit-card-form-card-type-card bp-sop-cardtype', value: 'creditCard' }),
            el('input', { type: 'hidden', id: 'braspag_creditcard-card-cardtoken', className: 'wc-credit-card-form-card-cardtoken', defaultValue: '' }),
            el('input', { type: 'hidden', id: 'braspag_creditcard-card-paymenttoken', className: 'wc-credit-card-form-card-paymenttoken', defaultValue: '' }),
            el('div', { id: 'bpsop_data', style: { display: 'none' } },
                el('div', { id: 'bpsop_data_token' },
                    el('input', { type: 'hidden', name: 'bp-sop-cardtype', id: 'bp-sop-cardtype', className: 'bp-sop-cardtype', defaultValue: 'creditCard' }),
                    el('input', { type: 'hidden', name: 'bp-sop-cardexpirationdate', id: 'bp-sop-cardexpirationdate', className: 'bp-sop-cardexpirationdate', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bp-sop-cardnumber', id: 'bp-sop-cardnumber', className: 'bp-sop-cardnumber', defaultValue: '' })
                )
            ),
            el('div', { id: 'bpmpi_data', style: { display: 'none' } },
                el('div', { id: 'bpmpi_data_auth' },
                    el('input', { type: 'hidden', name: 'test_environment', className: 'test_environment', defaultValue: settings.test_mode ? '1' : '0' }),
                    el('input', { type: 'hidden', name: 'bpmpi_accesstoken', className: 'bpmpi_accesstoken', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth', className: 'bpmpi_auth', defaultValue: 'true' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_notifyonly', className: 'bpmpi_auth_notifyonly', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_suppresschallenge', className: 'bpmpi_auth_suppresschallenge', defaultValue: 'false' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_failure_type', id: 'bpmpi_auth_failure_type', className: 'bpmpi_auth_failure_type', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_cavv', id: 'bpmpi_auth_cavv', className: 'bpmpi_auth_cavv', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_xid', id: 'bpmpi_auth_xid', className: 'bpmpi_auth_xid', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_eci', id: 'bpmpi_auth_eci', className: 'bpmpi_auth_eci', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_version', id: 'bpmpi_auth_version', className: 'bpmpi_auth_version', defaultValue: '' }),
                    el('input', { type: 'hidden', name: 'bpmpi_auth_reference_id', id: 'bpmpi_auth_reference_id', className: 'bpmpi_auth_reference_id', defaultValue: '' })
                ),
                el('div', { id: 'bpmpi_data_payment' },
                    el('input', { type: 'hidden', className: 'bpmpi_paymentmethod', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_cardnumber', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_cardexpirationmonth', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_cardexpirationyear', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_installments', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_totalamount', defaultValue: cartTotal }),
                    el('input', { type: 'hidden', className: 'bpmpi_currency', defaultValue: 'BRL' }),
                    el('input', { type: 'hidden', className: 'bpmpi_ordernumber', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_transaction_mode', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_merchant_url', defaultValue: window.location.hostname || '' })
                ),
                el('div', { id: 'bpmpi_data_billto' },
                    el('input', { type: 'hidden', className: 'bpmpi_billto_contactname', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_phonenumber', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_customerid', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_email', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_street1', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_street2', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_city', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_state', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_zipcode', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_billto_country', defaultValue: '' })
                ),
                el('div', { id: 'bpmpi_data_shipto' },
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_sameasbillto', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_addressee', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_phonenumber', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_email', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_street1', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_street2', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_city', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_state', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_zipcode', defaultValue: '' }),
                    el('input', { type: 'hidden', className: 'bpmpi_shipto_country', defaultValue: '' })
                )
            )
        );
    }

    const Content = (props) => {
        const installments = buildInstallments();
        const [cardNumber, setCardNumber] = useState('');
        const [cardBrand, setCardBrand] = useState(null);
        const [expiry, setExpiry] = useState('');
        const validationMessageRef = useRef('');

        function handleCardNumber(event) {
            const raw = event.target.value;
            const formatted = formatCardNumber(raw);
            const detectedCard = getCardInfoFromNumber(raw);

            setCardNumber(formatted);
            setCardBrand(detectedCard);
        }

        function handleExpiry(event) {
            setExpiry(formatExpiry(event.target.value));
        }

        function buildCardDetails() {
            const cardNumberValue = getInputValue('braspag_creditcard-card-number').replace(/\s+/g, '');
            const detectedCard = getCardInfoFromNumber(cardNumberValue);
            const expiryValue = normalizeExpiry(getInputValue('braspag_creditcard-card-expiry'));

            return {
                cardNumber: cardNumberValue,
                holderName: getInputValue('braspag_creditcard-card-holder').trim(),
                expirationDate: expiryValue,
                securityCode: getInputValue('braspag_creditcard-card-cvc').replace(/\s+/g, ''),
                brand: getInputValue('braspag_creditcard-card-type') || detectedCard?.typeName || '',
                cardType: detectedCard?.type || '',
            };
        }

        function validateFields() {
            const cardDetails = buildCardDetails();

            if (!cardDetails.holderName) {
                return __('Informe o nome do titular.', 'woocommerce-braspag');
            }

            if (!cardDetails.cardNumber || cardDetails.cardNumber.length < 13) {
                return __('Informe um número de cartão válido.', 'woocommerce-braspag');
            }

            if (!/^\d{2}\/\d{2}$/.test(cardDetails.expirationDate)) {
                return __('Informe uma data de expiração válida.', 'woocommerce-braspag');
            }

            if (!cardDetails.securityCode || cardDetails.securityCode.length < 3) {
                return __('Informe o código de segurança.', 'woocommerce-braspag');
            }

            if (!cardDetails.brand) {
                return __('Não foi possível identificar a bandeira do cartão.', 'woocommerce-braspag');
            }

            return '';
        }

        useEffect(() => {
            if (!props?.eventRegistration || !props?.emitResponse) {
                return undefined;
            }

            const { onCheckoutValidation, onPaymentSetup } = props.eventRegistration;
            const emitResponse = props.emitResponse;

            const unsubscribeValidation = onCheckoutValidation(async () => {
                const fieldError = validateFields();

                if (fieldError) {
                    validationMessageRef.current = fieldError;
                    return getErrorResponse(emitResponse, fieldError);
                }

                try {
                    const cardDetails = buildCardDetails();

                    if (settings.verify_enabled) {
                        await runVerifyProcess(cardDetails);
                    }

                    if (settings.auth3ds20_enabled) {
                        await run3dsProcess();
                    }

                    if (settings.sop_enabled) {
                        await runSopProcess();
                    }

                    validationMessageRef.current = '';
                    return true;
                } catch (error) {
                    validationMessageRef.current = error?.message || __('Falha ao validar o cartão.', 'woocommerce-braspag');
                    return getErrorResponse(emitResponse, validationMessageRef.current);
                }
            });

            const unsubscribePayment = onPaymentSetup(() => {
                if (validationMessageRef.current) {
                    return getErrorResponse(emitResponse, validationMessageRef.current);
                }

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: buildPaymentMethodData(),
                    },
                };
            });

            return () => {
                unsubscribeValidation();
                unsubscribePayment();
            };
        }, [props?.eventRegistration, props?.emitResponse]);

        return el(
            Fragment,
            null,
            settings.description ? el('div', { className: 'wc-braspag-blocks-desc' }, settings.description) : null,
            el(
                'div',
                { className: 'wc-braspag-blocks-card-form wc-credit-card-form wc-payment-form' },
                el('p', { className: 'form-row form-row-wide' },
                    el('label', { htmlFor: 'braspag_creditcard-card-holder' }, __('Nome do Titular', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_creditcard-card-holder',
                        type: 'text',
                        className: 'input-text wc-braspag-elements-field wc-credit-card-form-card-holder' + (settings.sop_enabled ? ' bp-sop-cardholdername' : ''),
                        autoComplete: 'cc-name',
                    })
                ),
                el('p', { className: 'form-row form-row-wide' },
                    el('label', { htmlFor: 'braspag_creditcard-card-number' }, __('Número do Cartão', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_creditcard-card-number',
                        type: 'tel',
                        className: (settings.sop_enabled ? 'bp-sop-cardnumber ' : '') + 'input-text wc-credit-card-form-braspag-card-number',
                        inputMode: 'numeric',
                        autoComplete: 'cc-number',
                        placeholder: '\u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022',
                        value: cardNumber,
                        onChange: handleCardNumber,
                        maxLength: 23,
                    }),
                    cardBrand ? el('img', {
                        src: getCardLogo(cardBrand),
                        alt: cardBrand.typeName,
                        style: { height: '24px', marginLeft: '8px', verticalAlign: 'middle' },
                    }) : null
                ),
                el('p', { className: 'form-row form-row-first' },
                    el('label', { htmlFor: 'braspag_creditcard-card-expiry' }, __('Data de Expiração (MM/YY)', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_creditcard-card-expiry',
                        type: 'tel',
                        className: 'input-text wc-credit-card-form-card-expiry' + (settings.sop_enabled ? ' bp-sop-cardexpirationdate' : ''),
                        inputMode: 'numeric',
                        autoComplete: 'cc-exp',
                        placeholder: 'MM/YY',
                        value: expiry,
                        onChange: handleExpiry,
                        maxLength: 7,
                    })
                ),
                el('p', { className: 'form-row form-row-last' },
                    el('label', { htmlFor: 'braspag_creditcard-card-cvc' }, __('Código de Segurança', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_creditcard-card-cvc',
                        type: 'tel',
                        className: 'input-text wc-credit-card-form-card-cvc' + (settings.sop_enabled ? ' bp-sop-cardcvv' : ''),
                        inputMode: 'numeric',
                        autoComplete: 'cc-csc',
                        maxLength: 4,
                    })
                ),
                el('input', {
                    type: 'hidden',
                    id: 'braspag_creditcard-card-type',
                    className: 'wc-credit-card-form-card-type',
                    value: cardBrand ? cardBrand.typeName : '',
                    readOnly: true,
                }),
                el('p', { className: 'form-row form-row-last' },
                    el('label', { htmlFor: 'braspag_creditcard-card-installments' }, __('Parcelamento', 'woocommerce-braspag')),
                    el(
                        'select',
                        {
                            id: 'braspag_creditcard-card-installments',
                            className: 'input-text wc-credit-card-form-card-cvc' + (settings.sop_enabled ? ' bp-sop-cardtype' : ''),
                        },
                        installments.map((option) => el('option', { key: option.value, value: option.value }, option.label))
                    )
                ),
                settings.save_card ? el('p', { className: 'form-row form-row-wide' },
                    el('label', { htmlFor: 'wc-braspag_creditcard-new-payment-method' },
                        el('input', { id: 'wc-braspag_creditcard-new-payment-method', type: 'checkbox' }),
                        ' ',
                        __('Salvar cartão para próximas compras', 'woocommerce-braspag')
                    )
                ) : null,
                settings.sop_enabled ? el('p', { className: 'form-row form-row-wide wc-braspag-blocks-document-notice' }, __('SOP habilitado: o checkout usará tokenização para autorizar o cartão.', 'woocommerce-braspag')) : null,
                el(HiddenInteropFields, props)
            )
        );
    };

    registerPaymentMethod({
        name: 'braspag_creditcard',
        label: settings.title || __('Braspag - Cartão de Crédito', 'woocommerce-braspag'),
        ariaLabel: settings.title || __('Braspag - Cartão de Crédito', 'woocommerce-braspag'),
        canMakePayment: () => true,
        content: el(Content, null),
        edit: el(Content, null),
        supports: settings.supports || { features: ['products'] },
        getPaymentMethodData: () => buildPaymentMethodData(),
    });
})();
