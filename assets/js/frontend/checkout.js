/* global jQuery, wc_ecster, EcsterPay, console */
(function ($) {
	'use strict';
	var wc_ecster_initialized = false;
	var wc_ecster_cart_key = null;
	var wc_ecster_on_customer_authenticated_data = false;
	var wc_ecster_on_changed_delivery_address_data = false;
	var wc_ecster_cart_key = wc_ecster.ecster_checkout_cart_key;

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
					}
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
		wc_ecster_create_cart();
	};

	// on Ecster checkout start failure. Triggered when Ecster cart session has expired.
    var wc_ecster_on_checkout_start_failure = function wc_ecster_on_checkout_start_failure(paymentData) {

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

        // Update ongoing order cart hash
        // Add invoice fee, if needed
        $.ajax(
            wc_ecster.ajaxurl,
            {
                type: "POST",
                dataType: "json",
                async: true,
                data: {
                    action:       "wc_ecster_on_payment_success",
                    payment_data: paymentData,
                    nonce:        wc_ecster.wc_ecster_nonce
                },
           success: function() {
					console.log( 'success' );
					var customerCountry;
					var customerPhone;
					var customerType;

                    // Separate billing and shipping address checkbox checked.
					$('#ship-to-different-address-checkbox').prop('checked', true);
					
					// Set customerType if it exist.
					if( $("input[name='ecster-customer-type']").length > 0 ){
						customerType = $("input[name='ecster-customer-type']:checked").val();
					}
					
                    // Set country.
                    if (paymentData.consumer.address.country) {
                        customerCountry = paymentData.consumer.address.country;
                    } else {
                        customerCountry = 'SE';
                    }

					// Set phone.
                    if (paymentData.consumer.contactInfo.cellular.number.indexOf("*") > -1) {
                        customerPhone = '0';
                    } else {
                        customerPhone = paymentData.consumer.contactInfo.cellular.number;
					}
					// Populate the form and submit it.
					$("form.checkout #billing_first_name").val(paymentData.consumer.name.firstName);
					$("form.checkout #billing_last_name").val(paymentData.consumer.name.lastName);
					$("form.checkout #billing_email").val(paymentData.consumer.contactInfo.email);
					$("form.checkout #billing_country").val(customerCountry);
					$("form.checkout #billing_address_1").val(paymentData.consumer.address.line1);
					$("form.checkout #billing_city").val(paymentData.consumer.address.city);
					$("form.checkout #billing_postcode").val(paymentData.consumer.address.zip);
					$("form.checkout #billing_phone").val(customerPhone);

					if( 'b2b' === customerType ) {
						$("form.checkout #billing_company").val(paymentData.consumer.address.line2);
					} else {
						$("form.checkout #billing_company").val('');
						$("form.checkout #billing_address_2").val(paymentData.consumer.address.line2);
					}

                    // Check if there's separate shipping address
                   if (paymentData.recipient) {
                        $("form.checkout #shipping_first_name").val(paymentData.recipient.name.firstName);
                        $("form.checkout #shipping_last_name").val(paymentData.recipient.name.lastName);
                        $("form.checkout #shipping_country").val(paymentData.recipient.address.country);
                        $("form.checkout #shipping_address_1").val(paymentData.recipient.address.line1);
                        $("form.checkout #shipping_city").val(paymentData.recipient.address.city);
						$("form.checkout #shipping_postcode").val(paymentData.recipient.address.zip);
						
						if( 'b2b' === customerType ) {
							$("form.checkout #shipping_company").val(paymentData.recipient.address.line2);
						} else {
							$("form.checkout #shipping_company").val('');
							$("form.checkout #shipping_address_2").val(paymentData.recipient.address.line2);
						}
                    } else {
                        $("form.checkout #ship-to-different-address-checkbox").prop("checked", false);
                    }

                    // Check Terms checkbox, if it exists
                    if ($("form.checkout #terms").length > 0) {
                        $("form.checkout #terms").prop("checked", true);
                    }
					console.log( 'submit' );
                    $("form.woocommerce-checkout").trigger("submit");
                }
            }
        );
    };

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
		console.log( bool );

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
}(jQuery));
