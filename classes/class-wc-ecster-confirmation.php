<?php
/**
 * Confirmation class file.
 *
 * @package WC_Ecster/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Confirmation class.
 */
class WC_Ecster_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$ecster_settings    = get_option( 'woocommerce_ecster_settings' );
		$this->testmode     = 'yes' === $ecster_settings['testmode'];
		$this->api_key      = $ecster_settings['api_key'];
		$this->merchant_key = $ecster_settings['merchant_key'];

		add_action( 'template_redirect', array( $this, 'confirm_order' ) );
	}

	/**
	 * Confirm order
	 */
	public function confirm_order() {
		$ecster_confirm              = filter_input( INPUT_GET, 'ecster_confirm', FILTER_SANITIZE_STRING );
		$order_key                   = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$internal_reference_embedded = filter_input( INPUT_GET, 'ecster_order_id', FILTER_SANITIZE_STRING );
		$internal_reference_redirect = filter_input( INPUT_GET, 'internalReference', FILTER_SANITIZE_STRING );
		$internal_reference          = ! empty( $internal_reference_embedded ) ? $internal_reference_embedded : $internal_reference_redirect;

		// Return if we dont have our parameters set.
		if ( empty( $ecster_confirm ) || empty( $internal_reference ) || empty( $order_key ) ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );

		// Return if we cant find an order id.
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// If the order is already completed, return.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		wc_ecster_confirm_order( $order_id, $internal_reference );
	}


}
WC_Ecster_Confirmation::get_instance();
