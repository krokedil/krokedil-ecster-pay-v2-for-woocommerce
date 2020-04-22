=== Ecster Pay v2 for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, ecster
Requires at least: 4.5
Tested up to: 5.4
WC requires at least: 3.5.0
WC tested up to: 4.0.1
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

= 2020.04.22        - version 1.1.0 =
* Tweak             - Add order note about cause of problem when order is created via checkout error flow.
* Tweak             - Moved unsetting of sessions to wc_ecster_unset_sessions() function.
* Tweak             - Defaulting countryCode and defaultDeliveryCountry to wc base location (if not set by customer).
* Fix               - Fixed double order problem in checkout error flow.

= 2020.03.04        - version 1.0.0 =
* Enhancement       -Automatic update feature via kernl.us.

= 2020.02.28        - version 0.1.0 =
* Initial release.