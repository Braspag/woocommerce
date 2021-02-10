<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Braspag_Customer
 */
class WC_Braspag_Customer {

	private $id = '';

	private $user_id = 0;
//
//	private $customer_data = array();

	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( $this->get_id_from_meta( $user_id ) );
		}
	}
//
//    /**
//     * @return string
//     */
//	public function get_id() {
//		return $this->id;
//	}

    /**
     * @param $id
     */
	public function set_id( $id ) {
		// Backwards compat for customer ID stored in array format. (Pre 3.0)
		if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
			$id = $id['customer_id'];

			$this->update_id_in_meta( $id );
		}

		$this->id = wc_clean( $id );
	}

    /**
     * @return int
     */
	public function get_user_id() {
		return absint( $this->user_id );
	}

    /**
     * @param $user_id
     */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}
//
//    /**
//     * @return bool|WP_User
//     */
//	protected function get_user() {
//		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
//	}
//
//    /**
//     *
//     */
//	public function clear_cache() {
//		delete_transient( 'braspag_sources_' . $this->get_id() );
//		delete_transient( 'braspag_customer_' . $this->get_id() );
//		$this->customer_data = array();
//	}

    /**
     * @param $user_id
     * @return mixed
     */
	public function get_id_from_meta( $user_id ) {
		return get_user_option( '_braspag_customer_id', $user_id );
	}

    /**
     * @param $id
     */
	public function update_id_in_meta( $id ) {
		update_user_option( $this->get_user_id(), '_braspag_customer_id', $id, false );
	}
//
//    /**
//     *
//     */
//	public function delete_id_from_meta() {
//		delete_user_option( $this->get_user_id(), '_braspag_customer_id', false );
//	}
}
