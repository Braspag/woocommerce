<?php

/**
 * Braspag - Cart/Checkout Blocks Abstract Payment Method
 */
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class WC_Braspag_Blocks_Abstract extends AbstractPaymentMethodType
{
    /** @var array */
    protected $settings = [];

    protected function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    protected function is_enabled()
    {
        return $this->get_setting('enabled', 'no') === 'yes';
    }
}
