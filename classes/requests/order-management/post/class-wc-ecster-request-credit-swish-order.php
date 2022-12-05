<?php
/**
 * Class for the request to credit/refund a Swish order.
 *
 * @package WC_Ecster_Request_Credit_Swish_Order/Classes/Requests/Order-Management/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ecster_Request_Credit_Order class.
 */
class WC_Ecster_Request_Credit_Swish_Order extends WC_Ecster_Request_Post {

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
		return $this->get_api_url_base() . 'v1/orders/' . $ecster_reference . '/refunds';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order = wc_get_order( $this->order_id );

		$body = array(
			'amount'           => round( $this->amount * 100 ),
			'debitTransaction' => get_post_meta( $this->order_id, '_wc_ecster_swish_id', true ),
			'message'          => $this->reason,
		);

		return $body;
	}
}
