<?php
/**
 * Admin product customizer panel.
 *
 * @package WC_Product_Customizer
 *
 * @var string $enabled
 * @var array  $sides
 * @var array  $settings
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="wcpc_customizer_data" class="panel woocommerce_options_panel">
	<?php wp_nonce_field( 'wcpc_save_product', 'wcpc_nonce' ); ?>

	<div class="options_group">
		<p class="form-field">
			<label for="wcpc_enabled"><?php esc_html_e( 'Enable Customizer', 'wc-product-customizer' ); ?></label>
			<input type="checkbox" id="wcpc_enabled" name="wcpc_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> />
			<span class="description"><?php esc_html_e( 'Show the "Customize" button on this product and let customers design it from the frontend.', 'wc-product-customizer' ); ?></span>
		</p>
	</div>

	<div class="options_group">
		<h4 style="padding:0 12px;margin:12px 0 4px"><?php esc_html_e( 'Print output settings', 'wc-product-customizer' ); ?></h4>
		<p class="form-field">
			<label for="wcpc_dpi"><?php esc_html_e( 'Print DPI', 'wc-product-customizer' ); ?></label>
			<input type="number" id="wcpc_dpi" name="wcpc_dpi" min="72" max="1200" step="1" value="<?php echo esc_attr( $settings['dpi'] ); ?>" />
			<span class="description"><?php esc_html_e( 'Resolution used when exporting the final print-ready image. 300 is standard for commercial printing.', 'wc-product-customizer' ); ?></span>
		</p>
		<p class="form-field">
			<label for="wcpc_print_w"><?php esc_html_e( 'Print size', 'wc-product-customizer' ); ?></label>
			<input type="number" id="wcpc_print_w" name="wcpc_print_w" min="0" step="0.01" value="<?php echo esc_attr( $settings['print_width'] ); ?>" style="width:80px" placeholder="W" />
			&times;
			<input type="number" id="wcpc_print_h" name="wcpc_print_h" min="0" step="0.01" value="<?php echo esc_attr( $settings['print_height'] ); ?>" style="width:80px" placeholder="H" />
			<select name="wcpc_unit" id="wcpc_unit">
				<option value="in" <?php selected( $settings['unit'], 'in' ); ?>>in</option>
				<option value="cm" <?php selected( $settings['unit'], 'cm' ); ?>>cm</option>
				<option value="mm" <?php selected( $settings['unit'], 'mm' ); ?>>mm</option>
			</select>
			<span class="description"><?php esc_html_e( 'Leave as 0 to infer size from the base image at the specified DPI.', 'wc-product-customizer' ); ?></span>
		</p>
		<p class="form-field">
			<label for="wcpc_bg_color"><?php esc_html_e( 'Default background', 'wc-product-customizer' ); ?></label>
			<input type="text" class="wcpc-color" id="wcpc_bg_color" name="wcpc_bg_color" value="<?php echo esc_attr( $settings['bg_color'] ); ?>" />
		</p>
	</div>

	<div class="options_group wcpc-designer-wrap">
		<h4 style="padding:0 12px;margin:12px 0 4px"><?php esc_html_e( 'Sides & editable regions', 'wc-product-customizer' ); ?></h4>
		<p class="form-field" style="padding-top:0">
			<span class="description"><?php esc_html_e( 'Add one side per printable face (e.g. Front / Back). Upload the base design image for each side and drag on the preview to mark editable regions.', 'wc-product-customizer' ); ?></span>
		</p>

		<div id="wcpc-sides" class="wcpc-sides"></div>

		<p class="form-field">
			<button type="button" class="button button-primary" id="wcpc-add-side">
				<?php esc_html_e( '+ Add Side', 'wc-product-customizer' ); ?>
			</button>
		</p>

		<input type="hidden" id="wcpc_sides_json" name="wcpc_sides_json" value="<?php echo esc_attr( wp_json_encode( $sides ) ); ?>" />
	</div>

	<script type="text/html" id="tmpl-wcpc-side">
		<div class="wcpc-side" data-index="{{ data.index }}">
			<div class="wcpc-side-head">
				<input type="text" class="wcpc-side-name" value="{{ data.name }}" placeholder="<?php echo esc_attr__( 'Side name (e.g. Front)', 'wc-product-customizer' ); ?>" />
				<button type="button" class="button wcpc-upload-btn"><?php esc_html_e( 'Upload / Change Base Image', 'wc-product-customizer' ); ?></button>
				<button type="button" class="button button-link-delete wcpc-remove-side"><?php esc_html_e( 'Remove Side', 'wc-product-customizer' ); ?></button>
			</div>
			<div class="wcpc-side-body">
				<div class="wcpc-canvas-wrap">
					<div class="wcpc-canvas" style="background-image: url('{{ data.image }}')">
						<# if (!data.image) { #>
							<div class="wcpc-no-image"><?php esc_html_e( 'No base image yet.', 'wc-product-customizer' ); ?></div>
						<# } #>
					</div>
					<p class="description"><?php esc_html_e( 'Drag on the image to draw a rectangular editable region.', 'wc-product-customizer' ); ?></p>
				</div>
				<div class="wcpc-regions-wrap">
					<h4><?php esc_html_e( 'Editable regions', 'wc-product-customizer' ); ?></h4>
					<div class="wcpc-regions-list"></div>
				</div>
			</div>
		</div>
	</script>

	<script type="text/html" id="tmpl-wcpc-region-row">
		<div class="wcpc-region-row" data-region="{{ data.id }}">
			<span class="wcpc-region-index">#{{ data.idx }}</span>
			<input type="text" class="wcpc-region-name" value="{{ data.name }}" placeholder="<?php echo esc_attr__( 'Label (e.g. Company name)', 'wc-product-customizer' ); ?>" />
			<select class="wcpc-region-type">
				<option value="text" <# if (data.type==='text') { #>selected<# } #>><?php esc_html_e( 'Editable Text', 'wc-product-customizer' ); ?></option>
				<option value="color" <# if (data.type==='color') { #>selected<# } #>><?php esc_html_e( 'Color Fill', 'wc-product-customizer' ); ?></option>
				<option value="image" <# if (data.type==='image') { #>selected<# } #>><?php esc_html_e( 'Image Upload', 'wc-product-customizer' ); ?></option>
			</select>
			<input type="text" class="wcpc-region-placeholder" value="{{ data.placeholder }}" placeholder="<?php echo esc_attr__( 'Placeholder / default', 'wc-product-customizer' ); ?>" />
			<button type="button" class="button button-small wcpc-remove-region">&times;</button>
		</div>
	</script>
</div>
