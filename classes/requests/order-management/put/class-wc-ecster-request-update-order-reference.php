<?php
/**
 * Class for the request to update order reference.
 *
 * @package WC_Ecster/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for the request to update the Ecster Pay cart session.
 */
class WC_Ecster_Request_Update_Order_Reference extends WC_Ecster_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Update order reference';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$ecster_order_id = $this->arguments['ecster_order_id'];
		return $this->get_api_url_base() . 'v1/orders/' . $ecster_order_id . '/orderReference';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$body = array(
			'orderReference' => $this->arguments['order_number'],
		);

		return $body;
	}
}
