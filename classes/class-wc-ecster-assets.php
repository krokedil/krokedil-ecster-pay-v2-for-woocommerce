<?php
/**
 * Main assets file.
 *
 * @package WC_Ecster/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Ecster_Assets class.
 */
class WC_Ecster_Assets {

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings      = get_option( 'woocommerce_ecster_settings', array() );
		$this->checkout_flow = $this->settings['checkout_flow'] ?? 'embedded';

		if ( 'embedded' === $this->checkout_flow ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		}

		if ( 'redirect' === $this->checkout_flow ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'load_redirect_script' ) );
		}

	}

	public function load_redirect_script() {
		if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		$order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		$order_id  = wc_get_order_id_by_order_key( $order_key );

		// Register Ecster Pay Script.
		wp_register_script( 'ecster_pay', $this->get_script_url(), array(), false, false );

		// Register plugin script.
		wp_register_script(
			'ecster_redirect',
			WC_ECSTER_PLUGIN_URL . '/assets/js/frontend/ecster-redirect.js',
			array( 'ecster_pay', 'jquery' ),
			WC_ECSTER_VERSION,
			true
		);

		wp_localize_script(
			'ecster_redirect',
			'ecster_wc_params',
			array(
				'terms'                    => wc_get_page_permalink( 'terms' ),
				'ecster_checkout_cart_key' => get_post_meta( $order_id, '_wc_ecster_internal_reference', true ),
			)
		);
		wp_enqueue_script( 'ecster_redirect' );
	}

	/**
	 * Enqueue checkout page scripts
	 */
	public function checkout_scripts() {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			$checkout_cart_key = ecster_maybe_create_order();
			// Register Ecster Pay Script.
			wp_register_script( 'ecster_pay', $this->get_script_url(), array(), false, false );

			// Register plugin script.
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script(
				'ecster_checkout',
				WC_ECSTER_PLUGIN_URL . '/assets/js/frontend/checkout' . $suffix . '.js',
				array( 'ecster_pay', 'jquery' ),
				WC_ECSTER_VERSION,
				true
			);

			$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', '_wp_http_referer' );

			wp_localize_script(
				'ecster_checkout',
				'ecster_wc_params',
				array(
					'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
					'terms'                        => wc_get_page_permalink( 'terms' ),
					'wc_ecster_nonce'              => wp_create_nonce( 'wc_ecster_nonce' ),
					'wc_change_to_ecster_nonce'    => wp_create_nonce( 'wc_change_to_ecster_nonce' ),
					'standard_woo_checkout_fields' => $standard_woo_checkout_fields,
					'ecster_checkout_cart_key'     => $checkout_cart_key,
					'timeout_time'                 => 9,
					'timeout_message'              => __( 'Please try again, something went wrong with processing your order.', 'krokedil-ecster-pay-for-woocommerce' ),
					'default_customer_type'        => wc_ecster_get_default_customer_type(),
					'submit_order'                 => WC_AJAX::get_endpoint( 'checkout' ),
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
	 *  Returns script URL based on plugin mode.
	 *
	 * @return string
	 */
	protected function get_script_url() {
		if ( 'yes' === $this->settings['testmode'] ) {
			return 'https://labs.ecster.se/pay/integration/ecster-pay-labs.js';
		}
		return 'https://secure.ecster.se/pay/integration/ecster-pay.js';
	}


}

new WC_Ecster_Assets();
