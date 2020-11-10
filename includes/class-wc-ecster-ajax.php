<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Ajax class.
 */
class WC_Ecster_Ajax {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/**
	 * WC_Ecster_Ajax constructor.
	 */
	function __construct() {
		$ecster_settings    = get_option( 'woocommerce_ecster_settings' );
		$this->testmode     = 'yes' === $ecster_settings['testmode'];
		$this->api_key      = $ecster_settings['api_key'];
		$this->merchant_key = $ecster_settings['merchant_key'];

		add_action( 'wp_ajax_wc_ecster_create_cart', array( $this, 'ajax_create_cart' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_create_cart', array( $this, 'ajax_create_cart' ) );

		add_action( 'wp_ajax_wc_ecster_update_cart', array( $this, 'ajax_update_cart' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_update_cart', array( $this, 'ajax_update_cart' ) );

		add_action( 'wp_ajax_wc_ecster_fail_local_order', array( $this, 'ajax_fail_local_order' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_fail_local_order', array( $this, 'ajax_fail_local_order' ) );

		add_action( 'wp_ajax_wc_ecster_on_customer_authenticated', array( $this, 'ajax_on_customer_authenticated' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_customer_authenticated', array( $this, 'ajax_on_customer_authenticated' ) );

		add_action( 'wp_ajax_wc_ecster_on_changed_delivery_address', array( $this, 'ajax_on_changed_delivery_address' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_changed_delivery_address', array( $this, 'ajax_on_changed_delivery_address' ) );

		add_action( 'wp_ajax_wc_ecster_on_payment_success', array( $this, 'ajax_on_payment_success' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_payment_success', array( $this, 'ajax_on_payment_success' ) );

		add_action( 'wp_ajax_wc_ecster_on_checkout_error', array( $this, 'ajax_on_checkout_error' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_checkout_error', array( $this, 'ajax_on_checkout_error' ) );

		add_action( 'wp_ajax_wc_change_to_ecster', array( $this, 'wc_change_to_ecster' ) );
		add_action( 'wp_ajax_nopriv_wc_change_to_ecster', array( $this, 'wc_change_to_ecster' ) );

		add_action( 'wp_ajax_wc_ecster_on_checkout_start_failure', array( $this, 'ajax_on_checkout_start_failure' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_checkout_start_failure', array( $this, 'ajax_on_checkout_start_failure' ) );
	}

	/**
	 * Creates Ecster cart.
	 */
	function ajax_create_cart() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - create_cart.' );
			exit( 'Nonce can not be verified.' );
		}

		$data     = array();
		$request  = new WC_Ecster_Request_Create_Cart( $this->api_key, $this->merchant_key, $this->testmode );
		$response = $request->response();

		if ( ! is_wp_error( $response ) && 201 == $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );
			if ( is_string( $decoded->checkoutCart->key ) ) {
				$data['wc_ecster_cart_key'] = $decoded->checkoutCart->key;
				wp_send_json_success( $data );
			}
		} else {
			if ( is_wp_error( $response ) ) {
				$error_title  = $response->get_error_code();
				$error_detail = $response->get_error_message();
			} else {
				$decoded      = json_decode( $response['body'] );
				$error_title  = $decoded->title;
				$error_detail = $decoded->detail;
			}
			WC_Gateway_Ecster::log( 'Ecster create cart ' . $error_title . ': ' . $error_detail );
			$data['error_message'] = __( 'Ecster Pay create cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
			wp_send_json_error( $data );
		}

		wp_die();
	}

	/**
	 * Updates Ecster cart.
	 */
	function ajax_update_cart() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - update_cart.' );
			exit( 'Nonce can not be verified.' );
		}

		if ( ! WC()->cart->needs_payment() ) {
			wp_send_json_success(
				array(
					'refreshZeroAmount' => 'refreshZeroAmount',
				)
			);
			wp_die();
		}

		$customer_type = ! empty( $_POST['customer_type'] ) ? $_POST['customer_type'] : null;
		$cart_key      = $_POST['cart_key'];
		$data          = array();
		$request       = new WC_Ecster_Request_Update_Cart( $this->api_key, $this->merchant_key, $this->testmode );
		$response      = $request->response( $cart_key, $customer_type );
		$response_body = json_decode( $response['body'] );

		WC()->session->set( 'ecster_checkout_cart_key', $response_body->checkoutCart->key );

		if ( ! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$decoded = json_decode( $response['body'] );
			if ( is_string( $decoded->checkoutCart->key ) ) {
				$data['wc_ecster_cart_key'] = $decoded->checkoutCart->key;
				wp_send_json_success( $data );
			}
		} else {
			if ( is_wp_error( $response ) ) {
				$error_title  = $response->get_error_code();
				$error_detail = $response->get_error_message();
			} else {
				$decoded      = json_decode( $response['body'] );
				$error_title  = $decoded->title;
				$error_detail = $decoded->detail;
			}
			WC_Gateway_Ecster::log( 'Ecster update cart ' . $error_title . ': ' . $error_detail );
			$data['error_message'] = __( 'Ecster Pay update cart request failed ' . $error_title . ' (' . $error_detail . ').', 'krokedil-ecster-pay-for-woocommerce' );
			wp_send_json_error( $data );
		}
		wp_die();
	}

	/**
	 * Fails WooCommerce order.
	 */
	function ajax_fail_local_order() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - fail_local_order.' );
			exit( 'Nonce can not be verified.' );
		}
		$reason = $_POST['reason'];

		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) {
			$local_order_id = WC()->session->get( 'order_awaiting_payment' );
			$local_order    = wc_get_order( $local_order_id );

			if ( 'failed' === $reason ) {
				$local_order->add_order_note( __( 'Ecster payment failed', 'krokedil-ecster-pay-for-woocommerce' ) );
			} elseif ( 'denied' === $reason ) {
				$local_order->add_order_note( __( 'Ecster payment denied', 'krokedil-ecster-pay-for-woocommerce' ) );
			}

			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
		wp_die();
	}

	/**
	 * On customer authentication.
	 *
	 * Maybe create local order.
	 * Populate local order.
	 * Populate session customer data.
	 * Calculate cart totals.
	 * Update order cart hash.
	 */
	function ajax_on_customer_authenticated() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_customer_authenticated.' );
			exit( 'Nonce can not be verified.' );
		}

		$must_login         = 'no';
		$must_login_message = apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) );

		$customer_data = $_POST['customer_data']; // Input var okay.

		// If customer exist, is not logged and guest checkout isn't enabled - tell the customer to login.
		if ( ! is_user_logged_in() && 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ) {
			if ( email_exists( $customer_data['email'] ) ) {
				// Email exist in a user account, customer must login.
				$must_login = 'yes';
			}
		}

		wp_send_json_success(
			array(
				'mustLogin'        => $must_login,
				'mustLoginMessage' => $must_login_message,
			)
		);
		wp_die();
	}

	/**
	 * On changed delivery address.
	 *
	 * Set delivery address
	 */
	function ajax_on_changed_delivery_address() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_changed_delivery_address.' );
			exit( 'Nonce can not be verified.' );
		}

		$delivery_address = $_POST['delivery_address']; // Input var okay.

		WC()->customer->set_shipping_address_1( $delivery_address['address'] );
		WC()->customer->set_shipping_city( $delivery_address['city'] );
		WC()->customer->set_shipping_postcode( $delivery_address['zip'] );
		WC()->customer->set_shipping_country( $delivery_address['countryCode'] );
		WC()->customer->set_shipping_first_name( $delivery_address['firstName'] );
		WC()->customer->set_shipping_last_name( $delivery_address['lastName'] );
		WC()->customer->save();

		wp_send_json_success();
		wp_die();
	}

	/**
	 * On payment success.
	 *
	 * Maybe add invoice fee to order.
	 * Calculate cart totals.
	 * Update order cart hash.
	 */
	public function ajax_on_payment_success() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) { // Input var okay.
			WC_Gateway_Ecster::log( 'Nonce can not be verified - on_payment_success.' );
			exit( 'Nonce can not be verified.' );
		}
		$payment_data = $_POST['payment_data']; // Input var okay.
		WC()->session->set( 'ecster_order_id', $payment_data['internalReference'] );

		// Prevent duplicate orders if payment complete event is triggered twice or if order already exist in Woo (via API callback).
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'ecster',
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = null;
		foreach ( $orders as $order_id ) {
			$order_payment_id = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );
			if ( $order_payment_id === $payment_data['internalReference'] ) {
				$order_id_match = $order_id;
				break;
			}
		}

		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );
			if ( $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
				WC_Gateway_Ecster::log( 'Payment success event triggeres but _wc_ecster_internal_reference already exist in order ID: ' . $order_id_match );
				$location = $order->get_checkout_order_received_url();
				WC_Gateway_Ecster::log( '$location: ' . $location );
				wp_send_json_error( array( 'redirect' => $location ) );
				wp_die();
			}
		}

