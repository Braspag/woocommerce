<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WC_Braspag_Blocks_Boleto extends WC_Braspag_Blocks_Abstract
{
    protected $name = 'braspag_boleto';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_braspag_boleto_settings', []);
    }

    public function is_active()
    {
        return $this->is_enabled();
    }

    public function get_payment_method_script_handles()
    {
        $handle = 'wc-braspag-blocks-boleto';

        wp_register_script(
            $handle,
            plugins_url('assets/js/blocks/braspag-boleto.js', WC_BRASPAG_MAIN_FILE),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'],
            WC_BRASPAG_VERSION,
            true
        );

        return [$handle];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title', __('Boleto', 'woocommerce-braspag')),
            'description' => $this->get_setting('description', ''),
            'supports' => ['products'],
        ];
    }
}
