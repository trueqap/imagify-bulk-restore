<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database.
 *
 * @package Imagify_Bulk_Restore
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin transients
 */
function imagify_bulk_restore_delete_transients() {
	global $wpdb;

	// Delete all transients related to this plugin.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_imagify_bulk_restore_%'
		OR option_name LIKE '_transient_timeout_imagify_bulk_restore_%'"
	);

	// For multisite installations.
	if ( is_multisite() ) {
		$wpdb->query(
			"DELETE FROM {$wpdb->sitemeta}
			WHERE meta_key LIKE '_site_transient_imagify_bulk_restore_%'
			OR meta_key LIKE '_site_transient_timeout_imagify_bulk_restore_%'"
		);
	}
}

/**
 * Delete all Action Scheduler tasks
 */
function imagify_bulk_restore_delete_scheduled_actions() {
	global $wpdb;

	// Check if Action Scheduler tables exist.
	$table_name = $wpdb->prefix . 'actionscheduler_actions';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		// Delete all scheduled actions for this plugin.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE hook = %s",
				'imagify_bulk_restore_single'
			)
		);
	}
}

/**
 * Delete all plugin options
 */
function imagify_bulk_restore_delete_options() {
	global $wpdb;

	// Delete any plugin options (currently we use only transients, but future-proof).
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE 'imagify_bulk_restore_%'"
	);

	// For multisite installations.
	if ( is_multisite() ) {
		$wpdb->query(
			"DELETE FROM {$wpdb->sitemeta}
			WHERE meta_key LIKE 'imagify_bulk_restore_%'"
		);
	}
}

// Run cleanup functions.
imagify_bulk_restore_delete_transients();
imagify_bulk_restore_delete_scheduled_actions();
imagify_bulk_restore_delete_options();

// Clear any object cache.
wp_cache_flush();
