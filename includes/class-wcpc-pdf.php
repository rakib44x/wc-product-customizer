<?php
/**
 * Minimal PDF generator: one page per side with the design image embedded.
 *
 * Dependencies-free (no Composer). Uses GD to transcode PNG → JPEG for
 * PDF /DCTDecode embedding, keeping output small and print-ready.
 *
 * @package WC_Product_Customizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * PDF generator.
 */
class WCPC_PDF {

	/**
	 * Generate a PDF combining all sides.
	 *
	 * @param string $dir   Directory with PNG files.
	 * @param array  $sides Sides metadata (from the ajax handler).
	 * @return string|null Path to generated PDF, or null on failure.
	 */
	public static function generate( $dir, $sides ) {
		if ( ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagejpeg' ) ) {
			return null;
		}

		$pages = array();
		foreach ( $sides as $side ) {
			if ( empty( $side['png'] ) ) {
				continue;
			}
			$png_path = trailingslashit( $dir ) . $side['png'];
			if ( ! file_exists( $png_path ) ) {
				continue;
			}
			$jpeg = self::png_to_jpeg( $png_path );
			if ( ! $jpeg ) {
				continue;
			}
			$pages[] = array(
				'jpeg'   => $jpeg['data'],
				'width'  => $jpeg['width'],
				'height' => $jpeg['height'],
			);
		}

		if ( empty( $pages ) ) {
			return null;
		}

		$pdf_content = self::build_pdf( $pages );
		if ( ! $pdf_content ) {
			return null;
		}

		$file = trailingslashit( $dir ) . 'design.pdf';
		if ( false === file_put_contents( $file, $pdf_content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return null;
		}
		return $file;
	}

	/**
	 * Convert PNG file → JPEG bytes.
	 *
	 * @param string $png_path PNG path.
	 * @return array|null { data, width, height }
	 */
	private static function png_to_jpeg( $png_path ) {
		$src = @imagecreatefrompng( $png_path );
		if ( ! $src ) {
			return null;
		}
		$w = imagesx( $src );
		$h = imagesy( $src );

		// Flatten transparency onto white.
		$flat = imagecreatetruecolor( $w, $h );
		$white = imagecolorallocate( $flat, 255, 255, 255 );
		imagefilledrectangle( $flat, 0, 0, $w, $h, $white );
		imagealphablending( $flat, true );
		imagecopy( $flat, $src, 0, 0, 0, 0, $w, $h );
		imagedestroy( $src );

		ob_start();
		imagejpeg( $flat, null, 92 );
		$jpeg = ob_get_clean();
		imagedestroy( $flat );

		if ( ! $jpeg ) {
			return null;
		}
		return array( 'data' => $jpeg, 'width' => $w, 'height' => $h );
	}

	/**
	 * Build a minimal PDF document.
	 *
	 * @param array $pages Array of { jpeg, width, height }.
	 * @return string PDF bytes.
	 */
	private static function build_pdf( $pages ) {
		$objects = array();
		$add     = function ( $body ) use ( &$objects ) {
			$objects[] = $body;
			return count( $objects );
		};

		// Placeholders — we'll fill Catalog + Pages after we know IDs.
		$catalog_id   = $add( '' );
		$pages_id     = $add( '' );

		$page_ids     = array();
		$kids_refs    = array();

		foreach ( $pages as $p ) {
			// Fit page at 72 DPI using image pixel dimensions.
			$page_w = $p['width']  * 72 / 150; // assume ~150 dpi default layout.
			$page_h = $p['height'] * 72 / 150;

			// Image XObject.
			$img_body  = "<< /Type /XObject /Subtype /Image /Width {$p['width']} /Height {$p['height']}"
				. " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
				. strlen( $p['jpeg'] ) . " >>\nstream\n" . $p['jpeg'] . "\nendstream";
			$img_id    = $add( $img_body );

			// Content stream: draw image filling the page.
			$content = sprintf( "q\n%F 0 0 %F 0 0 cm\n/Im%d Do\nQ", $page_w, $page_h, $img_id );
			$content_body = "<< /Length " . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
			$content_id   = $add( $content_body );

			// Page.
			$page_body = sprintf(
				"<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %F %F]"
				. " /Resources << /XObject << /Im%d %d 0 R >> /ProcSet [/PDF /ImageC] >>"
				. " /Contents %d 0 R >>",
				$pages_id,
				$page_w,
				$page_h,
				$img_id,
				$img_id,
				$content_id
			);
			$page_id   = $add( $page_body );
			$page_ids[]  = $page_id;
			$kids_refs[] = $page_id . ' 0 R';
		}

		// Now backfill Catalog + Pages objects.
		$objects[ $catalog_id - 1 ] = "<< /Type /Catalog /Pages {$pages_id} 0 R >>";
		$objects[ $pages_id - 1 ]   = "<< /Type /Pages /Kids [ " . implode( ' ', $kids_refs ) . " ] /Count " . count( $page_ids ) . " >>";

		// Emit PDF.
		$out     = "%PDF-1.4\n%\xFF\xFF\xFF\xFF\n";
		$offsets = array();
		foreach ( $objects as $idx => $body ) {
			$offsets[ $idx ] = strlen( $out );
			$out .= ( $idx + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref_pos = strlen( $out );
		$n        = count( $objects );
		$out     .= "xref\n0 " . ( $n + 1 ) . "\n";
		$out     .= "0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$out .= sprintf( "%010d 00000 n \n", $off );
		}
		$out .= "trailer\n<< /Size " . ( $n + 1 ) . " /Root {$catalog_id} 0 R >>\n";
		$out .= "startxref\n{$xref_pos}\n%%EOF";
		return $out;
	}
}
