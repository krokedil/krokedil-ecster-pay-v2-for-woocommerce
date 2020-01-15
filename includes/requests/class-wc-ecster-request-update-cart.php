<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ecster API Update Cart.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Update_Cart extends WC_Ecster_Request {

	/** @var string Ecster API request method. */
	private $request_method = 'PUT';

	/**
	 * Gets Update Cart request response.
	 *
	 * @param $cart_key Ecster cart key
	 *
	 * @return array|WP_Error
	 */
	public function response( $cart_key, $customer_type = null ) {
		$request_url = $this->base_url . 'v1/carts/' . $cart_key;
		$request     = wp_remote_request( $request_url, $this->get_request_args( $customer_type ) );
		WC_Gateway_Ecster::log( 'Ecster update order request response (URL ' . $request_url . '): ' . stripslashes_deep( json_encode( $request ) ) );

		return $request;
	}

	/**
	 * Gets Update Cart request arguments.
	 *
	 * @return array
	 */
	private function get_request_args( $customer_type ) {
		$request_args = array(
			'headers' => $this->request_header(),
			'body'    => $this->get_request_body( $customer_type ),
			'method'  => $this->request_method,
		);

		return $request_args;
	}

	/**
	 * Gets update cart request body.
	 *
	 * @return false|string
	 */
	private function get_request_body( $customer_type ) {
		if ( null === $customer_type ) {
			$settings      = get_option( 'woocommerce_ecster_settings' );
			$customer_type = isset( $settings['customer_types'] ) ? $settings['customer_types'] : 'b2c';
			if ( in_array( $customer_type, array( 'b2cb', 'b2bc' ), true ) ) {
				$customer_type = substr( $customer_type, 0, -1 );
			}
		}
		$formatted_request_body = array(
			'locale'          => $this->locale(),
			'parameters'      => $this->get_parameters( $customer_type ),
			'deliveryMethods' => $this->delivery_methods(),
			'cart'            => $this->cart(),
			'platform'        => $this->platform(),
			'notificationUrl' => $this->notification_url(),
		);

		return wp_json_encode( $formatted_request_body );
	}

}
