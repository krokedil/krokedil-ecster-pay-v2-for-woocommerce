<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Ecster_Api_Callbacks class.
 *
 * Class that handles Ecster API callbacks.
 */
class Ecster_Api_Callbacks {

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
	 * Ecster_Api_Callbacks constructor.
	 */
	public function __construct() {
		$ecster_settings    = get_option( 'woocommerce_ecster_settings' );
		$this->testmode     = 'yes' === $ecster_settings['testmode'];
		$this->api_key      = $ecster_settings['api_key'];
		$this->merchant_key = $ecster_settings['merchant_key'];

		add_action( 'ecster_execute_osn_callback', array( $this, 'execute_osn_callback' ), 10, 3 );

	}

	public function execute_osn_callback( $decoded, $ecster_temp_order_id = '' ) {
		$internal_reference = $decoded['orderId'];
		$external_reference = $decoded['orderReference'];
		$request            = new WC_Ecster_Request_Get_Order( $this->api_key, $this->merchant_key, $this->testmode );
		$response           = $request->response( $internal_reference );
		$response_body      = json_decode( $response['body'] );

		WC_Gateway_Ecster::log( 'OSN callback executed. Tmp order ID:' . $ecster_temp_order_id ); // Input var okay.
		WC_Gateway_Ecster::log( 'OSN callback executed. Response body:' . wp_json_encode( $response_body ) );

		$order_id = $this->get_order_id_from_internal_reference( $internal_reference );

		if ( empty( $order_id ) ) { // We're missing Order ID in callback. Try to get it via query by internal reference.
			$order_id = wc_ecster_get_order_id_by_temp_order_id( $ecster_temp_order_id );
		}

		if ( ! empty( $order_id ) ) { // Input var okay.

			$this->update_woocommerce_order( $response_body, $order_id, $internal_reference );

		} else { // We can't find a coresponding Order ID.
			WC_Gateway_Ecster::log( 'OSN callback. No corresponding order ID in Woo. Ecster order status: ' . $response_body->status . '. Woo order ID: ' . $order_id . '.' ); // Input var okay.
		} // End if().
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $private_id, $public_token, $customer_type.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function get_order_id_from_internal_reference( $internal_reference = null ) {

		if ( empty( $internal_reference ) ) {
			return false;
		}

		// Let's check so the internal reference doesn't already exist in an existing order.
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'ecster',
				'date_created'   => '>' . ( time() - ( 120 * DAY_IN_SECONDS ) ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';
		foreach ( $orders as $order_id ) {

			$order_internal_reference = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );

			if ( $order_internal_reference === $internal_reference ) {
				$order_id_match = $order_id;
				WC_Gateway_Ecster::log( 'Internal reference ' . $internal_reference . ' exist in order ID ' . $order_id_match );
				break;
			}
		}

		return $order_id_match;
	}


	/**
	 * Update WooCommerce order on Ecster OSN.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function update_woocommerce_order( $response_body, $order_id, $internal_reference ) {

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			WC_Gateway_Ecster::log( 'Error. Could not instantiate an order object in OSN callback for order id ' . $order_id . '. Aborting callback.' );
			return;
		}

		switch ( $response_body->status ) {
			case 'AWAITING_CONTRACT': // Do nothing - these order statuses should be handled in process_payment().
				break;
			case 'READY':
				if ( empty( $order->get_date_paid() ) ) {
					wc_ecster_confirm_order( $order_id, $internal_reference, $response_body );
					$order->add_order_note( __( 'Ecster reported order status ready.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'FULLY_DELIVERED':
				if ( 'INVOICE' == $response_body->properties->method || 'ACCOUNT' == $response_body->properties->method ) {
					$order->add_order_note( __( 'Ecster reported order fully delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				if ( empty( $order->get_date_paid() ) ) {
					wc_ecster_confirm_order( $order_id, $internal_reference, $response_body );
					$order->add_order_note( __( 'Ecster reported order status fully delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'PARTIALLY_DELIVERED':
				$order->add_order_note( __( 'Ecster reported order partially delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
			case 'DENIED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Denied', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'FAILED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'failed', __( 'Ecster reported order Failed', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'ABORTED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Aborted', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'ANNULED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Annuled', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'EXPIRED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Expired', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'STOPPED':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'cancelled', __( 'Ecster reported order Stopped', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			default:
				break;
		} // End switch().
	}
}
Ecster_Api_Callbacks::get_instance();
