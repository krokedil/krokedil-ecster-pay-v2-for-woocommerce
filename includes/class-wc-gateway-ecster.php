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
		$this->method_description = sprintf( __( 'Take payments via Ecster Pay v2. Documentation <a href="%s" target="_blank">can be found here</a>.', 'krokedil-ecster-pay-for-woocommerce' ), 'https://docs.krokedil.com/article/313-ecster-pay-v2-introduction' );
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
		add_action( 'woocommerce_thankyou', array( $this, 'ecster_thankyou' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_ecster_temp_order_id_to_order' ), 10, 3 );

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
		$post_body            = file_get_contents( 'php://input' );
		$decoded              = json_decode( $post_body );
		$ecster_temp_order_id = isset( $_GET['etoid'] ) ? $_GET['etoid'] : '';
		self::log( 'OSN callback triggered. Ecster internal reference: ' . $decoded->orderId ); // Input var okay.
		// wp_schedule_single_event( time() + 120, 'ecster_execute_osn_callback', array( $decoded, $order_id ) );

		$scheduled_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'ecster_execute_osn_callback',
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'args'   => array( $decoded, $ecster_temp_order_id ),
			),
			'ids'
		);

		if ( empty( $scheduled_actions ) ) {
			as_schedule_single_action( time() + 120, 'ecster_execute_osn_callback', array( $decoded, $ecster_temp_order_id ) );
		} else {
			self::log( 'OSN callback. Update already scheduled. ' . wp_json_encode( $scheduled_actions ) ); // Input var okay.
		}

		header( 'HTTP/1.0 200 OK' );
	}

	/**
	 * Enqueue checkout page scripts
	 */
	function checkout_scripts() {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			$checkout_cart_key = ecster_maybe_create_order();
			if ( $this->testmode ) {
				wp_register_script( 'ecster_pay', 'https://labs.ecster.se/pay/integration/ecster-pay-labs.js', array(), false, false );
			} else {
				wp_register_script( 'ecster_pay', 'https://secure.ecster.se/pay/integration/ecster-pay.js', array(), false, true );
			}
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script(
				'ecster_checkout',
				WC_ECSTER_PLUGIN_URL . '/assets/js/frontend/checkout' . $suffix . '.js',
				array( 'ecster_pay', 'jquery' ),
				WC_ECSTER_VERSION,
				true
			);

			wp_localize_script(
				'ecster_checkout',
				'wc_ecster',
				array(
					'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
					'terms'                       => wc_get_page_permalink( 'terms' ),
					'wc_ecster_nonce'             => wp_create_nonce( 'wc_ecster_nonce' ),
					'wc_change_to_ecster_nonce'   => wp_create_nonce( 'wc_change_to_ecster_nonce' ),
					'move_checkout_fields'        => apply_filters( 'wc_ecster_move_checkout_fields', array( '' ) ),
					'move_checkout_fields_origin' => apply_filters( 'wc_ecster_move_checkout_fields_origin', '.woocommerce-shipping-fields' ),
					'ecster_checkout_cart_key'    => $checkout_cart_key,
					'timeout_time'                => 9,
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
	 * Saves Ecster specific data stored in WC()->session to Woo order when created.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param array  $posted_data The WooCommerce checkout form posted data.
	 * @param object $order WooCommerce order.
	 *
	 * @return void
	 */
	public function save_ecster_temp_order_id_to_order( $order_id, $posted_data, $order ) {
		if ( 'ecster' === $order->get_payment_method() ) {
			update_post_meta( $order_id, '_wc_ecster_temp_order_id', WC()->session->get( 'ecster_temp_order_id' ) );
		}
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

		$response = array(
			'return_url' => add_query_arg( 'ecster_confirm', 'yes', $this->get_return_url( $order ) ),
			'time'       => time(),
		);
		return array(
			'result'   => 'success',
			'redirect' => '#ecster-success=' . base64_encode( wp_json_encode( $response ) ), //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to give a unique nondescript string.
		);
	}


	/**
	 * Add Ecster iframe to thankyou page.
	 */
	public function ecster_thankyou( $order_id ) {
		wc_ecster_unset_sessions();
	}

	/**
	 * Process refunds.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		// Check if amount equals total order
		$order = wc_get_order( $order_id );

		$credit_order = new WC_Ecster_Request_Credit_Order( $this->api_key, $this->merchant_key, $this->testmode );
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
