<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Create Cart.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Create_Cart extends WC_Ecster_Request {

	/** @var string Ecster API request method. */
	private $request_method = 'POST';

	/**
	 * Returns Create Cart request response.
	 *
	 * @return array|WP_Error
	 */
	public function response() {
		$request_url = $this->base_url . 'v1/carts/';
		$request     = wp_remote_request( $request_url, $this->get_request_args() );
		WC_Gateway_Ecster::log( 'Ecster create cart request response: ' . json_encode( $request ) );
		return $request;
	}

	/**
	 * Gets Create Cart request arguments.
	 *
	 * @return array
	 */
	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header(),
			'body'    => $this->get_request_body(),
			'method'  => $this->request_method,
		);
		return $request_args;
	}

	/**
	 * Gets Create Cart request body.
	 *
	 * @return false|string
	 */
	private function get_request_body() {
		$settings      = get_option( 'woocommerce_ecster_settings' );
		$customer_type = isset( $settings['customer_types'] ) ? $settings['customer_types'] : 'b2c';
		if ( in_array( $customer_type, array( 'b2cb', 'b2bc' ), true ) ) {
			$customer_type = substr( $customer_type, 0, -1 );
		}
		$formatted_request_body = array(
			'locale'          => $this->locale(),
			'parameters'      => $this->get_parameters( $customer_type ),
			'deliveryMethods' => $this->delivery_methods(),
			'cart'            => $this->cart(),
			'orderReference'  => 'TempOrderRef',
			'platform'        => $this->platform(),
			'notificationUrl' => $this->notification_url(),
		);

		WC_Gateway_Ecster::log( 'Ecster create cart request body: ' . json_encode( $formatted_request_body ) );

		return stripslashes_deep( wp_json_encode( $formatted_request_body ) );
	}

}
