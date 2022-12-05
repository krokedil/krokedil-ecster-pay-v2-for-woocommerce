<?php
/**
 * Main request class
 *
 * @package WC_Ecster/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class WC_Ecster_Request {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;


	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments = $arguments;
		$this->load_settings();
	}

	/**
	 * Loads the Qliro settings and sets them to be used here.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_ecster_settings' );
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		if ( 'yes' === $this->settings['testmode'] ) {
			return WC_ECSTER_BASE_URL_TEST;
		}

		return WC_ECSTER_BASE_URL_PROD;
	}

	/**
	 * Get the request headers.
	 *
	 * @param string $body json_encoded body.
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'x-api-key'      => $this->settings['api_key'],
			'x-merchant-key' => $this->settings['merchant_key'],
			'Content-type'   => 'application/json',
		);
	}

	/**
	 * Get the user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) . ' - WooCommerce: ' . WC()->version . ' - Ecster: ' . WC_ECSTER_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	abstract protected function get_request_args();

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return object|WP_Error
	 */
	public function request() {
		$url      = $this->get_request_url();
		$args     = $this->get_request_args();
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors as $error ) {
					$error_message .= ' ' . $error;
				}
			}
			$code          = wp_remote_retrieve_response_code( $response );
			$error_message = empty( $response['body'] ) ? "API Error ${code}" : json_decode( $response['body'], true );
			$return        = new WP_Error( $code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		$this->log_response( $response, $request_args, $request_url );
		return $return;
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$method   = $this->method;
		$title    = $this->log_title;
		$code     = wp_remote_retrieve_response_code( $response );
		$cart_key = $response['checkoutCart']['key'] ?? null;
		$log      = WC_Ecster_Logger::format_log( $cart_key, $method, $title, $request_args, $response, $code, $request_url );
		WC_Ecster_Logger::log( $log );
	}

	/**
	 * Returns locale for Ecster API requests.
	 *
	 * Currently only supports Sweden.
	 *
	 * @TODO: Add support for other countries, once they are available
	 * @return mixed|void
	 */
	protected function locale() {
		$iso_code = explode( '_', get_locale() );
		if ( in_array( $iso_code[0], array( 'sv', 'en', 'no', 'da', 'fi' ), true ) ) {
			$lang = $iso_code[0];
		} else {
			$lang = 'en';
		}

		$country = isset( $iso_code[1] ) ? $iso_code[1] : WC()->customer->get_billing_country();

		$ecster_locale = array(
			'language' => $lang,
			'country'  => strtoupper( $country ),
		);

		return apply_filters( 'wc_ecster_locale', $ecster_locale );
	}

	/**
	 * Gets country code for Ecster purchase.
	 *
	 * @return string
	 */
	protected function get_country_code() {
		// Try to use customer country if available.
		if ( ! empty( WC()->customer->get_billing_country() ) && strlen( WC()->customer->get_billing_country() ) === 2 ) {
			return WC()->customer->get_billing_country( 'edit' );
		}

		$base_location = wc_get_base_location();
		$country       = $base_location['country'];

		return $country;
	}

	/**
	 * Returns platform information (WooCommerce and version) for Ecster API requests.
	 *
	 * @return array
	 */
	protected function platform() {
		$ecster_platform = array(
			'reference' => WC_ECSTER_ECP_ID,
			'info'      => 'Ecster plugin version ' . WC_ECSTER_VERSION . '. WooCommerce version ' . WC_VERSION,
		);

		return $ecster_platform;
	}

	/**
	 * Returns notification URL for Ecster OSN callback.
	 *
	 * @link   https://developer.ecster.se/api-reference/#/get-order
	 * @return string
	 */
	protected function notification_url() {
		$ecster_notification_url = get_home_url() . '/wc-api/WC_Gateway_Ecster/';
		if ( WC()->session->get( 'ecster_temp_order_id' ) ) {
			$ecster_notification_url = add_query_arg( 'etoid', WC()->session->get( 'ecster_temp_order_id' ), $ecster_notification_url );
		}
		return $ecster_notification_url;
	}

	protected function get_parameters( $customer_type ) {
		$parameters                           = WC_Ecster_Request_Parameters::get_parameters( $customer_type );
		$parameters['defaultDeliveryCountry'] = $this->get_country_code();
		return $parameters;
	}

	/**
	 * Returns WooCommerce shipping options formatted for Ecster API requests.
	 *
	 * @return array
	 */
	protected function delivery_methods() {
		return WC_Ecster_Request_Delivery_Methods::delivery_methods();
	}


}
