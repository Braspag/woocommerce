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
        add_action('woocommerce_payment_tokens_cleanup', array($this, 'delete_expired_payment_tokens'));
        // Agendar a rotina para rodar diariamente
        function schedule_token_cleanup()
        {
            if (!as_next_scheduled_action('woocommerce_payment_tokens_cleanup')) {
                as_schedule_recurring_action(time(), DAY_IN_SECONDS, 'woocommerce_payment_tokens_cleanup');
            }
        }
        add_action('init', 'schedule_token_cleanup');
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
     * Delete Expired Payment Token
     * @param $customer_id
     * @param string $gateway_id
     * @return void
     */
    public function delete_expired_payment_tokens($gateway_id = 'braspag')
    {
        // Obter todos os tokens de pagamento salvos no WooCommerce
        $args = array(
            'token_id' => '',
            'user_id' => '',
            'gateway_id' => '',
            'type' => '',
        );
        $tokens = WC_Payment_Tokens::get_tokens($args);

        WC_Braspag_Logger::log(
            "Debug: Tokens: " . print_r(count($tokens), true)
        );

        if (empty($tokens)) {
            WC_Braspag_Logger::log(
                "Info: Nenhum token encontrado para verificação de expiração."
            );
            return;
        }

        foreach ($tokens as $token) {
            // Certifique-se de que é um token relacionado ao Braspag
            if ($token->get_gateway_id() !== 'braspag') {
                continue;
            }

            // Verifique se o token possui uma data de expiração
            $expiry_month = $token->get_meta('expiry_month');
            $expiry_year = $token->get_meta('expiry_year');

            // Pula tokens sem data de expiração
            if (!$expiry_month || !$expiry_year) {
                continue;
            }

            // Verifica se o token está expirado
            $current_year = (int) current_time('Y');
            $current_month = (int) current_time('m');

            if ($expiry_year < $current_year || ($expiry_year === $current_year && $expiry_month < $current_month)) {
                // Exclui o token localmente
                $token->delete();

                // TODO
                // Opcional: Excluir o token no sistema Braspag
                // $customer = new WC_Braspag_Customer();
                // $source_id = $token->get_token();
                // if (!$customer->delete_source($source_id)) {
                //     WC_Braspag_Logger::log(
                //         "Error: Ao excluir o token {$source_id} no Braspag."
                //     );
                // } else {
                //     WC_Braspag_Logger::log(
                //         "Info: Token {$source_id} excluído com sucesso no Braspag."
                //     );
                // }
            }
        }

        WC_Braspag_Logger::log(
            "Info: Rotina de exclusão de tokens expirados concluída."
        );
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
    public static function is_customer_token_already_saved($customer_id, $gateway_id = '', $token = null)
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
     * @return bool|WC_Payment_Token
     */
    public static function get_customer_token($customer_id, $gateway_id = '', $token = null)
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