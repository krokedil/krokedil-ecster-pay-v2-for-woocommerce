<?php
/**
 * Template class file.
 *
 * @package Ecster/Classes
 */
/**
 * Templates class.
 */
class Ecster_For_WooCommerce_Templates {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );
		add_action( 'ecster_wc_after_wrapper', array( $this, 'add_wc_form' ), 10 );
		add_action( 'ecster_wc_after_order_review', array( $this, 'add_extra_checkout_fields' ), 10 );
		// add_action( 'ecster_wc_before_checkout_form', 'ecster_maybe_show_validation_error_message', 5 );
		add_action( 'ecster_wc_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'ecster_wc_before_checkout_form', 'woocommerce_checkout_coupon_form', 20 );
		// add_action( 'ecster_wc_before_snippet', 'ecster_wc_show_another_gateway_button', 20 );
	}
	/**
	 * Overrides checkout form template if PaysonCheckout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 * @return string
	 */
	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() ) {
			// Don't display Ecster template if we have a cart that doesn't needs payment.
			if ( ! WC()->cart->needs_payment() ) {
				return $template;
			}
			// Ecster Pay Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				if ( locate_template( 'woocommerce/ecster-checkout.php' ) ) {
					$ecster_template = locate_template( 'woocommerce/ecster-checkout.php' );
				} else {
					$ecster_template = WC_ECSTER_PLUGIN_PATH . '/templates/ecster-checkout.php';
				}
				// Ecster Pay checkout page.
				if ( array_key_exists( 'ecster', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'ecster' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $ecster_template;
						}
					}
					// If chosen payment method does not exist and ecster is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );
						if ( 'ecster' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $ecster_template;
							}
						}
					}
					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );
							if ( 'ecster' === key( $available_gateways ) ) {
								if ( ! isset( $_GET['confirm'] ) ) {
									$template = $ecster_template;
								}
							}
						}
					}
				}
			}
		}
		return $template;
	}
	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="ecster-wc-form" style="position:absolute; top:0; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			<input id="payment_method_ecster" type="radio" class="input-radio" name="payment_method" value="ecster" checked="checked" />
		</div>
		<?php
	}
	/**
	 * Adds the extra checkout field div to the checkout page.
	 *
	 * @return void
	 */
	public function add_extra_checkout_fields() {
		?>
		<div id="ecster-extra-checkout-fields">
		</div>
		<?php
	}
}
new Ecster_For_WooCommerce_Templates();
