<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Ecster_Request_Get_Order
 */
class WC_Ecster_Swish_Poll_Refund extends WC_Ecster_Request {

	/** @var string Ecster API request path. */
	private $request_path = 'v1/orders/';

	/**
	 * Returns Get Order request response.
	 *
	 * @param $internal_reference
	 *
	 * @return array|WP_Error
	 */
	public function response( $url_id ) {
		$request_url = $this->base_url_public . $this->request_path . '/' . $url_id . '/refunds/latest/status';
		$request     = wp_safe_remote_get( $request_url, $this->get_request_args() );
		return $request;
	}

	/**
	 * Gets arguments for Get Order request.
	 *
	 * @return array
	 */
	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header(),
		);

		return $request_args;
	}

}
