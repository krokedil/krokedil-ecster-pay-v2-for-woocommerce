<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Ecster_Request_Parameters {

	public static function get_parameters( $customer_type = 'b2c' ) {
		return array(
			'shopTermsUrl'           => get_permalink( wc_get_page_id( 'terms' ) ),
			'purchaseType'           => array(
				'type' => $customer_type,
			),
			'defaultDeliveryCountry' => WC()->customer->get_billing_country(),
		);
	}

}
