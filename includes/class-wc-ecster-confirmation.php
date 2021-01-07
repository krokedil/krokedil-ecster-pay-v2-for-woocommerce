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

		add_action( 'template_redirect', array( $this, 'ecster_confirm_order' ) );
	}

	/**
	 * Confirm order
	 */
	public function ecster_confirm_order() {
		$ecster_confirm     = filter_input( INPUT_GET, 'ecster_confirm', FILTER_SANITIZE_STRING );
		$internal_reference = filter_input( INPUT_GET, 'ecster_order_id', FILTER_SANITIZE_STRING );
		$order_key          = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );

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

		// Save internal reference to WC order.
		update_post_meta( $order_id, '_wc_ecster_internal_reference', $internal_reference );

		// Update reference.
		$request  = new WC_Ecster_Request_Update_Reference( $this->api_key, $this->merchant_key, $this->testmode );
		$response = $request->response( $internal_reference, $order->get_order_number() );

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

		WC_Gateway_Ecster::log( 'Confirm order ID ' . $order_id . ' from the confirmation page. Ecster internal reference ' . $internal_reference . '. Response body - ' . json_encode( $response_body ) );

		// Payment method title.
		$payment_method_title = wc_ecster_get_payment_method_name( $response_body->properties->method );
		update_post_meta( $order_id, '_wc_ecster_payment_method', $response_body->properties->method );
		$order->add_order_note( sprintf( __( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ) );
		$order->set_payment_method_title( apply_filters( 'wc_ecster_payment_method_title', sprintf( __( '%s via Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ), $payment_method_title ) );
		$order->save();

		if ( $ecster_status ) {

			// Check Ecster order status
			switch ( $ecster_status ) {
				case 'PENDING_SIGNATURE': // Part payment with no contract signed yet
					$order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
					break;
				case 'READY': // Card payment/invoice
				case 'FULLY_DELIVERED': // Card payment
					if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
						$order->payment_complete( $internal_reference );
					}
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





}
WC_Ecster_Confirmation::get_instance();
