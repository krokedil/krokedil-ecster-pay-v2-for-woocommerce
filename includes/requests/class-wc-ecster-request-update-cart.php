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
	public function response( $cart_key ) {
		$request_url = $this->base_url . 'v1/carts/' . $cart_key;
		$request     = wp_remote_request( $request_url, $this->get_request_args() );

		return $request;
	}

	/**
	 * Gets Update Cart request arguments.
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
	 * Gets update cart request body.
	 *
	 * @return false|string
	 */
	private function get_request_body() {
		$formatted_request_body = array(
			'locale'          => $this->locale(),
			'parameters'      => $this->get_parameters(),
			'deliveryMethods' => $this->delivery_methods(),
			'cart'            => $this->cart(),
			'platform'        => $this->platform(),
			'notificationUrl' => $this->notification_url(),
		);

		return wp_json_encode( $formatted_request_body );
	}

}
