<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats API request header for Ecster.
 *
 * @since 1.0
 */
class WC_Ecster_Request_Header {

	/**
	 * Gets formatted Ecster API request header.
	 *
	 * @param $api_key
	 * @param $merchant_key
	 *
	 * @return array
	 */
	public static function get( $api_key, $merchant_key ) {
		$formatted_request_header = array(
			'x-api-key'      => $api_key,
			'x-merchant-key' => $merchant_key,
			'Content-Type'   => 'application/json',
		);

		return $formatted_request_header;
	}

}
