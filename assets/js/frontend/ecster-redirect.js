jQuery(function($) {

	const ecster_wc = {

		ecster_cart_key: ecster_wc_params.ecster_checkout_cart_key,

		// Payment method.
		paymentMethodEl: $('input[name="payment_method"]'),
		selectAnotherSelector: '#ecster-checkout-select-other',
		// Body element.
		bodyEl: $('body'),

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			$(document).ready( ecster_wc.documentReady() );
		},

		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			ecster_wc.wc_ecster_create_cart();
			
		},
		
		

		wc_ecster_create_cart: function() {


			console.log( 'create' );
	
			// Check if Ecster is selected, Ecster library loaded and Ecster container exists
			if ( typeof window.EcsterPay === "object" && null !== ecster_wc.ecster_cart_key ) {

                var url = EcsterPay.getUrl({
                    cartKey: ecster_wc.ecster_cart_key,
                    showCart: false,
                    shopTermsUrl: ecster_wc_params.terms,
                    returnUrl: 'https://krokedilserver1.se/#',
                    showPaymentResult: true
                });
                window.location.href = url;
            }
		},

		
	
	}

	ecster_wc.init();

});