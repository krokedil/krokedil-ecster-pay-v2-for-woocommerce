<?php
/**
 * Base class for all GET requests.
 *
 * @package WC_Ecster/Classes/Request
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  The main class for GET requests.
 */
abstract class WC_Ecster_Request_Get extends WC_Ecster_Request {

	/**
	 * WC_Ecster_Request_Get constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}

	/**
	 * Builds the request args for a GET request.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'ecster_request_timeout', 10 ),
		);
	}
}
