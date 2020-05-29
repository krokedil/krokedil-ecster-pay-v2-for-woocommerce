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
			$payment_method = __( 'Invoice', 'krokedil-ecster-pay-for-woocommerce' );
			break;
		case 'ACCOUNT':
			$payment_method = __( 'Part payment', 'krokedil-ecster-pay-for-woocommerce' );
			break;
		case 'CARD':
			$payment_method = __( 'Card payment', 'krokedil-ecster-pay-for-woocommerce' );
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
			$error_title   = isset( $response_body->code ) ? $response_body->code : '';
			$error_detail  = isset( $response_body->message ) ? $response_body->message : '';
		}
		WC_Gateway_Ecster::log( 'Ecster create cart ' . $error_title . ': ' . $error_detail );
		WC_Gateway_Ecster::log( 'Ecster create cart ' . $error_title . ': ' . $error_detail );
		return __( 'Error: Ecster Pay create cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
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

/**
 * Unset Ecster session
 */
function wc_ecster_unset_sessions() {

	if ( method_exists( WC()->session, '__unset' ) ) {
		if ( WC()->session->get( 'order_awaiting_payment' ) ) {
			WC()->session->__unset( 'order_awaiting_payment' );
		}
		if ( WC()->session->get( 'wc_ecster_method' ) ) {
			WC()->session->__unset( 'wc_ecster_method' );
		}
		if ( WC()->session->get( 'wc_ecster_invoice_fee' ) ) {
			WC()->session->__unset( 'wc_ecster_invoice_fee' );
		}
		if ( WC()->session->get( 'ecster_checkout_cart_key' ) ) {
			WC()->session->__unset( 'ecster_checkout_cart_key' );
		}
		if ( WC()->session->get( 'ecster_order_id' ) ) {
			WC()->session->__unset( 'ecster_order_id' );
		}
	}
}
