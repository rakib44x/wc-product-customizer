<?php
/**
 * Order-side integration: show design links in admin and customer emails/screens.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders design assets on orders.
 */
class WCPC_Order {

	/**
	 * Singleton.
	 *
	 * @var WCPC_Order|null
	 */
	private static $instance = null;

	/**
	 * Accessor.
	 *
	 * @return WCPC_Order
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_admin_order_item' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'rename_meta_key' ), 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_internal_meta' ) );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_customer_order_item' ), 10, 4 );
	}

	/**
	 * Hide internal meta keys from default display.
	 *
	 * @param array $hidden Hidden keys.
	 * @return array
	 */
	public function hide_internal_meta( $hidden ) {
		$hidden[] = '_wcpc_design_id';
		$hidden[] = '_wcpc_design_manifest';
		return $hidden;
	}

	/**
	 * Rename a meta key for display.
	 *
	 * @param string               $key Key.
	 * @param WC_Meta_Data         $meta Meta.
	 * @param WC_Order_Item        $item Line item.
	 * @return string
	 */
	public function rename_meta_key( $key, $meta, $item ) {
		unset( $meta, $item );
		return $key;
	}

	/**
	 * Admin order screen: link to PNGs/PDF for each line item.
	 *
	 * @param int           $item_id Line item ID.
	 * @param WC_Order_Item $item Line item.
	 * @param WC_Product    $product Product.
	 */
	public function render_admin_order_item( $item_id, $item, $product ) {
		unset( $item_id, $product );
		$manifest = $item->get_meta( '_wcpc_design_manifest' );
		if ( empty( $manifest ) || ! is_array( $manifest ) ) {
			return;
		}
		echo '<div class="wcpc-design-links" style="margin-top:6px">';
		echo '<strong>' . esc_html__( 'Customer design files:', 'wc-product-customizer' ) . '</strong><br />';
		foreach ( $manifest['sides'] as $side ) {
			if ( empty( $side['png'] ) ) {
				continue;
			}
			$src = trailingslashit( $manifest['url_base'] ) . $side['png'];
			echo '<a href="' . esc_url( $src ) . '" target="_blank" rel="noopener">' . esc_html( $side['name'] ) . ' PNG</a> ';
		}
		if ( ! empty( $manifest['pdf'] ) ) {
			$pdf = trailingslashit( $manifest['url_base'] ) . $manifest['pdf'];
			echo '<a href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener"><strong>' . esc_html__( 'Print-ready PDF', 'wc-product-customizer' ) . '</strong></a>';
		}
		echo '<div class="wcpc-design-thumbs" style="margin-top:6px">';
		foreach ( $manifest['sides'] as $side ) {
			if ( empty( $side['png'] ) ) {
				continue;
			}
			$src = trailingslashit( $manifest['url_base'] ) . $side['png'];
			echo '<img src="' . esc_url( $src ) . '" style="width:120px;height:auto;border:1px solid #ddd;border-radius:4px;background:#fff;margin-right:6px" />';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Customer-facing order: show thumbnails.
	 *
	 * @param int           $item_id Item id.
	 * @param WC_Order_Item $item Item.
	 * @param WC_Order      $order Order.
	 * @param bool          $plain_text Plain text.
	 */
	public function render_customer_order_item( $item_id, $item, $order, $plain_text ) {
		unset( $item_id, $order );
		if ( $plain_text ) {
			return;
		}
		$manifest = $item->get_meta( '_wcpc_design_manifest' );
		if ( empty( $manifest ) || ! is_array( $manifest ) ) {
			return;
		}
		echo '<div class="wcpc-design-thumbs" style="display:flex;gap:6px;margin-top:6px">';
		foreach ( $manifest['sides'] as $side ) {
			if ( empty( $side['png'] ) ) {
				continue;
			}
			$src = trailingslashit( $manifest['url_base'] ) . $side['png'];
			echo '<img src="' . esc_url( $src ) . '" style="width:90px;height:auto;border:1px solid #ddd;border-radius:4px;background:#fff" />';
		}
		echo '</div>';
	}
}
