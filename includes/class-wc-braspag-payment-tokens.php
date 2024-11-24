<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_Braspag_Payment_Tokens
 */
class WC_Braspag_Payment_Tokens extends WC_Payment_Tokens
{
    private static $_this;

    /**
     * WC_Braspag_Payment_Tokens constructor.
     */
    public function __construct()
    {

        add_action('woocommerce_payment_token_deleted', array($this, 'woocommerce_payment_token_deleted'), 10, 2);
    }

    /**
     * @return WC_Braspag_Payment_Tokens
     */
    public static function get_instance()
    {
        return self::$_this;
    }

    /**
     * @param $customer_id
     * @return bool
     */
    public static function customer_has_saved_methods($customer_id)
    {
        $gateways = array('braspag');

        if (empty($customer_id)) {
            return false;
        }

        $has_token = false;

        foreach ($gateways as $gateway) {
            $tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, $gateway);

            if (!empty($tokens)) {
                $has_token = true;
                break;
            }
        }

        return $has_token;
    }

    /**
     * @param $token_id
     * @param $token
     */
    public function woocommerce_payment_token_deleted($token_id, $token)
    {
        if ($token && $token->get_gateway_id() === 'braspag') {
            $customer_id = get_current_user_id();
            $customer = new WC_Braspag_Customer($customer_id);

            $token = $token->get_token();

            if (method_exists($customer, 'delete_source')) {
                $customer->delete_source($token);
            } else {
                WC_Braspag_Logger::log("O método delete_source() não está implementado na classe WC_Braspag_Customer.");
            }
        }
    }

    /**
     * @param $customer_id
     * @param string $gateway_id
     * @return WC_Payment_Token[]
     */
    public static function get_customer_tokens_types($customer_id, $gateway_id = '')
    {
        $types = [];
        $customer_tokens = parent::get_customer_tokens($customer_id, $gateway_id);

        foreach ($customer_tokens as $customer_token) {
            $types[] = $customer_token->get_meta('card_type');
        }

        $types = array_unique($types);

        return $types;
    }

    /**
     * @param $customer_id
     * @param string $gateway_id
     * @param $token
     * @return bool
     */
    public static function is_customer_token_already_saved($customer_id, $gateway_id = '', $token)
    {
        $customer_tokens = parent::get_customer_tokens($customer_id, $gateway_id);
        foreach ($customer_tokens as $customer_token) {
            if ($customer_token->get_token() == $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $customer_id
     * @param string $gateway_id
     * @param $token
     * @return bool
     */
    public static function get_customer_token($customer_id, $gateway_id = '', $token)
    {
        $customer_tokens = parent::get_customer_tokens($customer_id, $gateway_id);
        foreach ($customer_tokens as $customer_token) {
            if ($customer_token->get_token() == $token) {
                return $customer_token;
            }
        }

        return false;
    }
}

new WC_Braspag_Payment_Tokens();