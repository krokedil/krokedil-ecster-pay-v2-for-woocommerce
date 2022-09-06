<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_ecster_settings',
	array(
		'enabled'                    => array(
			'title'       => __( 'Enable/Disable', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Enable Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title'                      => array(
			'title'       => __( 'Title', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => __( 'Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ),
			'desc_tip'    => true,
		),
		'description'                => array(
			'title'       => __( 'Description', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => __( 'Pay using Ecster Pay.', 'krokedil-ecster-pay-for-woocommerce' ),
			'desc_tip'    => true,
		),
		'api_key'                    => array(
			'title'       => __( 'API key', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay API key. Used for order management. Can be retrieved from your Ecster Dashboard.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'merchant_key'               => array(
			'title'       => __( 'Merchant key', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Ecster Pay Merchant key. Used for order management. Can be retrieved from your Ecster Dashboard.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'testmode'                   => array(
			'title'       => __( 'Test mode', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Enable Test Mode', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'logging'                    => array(
			'title'       => __( 'Logging', 'krokedil-ecster-pay-for-woocommerce' ),
			'label'       => __( 'Log debug messages', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'select_another_method_text' => array(
			'title'       => __( 'Other payment method button text', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Ecster Pay. Leave blank to use the default (and translatable) text.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'manage_ecster_orders'       => array(
			'title'   => __( 'Manage orders', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => sprintf( __( 'Enable WooCommerce to manage orders in Ecsters backend (when order status changes to Cancelled and Completed in WooCommerce). Learn more in the <a href="%s" target="_blank">documentation</a>.', 'krokedil-ecster-pay-for-woocommerce' ), 'https://docs.krokedil.com/ecster-pay-for-woocommerce/get-started/order-management/' ),
			'default' => 'no',
		),
		'customer_types'             => array(
			'title'       => __( 'Allowed customer types', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Sets the allowed customer types for Ecster Pay', 'krokedil-ecster-pay-for-woocommerce' ),
			'options'     => array(
				'b2c'  => __( 'B2C', 'krokedil-ecster-pay-for-woocommerce' ),
				'b2b'  => __( 'B2B', 'krokedil-ecster-pay-for-woocommerce' ),
				'b2cb' => __( 'B2C & B2B default is B2C', 'krokedil-ecster-pay-for-woocommerce' ),
				'b2bc' => __( 'B2B & B2C default is B2B', 'krokedil-ecster-pay-for-woocommerce' ),
			),
			'default'     => 'b2c',
		),
		'checkout_layout'            => array(
			'title'    => __( 'Checkout layout', 'krokedil-ecster-pay-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array(
				'one_column_checkout' => __( 'One column checkout', 'krokedil-ecster-pay-for-woocommerce' ),
				'two_column_right'    => __( 'Two column checkout (Ecster Pay in right column)', 'krokedil-ecster-pay-for-woocommerce' ),
				'two_column_left'     => __( 'Two column checkout (Ecster Pay in left column)', 'krokedil-ecster-pay-for-woocommerce' ),
				'two_column_left_sf'  => __( 'Two column checkout (Ecster Pay in left column) - Storefront light', 'krokedil-ecster-pay-for-woocommerce' ),
			),
			'default'  => 'one_column_checkout',
			'desc_tip' => false,
		),
	)
);
