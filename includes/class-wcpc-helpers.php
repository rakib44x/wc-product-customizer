<?php
/**
 * Shared helpers.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Static helper methods.
 */
class WCPC_Helpers {

	const META_ENABLED  = '_wcpc_enabled';
	const META_SIDES    = '_wcpc_sides';
	const META_FONTS    = '_wcpc_fonts';
	const META_SETTINGS = '_wcpc_settings';

	/**
	 * Resolve a product from whatever the caller passed in. Accepts a WC_Product,
	 * a numeric ID, a WP_Post, a product slug string, or null/anything else.
	 *
	 * Needed because builder themes like Hello Elementor populate the
	 * `$product` global with the product slug string before WooCommerce
	 * overrides it with a WC_Product instance, and our frontend hooks
	 * can fire during that narrow window.
	 *
	 * @param mixed $product Product-ish.
	 * @return WC_Product|null
	 */
	public static function resolve_product( $product ) {
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( is_numeric( $product ) ) {
			$resolved = wc_get_product( (int) $product );
			return $resolved ? $resolved : null;
		}
		if ( $product instanceof WP_Post ) {
			$resolved = wc_get_product( $product );
			return $resolved ? $resolved : null;
		}
		if ( is_string( $product ) && '' !== $product ) {
			$post = get_page_by_path( $product, OBJECT, 'product' );
			if ( $post ) {
				$resolved = wc_get_product( $post );
				return $resolved ? $resolved : null;
			}
		}
		// Fallback: use the currently queried post.
		$id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;
		if ( $id ) {
			$resolved = wc_get_product( $id );
			return $resolved ? $resolved : null;
		}
		return null;
	}

	/**
	 * Is the given product customizable?
	 *
	 * @param mixed $product Product, product ID, WP_Post, slug, or anything.
	 * @return bool
	 */
	public static function is_customizable( $product ) {
		$product = self::resolve_product( $product );
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		return 'yes' === $product->get_meta( self::META_ENABLED );
	}

	/**
	 * Get sides config for product.
	 *
	 * Structure:
	 *   [
	 *     [
	 *       'name'     => 'Front',
	 *       'image_id' => 123,
	 *       'image'    => 'https://...',
	 *       'width'    => 1050,
	 *       'height'   => 600,
	 *       'regions'  => [
	 *          [
	 *            'id'    => 'r1',
	 *            'name'  => 'Company Name',
	 *            'type'  => 'text|color|image',
	 *            'x'     => 50,
	 *            'y'     => 100,
	 *            'w'     => 400,
	 *            'h'     => 80,
	 *            'placeholder' => 'Your name',
	 *            'defaultColor' => '#000000',
	 *          ],
	 *       ],
	 *     ],
	 *   ]
	 *
	 * @param int|WC_Product $product Product or ID.
	 * @return array
	 */
	public static function get_sides( $product ) {
		$product = self::resolve_product( $product );
		if ( ! $product instanceof WC_Product ) {
			return array();
		}
		$sides = $product->get_meta( self::META_SIDES );
		if ( empty( $sides ) || ! is_array( $sides ) ) {
			return array();
		}
		return array_values( $sides );
	}

