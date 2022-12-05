<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Return phone prefix for country.
 *
 * @param string $country Two letter country code.
 */
function wc_ecster_get_phone_prefix_for_country( $country = false ) {
	$result   = '';
	$prefixes = wc_ecster_get_all_prefixes();
	$result   = $prefixes[ $country ];
	return $result;
}

/**
 * Return all phone prefixes.
 */
function wc_ecster_get_all_prefixes() {
	return include WC()->plugin_path() . '/i18n/phone.php';
}
