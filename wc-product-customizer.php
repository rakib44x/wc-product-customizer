<?php
/**
 * Plugin Name: WC Product Customizer
 * Plugin URI:  https://bd.linkedin.com/in/rirakeeb
 * Description: Let customers personalize WooCommerce products from the frontend with a Canva-style editor (text, colors, fonts, drag & drop). Admin defines base designs, sides (front/back/etc.) and editable regions. On order, the admin receives print-ready high-resolution PNGs and a PDF per design.
 * Version:     1.0.0
 * Author:      Rakibul Islam
 * Author URI:  https://bd.linkedin.com/in/rirakeeb
 * Text Domain: wc-product-customizer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      9.0
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPC_VERSION', '1.0.0' );
define( 'WCPC_FILE', __FILE__ );
define( 'WCPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPC_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPC_BASENAME', plugin_basename( __FILE__ ) );

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once WCPC_DIR . 'includes/class-wcpc-plugin.php';

register_activation_hook( __FILE__, array( 'WCPC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCPC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WCPC_Plugin', 'instance' ) );
