<?php
/**
 * Class for the request to update order.
 *
 * @package WC_Ecster/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for the request to update the Ecster Pay cart session.
 */
class WC_Ecster_Request_Update_Cart extends WC_Ecster_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update order';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$cart_key = $this->arguments['cart_key'];
		return $this->get_api_url_base() . 'v1/carts/' . $cart_key;
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$customer_type = $this->arguments['customer_type'] ?? '';
		if ( empty( $customer_type ) ) {
			$settings      = get_option( 'woocommerce_ecster_settings' );
			$customer_type = isset( $settings['customer_types'] ) ? $settings['customer_types'] : 'b2c';
			if ( in_array( $customer_type, array( 'b2cb', 'b2bc' ), true ) ) {
				$customer_type = substr( $customer_type, 0, -1 );
			}
		}
		$body = array(
			'locale'          => $this->locale(),
			'countryCode'     => $this->get_country_code(),
			'parameters'      => $this->get_parameters( $customer_type ),
			'deliveryMethods' => $this->delivery_methods(),
			'cart'            => WC_Ecster_Request_Cart::cart(),
			'platform'        => $this->platform(),
			'notificationUrl' => $this->notification_url(),
		);

		return $body;
	}
}
