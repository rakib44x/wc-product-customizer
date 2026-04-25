<?php
/**
 * Frontend customizer.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Customize" button, the hidden design-id field, and enqueues the fabric-based editor.
 */
class WCPC_Frontend {

	/**
	 * Singleton.
	 *
	 * @var WCPC_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return WCPC_Frontend
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Classic and most block templates fire this hook from within form.cart.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_customize_button' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_editor_container' ), 20 );
		// Safety net for Elementor / Bricks / custom builders that bypass the in-form hook:
		// drop a tiny inline script in wp_footer that finds form.cart and injects the trigger
		// row before the submit button if it isn't already present.
		add_action( 'wp_footer', array( $this, 'output_safety_net_injector' ), 99 );
	}

	/**
	 * Resolve the current product safely. Works even when `global $product`
	 * is temporarily a slug string (as Hello Elementor does during early
	 * wp_enqueue_scripts) or when the global has not been set up yet.
	 *
	 * @return WC_Product|null
	 */
	private static function get_current_product() {
		global $product;
		$resolved = WCPC_Helpers::resolve_product( $product );
		if ( $resolved ) {
			return $resolved;
		}
		$id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
		if ( ! $id && function_exists( 'get_the_ID' ) ) {
			$id = get_the_ID();
		}
		return $id ? wc_get_product( $id ) : null;
	}

