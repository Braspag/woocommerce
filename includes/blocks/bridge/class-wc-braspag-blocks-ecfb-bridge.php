<?php
/**
 * Bridge: WooCommerce Blocks Additional Checkout Fields
 * <-> woocommerce-extra-checkout-fields-for-brazil (wcbcf_settings)
 *
 * Objetivo:
 * - Registrar campos no Checkout Blocks via Additional Checkout Fields API
 * - Validar com as mesmas regras do Extra Checkout Fields BR
 * - Espelhar valores para as metas legadas (_billing_cpf, _billing_number, etc)
 */

if (!defined('ABSPATH')) {
	exit;
}

class WC_Braspag_Blocks_ECFB_Bridge
{
	/**
	 * Request REST atual durante o ciclo do Store API.
	 *
	 * @var \WP_REST_Request|null
	 */
	private static $current_rest_request = null;

	/**
	 * Option name do plugin woocommerce-extra-checkout-fields-for-brazil
	 */
	const ECFB_OPTION_NAME = 'wcbcf_settings';

	/**
	 * Namespace dos campos no Blocks (tem que ter "namespace/field")
	 */
	const FIELD_NS = 'braspag-wcbcf';

	/**
	 * Meta keys legadas usadas pelo plugin do Brasil (e pelo teu gateway)
	 */
	const META_BILLING_NUMBER = '_billing_number';
	const META_BILLING_NEIGHBORHOOD = '_billing_neighborhood';
	const META_SHIPPING_NUMBER = '_shipping_number';
	const META_SHIPPING_NEIGHBORHOOD = '_shipping_neighborhood';

	const META_BILLING_PERSONTYPE = '_billing_persontype';
	const META_BILLING_CPF = '_billing_cpf';
	const META_BILLING_RG = '_billing_rg';
	const META_BILLING_CNPJ = '_billing_cnpj';
	const META_BILLING_IE = '_billing_ie';
	const META_BILLING_BIRTHDATE = '_billing_birthdate';
	const META_BILLING_GENDER = '_billing_gender';
	const META_BILLING_CELLPHONE = '_billing_cellphone';

	public static function init()
	{
		add_filter('rest_request_before_callbacks', array(__CLASS__, 'capture_current_rest_request'), 10, 3);
		add_filter('rest_request_after_callbacks', array(__CLASS__, 'release_current_rest_request'), 10, 3);
		add_filter('option_woocommerce_checkout_company_field', array(__CLASS__, 'force_company_field_visibility'));
		add_filter('default_option_woocommerce_checkout_company_field', array(__CLASS__, 'force_company_field_visibility'));

		// Registra campos no Blocks (Additional Checkout Fields API)
		add_action('woocommerce_init', array(__CLASS__, 'register_blocks_fields'), 20);

		// Espelha valores do Blocks para as metas legadas (_billing_*)
		add_action('woocommerce_set_additional_field_value', array(__CLASS__, 'sync_to_legacy_meta'), 10, 4);

		// Prefill (ler valor legado e colocar como default no Blocks)
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/number', array(__CLASS__, 'default_value_number'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/neighborhood', array(__CLASS__, 'default_value_neighborhood'), 10, 3);

		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/persontype', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/cpf', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/rg', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/cnpj', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/ie', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/birthdate', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/gender', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);
		add_filter('woocommerce_get_default_value_for_' . self::FIELD_NS . '/cellphone', array(__CLASS__, 'default_value_generic_billing_meta'), 10, 3);

		/**
		 * Validação “cross-field” e regras condicionais (country, person type…)
		 * Docs: validação por hooks no Blocks (Store API).
		 */
		add_action('woocommerce_blocks_validate_location_address_fields', array(__CLASS__, 'validate_address_fields'), 10, 3);
		add_action('woocommerce_blocks_validate_location_order_fields', array(__CLASS__, 'validate_other_fields'), 10, 3);

		// Remove os campos duplicados injetados pelo WooCommerce Blocks no admin do pedido.
		// O plugin ECFB já exibe/edita _billing_number, _shipping_number, _billing_neighborhood
		// e _shipping_neighborhood via woocommerce_admin_billing/shipping_fields. O WC Blocks
		// injetaria os mesmos dados com chave 'braspag-wcbcf/number' etc. (sem prefixo de grupo
		// no índice do array), gerando duplicatas.
		add_filter('woocommerce_admin_billing_fields', array(__CLASS__, 'remove_duplicate_admin_fields'), 30);
		add_filter('woocommerce_admin_shipping_fields', array(__CLASS__, 'remove_duplicate_admin_fields'), 30);

