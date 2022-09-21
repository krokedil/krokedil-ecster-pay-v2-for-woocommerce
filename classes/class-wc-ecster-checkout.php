<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package WC_Ecster/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing actions during the checkout process.
 */
class WC_Ecster_Checkout {
	/**
	 * Class constructor
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_aco_settings' );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_ecster_order' ), 9999 );
		// add_filter( 'woocommerce_checkout_fields', array( $this, 'add_ecster_cart_key_field' ) );

		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'add_ecster_cart_key_field2' ) );

	}

	/**
	 * Add hidden input fields for the shipping data to and from Unifaun.
	 *
	 * @param object $shipping_rate The WooCommerce shipping rate object.
	 * @return string
	 */
	public function add_ecster_cart_key_field2() {

		if ( method_exists( WC()->session, 'get' ) && ! empty( WC()->session->get( 'ecster_checkout_cart_key' ) ) ) {
			$ecster_cart_key = WC()->session->get( 'ecster_checkout_cart_key' );
		}
		?>
		<input type="hidden" id="ecster_cart_key" value="<?php echo esc_html( $ecster_cart_key ); ?>" >
		<?php
	}

	/**
	 * Add hidden input fields for the shipping data to and from Unifaun.
	 *
	 * @param array $fields WooCommerce checkout form fields.
	 * @return array
	 */
	public function add_ecster_cart_key_field( $fields ) {

		$ecster_cart_key = '';

		if ( method_exists( WC()->session, 'get' ) && ! empty( WC()->session->get( 'ecster_checkout_cart_key' ) ) ) {
			$ecster_cart_key = WC()->session->get( 'ecster_checkout_cart_key' );
		}
		// Customer selected shipping data.
		$fields['billing']['s_ecster_cart_key'] = array(
			'type'    => 'hidden',
			'class'   => array( 's_ecster_cart_key' ),
			'default' => esc_html( $ecster_cart_key ),
		);

		return $fields;
	}

	/**
	 * Update the Avarda order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_ecster_order() {
		$settings      = get_option( 'woocommerce_ecster_settings' );
		$checkout_flow = $settings['checkout_flow'] ?? 'embedded';

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'ecster' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		if ( 'redirect' === $checkout_flow ) {
			return;
		}

		// Only when its an actual AJAX request to update the order review (this is when update_checkout is triggered).
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'update_order_review' !== $ajax ) {
			return;
		}

		// Do not use Ecster for free orders.
		if ( ! WC()->cart->needs_payment() ) {
			WC()->session->reload_checkout = true;
			return;
		}

		$ecster_cart_key = WC()->session->get( 'ecster_checkout_cart_key' );
		error_log( '$ecster_cart_key ' . var_export( $ecster_cart_key, true ) );

		// Check if we have a Ecster cart key.
		if ( empty( $ecster_cart_key ) ) {
			wc_ecster_unset_sessions();
			WC_Ecster_Logger::log( 'Ecster cart key is missing in update Ecster order function. Clearing Ecster session.' );
			return;
		}

		// Check if the cart hash has been changed since last update.
		$cart_hash  = WC()->cart->get_cart_hash();
		$saved_hash = WC()->session->get( 'ecster_last_update_hash' );

		// If they are the same, return.
		if ( $cart_hash === $saved_hash ) {
			return;
		}

		// Update order.
		$ecster_order = Ecster_WC()->api->update_ecster_cart( $ecster_cart_key, $customer_type );

		// If the update failed - unset sessions and return error.
		if ( is_wp_error( $ecster_order ) ) {
			// Unset sessions.
			wc_ecster_unset_sessions();
			WC_Ecster_Logger::log( 'Ecster update request failed in update Ecster function. Clearing Ecster session.' );
			wc_add_notice( 'Ecster update request failed.', 'error' );
			WC()->session->reload_checkout = true;
			return;
		}
		WC()->session->set( 'ecster_checkout_cart_key', $ecster_order['checkoutCart']['key'] );
		$saved_hash = WC()->session->set( 'ecster_last_update_hash', $cart_hash );

	}
} new WC_Ecster_Checkout();
