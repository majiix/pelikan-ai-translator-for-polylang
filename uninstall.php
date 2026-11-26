<?php
/**
 * Pelikan Translator Uninstall
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'polai_provider' );
delete_option( 'polai_provider_configs' );
delete_option( 'polai_api_key' );
delete_option( 'polai_model' );