	/**
	 * Get product-level settings (dpi, print size, etc.).
	 *
	 * @param int|WC_Product $product Product.
	 * @return array
	 */
	public static function get_settings( $product ) {
		$product = self::resolve_product( $product );
		if ( ! $product instanceof WC_Product ) {
			return self::default_settings();
		}
		$settings = $product->get_meta( self::META_SETTINGS );
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Default product settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'dpi'          => 300,
			'print_width'  => 0,   // inches; 0 = infer from image.
			'print_height' => 0,
			'unit'         => 'in',
			'bg_color'     => '#ffffff',
		);
	}

	/**
	 * Available fonts.
	 *
	 * @return array
	 */
	public static function available_fonts() {
		return array(
			'Arial, sans-serif'                  => 'Arial',
			'"Helvetica Neue", Helvetica, Arial, sans-serif' => 'Helvetica',
			'Georgia, serif'                     => 'Georgia',
			'"Times New Roman", Times, serif'    => 'Times New Roman',
			'"Courier New", Courier, monospace'  => 'Courier New',
			'Verdana, Geneva, sans-serif'        => 'Verdana',
			'Tahoma, Geneva, sans-serif'         => 'Tahoma',
			'"Trebuchet MS", sans-serif'         => 'Trebuchet MS',
			'Impact, Charcoal, sans-serif'       => 'Impact',
			'"Comic Sans MS", cursive, sans-serif' => 'Comic Sans MS',
			'"Brush Script MT", cursive'         => 'Brush Script',
			'Roboto, sans-serif'                 => 'Roboto',
			'"Open Sans", sans-serif'            => 'Open Sans',
			'Lato, sans-serif'                   => 'Lato',
			'Montserrat, sans-serif'             => 'Montserrat',
			'Poppins, sans-serif'                => 'Poppins',
		);
	}

	/**
	 * Get the uploads directory for designs.
	 *
	 * @return array{path:string,url:string}
	 */
	public static function designs_upload_dir() {
		$upload = wp_upload_dir();
		return array(
			'path' => trailingslashit( $upload['basedir'] ) . 'wcpc-designs',
			'url'  => trailingslashit( $upload['baseurl'] ) . 'wcpc-designs',
		);
	}

	/**
	 * Generate a unique design ID.
	 *
	 * @return string
	 */
	public static function generate_design_id() {
		return 'des_' . wp_generate_password( 16, false, false );
	}

	/**
	 * Sanitize regions array from posted data.
	 *
	 * @param mixed $regions Raw regions.
	 * @return array
	 */
	public static function sanitize_regions( $regions ) {
		if ( ! is_array( $regions ) ) {
			return array();
		}
		$out = array();
		foreach ( $regions as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$out[] = array(
				'id'           => isset( $r['id'] ) ? sanitize_key( $r['id'] ) : '',
				'name'         => isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '',
				'type'         => isset( $r['type'] ) && in_array( $r['type'], array( 'text', 'color', 'image' ), true ) ? $r['type'] : 'text',
				'x'            => isset( $r['x'] ) ? floatval( $r['x'] ) : 0,
				'y'            => isset( $r['y'] ) ? floatval( $r['y'] ) : 0,
				'w'            => isset( $r['w'] ) ? floatval( $r['w'] ) : 0,
				'h'            => isset( $r['h'] ) ? floatval( $r['h'] ) : 0,
				'placeholder'  => isset( $r['placeholder'] ) ? sanitize_text_field( $r['placeholder'] ) : '',
				'defaultColor' => isset( $r['defaultColor'] ) ? sanitize_hex_color( $r['defaultColor'] ) : '',
				'defaultFont'  => isset( $r['defaultFont'] ) ? sanitize_text_field( $r['defaultFont'] ) : '',
				'defaultSize'  => isset( $r['defaultSize'] ) ? floatval( $r['defaultSize'] ) : 24,
			);
		}
		return $out;
	}

	/**
	 * Sanitize a sides array.
	 *
	 * @param mixed $sides Raw sides.
	 * @return array
	 */
	public static function sanitize_sides( $sides ) {
		if ( ! is_array( $sides ) ) {
			return array();
		}
		$out = array();
		foreach ( $sides as $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$image_id = isset( $s['image_id'] ) ? intval( $s['image_id'] ) : 0;
			$image    = '';
			$width    = isset( $s['width'] ) ? intval( $s['width'] ) : 0;
			$height   = isset( $s['height'] ) ? intval( $s['height'] ) : 0;
			if ( $image_id ) {
				$src = wp_get_attachment_image_src( $image_id, 'full' );
				if ( $src ) {
					$image  = $src[0];
					$width  = $width ? $width : intval( $src[1] );
					$height = $height ? $height : intval( $src[2] );
				}
			}
			$out[] = array(
				'name'     => isset( $s['name'] ) ? sanitize_text_field( $s['name'] ) : 'Side',
				'image_id' => $image_id,
				'image'    => $image,
				'width'    => $width,
				'height'   => $height,
				'regions'  => self::sanitize_regions( isset( $s['regions'] ) ? $s['regions'] : array() ),
			);
		}
		return $out;
	}
}
