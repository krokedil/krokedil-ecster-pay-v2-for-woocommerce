<?php
/**
 * Logging class file.
 *
 * @package WC_Ecster/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Logger class.
 */
class WC_Ecster_Logger {
	/**
	 * Log message string
	 *
	 * @var $log
	 */
	private static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_ecster_settings' );

		if ( 'yes' === $settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'ecster', wp_json_encode( $message ) );
		}

		if ( isset( $data['response']['code']['response']['code'] ) && ( $data['response']['code']['response']['code'] < 200 || $data['response']['code']['response']['code'] > 299 ) ) {
			self::log_to_db( $data );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param string $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$data['request']['body'] = json_decode( $data['request']['body'], true );
		}
		return $data;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $cart_key The Ecster cart id.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @param string $request_url The request url.
	 * @param string $tl_trace_id The returned Ecster trace ID.
	 * @return array
	 */
	public static function format_log( $cart_key, $method, $title, $request_args, $response, $code, $request_url = null, $tl_trace_id = '' ) {
		// Unset the snippet to prevent issues in the response.
		if ( ! is_wp_error( $response ) && isset( $response['OrderHtmlSnippet'] ) ) {// todo check snippet.
			unset( $response['OrderHtmlSnippet'] );
		}
		// Unset the snippet to prevent issues in the request body.
		if ( isset( $request_args['body'] ) ) {
			$request_body = json_decode( $request_args['body'], true );
			if ( isset( $request_body['OrderHtmlSnippet'] ) ) {
				unset( $request_body['OrderHtmlSnippet'] );
				$request_args['body'] = wp_json_encode( $request_body );
			}
		}

		// Remove Authorization token if it is returned.
		if ( ! is_wp_error( $response ) && isset( $response['body'] ) ) {
			$response_body = json_decode( $response['body'], true );
			if ( isset( $response_body['access_token'] ) && apply_filters( 'truelayer_remove_sensitive_data_from_logs', true ) ) {
				$response_body['access_token'] = 'Removed from log';
				$response['body']              = wp_json_encode( $response_body );
			}
		}

		if ( isset( $request_args['headers']['Authorization'] ) && apply_filters( 'truelayer_remove_sensitive_data_from_logs', true ) ) {
			$request_args['headers']['Authorization'] = 'Removed from log';
		}
		return array(
			'id'             => $cart_key,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'request_url'    => $request_url,
			'response'       => array(
				'body'        => $response,
				'code'        => $code,
				'tl_trace_id' => $tl_trace_id,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => WC_ECSTER_VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) ) {
						$priority   = $data['object']->current_priority();
						$name       = key( $data['object']->current() );
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_ecster', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, -14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_ecster', $logs, false );
	}

}
