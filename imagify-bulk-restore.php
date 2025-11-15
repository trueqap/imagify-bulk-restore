<?php
/**
 * Plugin Name: Imagify Bulk Restore
 * Plugin URI: https://github.com/trueqap/imagify-bulk-restore
 * Description: Bulk restore all optimized images from Imagify in one click. Compatible with Imagify plugin.
 * Version: 1.0.0
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Author: trueqap
 * Author URI: https://github.com/trueqap/imagify-bulk-restore
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagify-bulk-restore
 * Domain Path: /languages
 * Requires Plugins: imagify/imagify.php
 *
 * Copyright 2025 trueqap
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

// Define plugin constants.
define( 'IMAGIFY_BULK_RESTORE_VERSION', '1.0.0' );
define( 'IMAGIFY_BULK_RESTORE_FILE', __FILE__ );
define( 'IMAGIFY_BULK_RESTORE_PATH', plugin_dir_path( IMAGIFY_BULK_RESTORE_FILE ) );
define( 'IMAGIFY_BULK_RESTORE_URL', plugin_dir_url( IMAGIFY_BULK_RESTORE_FILE ) );

/**
 * Check if Imagify is active
 */
function imagify_bulk_restore_check_dependencies() {
	if ( ! defined( 'IMAGIFY_VERSION' ) ) {
		add_action( 'admin_notices', 'imagify_bulk_restore_missing_imagify_notice' );
		deactivate_plugins( plugin_basename( IMAGIFY_BULK_RESTORE_FILE ) );
		return false;
	}
	return true;
}

/**
 * Admin notice for missing Imagify plugin
 */
function imagify_bulk_restore_missing_imagify_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: plugin name */
				esc_html__( '%s requires the Imagify plugin to be installed and activated.', 'imagify-bulk-restore' ),
				'<strong>Imagify Bulk Restore</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function imagify_bulk_restore_init() {
	if ( ! imagify_bulk_restore_check_dependencies() ) {
		return;
	}

	// Load plugin files.
	require_once IMAGIFY_BULK_RESTORE_PATH . 'inc/functions/common.php';
	require_once IMAGIFY_BULK_RESTORE_PATH . 'inc/classes/class-imagify-bulk-restore.php';
	require_once IMAGIFY_BULK_RESTORE_PATH . 'inc/classes/class-imagify-bulk-restore-views.php';
	require_once IMAGIFY_BULK_RESTORE_PATH . 'inc/classes/class-imagify-bulk-restore-ajax.php';

	// Load WP-CLI commands if available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once IMAGIFY_BULK_RESTORE_PATH . 'inc/classes/class-imagify-bulk-restore-cli.php';
	}

	// Initialize classes.
	Imagify_Bulk_Restore::get_instance()->init();
	Imagify_Bulk_Restore_Views::get_instance()->init();
	Imagify_Bulk_Restore_Ajax::get_instance()->init();
}
add_action( 'plugins_loaded', 'imagify_bulk_restore_init', 20 );

/**
 * Load plugin textdomain
 */
function imagify_bulk_restore_load_textdomain() {
	load_plugin_textdomain(
		'imagify-bulk-restore',
		false,
		dirname( plugin_basename( IMAGIFY_BULK_RESTORE_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'imagify_bulk_restore_load_textdomain' );
