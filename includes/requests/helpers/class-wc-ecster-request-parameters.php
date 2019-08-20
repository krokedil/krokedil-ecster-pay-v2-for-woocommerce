<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Ecster_Request_Parameters {

	public static function get_parameters() {
		return array(
			'shopTermsUrl' => get_permalink( wc_get_page_id( 'terms' ) ),
		);
	}

}
