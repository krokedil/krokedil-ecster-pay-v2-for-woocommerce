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
	// Set temp order ID. Used for callbacks uhntil we have a WC order.
	WC()->session->set( 'ecster_temp_order_id', 'tmp' . md5( uniqid( wp_rand(), true ) ) );
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
		if ( WC()->session->get( 'ecster_temp_order_id' ) ) {
			WC()->session->__unset( 'ecster_temp_order_id' );
		}
	}
}

/**
 * Shows select another payment method button in Ecster Pay template page.
 */
function wc_ecster_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_ecster_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'krokedil-ecster-pay-for-woocommerce' );

		?>
		<p class="ecster-pay-choose-other">
			<a class="checkout-button button" href="#" id="ecster-checkout-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Finds an Order ID based on a temp order id set in Ecsters create request.
 *
 * @param string $ecster_temp_order_id A temporary order id set in create request sent to Ecster.
 * @return int The ID of an order, or 0 if the order could not be found.
 */
function wc_ecster_get_order_id_by_temp_order_id( $ecster_temp_order_id = null ) {

	if ( empty( $ecster_temp_order_id ) ) {
		return false;
	}

	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_wc_ecster_temp_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $ecster_temp_order_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '120 day ago',
			),
		),
	);

	$orders = get_posts( $query_args );

	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

/**
 * Returns the default customer type selected in Ecster settings.
 *
 * @return string $customer_type The default customer type.s
 */
function wc_ecster_get_default_customer_type() {
	$settings      = get_option( 'woocommerce_ecster_settings' );
	$customer_type = isset( $settings['customer_types'] ) ? $settings['customer_types'] : 'b2c';
	return substr( $customer_type, 0, 3 );
}

/**
 * Confirm order
 */
function wc_ecster_confirm_order( $order_id, $internal_reference, $response_body = false ) {
	$ecster_settings = get_option( 'woocommerce_ecster_settings' );
	$testmode        = 'yes' === $ecster_settings['testmode'];
	$api_key         = $ecster_settings['api_key'];
	$merchant_key    = $ecster_settings['merchant_key'];

	$order = wc_get_order( $order_id );

	// Save internal reference to WC order.
	update_post_meta( $order_id, '_wc_ecster_internal_reference', $internal_reference );

	// Update reference.
	$request  = new WC_Ecster_Request_Update_Reference( $api_key, $merchant_key, $testmode );
	$response = $request->response( $internal_reference, $order->get_order_number() );

	// Get purchase data from Ecster if bnot already passed into the function.
	if ( empty( $response_body ) ) {
		$request       = new WC_Ecster_Request_Get_Order( $api_key, $merchant_key, $testmode );
		$response      = $request->response( $internal_reference );
		$response_body = json_decode( $response['body'] );

		$ecster_swish_id = '';

		if ( $response_body->transactions ) {

			foreach ( $response_body->transactions as $key ) {
				if ( 'DEBIT' === $key->type ) {
					$ecster_swish_id = $key->id;
				}
			}

			if ( '' !== $ecster_swish_id ) {
				update_post_meta( $order_id, '_wc_ecster_swish_id', $ecster_swish_id );
			}
		}
	}

	// Check if we have an invoice fee.
	if ( isset( $response_body->properties->invoiceFee ) ) {
		$ecster_fee = new WC_Order_Item_Fee();
		$ecster_fee->set_name( __( 'Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' ) );
		$ecster_fee->set_total( $response_body->properties->invoiceFee / 100 );
		$ecster_fee->set_tax_status( 'none' );
		$ecster_fee->save();
		$order->add_item( $ecster_fee );
		$order->calculate_totals();
	}

	$ecster_status = $response_body->status;

	WC_Gateway_Ecster::log( 'Confirm order ID ' . $order_id . '. Ecster internal reference ' . $internal_reference . '. Response body - ' . json_encode( $response_body ) );

	// Add email to order.
	// In some cases we don't receive email on address update event and it is therefore not available in front end form submission.
	$order->set_billing_email( sanitize_email( $response_body->consumer->contactInfo->email ) );

	// Add phone to order.
	// In some cases we don't receive phone on address update event and it is therefore not available in front end form submission.
	$order->set_billing_phone( sanitize_text_field( $response_body->consumer->contactInfo->cellular->number ) );

	// Payment method title.
	$payment_method_title = wc_ecster_get_payment_method_name( $response_body->properties->method );
	update_post_meta( $order_id, '_wc_ecster_payment_method', $response_body->properties->method );
	$order->add_order_note( sprintf( __( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ) );
	$order->set_payment_method_title( apply_filters( 'wc_ecster_payment_method_title', sprintf( __( '%s via Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ), $payment_method_title ) );
	$order->save();

	if ( $ecster_status ) {

		// Check Ecster order status.
		switch ( $ecster_status ) {
			case 'PENDING_SIGNATURE': // Part payment with no contract signed yet
				$order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
			case 'READY': // Card payment/invoice
			case 'FULLY_DELIVERED': // Card payment
					$order->payment_complete( $internal_reference );
				break;
			default:
					$order->add_order_note( __( 'Confirmation payment sequenze in Woo triggered but purchase in Ecster is not finalized. Ecster status: ' . $ecster_status, 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
		}
	} else {
		// No Ecster order status detected.
		$order->add_order_note( __( 'No Ecster order status was decected in Woo process_payment sequenze.', 'krokedil-ecster-pay-for-woocommerce' ) );
	}
}