		add_action('wp_enqueue_scripts', function () {
			if (!function_exists('is_checkout') || !is_checkout())
				return;

			// Só carrega se a página realmente usa o bloco do checkout
			if (function_exists('has_block')) {
				$post = get_post();
				if ($post && !has_block('woocommerce/checkout', $post)) {
					return;
				}
			}

			wp_enqueue_script(
				'braspag-wcbcf-blocks-ui',
				plugins_url('assets/js/blocks/bridge/braspag-wcbcf-blocks-ui.js', WC_BRASPAG_MAIN_FILE),
				array(),
				'1.0.9',
				true
			);
		}, 20);
	}

	private static function get_settings(): array
	{
		$settings = get_option(self::ECFB_OPTION_NAME, array());

		// Se não há settings configuradas, aplicar defaults mínimos para funcionar com Braspag
		if (empty($settings) || !is_array($settings)) {
			$settings = array(
				'person_type' => 1, // PF/PJ selection
				'validate_cpf' => true,
				'validate_cnpj' => true,
				'only_brazil' => true,
			);
		}

		// Garantir que validação esteja habilitada para Braspag
		if (!isset($settings['validate_cpf'])) {
			$settings['validate_cpf'] = true;
		}
		if (!isset($settings['validate_cnpj'])) {
			$settings['validate_cnpj'] = true;
		}

		return $settings;
	}

	public static function capture_current_rest_request($response, $handler, $request)
	{
		if ($request instanceof \WP_REST_Request) {
			self::$current_rest_request = $request;
		}

		return $response;
	}

	public static function release_current_rest_request($response, $handler, $request)
	{
		if (self::$current_rest_request === $request) {
			self::$current_rest_request = null;
		}

		return $response;
	}

	public static function force_company_field_visibility($value)
	{
		if (self::is_checkout_blocks_context()) {
			return 'optional';
		}

		return $value;
	}

	private static function is_checkout_blocks_context(): bool
	{
		$request = self::get_current_rest_request();

		if ($request instanceof \WP_REST_Request) {
			$route = (string) $request->get_route();

			if (false !== strpos($route, '/wc/store/v1/checkout') || false !== strpos($route, '/wc/store/v1/cart')) {
				return true;
			}
		}

		if (!function_exists('is_checkout') || !is_checkout()) {
			return false;
		}

		if (!function_exists('has_block')) {
			return true;
		}

		$post = get_post();

		return !$post || has_block('woocommerce/checkout', $post);
	}

	private static function get_current_rest_request()
	{
		return self::$current_rest_request instanceof \WP_REST_Request ? self::$current_rest_request : null;
	}

	private static function is_partial_checkout_request(): bool
	{
		$request = self::get_current_rest_request();

		if (!$request instanceof \WP_REST_Request) {
			return false;
		}

		if (false === strpos((string) $request->get_route(), '/wc/store/v1/checkout')) {
			return false;
		}

		return in_array($request->get_method(), array('PUT', 'PATCH'), true);
	}

	private static function get_request_address_payload(string $group): array
	{
		$request = self::get_current_rest_request();

		if (!$request instanceof \WP_REST_Request) {
			return array();
		}

		$param = ('shipping' === $group) ? 'shipping_address' : 'billing_address';
		$address = $request->get_param($param);
		$address = is_array($address) ? $address : array();

		if ('shipping' === $group && empty($address)) {
			$billing_address = $request->get_param('billing_address');
			if (is_array($billing_address)) {
				return $billing_address;
			}
		}

		return $address;
	}

	private static function has_request_address_field(string $group, array $keys): bool
	{
		$address = self::get_request_address_payload($group);

		foreach ($keys as $key) {
			if (array_key_exists($key, $address)) {
				return true;
			}
		}

		return false;
	}

	private static function get_request_address_value(string $group, array $keys): string
	{
		$address = self::get_request_address_payload($group);

		foreach ($keys as $key) {
			if (isset($address[$key])) {
				return is_string($address[$key]) ? $address[$key] : '';
			}
		}

		return '';
	}

	private static function get_customer_address_value(string $group, string $key): string
	{
		if (!function_exists('WC') || !WC()->customer) {
			return '';
		}

		$getter = ('billing' === $group) ? 'get_billing' : 'get_shipping';

		if (!method_exists(WC()->customer, $getter)) {
			return '';
		}

		$address = WC()->customer->$getter();

		if (is_array($address) && isset($address[$key])) {
			return is_string($address[$key]) ? $address[$key] : '';
		}

		return '';
	}

	private static function should_validate_missing_value(bool $was_submitted): bool
	{
		return !self::is_partial_checkout_request() || $was_submitted;
	}

	private static function is_brazil_only_required(array $settings, string $country): bool
	{
		$only_brazil = isset($settings['only_brazil']) ? (bool) $settings['only_brazil'] : false;
		if (!$only_brazil) {
			return true;
		}
		// Se only_brazil ON, valida “pessoa/CPF/CNPJ/etc” só quando país = BR
		return strtoupper($country) === 'BR';
	}

	private static function get_billing_country_rule(array $settings): array
	{
		$only_brazil = self::is_brazil_only_required($settings, 'BR');

		if (!$only_brazil) {
			return array();
		}

		return array(
			'customer' => array(
				'properties' => array(
					'billing_address' => array(
						'type' => 'object',
						'properties' => array(
							'country' => array(
								'const' => 'BR',
							),
						),
						'required' => array('country'),
					),
				),
				'required' => array('billing_address'),
			),
		);
	}

	private static function get_person_document_required_rule(int $person_type_mode, string $person_type)
	{
		if (2 === $person_type_mode) {
			return '1' === $person_type;
		}

		if (3 === $person_type_mode) {
			return '2' === $person_type;
		}

		if (1 !== $person_type_mode) {
			return false;
		}

		return array(
			'checkout' => array(
				'properties' => array(
					'additional_fields' => array(
						'type' => 'object',
						'properties' => array(
							self::FIELD_NS . '/persontype' => array(
								'const' => $person_type,
							),
						),
						'required' => array(self::FIELD_NS . '/persontype'),
					),
				),
				'required' => array('additional_fields'),
			),
		);
	}

	public static function sanitize_digits($value): string
	{
		$value = is_string($value) ? $value : '';
		return preg_replace('/\D+/', '', $value);
	}

	public static function sanitize_text($value): string
	{
		return sanitize_text_field(is_string($value) ? $value : '');
	}

	/**
	 * CPF/CNPJ validation
	 * - Preferir a classe do plugin ECFB se existir
	 * - Fallback com validação local se não existir
	 */
	public static function is_valid_cpf(string $cpf): bool
	{
		$cpf = self::sanitize_digits($cpf);

		if (class_exists('Extra_Checkout_Fields_For_Brazil_Formatting')) {
			return \Extra_Checkout_Fields_For_Brazil_Formatting::is_cpf($cpf);
		}

		// Fallback básico (mesma lógica clássica)
		if (strlen($cpf) !== 11 || preg_match('/^(\\d)\\1{10}$/', $cpf))
			return false;
		for ($t = 9; $t < 11; $t++) {
			for ($d = 0, $c = 0; $c < $t; $c++) {
				$d += (int) $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ((int) $cpf[$c] !== $d)
				return false;
		}
		return true;
	}

	public static function is_valid_cnpj(string $cnpj): bool
	{
		$cnpj = self::sanitize_digits($cnpj);

		if (class_exists('Extra_Checkout_Fields_For_Brazil_Formatting')) {
			return \Extra_Checkout_Fields_For_Brazil_Formatting::is_cnpj($cnpj);
		}

		// Fallback básico
		if (strlen($cnpj) !== 14 || preg_match('/^(\\d)\\1{13}$/', $cnpj))
			return false;

		$length = 12;
		$numbers = substr($cnpj, 0, $length);
		$digits = substr($cnpj, $length);

		$sum = 0;
		$pos = $length - 7;
		for ($i = $length; $i >= 1; $i--) {
			$sum += (int) $numbers[$length - $i] * $pos--;
			if ($pos < 2)
				$pos = 9;
		}
		$result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
		if ((int) $digits[0] !== $result)
			return false;

		$length = 13;
		$numbers = substr($cnpj, 0, $length);
		$sum = 0;
		$pos = $length - 7;
		for ($i = $length; $i >= 1; $i--) {
			$sum += (int) $numbers[$length - $i] * $pos--;
			if ($pos < 2)
				$pos = 9;
		}
		$result = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
		return (int) $digits[1] === $result;
	}

	public static function register_blocks_fields()
	{
		if (!function_exists('woocommerce_register_additional_checkout_field')) {
			return;
		}

		$settings = self::get_settings();
		$brazil_rule = self::get_billing_country_rule($settings);

		// --------------------------
		// ADDRESS (aparece em billing e shipping)
		// --------------------------
		woocommerce_register_additional_checkout_field(
			array(
				'id' => self::FIELD_NS . '/number',
				'label' => __('Número', 'woocommerce-extra-checkout-fields-for-brazil'),
				'location' => 'address',
				'type' => 'text',
				'required' => true, // no plugin ECFB isso é obrigatório
				'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
			)
		);

		$neighborhood_required = isset($settings['neighborhood_required']) ? (bool) $settings['neighborhood_required'] : false;

		woocommerce_register_additional_checkout_field(
			array(
				'id' => self::FIELD_NS . '/neighborhood',
				'label' => __('Bairro', 'woocommerce-extra-checkout-fields-for-brazil'),
				'location' => 'address',
				'type' => 'text',
				'required' => $neighborhood_required,
				'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
			)
		);

		// --------------------------
		// ORDER (um bloco "Order information" / group "other")
		// Aqui ficam CPF/CNPJ/etc (não tem billing-only no API)
		// --------------------------
		$person_type_mode = isset($settings['person_type']) ? intval($settings['person_type']) : 0;

		if (0 !== $person_type_mode) {
			// persontype só é necessário quando modo = 1 (PF/PJ)
			if (1 === $person_type_mode) {
				woocommerce_register_additional_checkout_field(
					array(
						'id' => self::FIELD_NS . '/persontype',
						'label' => __('Tipo de Pessoa', 'woocommerce-extra-checkout-fields-for-brazil'),
						'location' => 'order',
						'type' => 'select',
						'required' => ($brazil_rule) ? true : false,
						'options' => array(
							array('value' => '1', 'label' => __('Pessoa Física', 'woocommerce-extra-checkout-fields-for-brazil')),
							array('value' => '2', 'label' => __('Pessoa Jurídica', 'woocommerce-extra-checkout-fields-for-brazil')),
						),
					)
				);
			}

			woocommerce_register_additional_checkout_field(
				array(
					'id' => self::FIELD_NS . '/cpf',
					'label' => __('CPF', 'woocommerce-extra-checkout-fields-for-brazil'),
					'location' => 'order',
					'type' => 'text',
					'required' => self::get_person_document_required_rule($person_type_mode, '1'),
					'sanitize_callback' => array(__CLASS__, 'sanitize_digits'),
					'validate_callback' => function ($value) use ($settings) {
						$value = WC_Braspag_Blocks_ECFB_Bridge::sanitize_digits($value);
						if ('' === $value)
							return;
						$validate = isset($settings['validate_cpf']) ? (bool) $settings['validate_cpf'] : false;
						if ($validate && !WC_Braspag_Blocks_ECFB_Bridge::is_valid_cpf($value)) {
							return new \WP_Error('invalid_cpf', __('CPF inválido.', 'woocommerce-extra-checkout-fields-for-brazil'));
						}
					},
				)
			);

			$rg_enabled = !empty($settings['rg']);
			if ($rg_enabled) {
				woocommerce_register_additional_checkout_field(
					array(
						'id' => self::FIELD_NS . '/rg',
						'label' => __('RG', 'woocommerce-extra-checkout-fields-for-brazil'),
						'location' => 'order',
						'type' => 'text',
						'required' => false,
						'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
					)
				);
			}

			woocommerce_register_additional_checkout_field(
				array(
					'id' => self::FIELD_NS . '/cnpj',
					'label' => __('CNPJ', 'woocommerce-extra-checkout-fields-for-brazil'),
					'location' => 'order',
					'type' => 'text',
					'required' => self::get_person_document_required_rule($person_type_mode, '2'),
					'sanitize_callback' => array(__CLASS__, 'sanitize_digits'),
					'validate_callback' => function ($value) use ($settings) {
						$value = WC_Braspag_Blocks_ECFB_Bridge::sanitize_digits($value);
						if ('' === $value)
							return;
						$validate = isset($settings['validate_cnpj']) ? (bool) $settings['validate_cnpj'] : false;
						if ($validate && !WC_Braspag_Blocks_ECFB_Bridge::is_valid_cnpj($value)) {
							return new \WP_Error('invalid_cnpj', __('CNPJ inválido.', 'woocommerce-extra-checkout-fields-for-brazil'));
						}
					},
				)
			);

			$ie_enabled = !empty($settings['ie']);
			if ($ie_enabled) {
				woocommerce_register_additional_checkout_field(
					array(
						'id' => self::FIELD_NS . '/ie',
						'label' => __('Inscrição Estadual', 'woocommerce-extra-checkout-fields-for-brazil'),
						'location' => 'order',
						'type' => 'text',
						'required' => false,
						'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
					)
				);
			}
		}

		if (isset($settings['birthdate'])) {
			woocommerce_register_additional_checkout_field(
				array(
					'id' => self::FIELD_NS . '/birthdate',
					'label' => __('Data de Nascimento', 'woocommerce-extra-checkout-fields-for-brazil'),
					'location' => 'order',
					'type' => 'text',
					'required' => false,
					'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
				)
			);
		}

		if (isset($settings['gender'])) {
			woocommerce_register_additional_checkout_field(
				array(
					'id' => self::FIELD_NS . '/gender',
					'label' => __('Gênero', 'woocommerce-extra-checkout-fields-for-brazil'),
					'location' => 'order',
					'type' => 'select',
					'required' => false,
					'options' => array(
						array('value' => 'female', 'label' => __('Feminino', 'woocommerce-extra-checkout-fields-for-brazil')),
						array('value' => 'male', 'label' => __('Masculino', 'woocommerce-extra-checkout-fields-for-brazil')),
						array('value' => 'other', 'label' => __('Outro', 'woocommerce-extra-checkout-fields-for-brazil')),
					),
				)
			);
		}

		$cell_phone = isset($settings['cell_phone']) ? (string) $settings['cell_phone'] : '';
		if ('1' === $cell_phone || '2' === $cell_phone) {
			woocommerce_register_additional_checkout_field(
				array(
					'id' => self::FIELD_NS . '/cellphone',
					'label' => __('Celular', 'woocommerce-extra-checkout-fields-for-brazil'),
					'location' => 'order',
					'type' => 'text',
					'required' => false,
					'sanitize_callback' => array(__CLASS__, 'sanitize_digits'),
				)
			);
		}
	}

	/**
	 * Remove campos duplicados injetados pelo WC Blocks no admin de pedidos.
	 *
	 * O plugin ECFB já exibe/edita _billing_number, _shipping_number, _billing_neighborhood,
	 * _shipping_neighborhood, _billing_cpf, etc. via woocommerce_admin_billing/shipping_fields.
	 *
	 * O WC Blocks (CheckoutFieldsAdmin) injeta os mesmos dados de duas formas:
	 * - admin_address_fields(): usa array_splice (perde as chaves string, ficam numéricas)
	 * - admin_order_fields()/admin_contact_fields(): usa array_merge (preserva chaves string)
	 *
	 * Precisamos remover por ambos os mecanismos: por chave string E por atributo 'id'.
	 */
	public static function remove_duplicate_admin_fields(array $fields): array
	{
		$ns_prefix = self::FIELD_NS . '/';

		foreach ($fields as $key => $field) {
			// Campos inseridos via array_merge mantêm chave string (ex: 'braspag-wcbcf/cpf')
			if (is_string($key) && strpos($key, $ns_prefix) === 0) {
				unset($fields[$key]);
				continue;
			}

			// Campos inseridos via array_splice perdem a chave string (fica numérica).
			// O 'id' do campo contém o prefixo do grupo WC Blocks (ex: '_wc_shipping/braspag-wcbcf/number').
			if (is_array($field) && isset($field['id']) && strpos($field['id'], $ns_prefix) !== false) {
				unset($fields[$key]);
			}
		}

		return $fields;
	}

	/**
	 * Sync Blocks additional fields -> legacy meta keys.
	 * Docs recomendam isso pra backward compatibility.
	 */
	public static function sync_to_legacy_meta($key, $value, $group, $wc_object)
	{
		if (!is_object($wc_object) || !method_exists($wc_object, 'update_meta_data')) {
			return;
		}

		$k = (string) $key;

		// Address fields (vem como group billing|shipping)
		if (self::FIELD_NS . '/number' === $k) {
			$meta_key = ('billing' === $group) ? self::META_BILLING_NUMBER : self::META_SHIPPING_NUMBER;
			$wc_object->update_meta_data($meta_key, self::sanitize_text($value), true);
			return;
		}
		if (self::FIELD_NS . '/neighborhood' === $k) {
			$meta_key = ('billing' === $group) ? self::META_BILLING_NEIGHBORHOOD : self::META_SHIPPING_NEIGHBORHOOD;
			$wc_object->update_meta_data($meta_key, self::sanitize_text($value), true);
			return;
		}

		// Other/order fields (vem como group other)
		$map = array(
			self::FIELD_NS . '/persontype' => self::META_BILLING_PERSONTYPE,
			self::FIELD_NS . '/cpf' => self::META_BILLING_CPF,
			self::FIELD_NS . '/rg' => self::META_BILLING_RG,
			self::FIELD_NS . '/cnpj' => self::META_BILLING_CNPJ,
			self::FIELD_NS . '/ie' => self::META_BILLING_IE,
			self::FIELD_NS . '/birthdate' => self::META_BILLING_BIRTHDATE,
			self::FIELD_NS . '/gender' => self::META_BILLING_GENDER,
			self::FIELD_NS . '/cellphone' => self::META_BILLING_CELLPHONE,
		);

		if (isset($map[$k])) {
			$meta_key = $map[$k];
			$val = $value;

			if (in_array($k, array(self::FIELD_NS . '/cpf', self::FIELD_NS . '/cnpj', self::FIELD_NS . '/cellphone'), true)) {
				$val = self::sanitize_digits($val);
			} else {
				$val = self::sanitize_text($val);
			}

			$wc_object->update_meta_data($meta_key, $val, true);
		}
	}

	/**
	 * Prefill: number
	 */
	public static function default_value_number($value, $group, $wc_object)
	{
		if (!is_object($wc_object) || !method_exists($wc_object, 'get_meta')) {
			return $value;
		}
		$meta_key = ('billing' === $group) ? self::META_BILLING_NUMBER : self::META_SHIPPING_NUMBER;
		$stored = $wc_object->get_meta($meta_key, true);
		return $stored ? $stored : $value;
	}

	/**
	 * Prefill: neighborhood
	 */
	public static function default_value_neighborhood($value, $group, $wc_object)
	{
		if (!is_object($wc_object) || !method_exists($wc_object, 'get_meta')) {
			return $value;
		}
		$meta_key = ('billing' === $group) ? self::META_BILLING_NEIGHBORHOOD : self::META_SHIPPING_NEIGHBORHOOD;
		$stored = $wc_object->get_meta($meta_key, true);
		return $stored ? $stored : $value;
	}

	/**
	 * Prefill genérico para metas billing_* em order/customer
	 */
	public static function default_value_generic_billing_meta($value, $group, $wc_object)
	{
		if (!is_object($wc_object) || !method_exists($wc_object, 'get_meta')) {
			return $value;
		}

		// Só faz sentido pra group "other"/order; mas não atrapalha.
		$current_filter = current_filter();

		// current_filter() = "woocommerce_get_default_value_for_braspag-wcbcf/cpf" etc
		$field = str_replace('woocommerce_get_default_value_for_', '', $current_filter);

		$map = array(
			self::FIELD_NS . '/persontype' => self::META_BILLING_PERSONTYPE,
			self::FIELD_NS . '/cpf' => self::META_BILLING_CPF,
			self::FIELD_NS . '/rg' => self::META_BILLING_RG,
			self::FIELD_NS . '/cnpj' => self::META_BILLING_CNPJ,
			self::FIELD_NS . '/ie' => self::META_BILLING_IE,
			self::FIELD_NS . '/birthdate' => self::META_BILLING_BIRTHDATE,
			self::FIELD_NS . '/gender' => self::META_BILLING_GENDER,
			self::FIELD_NS . '/cellphone' => self::META_BILLING_CELLPHONE,
		);

		if (isset($map[$field])) {
			$stored = $wc_object->get_meta($map[$field], true);
			return $stored ? $stored : $value;
		}

		return $value;
	}

	/**
	 * Validação de Address fields (number/neighborhood).
	 * $fields vem como array [ 'braspag-wcbcf/number' => '...', ...]
	 */
	public static function validate_address_fields($errors, $fields, $group)
	{
		$settings = self::get_settings();
		$number_keys = array(self::FIELD_NS . '/number', 'number');
		$number_submitted = self::has_request_address_field($group, $number_keys);

		$number = '';

		// Procurar o campo número com múltiplos fallbacks
		if (isset($fields[self::FIELD_NS . '/number'])) {
			$number = self::sanitize_text($fields[self::FIELD_NS . '/number']);
		} elseif (isset($fields['number'])) {
			$number = self::sanitize_text($fields['number']);
		} elseif ($number_submitted) {
			$number = self::sanitize_text(self::get_request_address_value($group, $number_keys));
		} else {
			// Verificar se há um campo de número no endereço padrão
			$number = self::sanitize_text(self::get_customer_address_value($group, 'number'));
		}

		// Só validar se realmente necessário (não campos preenchidos anteriormente)
		if ($number === '' && self::should_validate_missing_value($number_submitted) && !self::has_existing_number($group)) {
			$errors->add('missing_number', __('Número é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
		}

		// Validação de bairro similar...
		$neighborhood_required = isset($settings['neighborhood_required']) ? (bool) $settings['neighborhood_required'] : false;

		if ($neighborhood_required) {
			$neighborhood = '';
			$neighborhood_keys = array(self::FIELD_NS . '/neighborhood', 'neighborhood');
			$neighborhood_submitted = self::has_request_address_field($group, $neighborhood_keys);

			if (isset($fields[self::FIELD_NS . '/neighborhood'])) {
				$neighborhood = self::sanitize_text($fields[self::FIELD_NS . '/neighborhood']);
			} elseif (isset($fields['neighborhood'])) {
				$neighborhood = self::sanitize_text($fields['neighborhood']);
			} elseif ($neighborhood_submitted) {
				$neighborhood = self::sanitize_text(self::get_request_address_value($group, $neighborhood_keys));
			}

			if ($neighborhood === '' && self::should_validate_missing_value($neighborhood_submitted)) {
				$errors->add('missing_neighborhood', __('Bairro é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
			}
		}
	}

	public static function has_existing_number($group)
	{
		if (!function_exists('WC') || !WC()->customer) {
			return false;
		}

		$meta_key = ($group === 'billing') ? '_billing_number' : '_shipping_number';

		if (WC()->customer->get_meta($meta_key)) {
			return true;
		}

		$address = ('billing' === $group) ? WC()->customer->get_billing() : WC()->customer->get_shipping();
		if (is_array($address) && !empty($address['number'])) {
			return true;
		}

		return false;
	}

	/**
	 * Validação de “order/other” fields:
	 * - Tipo de pessoa
	 * - CPF/CNPJ + validação
	 * - RG/IE
	 * - Birthdate/Gender/Cellphone required (quando configurado)
	 * - Company obrigatório para PJ (usa billing_company do checkout)
	 */
	public static function validate_other_fields($errors, $fields, $group)
	{
		$settings = self::get_settings();

		$country = '';
		if (function_exists('WC') && WC()->customer) {
			$country = (string) WC()->customer->get_billing_country();
		}
		$country = $country ? strtoupper($country) : 'BR';

		$apply_person_rules = self::is_brazil_only_required($settings, $country);

		// Se only_brazil ON e país != BR, não valida “documentos/pessoa/etc”
		if (!$apply_person_rules) {
			return;
		}

		$person_type_mode = isset($settings['person_type']) ? intval($settings['person_type']) : 0;

		// ---------------------
		// Person type & docs
		// ---------------------
		if (0 !== $person_type_mode) {
			$selected_person = '';
			$person_type_submitted = array_key_exists(self::FIELD_NS . '/persontype', $fields);

			if ($person_type_submitted) {
				$selected_person = self::sanitize_digits((string) $fields[self::FIELD_NS . '/persontype']);
			}

			// Determina se precisa CPF/CNPJ
			$need_cpf = false;
			$need_cnpj = false;

			if (1 === $person_type_mode) {
				// PF/PJ (seleção obrigatória)
				if ('' === $selected_person) {
					if (!self::should_validate_missing_value($person_type_submitted)) {
						return;
					}

					$errors->add('missing_persontype', __('Selecione o tipo de pessoa.', 'woocommerce-extra-checkout-fields-for-brazil'));
					// sem tipo, não dá pra decidir - mas já acusa erro
					return;
				}

				$need_cpf = ('1' === $selected_person);
				$need_cnpj = ('2' === $selected_person);
			} elseif (2 === $person_type_mode) {
				// Só PF
				$need_cpf = true;
			} elseif (3 === $person_type_mode) {
				// Só PJ
				$need_cnpj = true;
			}

			if ($need_cpf) {
				$cpf_submitted = array_key_exists(self::FIELD_NS . '/cpf', $fields);
				$cpf = $cpf_submitted ? self::sanitize_digits($fields[self::FIELD_NS . '/cpf']) : '';

				if ('' === $cpf) {
					if (self::should_validate_missing_value($cpf_submitted)) {
						$errors->add('missing_cpf', __('CPF é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				} else {
					$validate_cpf = isset($settings['validate_cpf']) ? (bool) $settings['validate_cpf'] : false;
					if ($validate_cpf && !self::is_valid_cpf($cpf)) {
						$errors->add('invalid_cpf', __('CPF inválido.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				}

				if (!empty($settings['rg'])) {
					$rg_submitted = array_key_exists(self::FIELD_NS . '/rg', $fields);
					$rg = $rg_submitted ? self::sanitize_text($fields[self::FIELD_NS . '/rg']) : '';
					if ('' === $rg && self::should_validate_missing_value($rg_submitted)) {
						$errors->add('missing_rg', __('RG é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				}
			}

			if ($need_cnpj) {
				$company_submitted = self::has_request_address_field('billing', array('company', 'billing_company'));
				$company = self::sanitize_text(self::get_request_address_value('billing', array('company', 'billing_company')));

				if ('' === $company && function_exists('WC') && WC()->customer) {
					$company = self::sanitize_text((string) WC()->customer->get_billing_company());
				}

				if ('' === trim($company) && self::should_validate_missing_value($company_submitted)) {
					$errors->add('missing_company', __('Razão Social é obrigatória para Pessoa Jurídica.', 'woocommerce-extra-checkout-fields-for-brazil'));
				}

				$cnpj_submitted = array_key_exists(self::FIELD_NS . '/cnpj', $fields);
				$cnpj = $cnpj_submitted ? self::sanitize_digits($fields[self::FIELD_NS . '/cnpj']) : '';
				if ('' === $cnpj) {
					if (self::should_validate_missing_value($cnpj_submitted)) {
						$errors->add('missing_cnpj', __('CNPJ é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				} else {
					$validate_cnpj = isset($settings['validate_cnpj']) ? (bool) $settings['validate_cnpj'] : false;
					if ($validate_cnpj && !self::is_valid_cnpj($cnpj)) {
						$errors->add('invalid_cnpj', __('CNPJ inválido.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				}

				if (!empty($settings['ie'])) {
					$ie_submitted = array_key_exists(self::FIELD_NS . '/ie', $fields);
					$ie = $ie_submitted ? self::sanitize_text($fields[self::FIELD_NS . '/ie']) : '';
					if ('' === $ie && self::should_validate_missing_value($ie_submitted)) {
						$errors->add('missing_ie', __('Inscrição Estadual é obrigatória.', 'woocommerce-extra-checkout-fields-for-brazil'));
					}
				}
			}
		}

		// ---------------------
		// Birthdate / Gender
		// ---------------------
		if (!empty($settings['birthdate'])) {
			$birthdate = isset($fields[self::FIELD_NS . '/birthdate']) ? self::sanitize_text($fields[self::FIELD_NS . '/birthdate']) : '';
			if ('' === $birthdate) {
				$errors->add('missing_birthdate', __('Data de nascimento é obrigatória.', 'woocommerce-extra-checkout-fields-for-brazil'));
			}
		}

		if (!empty($settings['gender'])) {
			$gender = isset($fields[self::FIELD_NS . '/gender']) ? self::sanitize_text($fields[self::FIELD_NS . '/gender']) : '';
			if ('' === $gender) {
				$errors->add('missing_gender', __('Gênero é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
			}
		}

		// ---------------------
		// Cellphone
		// ---------------------
		$cell_phone = isset($settings['cell_phone']) ? (string) $settings['cell_phone'] : '';
		if ('1' === $cell_phone || '2' === $cell_phone) {
			$cell = isset($fields[self::FIELD_NS . '/cellphone']) ? self::sanitize_digits($fields[self::FIELD_NS . '/cellphone']) : '';
			if ('2' === $cell_phone && '' === $cell) {
				$errors->add('missing_cellphone', __('Celular é obrigatório.', 'woocommerce-extra-checkout-fields-for-brazil'));
			}

			if ('' !== $cell) {
				// validação mínima: 10-13 dígitos (BR + DDI)
				$len = strlen($cell);
				if ($len < 10 || $len > 13) {
					$errors->add('invalid_cellphone', __('Celular inválido.', 'woocommerce-extra-checkout-fields-for-brazil'));
				}
			}
		}
	}
}
