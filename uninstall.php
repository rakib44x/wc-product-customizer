<?php
/**
 * Uninstall WC Product Customizer.
 *
 * @package WC_Product_Customizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options (keep design files by default to avoid data loss on accidental uninstall).
delete_option( 'wcpc_settings' );
