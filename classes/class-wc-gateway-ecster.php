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
		$this->method_description = sprintf( __( 'Take payments via Ecster Pay v2. Documentation <a href="%s" target="_blank">can be found here</a>.', 'krokedil-ecster-pay-for-woocommerce' ), 'https://docs.krokedil.com/ecster-pay-for-woocommerce/' );
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
		$this->checkout_flow              = $this->settings['checkout_flow'] ?? 'embedded';

		if ( $this->testmode ) {
			$this->description .= ' TEST MODE ENABLED';
			$this->description  = trim( $this->description );
		}

		// Hooks.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
		$order = wc_get_order( $order_id );

		// Embedded flow.
		if ( 'embedded' === $this->checkout_flow && ! is_wc_endpoint_url( 'order-pay' ) ) {
			// Save payment type, card details & run $order->payment_complete() if all looks good.
			return $this->process_embedded_handler( $order_id );
		}

		// Redirect flow.
		// $response = Nets_Easy()->api->create_nets_easy_order( 'redirect', $order_id );
		return $this->process_redirect_handler( $order_id, array() );
	}

	/**
	 * Process the payment with information from Avarda and return the result.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function process_embedded_handler( $order_id ) {
		// Get the order object.
		$order = wc_get_order( $order_id );

		// Let other plugins hook into this sequence.
		do_action( 'ecster_wc_process_payment', $order_id );

		// 1. Process the payment.
		// 2. Return confirmation page url.
		$confirmation_url = add_query_arg(
			array(
				'ecster_confirm' => 'yes',
				'wc_order_id'    => $order_id,
			),
			$this->get_return_url( $order )
		);
		return array(
			'result'       => 'success',
			'redirect_url' => $confirmation_url,
		);
	}

	/**
	 * @param int   $order_id The WooCommerce order id.
	 * @param array $response The response from payment.
	 *
	 * @return array|string[]
	 */
	protected function process_redirect_handler( $order_id, $response ) {
		$order = wc_get_order( $order_id );

		$ecster_order = Ecster_WC()->api->create_ecster_cart( $order_id );
		if ( is_wp_error( $ecster_order ) || ! isset( $ecster_order['checkoutCart']['key'] ) ) {
			return array(
				'result'  => 'error',
				'message' => 'Error when creating Ecster session',
			);
		}

		// Save internal reference to WC order.
		update_post_meta( $order_id, '_wc_ecster_internal_reference', $ecster_order['checkoutCart']['key'] );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Receipt page.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 * @return void
	 */
	public function receipt_page( $order_id ) {
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

		$order = wc_get_order( $order_id );

		if ( '' !== get_post_meta( $order_id, '_wc_ecster_swish_id', true ) ) {

			$response = Ecster_WC()->api->refund_ecster_swish_order( $order_id, $amount, $reason );

			if ( is_wp_error( $response ) ) {
				$order->add_order_note( sprintf( __( 'Ecster credit Swish order failed. Code: %1$s. Type: %2$s. Message: %3$s', 'krokedil-ecster-pay-for-woocommerce' ), $response->get_error_code(), $response->get_error_message() ) );
				return false;
			}

			update_post_meta( $order_id, '_wc_ecster_order_amount', $amount );

			if ( 'ONGOING' === $response['status'] ) {

				// Poll status and possibly reschedule a new check.
				return wc_ecster_handle_swish_refund_status( $order_id, $amount );

			} elseif ( 'SUCCESS' === $response['status'] ) {
				$order->add_order_note( sprintf( __( 'Ecster order credited with %1$s. ', 'krokedil-ecster-pay-for-woocommerce' ), wc_price( $amount ) ) );
				return true;
			} else {
				$order->add_order_note( sprintf( __( 'Ecster credit order failed.', 'krokedil-ecster-pay-for-woocommerce' ) ) );
				return false;
			}
		} else {

			$response = Ecster_WC()->api->refund_ecster_order( $order_id, $amount, $reason );

			if ( is_wp_error( $response ) ) {
				$order->add_order_note( sprintf( __( 'Ecster credit order failed. Code: %1$s. Type: %2$s. Message: %3$s', 'krokedil-ecster-pay-for-woocommerce' ), $response->get_error_code(), $response->get_error_message() ) );
				return false;
			}

			$order->add_order_note( sprintf( __( 'Ecster order credited with %1$s. Transaction reference %2$s. <a href="%3$s" target="_blank">Credit invoice</a>.', 'krokedil-ecster-pay-for-woocommerce' ), wc_price( $amount ), $response['transaction']['transactionReference'], $response['transaction']['billPdfUrl'] ) );
			update_post_meta( $order_id, '_ecster_refund_id_' . $response['transaction']['transactionReference'], $response['transaction']['id'] );
			update_post_meta( $order_id, '_ecster_refund_id_' . $response['transaction']['transactionReference'] . '_invoice', $response['transaction']['billPdfUrl'] );
			return true;
		}
	}
}
