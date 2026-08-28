<?php

use PHPUnit\Framework\TestCase;

/**
 * Testa as guardas simples de WC_Braspag_Helper sem carregar WordPress.
 */
class HelperTest extends TestCase {

	public function test_get_braspag_currency_retorna_false_para_order_nula(): void {
		$this->assertFalse( WC_Braspag_Helper::get_braspag_currency( null ) );
	}

	public function test_update_braspag_currency_retorna_false_para_parametros_vazios(): void {
		$this->assertFalse( WC_Braspag_Helper::update_braspag_currency( '', '' ) );
	}

	public function test_update_braspag_currency_retorna_false_para_currency_vazia(): void {
		$this->assertFalse( WC_Braspag_Helper::update_braspag_currency( 'qualquer-order', '' ) );
	}
}
