<?php
/**
 * Cart / order line item integration.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attaches the design id to cart items and persists it onto order line items.
 */
class WCPC_Cart {

	/**
	 * Singleton.
	 *
	 * @var WCPC_Cart|null
	 */
	private static $instance = null;

	/**
	 * Accessor.
	 *
	 * @return WCPC_Cart
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook into cart + order lifecycle.
	 */
	private function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_line_item' ), 10, 4 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_thumbnail' ), 10, 3 );
	}

	/**
	 * Require a design before adding to cart.
	 *
	 * @param bool $passed Whether to allow add-to-cart.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		unset( $quantity );
		$product = wc_get_product( $product_id );
		if ( ! $product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return $passed;
		}
		$design_id = isset( $_POST['wcpc_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wcpc_design_id'] ) ) : ''; // phpcs:ignore
		if ( empty( $design_id ) ) {
			wc_add_notice( __( 'Please customize your design before adding this product to your cart.', 'wc-product-customizer' ), 'error' );
			return false;
		}
		$manifest = $this->lookup_manifest( $design_id );
		if ( ! $manifest ) {
			wc_add_notice( __( 'We could not find your saved design. Please try customizing again.', 'wc-product-customizer' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Attach design id to cart-item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		unset( $variation_id );
		$product = wc_get_product( $product_id );
		if ( ! $product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return $cart_item_data;
		}
		$design_id = isset( $_POST['wcpc_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wcpc_design_id'] ) ) : ''; // phpcs:ignore
		if ( ! $design_id ) {
			return $cart_item_data;
		}
		$cart_item_data['wcpc_design_id'] = $design_id;
		// Force unique cart line per design so two designs don't merge.
		$cart_item_data['unique_key']     = md5( $design_id . microtime() );
		return $cart_item_data;
	}

	/**
	 * Show thumbnails + name in cart.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcpc_design_id'] ) ) {
			return $item_data;
		}
		$manifest = $this->lookup_manifest( $cart_item['wcpc_design_id'] );
		if ( ! $manifest ) {
			return $item_data;
		}

		$thumbs = '';
		foreach ( $manifest['sides'] as $side ) {
			if ( empty( $side['png'] ) ) {
				continue;
			}
			$src = trailingslashit( $manifest['url_base'] ) . $side['png'];
			$thumbs .= '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $side['name'] ) . '" />';
		}

		$item_data[] = array(
			'key'     => __( 'Custom design', 'wc-product-customizer' ),
			'value'   => '#' . esc_html( $manifest['design_id'] ),
			'display' => '<div class="wcpc-design-thumbs">' . $thumbs . '</div>',
		);
		return $item_data;
	}

	/**
	 * Save design id to the created order line item.
	 *
	 * @param WC_Order_Item_Product $item Line item.
	 * @param string                $cart_item_key Key.
	 * @param array                 $values Cart item values.
	 * @param WC_Order              $order Order.
	 */
	public function save_order_line_item( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );
		if ( empty( $values['wcpc_design_id'] ) ) {
			return;
		}
		$design_id = $values['wcpc_design_id'];
		$item->add_meta_data( '_wcpc_design_id', $design_id );
		$manifest = $this->lookup_manifest( $design_id );
		if ( $manifest ) {
			$item->add_meta_data( '_wcpc_design_manifest', $manifest );
			// Also store a customer-visible label.
			$item->add_meta_data( __( 'Design ID', 'wc-product-customizer' ), $design_id );
		}
	}

	/**
	 * Replace cart thumbnail with the first design side preview.
	 *
	 * @param string $img HTML.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Key.
	 * @return string
	 */
	public function cart_thumbnail( $img, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		if ( empty( $cart_item['wcpc_design_id'] ) ) {
			return $img;
		}
		$manifest = $this->lookup_manifest( $cart_item['wcpc_design_id'] );
		if ( ! $manifest ) {
			return $img;
		}
		foreach ( $manifest['sides'] as $side ) {
			if ( ! empty( $side['png'] ) ) {
				$src = trailingslashit( $manifest['url_base'] ) . $side['png'];
				return '<img src="' . esc_url( $src ) . '" alt="" />';
			}
		}
		return $img;
	}

	/**
	 * Look up manifest by design id from session, or from disk manifest file.
	 *
	 * @param string $design_id ID.
	 * @return array|null
	 */
	private function lookup_manifest( $design_id ) {
		$design_id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $design_id );
		if ( ! $design_id ) {
			return null;
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			$pending = (array) WC()->session->get( 'wcpc_pending_designs', array() );
			if ( isset( $pending[ $design_id ] ) ) {
				return $pending[ $design_id ];
			}
		}
		$dirs     = WCPC_Helpers::designs_upload_dir();
		$manifest = trailingslashit( $dirs['path'] ) . $design_id . '/manifest.json';
		if ( file_exists( $manifest ) ) {
			$data = json_decode( file_get_contents( $manifest ), true ); // phpcs:ignore
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}
}