	/**
	 * Enqueue.
	 */
	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}
		$product = self::get_current_product();
		if ( ! $product instanceof WC_Product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'wcpc-frontend',
			WCPC_URL . 'public/css/frontend.css',
			array(),
			WCPC_VERSION
		);

		wp_enqueue_script(
			'wcpc-fabric',
			WCPC_URL . 'public/js/fabric.min.js',
			array(),
			'5.3.0',
			true
		);

		wp_enqueue_script(
			'wcpc-customizer',
			WCPC_URL . 'public/js/frontend-customizer.js',
			array( 'jquery', 'wcpc-fabric' ),
			WCPC_VERSION,
			true
		);

		$sides    = WCPC_Helpers::get_sides( $product );
		$settings = WCPC_Helpers::get_settings( $product );

		wp_localize_script(
			'wcpc-customizer',
			'WCPC',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wcpc_save_design' ),
				'productId'  => $product->get_id(),
				'sides'      => $sides,
				'settings'   => $settings,
				'fonts'      => WCPC_Helpers::available_fonts(),
				'i18n'      => array(
					'customize'   => __( 'Customize Design', 'wc-product-customizer' ),
					'editing'     => __( 'Editing:', 'wc-product-customizer' ),
					'save'        => __( 'Save & Continue', 'wc-product-customizer' ),
					'cancel'      => __( 'Cancel', 'wc-product-customizer' ),
					'close'       => __( 'Close', 'wc-product-customizer' ),
					'addText'     => __( 'Add text', 'wc-product-customizer' ),
					'uploadImg'   => __( 'Upload image', 'wc-product-customizer' ),
					'delete'      => __( 'Delete', 'wc-product-customizer' ),
					'duplicate'   => __( 'Duplicate', 'wc-product-customizer' ),
					'bringFront'  => __( 'Bring to front', 'wc-product-customizer' ),
					'sendBack'    => __( 'Send to back', 'wc-product-customizer' ),
					'textProps'   => __( 'Text properties', 'wc-product-customizer' ),
					'font'        => __( 'Font', 'wc-product-customizer' ),
					'size'        => __( 'Size', 'wc-product-customizer' ),
					'color'       => __( 'Color', 'wc-product-customizer' ),
					'bold'        => __( 'Bold', 'wc-product-customizer' ),
					'italic'      => __( 'Italic', 'wc-product-customizer' ),
					'underline'   => __( 'Underline', 'wc-product-customizer' ),
					'align'       => __( 'Align', 'wc-product-customizer' ),
					'bgColor'     => __( 'Background color', 'wc-product-customizer' ),
					'opacity'     => __( 'Opacity', 'wc-product-customizer' ),
					'regionFill'  => __( 'Region color', 'wc-product-customizer' ),
					'saving'      => __( 'Saving…', 'wc-product-customizer' ),
					'savedOk'     => __( 'Design saved. Add to cart to continue.', 'wc-product-customizer' ),
					'savedErr'    => __( 'Could not save design. Please try again.', 'wc-product-customizer' ),
					'emptyDesign' => __( 'This product has no editable sides configured yet.', 'wc-product-customizer' ),
					'requiresDesign' => __( 'Please customize your design before adding to cart.', 'wc-product-customizer' ),
					'sides'       => __( 'Sides', 'wc-product-customizer' ),
					'typeHere'    => __( 'Type here', 'wc-product-customizer' ),
				),
			)
		);
	}

	/**
	 * "Customize" button above add-to-cart.
	 */
	public function render_customize_button() {
		$product = self::get_current_product();
		if ( ! $product instanceof WC_Product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return;
		}
		$sides = WCPC_Helpers::get_sides( $product );
		if ( empty( $sides ) ) {
			return;
		}
		echo '<div class="wcpc-customize-trigger">';
		echo '<button type="button" class="button alt wcpc-open-customizer">' . esc_html__( 'Customize Design', 'wc-product-customizer' ) . '</button>';
		echo '<span class="wcpc-status" aria-live="polite"></span>';
		echo '<input type="hidden" name="wcpc_design_id" value="" />';
		echo '</div>';
	}

	/**
	 * Empty modal mount point.
	 */
	public function render_editor_container() {
		$product = self::get_current_product();
		if ( ! $product instanceof WC_Product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return;
		}
		$sides = WCPC_Helpers::get_sides( $product );
		if ( empty( $sides ) ) {
			return;
		}
		echo '<div id="wcpc-editor-root"></div>';
	}

	/**
	 * Inline footer script that injects the customize trigger into any
	 * `form.cart` it finds, in case a builder template (Elementor / Bricks /
	 * custom theme) skipped `woocommerce_before_add_to_cart_button`.
	 *
	 * Idempotent — does nothing if the trigger is already present.
	 */
	public function output_safety_net_injector() {
		if ( ! is_product() ) {
			return;
		}
		$product = self::get_current_product();
		if ( ! $product instanceof WC_Product || ! WCPC_Helpers::is_customizable( $product ) ) {
			return;
		}
		$sides = WCPC_Helpers::get_sides( $product );
		if ( empty( $sides ) ) {
			return;
		}
		$label = esc_html__( 'Customize Design', 'wc-product-customizer' );
		?>
<script id="wcpc-injector">
( function () {
	function inject() {
		var forms = document.querySelectorAll( 'form.cart' );
		if ( !forms.length ) { return; }
		forms.forEach( function ( form ) {
			if ( form.querySelector( '.wcpc-open-customizer' ) ) { return; }
			var trigger = document.createElement( 'div' );
			trigger.className = 'wcpc-customize-trigger';
			trigger.innerHTML =
				'<button type="button" class="button alt wcpc-open-customizer"><?php echo esc_js( $label ); ?></button>' +
				'<span class="wcpc-status" aria-live="polite"></span>' +
				'<input type="hidden" name="wcpc_design_id" value="" />';
			var submit = form.querySelector( 'button[type="submit"], .single_add_to_cart_button' );
			if ( submit && submit.parentNode === form ) {
				form.insertBefore( trigger, submit );
			} else if ( submit ) {
				submit.parentNode.insertBefore( trigger, submit );
			} else {
				form.appendChild( trigger );
			}
			if ( !document.getElementById( 'wcpc-editor-root' ) ) {
				var root = document.createElement( 'div' );
				root.id = 'wcpc-editor-root';
				document.body.appendChild( root );
			}
		} );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', inject );
	} else {
		inject();
	}
	// Re-run after Elementor / template rerenders (best-effort).
	if ( window.MutationObserver ) {
		var mo = new MutationObserver( function () { inject(); } );
		mo.observe( document.body, { childList: true, subtree: true } );
		setTimeout( function () { mo.disconnect(); }, 5000 );
	}
} )();
</script>
		<?php
	}
}
