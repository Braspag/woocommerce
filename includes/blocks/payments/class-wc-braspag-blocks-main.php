<?php
if (!defined('ABSPATH'))
    exit;

final class WC_Braspag_Blocks_Main extends WC_Braspag_Blocks_Abstract
{
    /**
     * TEM que bater com o ID do gateway base:
     * $this->id = 'braspag'
     */
    protected $name = 'braspag';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_braspag_settings', []);
    }

    public function is_active()
    {
        // Se o gateway base estiver habilitado no Woo, o Blocks entende como "ativo".
        // A gente mantém true pra ele ser "compatível" e parar o aviso.
        return $this->is_enabled();
    }

    public function get_payment_method_script_handles()
    {
        $handle = 'wc-braspag-blocks-main';

        wp_register_script(
            $handle,
            plugins_url('assets/js/blocks/braspag-main.js', WC_BRASPAG_MAIN_FILE),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'],
            WC_BRASPAG_VERSION,
            true
        );

        return [$handle];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title', __('Braspag', 'woocommerce-braspag')),
            'description' => $this->get_setting('description', ''),
            // padroniza no formato correto:
            'supports' => ['features' => ['products']],
        ];
    }
}
