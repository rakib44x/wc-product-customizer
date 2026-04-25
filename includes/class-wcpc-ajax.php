<?php
/**
 * AJAX handlers.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles saving design payloads from the frontend editor.
 */
class WCPC_Ajax {

	/**
	 * Singleton.
	 *
	 * @var WCPC_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return WCPC_Ajax
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook up AJAX endpoints.
	 */
	private function __construct() {
		add_action( 'wp_ajax_wcpc_save_design',        array( $this, 'save_design' ) );
		add_action( 'wp_ajax_nopriv_wcpc_save_design', array( $this, 'save_design' ) );
	}

	/**
	 * Save design.
	 */
	public function save_design() {
		check_ajax_referer( 'wcpc_save_design', 'nonce' );

		$product_id = isset( $_POST['productId'] ) ? absint( $_POST['productId'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! WCPC_Helpers::is_customizable( $product ) ) {
			wp_send_json_error( array( 'message' => 'Invalid product.' ), 400 );
		}

		$raw     = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$payload = json_decode( $raw, true );
		if ( empty( $payload ) || ! is_array( $payload ) || empty( $payload['sides'] ) || ! is_array( $payload['sides'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid payload.' ), 400 );
		}

		$design_id = WCPC_Helpers::generate_design_id();
		$dirs      = WCPC_Helpers::designs_upload_dir();
		$dir       = trailingslashit( $dirs['path'] ) . $design_id;
		$url       = trailingslashit( $dirs['url'] ) . $design_id;

		if ( ! wp_mkdir_p( $dir ) ) {
			wp_send_json_error( array( 'message' => 'Cannot create design directory.' ), 500 );
		}

		$sides_meta = array();

		foreach ( $payload['sides'] as $i => $side ) {
			$name     = isset( $side['name'] ) ? sanitize_text_field( $side['name'] ) : ( 'Side ' . ( $i + 1 ) );
			$slug     = sanitize_title( $name ) ? sanitize_title( $name ) : ( 'side-' . ( $i + 1 ) );
			$width    = isset( $side['width'] ) ? intval( $side['width'] ) : 0;
			$height   = isset( $side['height'] ) ? intval( $side['height'] ) : 0;
			$png      = isset( $side['png'] ) ? $side['png'] : '';
			$json_obj = isset( $side['json'] ) ? $side['json'] : null;

			$png_rel = '';
			$json_rel = '';

			if ( ! empty( $png ) && is_string( $png ) && 0 === strpos( $png, 'data:image/' ) ) {
				$decoded = $this->decode_data_url( $png );
				if ( $decoded ) {
					$filename = $slug . '-' . ( $i + 1 ) . '.png';
					$target   = trailingslashit( $dir ) . $filename;
					if ( false !== file_put_contents( $target, $decoded ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						$png_rel = $filename;
					}
				}
			}

			if ( $json_obj ) {
				$filename = $slug . '-' . ( $i + 1 ) . '.json';
				$target   = trailingslashit( $dir ) . $filename;
				if ( false !== file_put_contents( $target, wp_json_encode( $json_obj ) ) ) { // phpcs:ignore
					$json_rel = $filename;
				}
			}

			$sides_meta[] = array(
				'name'   => $name,
				'slug'   => $slug,
				'width'  => $width,
				'height' => $height,
				'png'    => $png_rel,
				'json'   => $json_rel,
			);
		}

		// Generate PDF.
		$pdf_file = '';
		try {
			$pdf_path = WCPC_PDF::generate( $dir, $sides_meta );
			if ( $pdf_path && file_exists( $pdf_path ) ) {
				$pdf_file = basename( $pdf_path );
			}
		} catch ( Exception $e ) {
			// PDF is optional — continue even if it fails.
			$pdf_file = '';
		}

		$manifest = array(
			'design_id'  => $design_id,
			'product_id' => $product_id,
			'created'    => gmdate( 'c' ),
			'sides'      => $sides_meta,
			'pdf'        => $pdf_file,
			'url_base'   => $url,
		);
		file_put_contents( trailingslashit( $dir ) . 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) ); // phpcs:ignore

		// Stash in user session so cart can pick it up.
		if ( function_exists( 'WC' ) && WC()->session ) {
			$pending                  = (array) WC()->session->get( 'wcpc_pending_designs', array() );
			$pending[ $design_id ]    = $manifest;
			WC()->session->set( 'wcpc_pending_designs', $pending );
		}

		wp_send_json_success(
			array(
				'designId' => $design_id,
				'pdf'      => $pdf_file ? trailingslashit( $url ) . $pdf_file : '',
			)
		);
	}

	/**
	 * Decode a data URL.
	 *
	 * @param string $data_url Data URL.
	 * @return string|false Binary content, or false.
	 */
	private function decode_data_url( $data_url ) {
		if ( ! preg_match( '#^data:image/(png|jpeg|jpg);base64,(.+)$#i', $data_url, $m ) ) {
			return false;
		}
		return base64_decode( $m[2] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
}
