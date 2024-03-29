<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Order_Management class.
 */
class WC_Ecster_Order_Management {

	/** @var string Ecster API username. */
	private $username;

	/** @var string Ecster API password. */
	private $password;

	/** @var boolean Ecster API testmode. */
	private $testmode;

	/**
	 * WC_Ecster_Order_Management constructor.
	 */
	public function __construct() {
		$ecster_settings     = get_option( 'woocommerce_ecster_settings' );
		$this->testmode      = 'yes' === $ecster_settings['testmode'];
		$this->manage_orders = isset( $ecster_settings['manage_ecster_orders'] ) ? $ecster_settings['manage_ecster_orders'] : '';
		$this->api_key       = isset( $ecster_settings['api_key'] ) ? $ecster_settings['api_key'] : '';
		$this->merchant_key  = isset( $ecster_settings['merchant_key'] ) ? $ecster_settings['merchant_key'] : '';

		add_action( 'woocommerce_order_status_completed', array( $this, 'complete_ecster_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_ecster_order' ) );
		add_action( 'ecster_poll_swish_refund', array( $this, 'ecster_poll_swish_refund_cb' ), 10, 2 );
	}

	/**
	 * Cancel order.
	 */
	public function cancel_ecster_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( 'ecster' === $order->get_payment_method() && 'yes' == $this->manage_orders && ! empty( $this->api_key ) && ! empty( $this->merchant_key ) ) {
			$payment_method_title = get_post_meta( $order_id, '_payment_method_title', true );
			$swish_order          = ( false !== stripos( $payment_method_title, 'swish' ) ) ? true : false;
			if ( $swish_order ) {
				$order->add_order_note( __( 'No cancellation performed in Ecster\'s system. Swish payments should be manually handled directly with Swish/your bank.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}
			if ( get_post_meta( $order_id, '_ecster_order_cancelled_id', true ) ) {
				$order->add_order_note( __( 'Ecster reservation is already cancelled.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}

			$response = Ecster_WC()->api->cancel_ecster_order( $order_id );

			// Something went wrong.
			if ( is_wp_error( $response ) ) {
				$order->add_order_note( sprintf( __( 'Ecster annul request failed. Error code: %1$s. Error message: %2$s', 'krokedil-ecster-pay-for-woocommerce' ), $response->get_error_code(), $response->get_error_message() ) );
				return;
			}

			// All ok.
			if ( 'ANNULLED' === $response['orderStatus'] ) {
				// Add transaction id, used to prevent duplicate cancellations for the same order.
				update_post_meta( $order_id, '_ecster_order_cancelled_id', $response['transaction']['id'] );
				$order->add_order_note( sprintf( __( 'Ecster payment was successfully cancelled (transaction id %s).', 'krokedil-ecster-pay-for-woocommerce' ), $response['transaction']['id'] ) );
			}
		}
	}

	/**
	 * Complete order.
	 */
	public function complete_ecster_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'ecster' === $order->get_payment_method() && 'yes' == $this->manage_orders && ! empty( $this->api_key ) && ! empty( $this->merchant_key ) ) {
			$payment_method_title = get_post_meta( $order_id, '_payment_method_title', true );
			$swish_order          = ( false !== stripos( $payment_method_title, 'swish' ) ) ? true : false;
			if ( $swish_order ) {
				$order->add_order_note( __( 'No activation needed in Ecster\'s system since Swish payments is charged directly during purchase.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}
			if ( get_post_meta( $order_id, '_ecster_order_captured_id', true ) ) {
				$order->add_order_note( __( 'Ecster reservation is already captured.', 'krokedil-ecster-pay-for-woocommerce' ) );
				return;
			}

			$response = Ecster_WC()->api->activate_ecster_order( $order_id );

			// Something went wrong.
			if ( is_wp_error( $response ) ) {
				if ( is_array( $response->get_error_message() ) ) {
					$error_response = $response->get_error_message();
					$error_message  = $error_response['message'] ?? json_encode( $error_response );
					$error_code     = $error_response['code'] ?? $response->get_error_code();
				}

				// translators: %s the error code, %s the error message.
				$order->add_order_note( sprintf( __( 'Ecster debit request failed. Error code: %1$s. Error message: %2$s', 'krokedil-ecster-pay-for-woocommerce' ), $error_code, $error_message ) );

				// Maybe change order status to On hold.
				// Don't change it if status code is 4424 (order is not in a valid state).
				// This could indicate that the order already has been activated in Ecters backend.
				if ( 4424 !== $error_code ) {
					$order->update_status( apply_filters( 'ecster_failed_capture_status', 'on-hold', $order_id ) );
					$order->save();
				}
				return;
			}

			// All ok.
			if ( 'FULLY_DELIVERED' === $response['orderStatus'] ) {
				// Add transaction id, used to prevent duplicate cancellations for the same order.
				update_post_meta( $order_id, '_ecster_order_captured_id', $response['transaction']['id'] );
				$order->add_order_note( sprintf( __( 'Ecster payment debited (transaction id %s).', 'krokedil-ecster-pay-for-woocommerce' ), $response['transaction']['id'] ) );
			} else {
				if ( $response['transaction']['id'] ) {
					update_post_meta( $order_id, '_ecster_order_captured_id', $response['transaction']['id'] );
				}
				// Request was ok but the order is not fully FULLY_DELIVERED.
				$order->add_order_note( sprintf( __( 'Ecster debit request problem. Ecster order status: %s', 'krokedil-ecster-pay-for-woocommerce' ), $response['orderStatus'] ) );
				$order->update_status( apply_filters( 'ecster_failed_capture_status', 'on-hold', $order_id ) );
			}
		}
	}

	/**
	 * Retry Swish refund after elapsed time.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @param string $amount The order total amount.
	 * @return void
	 */
	public function ecster_poll_swish_refund_cb( $order_id, $amount ) {
		wc_ecster_handle_swish_refund_status( $order_id, $amount );
	}


}
$wc_ecster_order_management = new WC_Ecster_Order_Management();
