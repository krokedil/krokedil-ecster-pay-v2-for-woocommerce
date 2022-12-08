<?php
/**
 * Class for the request to credit/refund an order.
 *
 * @package WC_Ecster_Request_Credit_Order/Classes/Requests/Order-Management/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ecster_Request_Credit_Order class.
 */
class WC_Ecster_Request_Credit_Order extends WC_Ecster_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Credit order';
		$this->arguments = $arguments;
		$this->order_id  = $this->arguments['order_id'];
		$this->amount    = $this->arguments['amount'];
		$this->reason    = $this->arguments['reason'];
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$ecster_reference = get_post_meta( $this->order_id, '_wc_ecster_internal_reference', true );
		return $this->get_api_url_base() . 'v1/orders/' . $ecster_reference . '/transactions';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order = wc_get_order( $this->order_id );

		$body = array(
			'type'                 => 'CREDIT',
			'amount'               => round( $this->amount * 100 ),
			'debitTransaction'     => get_post_meta( $this->order_id, '_ecster_order_captured_id', true ),
			'transactionReference' => WC_Ecster_Get_Refund_Order_Items::get_refunded_order_id( $this->order_id ),
			'rows'                 => WC_Ecster_Get_Refund_Order_Items::get_items( $this->order_id, $this->amount, $this->reason ),
		);

		return $body;
	}
}