		if ( isset( $payment_data['fees'] ) ) {
			$this->helper_add_invoice_fees_to_session( $payment_data['fees'] );
		}

		wp_send_json_success();
		wp_die();
	}

	/**
	 * Handles WooCommerce checkout error, after Ecster order has already been created.
	 */
	public function ajax_on_checkout_error() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		WC_Gateway_Ecster::log( 'Ecster checkout_error triggered' );
		$ecster_order_id = WC()->session->get( 'ecster_order_id' );
		$redirect_url    = $this->check_if_order_exists( $ecster_order_id );

		if ( empty( $redirect_url ) ) {

			$request       = new WC_Ecster_Request_Get_Order( $this->api_key, $this->merchant_key, $this->testmode );
			$response      = $request->response( $ecster_order_id );
			$response_body = json_decode( $response['body'] );

			$order    = wc_create_order();
			$order_id = $order->get_id();
			WC_Gateway_Ecster::log( 'Ecster checkout_error - creating order id ' . $order_id );

			update_post_meta( $order_id, '_wc_ecster_internal_reference', $ecster_order_id );
			update_post_meta( $order_id, '_transaction_id', $ecster_order_id );
			update_post_meta( $order_id, '_wc_ecster_payment_method', $response_body->properties->method );

			$this->add_order_payment_method( $order, $response_body ); // Store payment method.

			$this->helper_add_customer_data_to_local_order( $order, $response_body );

			$this->helper_add_items_to_local_order( $order );

			// Add order fees.
			$this->helper_add_order_fees( $order );

			// Add Ecster invoice fee
			$this->helper_maybe_add_invoice_fee( $order );

			// Add order shipping.
			$this->helper_add_order_shipping( $order );

			// Add order taxes.
			$this->helper_add_order_tax_rows( $order );

			// Store coupons.
			$this->helper_add_order_coupons( $order );

			// Save order totals
			$this->helper_calculate_order_totals( $order );

			// Add order note
			if ( ! empty( $_POST['error_message'] ) ) { // Input var okay.
				$error_message = 'Error message: ' . sanitize_text_field( trim( $_POST['error_message'] ) );
			} else {
				$error_message = 'Error message could not be retreived';
			}
			$order->set_status( 'on-hold' );
			$order->save();

			/**
			 * Added to simulate WCs own order creation.
			 *
			 * TODO: Add the order content into a $data variable and pass as second parameter to the hook.
			 */
			do_action( 'woocommerce_checkout_update_order_meta', $order_id, array() );

			$note = sprintf( __( 'This order was made as a fallback due to an error in the checkout (%s). Please verify the order with Ecster.', 'krokedil-ecster-pay-for-woocommerce' ), $error_message );
			$order->add_order_note( $note );

			$redirect_url = $order->get_checkout_order_received_url();
		}

		/*
		$redirect_url = add_query_arg(
			array(
				'ecster-osf' => 'true',
				'order-id'   => $local_order_id,
			),
			$redirect_url
		);
		*/

		wp_send_json_success( array( 'redirect' => $redirect_url ) );
		wp_die();
	}

	/**
	 * Ecster checkout start failure. Triggered when Ecster cart session has expired.
	 * Removes the ecster_checkout_cart_key session.
	 */
	public function ajax_on_checkout_start_failure() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - ajax_on_checkout_start_failure.' );
			exit( 'Nonce can not be verified.' );
		}

		if ( method_exists( WC()->session, '__unset' ) ) {
			if ( WC()->session->get( 'ecster_checkout_cart_key' ) ) {
				WC()->session->__unset( 'ecster_checkout_cart_key' );
			}
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
		wp_die();
	}

	/**
	 * Helpers.
	 */

	/**
	 * Check if an order already exist with the current Ecster internal reference.
	 *
	 * @return void.
	 */
	public function check_if_order_exists( $ecster_order_id = null ) {
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'ecster',
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = null;
		foreach ( $orders as $order_id ) {
			$ecster_internal_reference = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );
			if ( strtolower( $ecster_internal_reference ) === strtolower( $ecster_order_id ) ) {
				$order_id_match = $order_id;
				break;
			}
		}
		// _wc_ecster_internal_reference already exist in an order. Let's redirect the customer to the thankyou page for that order.
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );
			return $order->get_checkout_order_received_url();
		}
		return false;
	}

	/**
	 * Adds order items to ongoing order.
	 *
	 * @param  integer $order WooCommerce order.
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_items_to_local_order( $order ) {

		// Remove items as to stop the item lines from being duplicated.
		$order->remove_order_items();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) { // Store the line items to the new/resumed order.
			$item_id = $order->add_product(
				$values['data'],
				$values['quantity'],
				array(
					'variation' => $values['variation'],
					'totals'    => array(
						'subtotal'     => $values['line_subtotal'],
						'subtotal_tax' => $values['line_subtotal_tax'],
						'total'        => $values['line_total'],
						'tax'          => $values['line_tax'],
						'tax_data'     => $values['line_tax_data'],
					),
				)
			);

			if ( ! $item_id ) {
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 525 ) );
			}

			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key ); // Allow plugins to add order item meta.
		}
	}

	/**
	 * Adds customer data to WooCommerce order.
	 *
	 * @param integer $local_order_id WooCommerce order ID.
	 * @param array   $customer_data  Customer data returned by Ecster.
	 * @param array   $addresses      Addresses to update (shipping and/or billing).
	 */
	public function helper_add_customer_data_to_local_order( $order, $response_body ) {

		// Add/update customer and order info to order
		$billing_first_name = ( $response_body->consumer->name->firstName ?: $response_body->recipient->firstName );
		$billing_last_name  = ( $response_body->consumer->name->lastName ?: $response_body->recipient->lastName );
		$billing_postcode   = ( $response_body->consumer->address->zip ?: $response_body->recipient->zip );
		$billing_address    = ( $response_body->consumer->address->line1 ?: $response_body->recipient->address );
		$billing_city       = ( $response_body->consumer->address->city ?: $response_body->recipient->city );
		if ( ! isset( $response_body->consumer->address->country ) ) {
			$billing_country = 'SE';
		} else {
			$billing_country = $response_body->consumer->address->country;
		}

		$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
		$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
		$order->set_billing_country( sanitize_text_field( $billing_country ) );
		$order->set_billing_address_1( sanitize_text_field( $billing_address ) );
		$order->set_billing_city( sanitize_text_field( $billing_city ) );
		$order->set_billing_postcode( sanitize_text_field( $billing_postcode ) );
		$order->set_billing_phone( sanitize_text_field( $response_body->consumer->contactInfo->cellular->number ) );
		$order->set_billing_email( sanitize_text_field( $response_body->consumer->contactInfo->email ) );

		if ( isset( $response_body->recipient ) ) {
			$order->set_shipping_first_name( sanitize_text_field( $response_body->recipient->name->firstName ) );
			$order->set_shipping_last_name( sanitize_text_field( $response_body->recipient->name->lastName ) );
			$order->set_shipping_country( sanitize_text_field( $response_body->recipient->address->country ) );
			$order->set_shipping_address_1( sanitize_text_field( $response_body->recipient->address->line1 ) );
			$order->set_shipping_city( sanitize_text_field( $response_body->recipient->address->city ) );
			$order->set_shipping_postcode( sanitize_text_field( $response_body->recipient->address->zip ) );
		} else {
			$order->set_shipping_first_name( sanitize_text_field( $response_body->consumer->name->firstName ) );
			$order->set_shipping_last_name( sanitize_text_field( $response_body->consumer->name->lastName ) );
			$order->set_shipping_country( sanitize_text_field( $billing_country ) );
			$order->set_shipping_address_1( sanitize_text_field( $response_body->consumer->address->line1 ) );
			$order->set_shipping_city( sanitize_text_field( $response_body->consumer->address->city ) );
			$order->set_shipping_postcode( sanitize_text_field( $response_body->consumer->address->zip ) );
		}
	}

	/**
	 * Adds customer data to WooCommerce order.
	 *
	 * @param array $customer_data Customer data returned by Ecster.
	 * @param array $addresses     Addresses to update (shipping and/or billing).
	 */
	function helper_add_customer_data_to_session( $customer_data, $addresses ) {
		$country = isset( $customer_data['countryCode'] ) ? $customer_data['countryCode'] : 'SE';

		if ( in_array( 'billing', $addresses, true ) ) {
			krokedil_customer_set_country( $country );
			krokedil_customer_set_postcode( $customer_data['zip'] );
			krokedil_customer_set_city( $customer_data['city'] );
			krokedil_customer_set_address( $customer_data['address'] );
		}

		if ( in_array( 'shipping', $addresses, true ) ) {
			WC()->customer->set_shipping_country( $country );
			WC()->customer->set_shipping_postcode( $customer_data['zip'] );
			WC()->customer->set_shipping_city( $customer_data['city'] );
			WC()->customer->set_shipping_address( $customer_data['address'] );
		}
	}

	/**
	 * Adds order items to ongoing order.
	 *
	 * @param integer $local_order_id WooCommerce order ID.
	 */
	function helper_calculate_order_cart_hash( $local_order_id ) {
		if ( method_exists( WC()->cart, 'get_cart_hash' ) ) {
			$hash = WC()->cart->get_cart_hash();
		} else {
			$hash = md5( wp_json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total );
		}
		update_post_meta( $local_order_id, '_cart_hash', $hash );
	}

	/**
	 * Calculates cart totals.
	 */
	function helper_calculate_cart_totals() {
		WC()->cart->calculate_fees();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
	}

	/**
	 * Calculates cart totals.
	 */
	function helper_calculate_order_totals( $order ) {
		$order->calculate_totals();
		$order->save();
	}

	/**
	 * Adds invoice fee to WooCommerce order
	 *
	 * @param array $fees Array of fees returned by Ecster.
	 * @TODO: Fix this
	 */
	function helper_add_invoice_fees_to_session( $fees ) {
		WC()->session->set( 'wc_ecster_invoice_fee', $fees );
	}

	/**
	 * Adds order fees to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_fees( $order ) {
		$order_id = krokedil_get_order_id( $order );
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );
			if ( ! $item_id ) {
				WC_Gateway_Ecster::log( 'Unable to add order fee.' );
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
			// Allow plugins to add order item meta to fees.
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}
	}

	/**
	 * Adds Ecster invoice fee to local order if session exist.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_maybe_add_invoice_fee( $order ) {
		if ( WC()->session->get( 'wc_ecster_invoice_fee' ) ) {
			$fees = WC()->session->get( 'wc_ecster_invoice_fee' );
			foreach ( $fees as $fee ) {
				$ecster_fee            = new stdClass();
				$ecster_fee->id        = sanitize_title( __( 'Ecster Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' ) );
				$ecster_fee->name      = __( 'Ecster Invoice Fee', 'krokedil-ecster-pay-for-woocommerce' );
				$ecster_fee->amount    = $fee / 100;
				$ecster_fee->taxable   = false;
				$ecster_fee->tax       = 0;
				$ecster_fee->tax_data  = array();
				$ecster_fee->tax_class = '';
				$fee_id                = $order->add_fee( $ecster_fee );

				if ( ! $fee_id ) {
					$order->add_order_note( __( 'Unable to add Ecster invoice fee to the order.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				WC()->session->__unset( 'wc_ecster_invoice_fee' );
			}
		}
	}

	/**
	 * Adds order shipping to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 * @internal param object $order_id Ecster order.
	 */
	public function helper_add_order_shipping( $order ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$order_id              = krokedil_get_order_id( $order );
		$this_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		WC()->cart->calculate_shipping();
		// Store shipping for all packages.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );
				if ( ! $item_id ) {
					WC_Gateway_Ecster::log( 'Unable to add shipping item.' );
					throw new Exception( __( 'Error: Unable to add shipping item. Please try again.', 'woocommerce' ) );
				}
				// Allows plugins to add order item meta to shipping.
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}
	/**
	 * Adds order tax rows to local order.
	 *
	 * @since  1.6.0
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_tax_rows( $order ) {
		// Store tax rows.
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				WC_Gateway_Ecster::log( 'Unable to add taxes.' );
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 405 ) );
			}
		}
	}
	/**
	 * Adds order coupons to local order.
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 *
	 * @throws Exception PHP Exception.
	 */
	public function helper_add_order_coupons( $order ) {
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, WC()->cart->get_coupon_discount_amount( $code ) ) ) {
				WC_Gateway_Ecster::log( 'Unable to create order.' );
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
		}
	}

	/**
	 * Adds payment method to local order.
	 *
	 * @since  1.4.1
	 * @access public
	 */
	public function add_order_payment_method( $order, $response_body ) {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['ecster'];
		$order->set_payment_method( $payment_method );
		// Payment method title.
		$payment_method_title = wc_ecster_get_payment_method_name( $response_body->properties->method );
		$order->add_order_note( sprintf( __( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ) );
		$order->set_payment_method_title( apply_filters( 'wc_ecster_payment_method_title', sprintf( __( '%s via Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ), $payment_method_title ) );
	}

	public function wc_change_to_ecster() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'wc_change_to_ecster_nonce' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['ecster'] ) {
			// Set chosen payment method to first gateway that is not PaysonCheckout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'ecster' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'ecster' );
		}
		WC()->payment_gateways()->set_current_gateway( $available_gateways );
		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);
		wp_send_json_success( $data );
		wp_die();
	}
}
$wc_ecster_ajax = new WC_Ecster_Ajax();
