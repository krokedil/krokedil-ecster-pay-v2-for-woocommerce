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

		add_action( 'wp_ajax_wc_change_to_ecster', array( $this, 'wc_change_to_ecster' ) );
		add_action( 'wp_ajax_nopriv_wc_change_to_ecster', array( $this, 'wc_change_to_ecster' ) );

		add_action( 'wp_ajax_wc_ecster_on_checkout_start_failure', array( $this, 'ajax_on_checkout_start_failure' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_on_checkout_start_failure', array( $this, 'ajax_on_checkout_start_failure' ) );

		add_action( 'wp_ajax_wc_ecster_log_js_to_file', array( $this, 'log_js_to_file' ) );
		add_action( 'wp_ajax_nopriv_wc_ecster_log_js_to_file', array( $this, 'log_js_to_file' ) );
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
				$data['ecster_cart_key'] = $decoded->checkoutCart->key;
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
				$data['ecster_cart_key'] = $decoded->checkoutCart->key;
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
	 * Adds invoice fee to WooCommerce order
	 *
	 * @param array $fees Array of fees returned by Ecster.
	 * @TODO: Fix this
	 */
	function helper_add_invoice_fees_to_session( $fees ) {
		WC()->session->set( 'wc_ecster_invoice_fee', $fees );
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

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public function log_js_to_file() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_ecster_nonce' ) ) {
			WC_Gateway_Ecster::log( 'Nonce can not be verified - log_js_to_file.' );
			exit( 'Nonce can not be verified.' );
		}
		$posted_message  = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$ecster_order_id = WC()->session->get( 'ecster_checkout_cart_key' );
		$message         = "Frontend JS $ecster_order_id: $posted_message";
		WC_Gateway_Ecster::log( $message );
		wp_send_json_success();
		wp_die();
	}
}
$wc_ecster_ajax = new WC_Ecster_Ajax();
