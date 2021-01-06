/* global jQuery, wc_ecster, EcsterPay, console */
(function ($) {
	'use strict';
	var wc_ecster_initialized = false;
	var wc_ecster_cart_key = null;
	var wc_ecster_on_customer_authenticated_data = false;
	var wc_ecster_on_changed_delivery_address_data = false;
	var wc_ecster_cart_key = wc_ecster.ecster_checkout_cart_key;
	var wc_ecster_order_processing = false;

	var wc_ecster_body_class = function wc_ecster_body_class() {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			$("body").addClass("ecster-selected").removeClass("ecster-deselected");
		} else {
			$("body").removeClass("ecster-selected").addClass("ecster-deselected");
		}
	};

	/*var wc_ecster_add_container = function wc_ecster_add_container() {
		if (!$("#ecster-pay-ctr").length) {
			// Add the element
			$('form.woocommerce-checkout').after('<div id="ecster-pay-ctr"></div>');
		}
	};*/

	var wc_ecster_create_cart = function wc_ecster_create_cart() {
		console.log( 'create' );
		//wc_ecster_add_container();

		/*$('#billing_first_name, #billing_last_name, #billing_company, #billing_email, #billing_phone, #billing_country, #billing_address_1, #billing_address_2, #billing_postcode, #billing_city').val('');
		$('#shipping_first_name, #shipping_last_name, #shipping_company, #shipping_country, #shipping_address_1, #shipping_address_2, #shipping_postcode, #shipping_city').val('');
		*/

		// Check if Ecster is selected, Ecster library loaded and Ecster container exists
		if ("ecster" === $("input[name='payment_method']:checked").val() && typeof window.EcsterPay === "object" && $("#ecster-pay-ctr").length) {
			if (null !== wc_ecster_cart_key && true !== wc_ecster_cart_key.includes( 'Error' ) ) {
				EcsterPay.start({
					cartKey: wc_ecster_cart_key, // from create cart REST call
					shopTermsUrl: wc_ecster.terms,
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
						wc_ecster_initialized = true; // Mark Ecster as initialized on success
						$("body").trigger("update_checkout");
					},
					onCheckoutStartFailure: function (failureData) {
						$("#order_review").unblock();
						console.log('onCheckoutStartFailure');
						console.log(failureData);
						wc_ecster_on_checkout_start_failure(failureData);
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
						wc_ecster_on_customer_authenticated_data = authenticatedData.customer;
						wc_ecster_on_customer_authenticated(authenticatedData.customer);
					},
					onChangedDeliveryMethod: function (newDeliveryMethod) {
					},
					onChangedDeliveryAddress: function (newDeliveryAddress) {
						wc_ecster_on_changed_delivery_address_data = newDeliveryAddress;
						wc_ecster_on_changed_delivery_address(newDeliveryAddress);
					},
					onPaymentSuccess: function (paymentData) {
						wc_ecster_on_payment_success(paymentData);
					},
					onPaymentFailure: function () {
						wc_ecster_fail_local_order('failed');
					},
					onPaymentDenied: function (deniedData) {
						wc_ecster_fail_local_order('denied');
					},
					onBeforeSubmit: function (data, callback) {
						console.log('onBeforeSubmit');
						console.log( data);
						console.log( callback);
						
						console.log('hej');

						// Empty current hash.
						window.location.hash = '';
						// Check for any errors.
						wc_ecster.timeout = setTimeout( function() { failOrder(  'timeout', callback ); }, wc_ecster.timeout_time * 1000 );
						$( document.body ).on( 'checkout_error', function() { failOrder( 'checkout_error', callback ); } );
						// Run interval until we find a hashtag or timer runs out.
						wc_ecster.interval = setInterval( function() { checkUrl( callback ); }, 500 );
						
						processWooCheckout(data);
					},
				});
			} else {
				console.log('wc_ecster_cart_key');
				console.log(wc_ecster_cart_key);
				document.querySelector('#ecster-pay-ctr').innerHTML = '<div class="woocommerce-error">' + wc_ecster_cart_key + '</div>';
				
			}
		}
	};

	var wc_ecster_update_cart = function wc_ecster_update_cart() {
		var updated_cart_callback = EcsterPay.updateCart(wc_ecster_cart_key);
		var customer_type = ( $('input[name="ecster-customer-type"]:checked').val() ) ? $('input[name="ecster-customer-type"]:checked').val() : null;
		$.ajax(
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					action: "wc_ecster_update_cart",
					cart_key: wc_ecster_cart_key,
					customer_type: customer_type,
					nonce: wc_ecster.wc_ecster_nonce
				},
				success: function (response) {

					if(response.success && response.data.refreshZeroAmount){
						window.location.reload();
					}

					if (response.success && response.data.wc_ecster_cart_key) {



						updated_cart_callback(response.data.wc_ecster_cart_key);
						wc_ecster_cart_key = response.data.wc_ecster_cart_key;
					} else {



						//wc_ecster_add_container();
						$("#ecster-pay-ctr").html('<div class="woocommerce-error" id="wc-ecster-api-error">' + response.data.error_message + '</div>');
					}
				}
			}
		);
	};

	var wc_ecster_fail_local_order = function wc_ecster_fail_local_order(reason) {
		$.ajax(
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					action: "wc_ecster_fail_local_order",
					reason: reason,
					nonce: wc_ecster.wc_ecster_nonce
				}
			}
		);
	};

	// Initializes Ecster in checkout page
	// Triggered when page is first loaded, if Ecster is selected or when payment method is
	// changed to Ecster for the first time
	var wc_ecster_init = function wc_ecster_init() {
		moveExtraCheckoutFields();
		wc_ecster_create_cart();
	};

	// on Ecster checkout start failure. Triggered when Ecster cart session has expired.
    var wc_ecster_on_checkout_start_failure = function wc_ecster_on_checkout_start_failure(paymentData) {

		// Don't trigger on checkout start failure if we are currently submitting/processing the Woo order.
		if( true === wc_ecster_order_processing ) {
			console.log( 'Aborting Ecster on_checkout_start_failure. Order processing active.' );
			return;
		}

        // Delete the current cart key and reload the page.
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type: "POST",
                dataType: "json",
                async: true,
                data: {
                    action:       "wc_ecster_on_checkout_start_failure",
                    payment_data: paymentData,
                    nonce:        wc_ecster.wc_ecster_nonce
                },
           	success: function() {
				window.location.reload(true);
            }
            }
        );
    };

    // on customer authentication
    var wc_ecster_on_customer_authenticated = function wc_ecster_on_customer_authenticated(customer_data) {
        $('#ecster-pay-ctr').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type:     "POST",
                dataType: "json",
                async:    true,
                data: {
                    action:        "wc_ecster_on_customer_authenticated",
                    customer_data: customer_data,
                    nonce:         wc_ecster.wc_ecster_nonce
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
					
					if( 'yes' == response.data.mustLogin ) {
						// Customer might need to login.
						var $form = $( 'form.checkout' );
						$form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview"><ul class="woocommerce-error" role="alert"><li>' + response.data.mustLoginMessage + '</li></ul></div>' );
						var etop = $('form.checkout').offset().top;
						$('html, body').animate({
							scrollTop: etop
						  }, 1000);
					} else {
						$("body").trigger("update_checkout");
					}
                }
            }
        );
    };

    // on changed delivery address
    var wc_ecster_on_changed_delivery_address = function wc_ecster_on_changed_delivery_address(delivery_address) {
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type:     "POST",
                dataType: "json",
                async:    true,
                data: {
                    action:           "wc_ecster_on_changed_delivery_address",
                    delivery_address: delivery_address,
                    nonce:            wc_ecster.wc_ecster_nonce
                },
                success: function() {
                    var customerDeliveryCountry;
                    if (delivery_address.countryCode) {
                        customerDeliveryCountry = delivery_address.countryCode;
                    } else {
                        customerDeliveryCountry = 'SE';
                    }
                    
                    if (wc_ecster_on_customer_authenticated_data) { // If authentication is done
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

                    $("body").trigger("update_checkout");
                }
            }
        );
    };

	/**
	 * Moves Add Order Notes Field to before the Ecster Checkout details field.
	 */
	function moveExtraCheckoutFields() {
		// Move order comments.
		$('#order_comments').appendTo('#ecster-extra-checkout-fields');
	}

    // on Ecster payment success
    var wc_ecster_on_payment_success = function wc_ecster_on_payment_success(paymentData) {
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
			window.location.href = redirectUrl;
		}
	};
	
	// Fill form and submit it to create the order.
    function processWooCheckout(paymentData) {
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

		console.log('wc_ecster_on_customer_authenticated_data');
		console.log(wc_ecster_on_customer_authenticated_data);
		fillForm();
		// Submit wc order.
		submitForm();
	};
	
	function fillForm() {
		console.log('fillForm');
		var customer = wc_ecster_on_customer_authenticated_data;
		var firstName = ( ( 'firstName' in customer ) ? customer.firstName : '' );
		var lastName = ( ( 'lastName' in customer ) ? customer.lastName : '' );
		var city = ( ( 'city' in customer ) ? customer.city : '' );
		var countryCode = ( ( 'countryCode' in customer ) ? customer.countryCode : '' );
		var email = ( ( 'email' in customer ) ? customer.email : '' );
		var phone = ( ( 'cellular' in customer ) ? customer.cellular : '' );
		var postalCode = ( ( 'zip' in customer ) ? customer.zip : '' );
		var street = ( ( 'address' in customer ) ? customer.address : '' );
		console.log('fillForm2');
		// Set customerType if it exist.
		if( $("input[name='ecster-customer-type']").length > 0 ){
			customerType = $("input[name='ecster-customer-type']:checked").val();
		}
		console.log('fillForm22');
		/*
		if( 'b2b' === customerType ) {
			$("form.checkout #billing_company").val(customer.address.line2);
		} else {
			$("form.checkout #billing_company").val('');
			$("form.checkout #billing_address_2").val(customer.address.line2);
		}
		*/
		console.log('fillForm23');
		// billing first name
		$( '#billing_first_name' ).val( firstName );
		// shipping first name
		$( '#shipping_first_name' ).val( firstName );
		console.log('fillForm24');
		// billing last name
		$( '#billing_last_name' ).val(lastName);
		// shipping last name.
		$( '#shipping_last_name' ).val(lastName);
		console.log('fillForm3');
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
		console.log('fillForm4');
	}

	function submitForm() {
		console.log('submitForm');
		if ( 0 < $( 'form.checkout #terms' ).length ) {
			$( 'form.checkout #terms' ).prop( 'checked', true );
		}
		$( 'form.checkout' ).submit();
	}

    // Set body class when DOM is ready
	$(document).ready(function () {
		wc_ecster_body_class();
		wc_ecster_init();
	});

	// When payment method is changed:
	//
	// - Change body class (CSS uses body class to hide and show elements)
	// - If changing to Ecster trigger update_checkout
	$(document.body).on("change", "input[name='payment_method']", function (event) {
		wc_ecster_body_class();

		// If switching to Ecster, update checkout
		if ("ecster" === event.target.value) {
			$("body").trigger("update_checkout");
		}
	});

	$(document.body).on("change", "input[name='ecster-customer-type']", function (event) {
		wc_ecster_update_cart();
	});

	// When checkout gets updated
	//
	// If Ecster is the selected method and Ecster has not been initialized, initialize it
	// If Ecster is the selected method and Ecster has been initialized, update cart
	$(document.body).on("updated_checkout", function () {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			if (!wc_ecster_initialized) {
				wc_ecster_init();
			} else {
				wc_ecster_update_cart();
			}
		}
	});

	$(document.body).on('click', '.ecster-pay-choose-other a', function (e) {
		changePaymentMethod( false );
	});
	
	
	// When WooCommerce checkout submission fails
	$(document.body).on("checkout_error", function () {
		if ("ecster" === $("input[name='payment_method']:checked").val()) {
			var error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
			$.ajax(
	            wc_ecster.ajaxurl,
	            {
	                type: "POST",
	                dataType: "json",
	                async: true,
	                data: {
						cart_key: 	wc_ecster_cart_key,
						error_message: error_message,
						action:		"wc_ecster_on_checkout_error",
	                    nonce:		wc_ecster.wc_ecster_nonce
	                },
	                success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						console.log('ecster checkout error');
						console.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
	            }
	        );
			
		}
	});
	$(document.body).on('change', 'input[name="payment_method"]', function() {
		if ( 'ecster' === $(this).val() ) { 
			changePaymentMethod( true );
		}
	});

	function changePaymentMethod( bool ) {
		console.log( 'Ecster changePaymentMethod' );
		console.log( bool );

		// Don't change payment method if we are currently submitting/processing the Woo order.
		if( true === wc_ecster_order_processing ) {
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
			wc_ecster.ajaxurl,
			{
				type: "POST",
				dataType: "json",
				async: true,
				data: {
					ecster: 	bool,
					action:		"wc_change_to_ecster",
					nonce:		wc_ecster.wc_change_to_ecster_nonce
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
	};

	function failOrder( event, callback ) {
		console.log('failOrder');
		console.log(event);
		$("#order_review").unblock();
		$('form.checkout').unblock();
		$('form.checkout').removeClass( 'processing' );
		$('#ecster-wrapper').unblock();
		callback( false );
	};
	function checkUrl( callback ) {
		if ( window.location.hash ) {
			var currentHash = window.location.hash;
			if ( -1 < currentHash.indexOf( '#ecster-success' ) ) {
				var splittedHash = currentHash.split("=");
				console.log('splittedHash');
				console.log(splittedHash[0]);
				console.log(splittedHash[1]);
				var response = JSON.parse( atob( splittedHash[1] ) );
				window.dibsRedirectUrl = response.redirect_url;
                console.log('response.return_url');
                console.log(response.return_url);
                sessionStorage.setItem( 'ecsterRedirectUrl', response.return_url );
				callback( true );
				// Clear the interval.
				clearInterval(wc_ecster.interval);
				// Remove the timeout.
				clearTimeout( wc_ecster.timeout );
				// Remove the processing class from the form.
				// pco_wc.checkoutFormSelector.removeClass( 'processing' );
				$( '.woocommerce-checkout-review-order-table' ).unblock();
				// $( pco_wc.checkoutFormSelector ).unblock();
				console.log('checkUrl end - callback true triggered');
			}
		}
	}

}(jQuery));
