<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get localized and formatted payment method name.
 *
 * @param $payment_method
 *
 * @return string
 */
function wc_ecster_get_payment_method_name( $payment_method ) {
	switch ( $payment_method ) {
		case 'INVOICE':
			$payment_method = __( 'Invoice', 'collector-checkout-for-woocommerce' );
			break;
		case 'ACCOUNT':
			$payment_method = __( 'Part payment', 'collector-checkout-for-woocommerce' );
			break;
		case 'CARD':
			$payment_method = __( 'Card payment', 'collector-checkout-for-woocommerce' );
			break;
		default:
			break;
	}
	return $payment_method;
}

/**
 * Gets the Ecster HTML snippet.
 *
 * @return void
 */
function ecster_wc_show_snippet() {
	?>
		<div id="ecster-pay-ctr">
		</div>
	<?php
}
/**
 * Maybe creates or get the current Ecster order.
 *
 * @return string
 */
function ecster_maybe_create_order() {
	if ( WC()->session->get( 'ecster_checkout_cart_key' ) ) {
		// Maybe use later to check order status stuff.
		// $ecster_order = ecster_get_order( WC()->session->get( 'ecster_checkout_cart_key' ) );
		return WC()->session->get( 'ecster_checkout_cart_key' );
	} else {
		return ecster_create_order();
	}
}

/**
 * Creates the Ecster order.
 *
 * @return string
 */
function ecster_create_order() {
	$ecster_settings = get_option( 'woocommerce_ecster_settings' );
	$testmode        = 'yes' === $ecster_settings['testmode'];
	$api_key         = $ecster_settings['api_key'];
	$merchant_key    = $ecster_settings['merchant_key'];

	$request  = new WC_Ecster_Request_Create_Cart( $api_key, $merchant_key, $testmode );
	$response = $request->response();

	if ( ! is_wp_error( $response ) && 201 == $response['response']['code'] ) {
		$response_body = json_decode( $response['body'] );
		if ( is_string( $response_body->checkoutCart->key ) ) {
			WC()->session->set( 'ecster_checkout_cart_key', $response_body->checkoutCart->key );
			return $response_body->checkoutCart->key;
		}
	} else {
		if ( is_wp_error( $response ) ) {
			$error_title  = $response->get_error_code();
			$error_detail = $response->get_error_message();
		} else {
			$response_body = json_decode( $response['body'] );
			$error_title   = $response_body->title;
			$error_detail  = $response_body->detail;
		}
		WC_Gateway_Ecster::log( 'Ecster create cart ' . $error_title . ': ' . $error_detail );
		return __( 'Ecster Pay create cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
	}
}

/**
 * Get the Ecster order.
 *
 * @param string $checkout_cart_key The Checkout cart key for the current Ecster order.
 * @return array
 */
function ecster_get_order( $checkout_cart_key ) {
	$request       = new WC_Ecster_Request_Get_Order( $this->api_key, $this->merchant_key, $this->testmode );
	$response      = $request->response( $internal_reference );
	$response_body = json_decode( $response['body'] );

	return $response_body;
}
