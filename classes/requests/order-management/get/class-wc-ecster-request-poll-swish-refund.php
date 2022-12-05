<?php
/**
 * Class for the request to get order.
 *
 * @package WC_Ecster/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for the request to get the Ecster Pay order.
 */
class WC_Ecster_Request_Swish_Poll_Refund extends WC_Ecster_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Poll Swish refund';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$ecster_order_id = $this->arguments['ecster_order_id'];
		return $this->get_api_url_base() . 'v1/orders/' . $ecster_order_id . '/refunds/latest/status';
	}
}
