jQuery(function($) {

	const ecster_wc = {

		wc_ecster_initialized: false,
		ecster_cart_key: null,
		wc_ecster_on_customer_authenticated_data: false,
		wc_ecster_on_changed_delivery_address_data: false,
		ecster_cart_key: ecster_wc_params.ecster_checkout_cart_key,
		wc_ecster_order_processing: false,

		// Payment method.
		paymentMethodEl: $('input[name="payment_method"]'),
		selectAnotherSelector: '#ecster-checkout-select-other',
		// Body element.
		bodyEl: $('body'),

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			// Check if Ecster is the selected payment method before we do anything.
			if( ecster_wc.checkIfSelected() ) {
				$(document).ready( ecster_wc.documentReady() );

				// Update Ecster payment.
				ecster_wc.bodyEl.on('updated_checkout', ecster_wc.wc_ecster_update_cart);
			}

			ecster_wc.bodyEl.on('change', 'input[name="payment_method"]', ecster_wc.maybeChangeToEcster);
			ecster_wc.bodyEl.on( 'click', ecster_wc.selectAnotherSelector, function() {
				ecster_wc.changePaymentMethod( false ) }
			);
			
			// Update Ecster cart when changing between B2B/B2C
			ecster_wc.bodyEl.on('change', 'input[name="ecster-customer-type"]', ecster_wc.wc_ecster_update_cart);
		},

		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			ecster_wc.wc_ecster_body_class();
			ecster_wc.moveExtraCheckoutFields();
			ecster_wc.wc_ecster_create_cart();
			
		},
		/*
		 * Check if our gateway is the selected gateway.
		 */
		checkIfSelected: function() {
			if (ecster_wc.paymentMethodEl.length > 0) {
				ecster_wc.paymentMethod = ecster_wc.paymentMethodEl.filter(':checked').val();
				if( 'ecster' === ecster_wc.paymentMethod ) {
					return true;
				}
			} 
			return false;
		},

		wc_ecster_body_class: function() {
			if ("ecster" === $("input[name='payment_method']:checked").val()) {
				$("body").addClass("ecster-selected").removeClass("ecster-deselected");
			} else {
				$("body").removeClass("ecster-selected").addClass("ecster-deselected");
			}
		},

		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {

			// Move order comments.
			$( '.woocommerce-additional-fields' ).appendTo( '#ecster-extra-checkout-fields' );
			var form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' );
			for ( i = 0; i < form.length; i++ ) {
				var name = form[i].name;
				// Check if field is inside the order review.
				if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, ecster_wc_params.standard_woo_checkout_fields ) ) {					
					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
						$( 'p#' + name + '_field' ).appendTo( '#ecster-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#ecster-extra-checkout-fields' );
					}
				}
			}
		},

		wc_ecster_create_cart: function() {
			console.log( 'create' );
	
			// Check if Ecster is selected, Ecster library loaded and Ecster container exists
			if ("ecster" === $("input[name='payment_method']:checked").val() && typeof window.EcsterPay === "object" && $("#ecster-pay-ctr").length) {
				if (null !== ecster_wc.ecster_cart_key && true !== ecster_wc.ecster_cart_key.includes( 'Error' ) ) {
					EcsterPay.start({
						cartKey: ecster_wc.ecster_cart_key, // from create cart REST call
						shopTermsUrl: ecster_wc_params.terms,
						showCart: false,
						showPaymentResult: false,
						onCheckoutStartInit: function () {
							$("#order_review").block({
								message: null,
								overlayCSS: {
									background: "#fff",
									opacity: 0.6
								}
							});
						},
						onCheckoutStartSuccess: function () {
							$("#order_review").unblock();
							ecster_wc.wc_ecster_initialized = true; // Mark Ecster as initialized on success
							$("body").trigger("update_checkout");
						},
						onCheckoutStartFailure: function (failureData) {
							$("#order_review").unblock();
							console.log('onCheckoutStartFailure');
							console.log(failureData);
							ecster_wc.wc_ecster_on_checkout_start_failure(failureData);
						},
						onCheckoutUpdateInit: function () {
							$("#order_review").block({
								message: null,
								overlayCSS: {
									background: "#fff",
									opacity: 0.6
								}
							});
						},
						onCheckoutUpdateSuccess: function () {
							$("#order_review").unblock();
							$('#ecster-pay-ctr').unblock();
						},
						onCheckoutUpdateFailure: function () {
							$("#order_review").unblock();
						},
						onCustomerAuthenticated: function (authenticatedData) {
							ecster_wc.wc_ecster_on_customer_authenticated_data = authenticatedData.customer;
							ecster_wc.wc_ecster_on_customer_authenticated(authenticatedData.customer);
						},
						onChangedDeliveryMethod: function (newDeliveryMethod) {
						},
						onChangedDeliveryAddress: function (newDeliveryAddress) {
							ecster_wc.wc_ecster_on_changed_delivery_address_data = newDeliveryAddress;
							ecster_wc.wc_ecster_on_changed_delivery_address(newDeliveryAddress);
						},
						onPaymentSuccess: function (paymentData) {
							ecster_wc.wc_ecster_on_payment_success(paymentData);
						},
						onPaymentFailure: function () {
							ecster_wc.wc_ecster_fail_local_order('failed');
						},
						onPaymentDenied: function (deniedData) {
							ecster_wc.wc_ecster_fail_local_order('denied');
						},
						onBeforeSubmit: function (data, callback) {
							console.log('onBeforeSubmit');
							console.log( data);
							console.log( callback);
							
							ecster_wc.processWooCheckout(data, callback );
						},
					});
				} else {
					console.log('ecster_wc.ecster_cart_key');
					console.log(ecster_wc.ecster_cart_key);
					document.querySelector('#ecster-pay-ctr').innerHTML = '<div class="woocommerce-error">' + ecster_wc.ecster_cart_key + '</div>';
					
				}
			}
		},

		wc_ecster_update_cart: function() {

			if( false === ecster_wc.wc_ecster_initialized ) {
				return;
			}

			var updated_cart_callback = EcsterPay.updateCart(ecster_wc.ecster_cart_key);
			var customer_type = ( $('input[name="ecster-customer-type"]:checked').val() ) ? $('input[name="ecster-customer-type"]:checked').val() : null;
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type: "POST",
					dataType: "json",
					async: true,
					data: {
						action: "wc_ecster_update_cart",
						cart_key: ecster_wc.ecster_cart_key,
						customer_type: customer_type,
						nonce: ecster_wc_params.wc_ecster_nonce
					},
					success: function (response) {
	
						if(response.success && response.data.refreshZeroAmount){
							window.location.reload();
						}
	
						if (response.success && response.data.ecster_cart_key) {
	
	
	
							updated_cart_callback(response.data.ecster_cart_key);
							ecster_wc.ecster_cart_key = response.data.ecster_cart_key;
						} else {
							$("#ecster-pay-ctr").html('<div class="woocommerce-error" id="wc-ecster-api-error">' + response.data.error_message + '</div>');
						}
					}
				}
			);
		},
	
		wc_ecster_fail_local_order: function(reason) {
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type: "POST",
					dataType: "json",
					async: true,
					data: {
						action: "wc_ecster_fail_local_order",
						reason: reason,
						nonce: ecster_wc_params.wc_ecster_nonce
					}
				}
			);
		},

		// on Ecster checkout start failure. Triggered when Ecster cart session has expired.
		wc_ecster_on_checkout_start_failure: function(paymentData) {

			// Don't trigger on checkout start failure if we are currently submitting/processing the Woo order.
			if( true === ecster_wc.wc_ecster_order_processing ) {
				console.log( 'Aborting Ecster on_checkout_start_failure. Order processing active.' );
				return;
			}
	
			// Delete the current cart key and reload the page.
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type: "POST",
					dataType: "json",
					async: true,
					data: {
						action:       "wc_ecster_on_checkout_start_failure",
						payment_data: paymentData,
						nonce:        ecster_wc_params.wc_ecster_nonce
					},
				   success: function() {
					window.location.reload(true);
				}
				}
			);
		},
	
		// on customer authentication
		wc_ecster_on_customer_authenticated: function(customer_data) {
			$('#ecster-pay-ctr').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type:     "POST",
					dataType: "json",
					async:    true,
					data: {
						action:        "wc_ecster_on_customer_authenticated",
						customer_data: customer_data,
						nonce:         ecster_wc_params.wc_ecster_nonce
					},
					success: function(response) {
						var customerAuthCountry;
	
						if (customer_data.countryCode) {
							customerAuthCountry = customer_data.countryCode;
						} else {
							customerAuthCountry = 'SE';
						}
	
						// Update country and ZIP, so shipping can be calculated on update_checkout
						$("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
						$("form.checkout #billing_country").val(customerAuthCountry);
						$("form.checkout #billing_postcode").val(customer_data.zip);
						console.log('Customer authenticated sucess');
						console.log(response);
						
						$("body").trigger("update_checkout");
					}
				}
			);
		},
	
		// on changed delivery address
		wc_ecster_on_changed_delivery_address: function(delivery_address) {
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type:     "POST",
					dataType: "json",
					async:    true,
					data: {
						action:           "wc_ecster_on_changed_delivery_address",
						delivery_address: delivery_address,
						nonce:            ecster_wc_params.wc_ecster_nonce
					},
					success: function() {
						var customerDeliveryCountry;
						if (delivery_address.countryCode) {
							customerDeliveryCountry = delivery_address.countryCode;
						} else {
							customerDeliveryCountry = 'SE';
						}
						
						if (ecster_wc.wc_ecster_on_customer_authenticated_data) { // If authentication is done
							// Update country and ZIP, so shipping can be calculated on update_checkout
							$("form.checkout #ship-to-different-address-checkbox").prop("checked", true);
							$("form.checkout #shipping_country").val(customerDeliveryCountry);
							$("form.checkout #shipping_postcode").val(delivery_address.zip);
						} else {
							// Update country and ZIP, so shipping can be calculated on update_checkout
							$("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
							$("form.checkout #billing_country").val(customerDeliveryCountry);
							$("form.checkout #billing_postcode").val(delivery_address.zip);
						}
						console.log('Customer changed delivery address sucess');
						$("body").trigger("update_checkout");
					}
				}
			);
		},

		// on Ecster payment success
		wc_ecster_on_payment_success: function(paymentData) {
			// Block the iframe until page reloads
			$("#ecster-pay-ctr").block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6
				}
			});
	
			// Also block the order review
			$("#order_review").block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6
				}
			});
			var redirectUrl = sessionStorage.getItem( 'ecsterRedirectUrl' );
			console.log('wc_ecster_on_payment_success');
			console.log(redirectUrl);
			if( redirectUrl ) {
				redirectUrl = redirectUrl + '&ecster_order_id=' +paymentData.internalReference;
				console.log(redirectUrl);
				window.location.href = redirectUrl;
			}
		},
		
		// Fill form and submit it to create the order.
		processWooCheckout: function(paymentData, callback) {
			// Block the iframe until page reloads
			$("#ecster-pay-ctr").block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6
				}
			});
	
			// Also block the order review
			$("#order_review").block({
				message: null,
				overlayCSS: {
					background: "#fff",
					opacity: 0.6
				}
			});
	
			console.log('processWooCheckout');
			console.log(ecster_wc.wc_ecster_on_customer_authenticated_data);
			ecster_wc.fillForm();
			// Submit wc order.
			ecster_wc.submitForm(callback);
		},
		
		fillForm: function() {
			console.log('fillForm');
			
			if( ecster_wc.wc_ecster_on_changed_delivery_address_data ) {
				var customer = ecster_wc.wc_ecster_on_changed_delivery_address_data;
			} else {
				var customer = ecster_wc.wc_ecster_on_customer_authenticated_data;
			}

			console.log('customer');
			console.log(customer);

			var firstName = ( ( 'firstName' in customer ) ? customer.firstName : '' );
			var lastName = ( ( 'lastName' in customer ) ? customer.lastName : '' );
			var city = ( ( 'city' in customer ) ? customer.city : '' );
			var countryCode = ( ( 'countryCode' in customer ) ? customer.countryCode : '' );
			var postalCode = ( ( 'zip' in customer ) ? customer.zip : '' );
			var street = ( ( 'address' in customer ) ? customer.address : '' );
			var email = ( ( 'email' in customer ) ? customer.email : 'ecster.temp@domain.com' );
			var phone = ( ( 'cellular' in customer ) ? customer.cellular : '.' );

			// Set customerType if it exist.
			if( $("input[name='ecster-customer-type']").length > 0 ){
				customerType = $("input[name='ecster-customer-type']:checked").val();
			} else {
				customerType = ecster_wc_params.default_customer_type;
			}

			
			if( 'b2b' === customerType ) {
				$("form.checkout #billing_company").val(customer.address2);
			} else {
				$("form.checkout #billing_company").val('');
				$("form.checkout #billing_address_2").val(customer.address2);
			}
			
			// billing first name
			$( '#billing_first_name' ).val( firstName );
			// shipping first name
			$( '#shipping_first_name' ).val( firstName );
			// billing last name
			$( '#billing_last_name' ).val(lastName);
			// shipping last name.
			$( '#shipping_last_name' ).val(lastName);

			if( countryCode ) {
				// billing country
				$('#billing_country').val(countryCode);
				// shipping country
				$('#shipping_country').val(countryCode);
			}
			
			// billing street
			$('#billing_address_1').val(street);
			// shipping street
			$('#shipping_address_1').val(street);
			// billing city
			$('#billing_city').val(city);
			// shipping city
			$('#shipping_city').val(city);
			// billing postal code
			$('#billing_postcode').val(postalCode);
			// shipping postal code
			$('#shipping_postcode').val(postalCode);
			// billing phone
			$( '#billing_phone' ).val(phone);
			// billing email
			$('#billing_email').val(email);
		},
	
		submitForm: function(callback) {
			console.log('submitForm');
			if ( 0 < $( 'form.checkout #terms' ).length ) {
				$( 'form.checkout #terms' ).prop( 'checked', true );
			}
			$( '.woocommerce-checkout-review-order-table' ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			$.ajax({
				type: 'POST',
				url: ecster_wc_params.submit_order,
				timeout:  ecster_wc_params.timeout_time * 1000,
				data: $('form.checkout').serialize(),
				dataType: 'json',
				success: function( data ) {
					try {
						if ( 'success' === data.result ) {
							ecster_wc.logToFile( 'Successfully placed order. Sending "beforeSubmitContinue" true to Avarda' );

							console.log('data.redirect_url');
							console.log(data.redirect_url);
							sessionStorage.setItem( 'ecsterRedirectUrl', data.redirect_url );

							callback( true );
							// Clear the interval.
							clearInterval(ecster_wc.interval);
							// Remove the timeout.
							clearTimeout( ecster_wc.timeout );

							// Remove the processing class from the form.
							$('form.checkout').removeClass( 'processing' ).unblock();
							$( '.woocommerce-checkout-review-order-table' ).unblock();
							$('#ecster-pay-ctr').unblock();
							console.log('submitForm end - callback true triggered');
						} else {
							throw 'Result failed';
						}
					} catch ( err ) {
						if ( data.messages )  {
							ecster_wc.logToFile( 'Checkout error | ' + data.messages );
							ecster_wc.failOrder( 'submission', data.messages, callback );
						} else {
							ecster_wc.logToFile( 'Checkout error | No message' );
							ecster_wc.failOrder( 'submission', '<div class="woocommerce-error">' + 'Checkout error' + '</div>', callback );
						}
					}
				},
				error: function( data , textStatus ) {
					ecster_wc.logToFile( 'AJAX error | ' + data.statusText );
					ecster_wc.failOrder( 'ajax-error', 'Ecster checkout error: ' + data.statusText, callback );
				}
			});
		},
		maybeChangeToEcster: function() {
			if ( 'ecster' === $(this).val() ) {
				ecster_wc.changePaymentMethod( true );
			}
		},
		changePaymentMethod: function( bool ) {
			console.log( 'Ecster changePaymentMethod' );
			console.log( bool );
	
			// Don't change payment method if we are currently submitting/processing the Woo order.
			if( true === ecster_wc.wc_ecster_order_processing ) {
				console.log( 'Aborting Ecster change payment method. Order processing active.' );
				return;
			}
	
			$('form.checkout').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
	
			$('.woocommerce-info').remove();
	
			$.ajax(
				ecster_wc_params.ajaxurl,
				{
					type: "POST",
					dataType: "json",
					async: true,
					data: {
						ecster: 	bool,
						action:		"wc_change_to_ecster",
						nonce:		ecster_wc_params.wc_change_to_ecster_nonce
					},
					success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						console.log( data );
						window.location.href = data.responseJSON.data.redirect;
					}
				}
			);
		},

		failOrder: function( event, error_message, callback ) {
			console.log('failOrder');
			console.log(event);

			// Clear the interval.
			clearInterval(ecster_wc.interval);
			// Remove the timeout.
			clearTimeout( ecster_wc.timeout );

			// Send false and cancel.
			callback( false );
		
			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			$( ecster_wc.checkoutFormSelector ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();

			// Print error messages, and trigger checkout_error, and scroll to notices.
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$( 'form.checkout' ).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>' + error_message + '</li></ul></div>' ); // eslint-disable-line max-len
			$( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( 'form.checkout' ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			$( document.body ).trigger( 'checkout_error' , [ error_message ] );
			$( 'html, body' ).animate( {
				scrollTop: ( $( 'form.checkout' ).offset().top - 100 )
			}, 1000 );
		},

		checkUrl: function( callback ) {
			if ( window.location.hash ) {
				var currentHash = window.location.hash;
				if ( -1 < currentHash.indexOf( '#ecster-success' ) ) {
					ecster_wc.logToFile( 'ecster-success hashtag detected in URL.' );
					var splittedHash = currentHash.split("=");
					console.log('splittedHash');
					console.log(splittedHash[0]);
					console.log(splittedHash[1]);
					var response = JSON.parse( atob( splittedHash[1] ) );

					console.log('response.return_url');
					console.log(response.return_url);
					sessionStorage.setItem( 'ecsterRedirectUrl', response.return_url );
					callback( true );
					// Clear the interval.
					clearInterval(ecster_wc.interval);
					// Remove the timeout.
					clearTimeout( ecster_wc.timeout );
					// Remove the processing class from the form.
					$( '.woocommerce-checkout-review-order-table' ).unblock();
					console.log('checkUrl end - callback true triggered');
				}
			}
		},

		/**
		 * Logs the message to the klarna checkout log in WooCommerce.
		 * @param {string} message 
		 */
		logToFile: function( message ) {
			$.ajax(
				{
					url: ecster_wc_params.ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: {
						action:	"wc_ecster_log_js_to_file",
						message: message,
						nonce: ecster_wc_params.wc_ecster_nonce
					}
				}
			);
		},
	
	}

	ecster_wc.init();

});