/* global wc */
(function () {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { __ } = wp.i18n;
    const { createElement: el, Fragment } = wp.element;
    const settings = getSetting('braspag_data', {});

    const Content = () =>
        el(
            Fragment,
            null,
            null
        );

    registerPaymentMethod({
        name: 'braspag',
        label: settings.title || __('Braspag', 'woocommerce-braspag'),
        ariaLabel: settings.title || __('Braspag', 'woocommerce-braspag'),

        // 👇 nunca deixa aparecer no checkout/cart blocks
        canMakePayment: () => false,

        // 👇 evita erro: "content must be a React element or null"
        content: el(Content, null),
        edit: el(Content, null),

        supports: settings.supports || { features: ['products'] },
    });
})();
