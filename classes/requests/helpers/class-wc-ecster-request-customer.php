<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request customer for Ecster.
 *
 * @since 1.0
 * @return array
 */
class WC_Ecster_Request_Customer {

	/**
	 * Return customer.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array.
	 */
	public static function customer( $order_id = null ) {
		$order           = wc_get_order( $order_id );
		$ecster_customer = array(
			'name'        => self::customer_name( $order ),
			'address'     => self::customer_address( $order ),
			'contactInfo' => self::customer_contact_info( $order ),
		);

		return apply_filters( 'wc_ecster_customer', $ecster_customer );
	}


	/**
	 * Return customer name.
	 *
	 * @param object $order WooCommerce order.
	 * @return array.
	 */
	private static function customer_name( $order ) {
		return array(
			'firstName' => $order->get_billing_first_name(),
			'lastName'  => $order->get_billing_last_name(),
		);
	}

	/**
	 * Return customer address.
	 *
	 * @param object $order WooCommerce order.
	 * @return array.
	 */
	private static function customer_address( $order ) {
		$adress = array(
			'line1'   => $order->get_billing_address_1(),
			'zip'     => $order->get_billing_postcode(),
			'city'    => $order->get_billing_city(),
			'country' => $order->get_billing_country(),
		);

		if ( $order->get_billing_state() ) {
			$adress['province'] = $order->get_billing_state();
		}

		if ( $order->get_billing_address_2() ) {
			$adress['line2'] = $order->get_billing_address_2();
		}

		if ( $order->get_billing_company() ) {
			$adress['line2'] = $order->get_billing_company();
		}

		return $adress;
	}

	/**
	 * Return customer contact info.
	 *
	 * @param object $order WooCommerce order.
	 * @return array.
	 */
	private static function customer_contact_info( $order ) {
		$contact_info = array(
			'email'    => $order->get_billing_email(),
			'cellular' => array(
				'number'  => self::get_phone_number( $order ),
				'country' => self::get_phone_prefix( $order ),

			),
		);

		return $contact_info;
	}

	/**
	 * Gets customer phone prefix formatted for Ecster.
	 *
	 * @param object $order The WooCommerce order.
	 * @return string
	 */
	public static function get_phone_prefix( $order ) {
		$prefix = null;
		if ( substr( $order->get_billing_phone(), 0, 1 ) === '+' ) {
			$prefix = substr( $order->get_billing_phone(), 0, 3 );
		} else {
			$prefix = self::get_phone_prefix_for_country( $order->get_billing_country() );
		}
		return $prefix;
	}

	/**
	 * Gets customer phone number formatted for Ecster.
	 *
	 * @param object $order The WooCommerce order.
	 * @return string
	 */
	public static function get_phone_number( $order ) {
		$phone_number = null;
		if ( substr( $order->get_billing_phone(), 0, 1 ) === '+' ) {
			$phone_number = substr( $order->get_billing_phone(), strlen( self::get_phone_prefix( $order ) ) );
			$phone_number = str_replace( ' ', '', $phone_number );
		} else {
			$phone_number = str_replace( '-', '', $order->get_billing_phone() );
			$phone_number = str_replace( ' ', '', $phone_number );
		}
		return $phone_number;
	}

	/**
	 * Return phone prefix for country.
	 *
	 * @param string $country Two letter country code.
	 */
	public static function get_phone_prefix_for_country( $country = false ) {
		$result   = '';
		$prefixes = self::get_all_country_prefixes();
		$result   = $prefixes[ $country ];
		return $result;
	}

	/**
	 * Return all phone prefixes.
	 */
	public static function get_all_country_prefixes() {
		return include WC()->plugin_path() . '/i18n/phone.php';
	}

}
