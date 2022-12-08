<?php
/**
 * API Class file.
 *
 * @package WC_Ecster/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_API class.
 *
 * Class that has functions for the Ecster communication.
 */
class WC_Ecster_API {


	/**
	 * Creates a Ecster Pay order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function create_ecster_cart( $order_id = null ) {

		$request  = new WC_Ecster_Request_Create_Cart(
			array(
				'order_id' => $order_id,
			)
		);
		$response = $request->request();

		return $this->check_for_api_error( $response );

	}

	/**
	 * Updates a Ecster Pay order.
	 *
	 * @param string $cart_key The current session/cart id.
	 *
	 * @return array|mixed
	 */
	public function update_ecster_cart( $cart_key, $customer_type = null ) {
		$request  = new WC_Ecster_Request_Update_Cart(
			array(
				'cart_key'      => $cart_key,
				'customer_type' => $customer_type,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update reference information
	 *
	 * @param string $ecster_order_id The payment identifier.
	 * @param int    $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function update_ecster_order_reference( $ecster_order_id, $order_id = null ) {
		$order   = wc_get_order( $order_id );
		$request = new WC_Ecster_Request_Update_Order_Reference(
			array(
				'ecster_order_id' => $ecster_order_id,
				'order_number'    => $order->get_order_number(),
			)
		);

		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Cancels Ecster order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function cancel_ecster_order( $order_id ) {
		$request  = new WC_Ecster_Request_Annul_Order(
			array(
				'order_id' => $order_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 *
	 * Refunds Ecster order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function refund_ecster_order( $order_id, $amount, $reason ) {
		$request  = new WC_Ecster_Request_Credit_Order(
			array(
				'order_id' => $order_id,
				'amount'   => $amount,
				'reason'   => $reason,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 *
	 * Refunds Ecster order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function refund_ecster_swish_order( $order_id, $amount, $reason ) {
		$request  = new WC_Ecster_Request_Credit_Swish_Order(
			array(
				'order_id' => $order_id,
				'amount'   => $amount,
				'reason'   => $reason,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Retrieves Ecster order.
	 *
	 * @param string $ecster_order_id The payment identifier.
	 *
	 * @return array|mixed
	 */
	public function poll_swish_refund( $ecster_order_id ) {
		$request  = new WC_Ecster_Request_Swish_Poll_Refund(
			array(
				'ecster_order_id' => $ecster_order_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Retrieves Ecster order.
	 *
	 * @param string $ecster_order_id The payment identifier.
	 *
	 * @return array|mixed
	 */
	public function get_ecster_order( $ecster_order_id ) {
		$request  = new WC_Ecster_Request_Get_Order(
			array(
				'ecster_order_id' => $ecster_order_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Activate Ecster Order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|mixed
	 */
	public function activate_ecster_order( $order_id ) {
		$request  = new WC_Ecster_Request_Debit_Order(
			array(
				'order_id' => $order_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array or a false.
	 *
	 * @param array|WP_Error $response The response from the request.
	 * @return mixed
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) && ! is_admin() ) {
			ecster_print_error_message( $response );
		}
		return $response;
	}
}
