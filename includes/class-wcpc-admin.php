<?php
/**
 * Admin UI.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin class: adds "Customizer" tab to WooCommerce product data, renders the side/region designer,
 * and persists configuration on save.
 */
class WCPC_Admin {

	/**
	 * Singleton.
	 *
	 * @var WCPC_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WCPC_Admin
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
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . WCPC_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">' . esc_html__( 'Products', 'wc-product-customizer' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wcpc-admin',
			WCPC_URL . 'admin/css/admin.css',
			array(),
			WCPC_VERSION
		);

		wp_enqueue_script(
			'wcpc-admin-designer',
			WCPC_URL . 'admin/js/admin-designer.js',
			array( 'jquery', 'wp-util' ),
			WCPC_VERSION,
			true
		);

		wp_localize_script(
			'wcpc-admin-designer',
			'WCPC_ADMIN',
			array(
				'i18n' => array(
					'addSide'       => __( 'Add Side', 'wc-product-customizer' ),
					'sideName'      => __( 'Side name (e.g. Front, Back)', 'wc-product-customizer' ),
					'uploadImage'   => __( 'Upload / Select Base Image', 'wc-product-customizer' ),
					'chooseImage'   => __( 'Choose Base Image', 'wc-product-customizer' ),
					'useImage'      => __( 'Use this image', 'wc-product-customizer' ),
					'removeSide'    => __( 'Remove Side', 'wc-product-customizer' ),
					'addRegion'     => __( 'Add Editable Region', 'wc-product-customizer' ),
					'removeRegion'  => __( 'Remove', 'wc-product-customizer' ),
					'confirmRemove' => __( 'Remove this?', 'wc-product-customizer' ),
					'regionName'    => __( 'Region label', 'wc-product-customizer' ),
					'placeholder'   => __( 'Placeholder text', 'wc-product-customizer' ),
					'regionType'    => __( 'Region type', 'wc-product-customizer' ),
					'text'          => __( 'Editable Text', 'wc-product-customizer' ),
					'color'         => __( 'Color Fill', 'wc-product-customizer' ),
					'image'         => __( 'Image Upload', 'wc-product-customizer' ),
					'drawHint'      => __( 'Drag on the image to draw a rectangular editable region.', 'wc-product-customizer' ),
					'noImage'       => __( 'Upload a base image first.', 'wc-product-customizer' ),
				),
			)
		);
	}

	/**
	 * Register product-data tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_product_tab( $tabs ) {
		$tabs['wcpc_customizer'] = array(
			'label'    => __( 'Customizer', 'wc-product-customizer' ),
			'target'   => 'wcpc_customizer_data',
			'class'    => array(),
			'priority' => 70,
		);
		return $tabs;
	}

	/**
	 * Render panel.
	 */
	public function render_product_panel() {
		global $post;
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}

		$enabled  = WCPC_Helpers::is_customizable( $product ) ? 'yes' : 'no';
		$sides    = WCPC_Helpers::get_sides( $product );
		$settings = WCPC_Helpers::get_settings( $product );

		include WCPC_DIR . 'admin/views/meta-box.php';
	}

	/**
	 * Save meta.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_product_meta( $product_id ) {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wcpc_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wcpc_nonce'] ), 'wcpc_save_product' ) ) { // phpcs:ignore
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$enabled = isset( $_POST['wcpc_enabled'] ) ? 'yes' : 'no';
		$product->update_meta_data( WCPC_Helpers::META_ENABLED, $enabled );

		$raw = isset( $_POST['wcpc_sides_json'] ) ? wp_unslash( $_POST['wcpc_sides_json'] ) : ''; // phpcs:ignore
		$sides_decoded = json_decode( $raw, true );
		$sides         = WCPC_Helpers::sanitize_sides( is_array( $sides_decoded ) ? $sides_decoded : array() );
		$product->update_meta_data( WCPC_Helpers::META_SIDES, $sides );

		$settings = array(
			'dpi'          => isset( $_POST['wcpc_dpi'] ) ? intval( $_POST['wcpc_dpi'] ) : 300,
			'print_width'  => isset( $_POST['wcpc_print_w'] ) ? floatval( $_POST['wcpc_print_w'] ) : 0,
			'print_height' => isset( $_POST['wcpc_print_h'] ) ? floatval( $_POST['wcpc_print_h'] ) : 0,
			'unit'         => isset( $_POST['wcpc_unit'] ) && in_array( $_POST['wcpc_unit'], array( 'in', 'cm', 'mm' ), true ) ? $_POST['wcpc_unit'] : 'in', // phpcs:ignore
			'bg_color'     => isset( $_POST['wcpc_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wcpc_bg_color'] ) ) : '#ffffff',
		);
		$product->update_meta_data( WCPC_Helpers::META_SETTINGS, $settings );

		$product->save();
	}
}
