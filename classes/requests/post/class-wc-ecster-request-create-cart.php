<?php
/**
 * Class for the request to create cart.
 *
 * @package WC_Ecster_Request_Create_Cart/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ecster_Request_Create_Cart class.
 */
class WC_Ecster_Request_Create_Cart extends WC_Ecster_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		// todo order id.
		$this->log_title = 'Create Ecster cart';
		$this->arguments = $arguments;
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'v1/carts/';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$settings      = get_option( 'woocommerce_ecster_settings' );
		$customer_type = isset( $settings['customer_types'] ) ? $settings['customer_types'] : 'b2c';
		if ( in_array( $customer_type, array( 'b2cb', 'b2bc' ), true ) ) {
			$customer_type = substr( $customer_type, 0, -1 );
		}
		$body = array(
			'locale'          => $this->locale(),
			'countryCode'     => $this->get_country_code(),
			'parameters'      => $this->get_parameters( $customer_type ),
			'deliveryMethods' => $this->delivery_methods(),
			'platform'        => $this->platform(),
			'notificationUrl' => $this->notification_url(),
		);

		if ( ! empty( $this->arguments['order_id'] ) ) {
			$order = wc_get_order( $this->arguments['order_id'] );

			$return_url = add_query_arg(
				array(
					'ecster_confirm' => 'yes',
				),
				$order->get_checkout_order_received_url()
			);
			// $body['cart'] = WC_Ecster_Request_Cart::cart();
			unset( $body['deliveryMethods'] );
			$body['cart']['rows']            = WC_Ecster_Get_Order_Items::get_items( $this->arguments['order_id'] );
			$body['cart']['amount']          = round( $order->get_total() * 100 );
			$body['cart']['currency']        = $order->get_currency();
			$body['parameters']['returnUrl'] = $return_url;
			// $body['parameters']['showPaymentResult'] = true;
			$body['orderReference'] = $order->get_order_number();
			$body['consumer']       = WC_Ecster_Request_Customer::customer( $this->arguments['order_id'] );
		} else {
			$body['cart']           = WC_Ecster_Request_Cart::cart();
			$body['orderReference'] = 'TempOrderRef';
		}
		return $body;
	}
}
