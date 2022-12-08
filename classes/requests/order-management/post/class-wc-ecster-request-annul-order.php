<?php
/**
 * Class for the request to annul/cvancel an order.
 *
 * @package WC_Ecster_Request_Annul_Order/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Ecster_Request_Annul_Order class.
 */
class WC_Ecster_Request_Annul_Order extends WC_Ecster_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Annul order';
		$this->arguments = $arguments;
		$this->order_id  = $this->arguments['order_id'];
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

		$body = array(
			'type' => 'ANNUL',
		);

		return $body;
	}
}
