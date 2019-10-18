<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Ecster class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Ecster extends WC_Payment_Gateway {

	/** @var string Ecster API username. */
	public $username;

	/** @var string Ecster API password. */
	public $password;

	/** @var boolean Ecster API testmode. */
	public $testmode;

	/** @var boolean Ecster debug logging. */
	public $logging;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	private $allowed_tax_rates = array( 0, 6, 12, 25 );

	/**
	 * WC_Gateway_Ecster constructor.
	 */
	public function __construct() {
		$this->id                 = 'ecster';
		$this->method_title       = __( 'Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' );
		$this->method_description = __( 'Ecster Pay description', 'krokedil-ecster-pay-for-woocommerce' );
		$this->method_description = sprintf( __( 'Documentation <a href="%s" target="_blank">can be found here</a>.', 'krokedil-ecster-pay-for-woocommerce' ), 'http://docs.krokedil.com/se/documentation/ecster-pay-woocommerce/' );
		$this->has_fields         = true;
		$this->supports           = array( 'products', 'refunds' );
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		// Get setting values.
		$this->title                      = $this->get_option( 'title' );
		$this->description                = $this->get_option( 'description' );
		$this->enabled                    = $this->get_option( 'enabled' );
		$this->testmode                   = 'yes' === $this->get_option( 'testmode' );
		$this->logging                    = 'yes' === $this->get_option( 'logging' );
		$this->username                   = $this->testmode ? $this->get_option( 'test_username' ) : $this->get_option( 'username' );
		$this->password                   = $this->testmode ? $this->get_option( 'test_password' ) : $this->get_option( 'password' );
		$this->api_key                    = $this->get_option( 'api_key' );
		$this->merchant_key               = $this->get_option( 'merchant_key' );
		$this->select_another_method_text = $this->get_option( 'select_another_method_text' );

		if ( $this->testmode ) {
			$this->description .= ' TEST MODE ENABLED';
			$this->description  = trim( $this->description );
		}
		// Hooks.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		add_action( 'woocommerce_api_wc_gateway_ecster', array( $this, 'osn_listener' ) );
		add_action( 'woocommerce_thankyou_ecster', array( $this, 'ecster_thankyou' ) );
	}

	/**
	 * Logging method.
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		$ecster_settings = get_option( 'woocommerce_ecster_settings' );
		if ( 'yes' === $ecster_settings['logging'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'ecster', $message );
		}
	}

	/**
	 * Listens for ping by Ecster, containing full order details.
	 */
	function osn_listener() {
		$post_body = file_get_contents( 'php://input' );
		$decoded   = json_decode( $post_body );
		$order_id  = isset( $_GET['order_id'] ) ? $_GET['order_id'] : '';

		wp_schedule_single_event( time() + 120, 'ecster_execute_osn_callback', array( $decoded, $order_id ) );

		header( 'HTTP/1.0 200 OK' );
	}

	/**
	 * Enqueue checkout page scripts
	 */
	function checkout_scripts() {
		$checkout_cart_key = ecster_maybe_create_order();
		if ( is_checkout() ) {
			if ( $this->testmode ) {
				wp_register_script( 'ecster_pay', 'https://labs.ecster.se/pay/integration/ecster-pay-labs.js', array(), false, false );
			} else {
				wp_register_script( 'ecster_pay', 'https://secure.ecster.se/pay/integration/ecster-pay.js', array(), false, true );
			}
			$suffix                     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$select_another_method_text = ( $this->select_another_method_text ?: __( 'Select another payment method', 'krokedil-ecster-pay-for-woocommerce' ) );
			wp_register_script(
				'ecster_checkout',
				WC_ECSTER_PLUGIN_URL . '/assets/js/frontend/checkout' . $suffix . '.js',
				array( 'ecster_pay', 'jquery' ),
				WC_ECSTER_VERSION,
				true
			);
			wp_localize_script(
				'ecster_checkout', 'wc_ecster', array(
					'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
					'terms'                       => wc_get_page_permalink( 'terms' ),
					'select_another_method_text'  => $select_another_method_text,
					'wc_ecster_nonce'             => wp_create_nonce( 'wc_ecster_nonce' ),
					'move_checkout_fields'        => apply_filters( 'wc_ecster_move_checkout_fields', array( '' ) ),
					'move_checkout_fields_origin' => apply_filters( 'wc_ecster_move_checkout_fields_origin', '.woocommerce-shipping-fields' ),
					'ecster_checkout_cart_key'    => $checkout_cart_key,
				)
			);
			wp_enqueue_script( 'ecster_checkout' );
			wp_register_style(
				'ecster_checkout',
				WC_ECSTER_PLUGIN_URL . '/assets/css/frontend/checkout' . $suffix . '.css',
				array(),
				WC_ECSTER_VERSION
			);
			wp_enqueue_style( 'ecster_checkout' );
		}
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';
		$icon  = '';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}


	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include 'settings-ecster.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		echo $this->description;
	}

	/**
	 * Process the payment
	 *
	 * @param int     $order_id Reference.
	 * @param boolean $retry    Retry processing or not.
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = false ) {
		$order = wc_get_order( $order_id );
		$internal_reference = WC()->session->get( 'ecster_order_id' );
		update_post_meta( $order_id, '_wc_ecster_internal_reference', $internal_reference );
		$ecster_status = '';
		if ( $internal_reference ) {
			// Update reference
			$request  = new WC_Ecster_Request_Update_Reference( $this->api_key, $this->merchant_key, $this->testmode );
			$response = $request->response( $internal_reference, $order_id );

			// Get purchase data from Ecster
			$request       = new WC_Ecster_Request_Get_Order( $this->api_key, $this->merchant_key, $this->testmode );
			$response      = $request->response( $internal_reference );
			$response_body = json_decode( $response['body'] );

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
		}

		WC_Gateway_Ecster::log( 'Process payment for order ID ' . $order_id . '. Ecster internal reference ' . $internal_reference . '. Response body - ' . json_encode( $response_body ) );

		if ( $ecster_status ) {
			// Check Ecster order status
			switch ( $ecster_status ) {
				case 'PENDING_SIGNATURE': // Part payment with no contract signed yet
					$order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
					break;
				case 'READY': // Card payment/invoice
				case 'FULLY_DELIVERED': // Card payment
					if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
						$order->payment_complete();
					}
					break;
				default:
						$order->add_order_note( __( 'Thank you page rendered but purchase in Ecster is not finalized. Ecster status: ' . $ecster_status, 'krokedil-ecster-pay-for-woocommerce' ) );
					break;
			}

			// Payment method title.
			$payment_method_title = wc_ecster_get_payment_method_name( $response_body->properties->method );

			$order->add_order_note( sprintf( __( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ) );
			$order->set_payment_method_title( apply_filters( 'wc_ecster_payment_method_title', sprintf( __( '%s via Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ), $payment_method_title ) );
			$order->save();

		} else {
			// No Ecster order sttus detected
			$order->add_order_note( __( 'Thank you page rendered but no Ecster order status was decected.', 'krokedil-ecster-pay-for-woocommerce' ) );
		}

		WC()->session->__unset( 'order_awaiting_payment' );
		WC()->session->__unset( 'wc_ecster_method' );
		WC()->session->__unset( 'wc_ecster_invoice_fee' );
		WC()->session->__unset( 'ecster_checkout_cart_key' );
		WC()->session->__unset( 'ecster_order_id' );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}


	/**
	 * Add Ecster iframe to thankyou page.
	 */
	public function ecster_thankyou( $order_id ) {
		
	}

	/**
	 * Process refunds.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		// Check if amount equals total order
		$order = wc_get_order( $order_id );

		$credit_order = new WC_Ecster_Request_Credit_Order( $this->username, $this->password, $this->testmode, $this->api_key, $this->merchant_key );
		$response     = $credit_order->response( $order_id, $amount, $reason );
		$decoded      = json_decode( $response['body'] );

		if ( 201 == $response['response']['code'] && $decoded->transaction->amount == ( $amount * 100 ) ) {
			$order->add_order_note( sprintf( __( 'Ecster order credited with %1$s. Transaction reference %2$s. <a href="%3$s" target="_blank">Credit invoice</a>.', 'krokedil-ecster-pay-for-woocommerce' ), wc_price( $amount ), $decoded->transaction->transactionReference, $decoded->transaction->billPdfUrl ) );
			update_post_meta( $order_id, '_ecster_refund_id_' . $decoded->transaction->transactionReference, $decoded->transaction->id );
			update_post_meta( $order_id, '_ecster_refund_id_' . $decoded->transaction->transactionReference . '_invoice', $decoded->transaction->billPdfUrl );
			return true;
		} else {
			$order->add_order_note( sprintf( __( 'Ecster credit order failed. Code: %1$s. Type: %2$s. Message: %3$s', 'krokedil-ecster-pay-for-woocommerce' ), $decoded->code, $decoded->type, $decoded->message ) );
			return false;
		}
	}
}
