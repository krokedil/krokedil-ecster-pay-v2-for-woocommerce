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

	public function execute_osn_callback( $decoded, $order_id = '' ) {
		$internal_reference = $decoded['orderId'];
		$external_reference = $decoded['orderReference'];
		$request            = new WC_Ecster_Request_Get_Order( $this->api_key, $this->merchant_key, $this->testmode );
		$response           = $request->response( $internal_reference );
		$response_body      = json_decode( $response['body'] );

		WC_Gateway_Ecster::log( 'OSN callback executed. Order ID:' . $order_id ); // Input var okay.
		WC_Gateway_Ecster::log( 'OSN callback executed. Response body:' . wp_json_encode( $response_body ) );

		if ( empty( $order_id ) ) { // We're missing Order ID in callback. Try to get it via query by internal reference
			$order_id = $this->get_order_id_from_internal_reference( $internal_reference );
		}

		if ( ! empty( $order_id ) ) { // Input var okay.

			$this->update_woocommerce_order( $response_body, $order_id );

		} else { // We can't find a coresponding Order ID.
			if ( 'FULLY_DELIVERED' === $response_body->status || 'READY' === $response_body->status ) {

				// Create the order in Woo.
				$order = $this->create_woocommerce_order( $response_body, $internal_reference, $external_reference );

				// Send order number to Ecster.
				if ( is_object( $order ) ) {
					$this->update_order_reference_in_ecster( $internal_reference, $order );
				}
			} else {
				WC_Gateway_Ecster::log( 'OSN callback. No corresponding order ID in Woo but Ecster order status: ' . $response_body->status . '. No backup order creation needed.' ); // Input var okay.
			}
		} // End if().
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $private_id, $public_token, $customer_type.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function get_order_id_from_internal_reference( $internal_reference ) {

		// Let's check so the internal reference doesn't already exist in an existing order
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'ecster',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';
		foreach ( $orders as $order_id ) {

			$order_internal_reference = get_post_meta( $order_id, '_wc_ecster_internal_reference', true );

			if ( $order_internal_reference === $internal_reference ) {
				$order_id_match = $order_id;
				WC_Gateway_Ecster::log( 'Order ID is missing in OSN callback but Internal reference ' . $internal_reference . '. already exist in order ID ' . $order_id_match );
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
	private function update_woocommerce_order( $response_body, $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			WC_Gateway_Ecster::log( 'Error. Could not instantiate an order object in OSN callback for order id ' . $order_id . '. Aborting callback.' );
			return;
		}

		switch ( $response_body->status ) {
			case 'AWAITING_CONTRACT': // Do nothing - these order statuses should be handled in process_payment()
				break;
			case 'READY':
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->payment_complete();
					$order->add_order_note( __( 'Ecster reported order status ready.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				break;
			case 'FULLY_DELIVERED':
				if ( 'INVOICE' == $response_body->properties->method || 'ACCOUNT' == $response_body->properties->method ) {
					$order->add_order_note( __( 'Ecster reported order fully delivered.', 'krokedil-ecster-pay-for-woocommerce' ) );
				}
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->payment_complete();
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

	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $collector_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function create_woocommerce_order( $response_body, $internal_reference, $external_reference ) {
		WC_Gateway_Ecster::log( 'Order ID is missing in OSN callback and we could not find Internal reference ' . $internal_reference . ' in an existing order. Starting backup order creation...' );

		// Create local order
		$order = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			WC_Gateway_Ecster::log( 'Backup order creation. Error - could not create order. ' . var_export( $order->get_error_message(), true ) );
		} else {
			$order_id = krokedil_get_order_id( $order );
			WC_Gateway_Ecster::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );

		}

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

		$order->set_created_via( 'ecster_api' );
		$order->set_currency( sanitize_text_field( $response_body->currency ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['ecster'];
		$order->set_payment_method( $payment_method );

		// Add items to order
		foreach ( $response_body->transactions as $transactions ) {
			// Only use ORIGINAL type of transactions.
			if ( 'ORIGINAL' === $transactions->type ) {
				foreach ( $transactions->rows as $order_row ) {
					if ( isset( $order_row->partNumber ) ) { // partNumber is only set for product order items.
						if ( isset( $product ) ) {
							unset( $product );
						}

						if ( wc_get_product( $order_row->partNumber ) ) { // If we got product ID.
							$product = wc_get_product( $order_row->partNumber );
						} else { // Get product ID based on SKU.
							$product_id = wc_get_product_id_by_sku( $order_row->partNumber );
							if ( $product_id ) {
								$product = wc_get_product( $product_id );
							}
						}

						if ( $product ) {
							$item_id = $order->add_product( $product, $order_row->quantity, array() );
							if ( ! $item_id ) {
								WC_Gateway_Ecster::log( 'Error. Unable to add product to order ' . $order->get_id() . '. add_product() response - ' . var_export( $item_id, true ) );
								throw new Exception( sprintf( __( 'Error %d: Unable to add product. Please try again.', 'woocommerce' ), 525 ) );
							}
						}
					} elseif ( 'Shipping fee' === $order_row->name || 'Fraktavgift' === $order_row->name ) {

						// Calculate price excluding tax since we are not able to send over shipping method id to Ecster.
						if ( $order_row->vatRate > 0 ) {
							$shipping_price = ( $order_row->unitAmount / ( 1 + ( $order_row->vatRate / 10000 ) ) ) / 100;
						} else {
							$shipping_price = $order_row->unitAmount / 100;
						}
						$item = new WC_Order_Item_Shipping();
						$item->set_props(
							array(
								'method_title' => $order_row->name,
								'method_id'    => '',
								'tax_class'    => null,
								'total_tax'    => 0,
								'tax_status'   => 'none',
								'total'        => wc_format_decimal( $shipping_price ),
							)
						);
						$order->add_item( $item );
					}
				}
			}
		}

		// Make sure to run Sequential Order numbers if plugin exsists
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		update_post_meta( $order_id, '_wc_ecster_internal_reference', $internal_reference );
		update_post_meta( $order_id, '_transaction_id', $internal_reference );
		update_post_meta( $order_id, '_wc_ecster_external_reference', $external_reference );
		update_post_meta( $order_id, '_wc_ecster_payment_method', $response_body->properties->method );

		// Payment method title.
		$payment_method_title = wc_ecster_get_payment_method_name( $response_body->properties->method );
		$order->add_order_note( sprintf( __( 'Payment via Ecster Pay %s.', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ) );
		$order->set_payment_method_title( apply_filters( 'wc_ecster_payment_method_title', sprintf( __( '%s via Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ), $payment_method_title ), $payment_method_title ) );

		$order->calculate_totals();
		$order->save();

		// Check Ecster order status
		switch ( $response_body->status ) {
			case 'AWAITING_CONTRACT': // Part payment with no contract signed yet
				$order->update_status( 'on-hold', __( 'Ecster payment approved but Ecster awaits signed customer contract. Order can NOT be delivered yet.', 'krokedil-ecster-pay-for-woocommerce' ) );
				break;
			case 'READY': // Invoice
			case 'FULLY_DELIVERED': // Card payment with direct charge
				$order->update_status( 'on-hold' );
				break;
			default:
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$order->update_status( 'on-hold' );
				}
				break;
		}
		$order->add_order_note( __( 'Order created via Ecster Pay API callback. Please verify the order in Ecsters system.', 'krokedil-ecster-pay-for-woocommerce' ) );

		return $order;
	}

	/**
	 * Update the Collector Order with the WooCommerce Order number
	 */
	public function update_order_reference_in_ecster( $internal_reference, $order ) {

		$request  = new WC_Ecster_Request_Update_Reference( $this->api_key, $this->merchant_key, $this->testmode );
		$response = $request->response( $internal_reference, $order->get_order_number() );

		WC_Gateway_Ecster::log( 'Update Ecster order reference in backup order creation (for internal reference ' . $internal_reference . ') ' . $order->get_order_number() );
	}
}
Ecster_Api_Callbacks::get_instance();
