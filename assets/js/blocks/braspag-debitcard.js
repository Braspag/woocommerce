/* global wc, bpmpi */
(function () {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { __ } = wp.i18n;
    const { createElement: el, Fragment, useEffect, useRef, useState } = wp.element;

    const settings = getSetting('braspag_debitcard_data', {});

    const braspagCards = [
        { type: 'visa', typeName: 'Visa', patterns: [4], regex_include: '^(4)', format: /\d{1,4}/g, length: [13, 16], logo: 'visa' },
        { type: 'maestro', typeName: 'Master', patterns: [5018, 502, 503, 506, 56, 58, 639, 6220, 67], regex_include: '', format: /\d{1,4}/g, length: [12, 13, 14, 15, 16, 17, 18, 19], logo: 'mastercard' },
        { type: 'mastercard', typeName: 'Master', patterns: [51, 52, 53, 54, 55, 22, 23, 24, 25, 26, 27], regex_include: '^(5[1-5]|2[2-7])', format: /\d{1,4}/g, length: [16], logo: 'mastercard' },
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
        return (sanitized.match(card.format) || []).join(' ');
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

        return '/wp-content/plugins/woocommerce-braspag-dev/assets/images/' + card.logo + '.svg';
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
            payment_method: 'braspag_debitcard',
            'braspag_debitcard-card-holder': getInputValue('braspag_debitcard-card-holder'),
            'braspag_debitcard-card-number': getInputValue('braspag_debitcard-card-number').replace(/\s+/g, ''),
            'braspag_debitcard-card-expiry': normalizeExpiry(getInputValue('braspag_debitcard-card-expiry')),
            'braspag_debitcard-card-cvc': getInputValue('braspag_debitcard-card-cvc'),
            'braspag_debitcard-card-type': getInputValue('braspag_debitcard-card-type'),
            bpmpi_auth_cavv: getInputValue('bpmpi_auth_cavv'),
            bpmpi_auth_xid: getInputValue('bpmpi_auth_xid'),
            bpmpi_auth_eci: getInputValue('bpmpi_auth_eci'),
            bpmpi_auth_version: getInputValue('bpmpi_auth_version'),
            bpmpi_auth_reference_id: getInputValue('bpmpi_auth_reference_id'),
            bpmpi_auth_failure_type: getInputValue('bpmpi_auth_failure_type', '0'),
        };
    }

    async function run3dsProcess() {
        if (!settings.auth3ds20_enabled) {
            return true;
        }

        if (typeof bpmpi === 'undefined' || !bpmpi.isBpmpiEnabled()) {
            return true;
        }

        bpmpi.paymentType = 'debitcard';
        bpmpi.transactionStarted = false;

        await bpmpi.startTransaction();
        await bpmpi.renderData();
        await bpmpi.getAuthenticateData();

        return true;
    }

    function HiddenInteropFields(props) {
        const cartTotal = props?.billing?.cartTotal?.value || '';

        return el(
            Fragment,
            null,
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
                    el('input', { type: 'hidden', className: 'bpmpi_installments', defaultValue: '1' }),
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

        function validateFields() {
            const holder = getInputValue('braspag_debitcard-card-holder').trim();
            const number = getInputValue('braspag_debitcard-card-number').replace(/\s+/g, '');
            const securityCode = getInputValue('braspag_debitcard-card-cvc').replace(/\s+/g, '');
            const brand = getInputValue('braspag_debitcard-card-type');
            const rawExpiry = getInputValue('braspag_debitcard-card-expiry');

            if (!holder) {
                return __('Informe o nome do titular.', 'woocommerce-braspag');
            }

            if (!number || number.length < 13) {
                return __('Informe um número de cartão válido.', 'woocommerce-braspag');
            }

            if (!/^\d{2}\/\d{2}$/.test(normalizeExpiry(rawExpiry)) && !/^\d{2}\/\d{4}$/.test(rawExpiry)) {
                return __('Informe uma data de expiração válida.', 'woocommerce-braspag');
            }

            if (!securityCode || securityCode.length < 3) {
                return __('Informe o código de segurança.', 'woocommerce-braspag');
            }

            if (!brand) {
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
                    if (settings.auth3ds20_enabled) {
                        await run3dsProcess();
                    }

                    validationMessageRef.current = '';
                    return true;
                } catch (error) {
                    validationMessageRef.current = error?.message || __('Falha ao validar o cartão de débito.', 'woocommerce-braspag');
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
                    el('label', { htmlFor: 'braspag_debitcard-card-holder' }, __('Nome do Titular', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_debitcard-card-holder',
                        type: 'text',
                        className: 'input-text wc-braspag-elements-field wc-credit-card-form-card-holder',
                        autoComplete: 'cc-name',
                    })
                ),
                el('p', { className: 'form-row form-row-wide' },
                    el('label', { htmlFor: 'braspag_debitcard-card-number' }, __('Número do Cartão', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_debitcard-card-number',
                        type: 'tel',
                        className: 'input-text wc-credit-card-form-braspag-card-number',
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
                    el('label', { htmlFor: 'braspag_debitcard-card-expiry' }, __('Data de Expiração (MM/YY)', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_debitcard-card-expiry',
                        type: 'tel',
                        className: 'input-text wc-credit-card-form-card-expiry',
                        inputMode: 'numeric',
                        autoComplete: 'cc-exp',
                        placeholder: 'MM/YY',
                        value: expiry,
                        onChange: handleExpiry,
                        maxLength: 7,
                    })
                ),
                el('p', { className: 'form-row form-row-last' },
                    el('label', { htmlFor: 'braspag_debitcard-card-cvc' }, __('Código de Segurança', 'woocommerce-braspag')),
                    el('input', {
                        id: 'braspag_debitcard-card-cvc',
                        type: 'tel',
                        className: 'input-text wc-credit-card-form-card-cvc',
                        inputMode: 'numeric',
                        autoComplete: 'cc-csc',
                        maxLength: 4,
                    })
                ),
                el('input', {
                    type: 'hidden',
                    id: 'braspag_debitcard-card-type',
                    className: 'wc-credit-card-form-card-type',
                    value: cardBrand ? cardBrand.typeName : '',
                    readOnly: true,
                }),
                el(HiddenInteropFields, props)
            )
        );
    };

    registerPaymentMethod({
        name: 'braspag_debitcard',
        label: settings.title || __('Braspag - Cartão de Débito', 'woocommerce-braspag'),
        ariaLabel: settings.title || __('Braspag - Cartão de Débito', 'woocommerce-braspag'),
        canMakePayment: () => true,
        content: el(Content, null),
        edit: el(Content, null),
        supports: settings.supports || { features: ['products'] },
        getPaymentMethodData: () => buildPaymentMethodData(),
    });
})();
