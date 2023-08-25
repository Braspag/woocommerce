<?php
/**
 * WooCommerce Braspag Exception Class
 *
 * Extends Exception to provide additional data
 *
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class WC_Braspag_Exception extends Exception
{

	protected $localized_message;

	/**
	 * WC_Braspag_Exception constructor.
	 * @param string $error_message
	 * @param string $localized_message
	 */
	public function __construct($error_message = '', $localized_message = '')
	{
		$this->localized_message = $localized_message;
		parent::__construct($error_message);
	}

	/**
	 * @return string
	 */
	public function getLocalizedMessage()
	{
		return $this->localized_message;
	}
}