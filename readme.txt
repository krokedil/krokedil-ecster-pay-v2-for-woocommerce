=== Ecster Pay v2 for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, ecster
Requires at least: 4.5
Tested up to: 5.5
WC requires at least: 3.7.0
WC tested up to: 4.4.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: trunk

Ecster Pay for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Ecster.


== DESCRIPTION ==
Ecster Pay is an e-commerce checkout solution that gives you more than the individual purchase. The checkout is built to provide more repeat customers and an increased average order value.

To get started with Ecster Pay you need to [sign up](https://www.ecster.se/foretag/ehandel/ecster-pay/bestall-ecster-pay) for an account.

More information on how to get started can be found in the [plugin documentation](https://docs.krokedil.com/article/313-ecster-pay-v2-introduction).


== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Ecster Pay settings.
6. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/article/313-ecster-pay-v2-introduction).


== CHANGELOG ==

= 2020.08.20        - version 2.0.7 =
* Fix               - Avoid division by zero PHP warning in calculating line item tax.

= 2020.08.14        - version 2.0.6 =
* Tweak             - Changed CSS to B2B/B2C selector. Could be displayed on two rows with certain themes.

= 2020.07.08        - version 2.0.5 =
* Fix               - Don't trigger payment method change function during Woo form submission/order creation process. Could cause conflict with other plugins and prevent user from being redirected to thank uou page.
* Fix               - Don't trigger wc_ecster_on_checkout_start_failure function during Woo form submission/order creation process.

= 2020.06.29        - version 2.0.4 =
* Fix               - Escape " characters in cart/order data sent to Ecster. Caused Ecster checkout to fail.
* Fix               - Only create WC order via fallback order creation sequence if Ecster order status is READY or FULLY_DELIVERED.

= 2020.06.25        - version 2.0.3 =
* Tweak             - Check if Ecster reference already exist in order before processing/creating Woo order.
* Tweak             - Adds CSS class processing when submitting Woo form to avoid double posting.
* Fix               - Save Ecster reference as _transaction_id in backup order creation.

= 2020.06.10        - version 2.0.2 =
* Tweak             - Move Select another payment method button to above iframe. Render it via template hook instead of via JS.
* Fix               - Save company name correct in WooCommerce order for B2B purchases.

= 2020.06.04        - version 2.0.1 =
* Tweak             - Improved displaying of error message in checkout if wrong API keys are entered in settings.
* Fix               - Remove current Ecster session and create a new if it has expired. Avoids eternal Ecster spinner in checkout.
* Fix               - Change vatRate for fees to work with new API.
* Fix               - Rounding fix in fee amount and cart total amount.

= 2020.05.08        - version 2.0.0 =
* Tweak             - Major version bump to easier be able to distinguish between EP1 & EP2.
* Tweak             - Added plugin version to header in requests sent to Ecster.
* Tweak             - Save _wc_ecster_payment_method order post meta in regular checkout flow.
* Fix               - Save phone number and email in order if it hasen't been saved during regular checkout form submission.
* Fix               - Set correct payment method in backup order creation flow.
* Fix               - Fixed bug in WC_Ecster_Request_Update_Reference request in backup order creation flow.
* Fix               - Set correct Ecster payment method name and save _transaction_id in submission error (checkout_error) sequence.

= 2020.04.30        - version 1.1.1 =
* Tweak             - Updated a couple of strings in Swedish translation.
* Fix               - Don't run Ecster js scripts on thank you page. Could cause a new session request to Ecster.
* Fix               - Don't try to create order in Woo on backup order creation callback if the callback data contain info regarding a failed payment.

= 2020.04.22        - version 1.1.0 =
* Tweak             - Add order note about cause of problem when order is created via checkout error flow.
* Tweak             - Moved unsetting of sessions to wc_ecster_unset_sessions() function.
* Tweak             - Defaulting countryCode and defaultDeliveryCountry to wc base location (if not set by customer).
* Fix               - Fixed double order problem in checkout error flow.

= 2020.03.04        - version 1.0.0 =
* Enhancement       -Automatic update feature via kernl.us.

= 2020.02.28        - version 0.1.0 =
* Initial release.