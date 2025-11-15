<?php
/**
 * Common Helper Functions
 *
 * Shared utility functions for the Imagify Bulk Restore plugin
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

/**
 * Check if custom folders optimization is available
 *
 * @return bool
 */
function imagify_bulk_restore_can_optimize_custom_folders() {
	return function_exists( 'imagify_can_optimize_custom_folders' )
		&& imagify_can_optimize_custom_folders();
}

/**
 * Sanitize context value
 *
 * @param string $context Context to sanitize.
 * @return string Sanitized context.
 */
function imagify_bulk_restore_sanitize_context( $context ) {
	$valid_contexts = [ 'wp', 'custom-folders' ];

	if ( ! in_array( $context, $valid_contexts, true ) ) {
		return 'wp';
	}

	return $context;
}

/**
 * Get admin page URL
 *
 * @return string Admin page URL.
 */
function imagify_bulk_restore_get_admin_url() {
	if ( is_network_admin() ) {
		return network_admin_url( 'admin.php?page=imagify-bulk-restore' );
	}

	return admin_url( 'upload.php?page=imagify-bulk-restore' );
}

/**
 * Check if a restore operation is currently running
 *
 * @return bool
 */
function imagify_bulk_restore_is_running() {
	$wp_running      = get_transient( 'imagify_bulk_restore_wp_running' );
	$folders_running = get_transient( 'imagify_bulk_restore_custom-folders_running' );

	return ( false !== $wp_running || false !== $folders_running );
}

/**
 * Log a message (debug helper)
 *
 * @param string $message Message to log.
 * @param string $level   Log level (info, error, warning).
 */
function imagify_bulk_restore_log( $message, $level = 'info' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( sprintf( '[Imagify Bulk Restore][%s] %s', strtoupper( $level ), $message ) );
	}
}
