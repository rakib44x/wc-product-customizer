<?php
/**
 * Main plugin bootstrap.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton.
 */
class WCPC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WCPC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WCPC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();

		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
			return;
		}

		$this->includes();
		$this->init_components();
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function wc_missing_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WC Product Customizer requires WooCommerce to be installed and active.', 'wc-product-customizer' );
		echo '</p></div>';
	}

	/**
	 * Load text domain.
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'wc-product-customizer', false, dirname( WCPC_BASENAME ) . '/languages' );
	}

	/**
	 * Require class files.
	 */
	private function includes() {
		require_once WCPC_DIR . 'includes/class-wcpc-helpers.php';
		require_once WCPC_DIR . 'includes/class-wcpc-admin.php';
		require_once WCPC_DIR . 'includes/class-wcpc-frontend.php';
		require_once WCPC_DIR . 'includes/class-wcpc-ajax.php';
		require_once WCPC_DIR . 'includes/class-wcpc-cart.php';
		require_once WCPC_DIR . 'includes/class-wcpc-order.php';
		require_once WCPC_DIR . 'includes/class-wcpc-pdf.php';
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		WCPC_Admin::instance();
		WCPC_Frontend::instance();
		WCPC_Ajax::instance();
		WCPC_Cart::instance();
		WCPC_Order::instance();
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		// Create uploads directory.
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'wcpc-designs';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Protect directory.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Options -Indexes\n" );
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' );
		}
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		// No-op for now. Keep user data.
	}
}
