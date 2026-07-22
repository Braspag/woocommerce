/* global wc */
(function () {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { __ } = wp.i18n;
    const { createElement: el, Fragment } = wp.element;

    const settings = getSetting('braspag_pix_data', {});

    const Content = () =>
        el(
            Fragment,
            null,
            settings.description
                ? el('div', { className: 'wc-braspag-blocks-desc' }, settings.description)
                : null,
            el('div', 
                { 
                    className: 'wc-braspag-blocks-document-notice',
                    style: {
                        padding: '12px',
                        backgroundColor: '#f0f6ff',
                        border: '1px solid #c3dafe',
                        borderRadius: '6px',
                        margin: '12px 0',
                        fontSize: '14px',
                        color: '#1e40af'
                    }
                },
                el('strong', null, '📄 Documento obrigatório: '),
                'Certifique-se de preencher seu CPF ou CNPJ nos dados de cobrança para utilizar o PIX.'
            )
        );

    registerPaymentMethod({
        name: 'braspag_pix',
        label: 'Braspag - ' + settings.title || __('Braspag - Pix', 'woocommerce-braspag'),
        ariaLabel: 'Braspag - ' + settings.title || __('Braspag - Pix', 'woocommerce-braspag'),
        canMakePayment: () => true,
        content: el(Content, null),
        edit: el(Content, null),
        supports: settings.supports || { features: ['products'] },

        // Pix não precisa mandar payment_data extra.
        getPaymentMethodData: () => ({}),
    });
})();
