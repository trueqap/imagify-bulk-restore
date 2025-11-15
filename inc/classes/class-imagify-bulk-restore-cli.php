<?php
/**
 * WP-CLI Command Class
 *
 * Provides WP-CLI commands for bulk restore operations
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

/**
 * Imagify Bulk Restore WP-CLI Commands
 */
class Imagify_Bulk_Restore_CLI {

	/**
	 * Restore all optimized images from Media Library
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * [--dry-run]
	 * : Show what would be restored without actually restoring
	 *
	 * ## EXAMPLES
	 *
	 *     # Restore all Media Library images
	 *     wp imagify-restore media
	 *
	 *     # Restore without confirmation
	 *     wp imagify-restore media --yes
	 *
	 *     # Preview what would be restored
	 *     wp imagify-restore media --dry-run
	 *
	 * @when after_wp_load
	 */
	public function media( $args, $assoc_args ) {
		$this->restore_context( 'wp', $assoc_args );
	}

	/**
	 * Restore all optimized files from Custom Folders
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * [--dry-run]
	 * : Show what would be restored without actually restoring
	 *
	 * ## EXAMPLES
	 *
	 *     # Restore all Custom Folders files
	 *     wp imagify-restore folders
	 *
	 *     # Restore without confirmation
	 *     wp imagify-restore folders --yes
	 *
	 *     # Preview what would be restored
	 *     wp imagify-restore folders --dry-run
	 *
	 * @when after_wp_load
	 */
	public function folders( $args, $assoc_args ) {
		// Check if custom folders is available.
		if ( ! imagify_can_optimize_custom_folders() ) {
			WP_CLI::error( 'Custom folders optimization is not available.' );
		}

		$this->restore_context( 'custom-folders', $assoc_args );
	}

	/**
	 * Restore all optimized images from both Media Library and Custom Folders
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * [--dry-run]
	 * : Show what would be restored without actually restoring
	 *
	 * ## EXAMPLES
	 *
	 *     # Restore everything
	 *     wp imagify-restore all
	 *
	 *     # Restore without confirmation
	 *     wp imagify-restore all --yes
	 *
	 *     # Preview what would be restored
	 *     wp imagify-restore all --dry-run
	 *
	 * @when after_wp_load
	 */
	public function all( $args, $assoc_args ) {
		$contexts = [ 'wp' ];

		if ( imagify_can_optimize_custom_folders() ) {
			$contexts[] = 'custom-folders';
		}

		foreach ( $contexts as $context ) {
			$this->restore_context( $context, $assoc_args );
		}
	}

	/**
	 * Show statistics about restorable images
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show statistics
	 *     wp imagify-restore stats
	 *
	 *     # Output as JSON
	 *     wp imagify-restore stats --format=json
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc_args ) {
		$bulk_restore = Imagify_Bulk_Restore::get_instance();

		WP_CLI::log( 'Gathering statistics...' );

		$wp_stats     = $bulk_restore->get_context_stats( 'wp' );
		$folder_stats = [ 'total_media' => 0, 'original_size' => 0, 'optimized_size' => 0, 'saved_size' => 0 ];

		if ( imagify_can_optimize_custom_folders() ) {
			$folder_stats = $bulk_restore->get_context_stats( 'custom-folders' );
		}

		$total_media     = $wp_stats['total_media'] + $folder_stats['total_media'];
		$total_original  = $wp_stats['original_size'] + $folder_stats['original_size'];
		$total_optimized = $wp_stats['optimized_size'] + $folder_stats['optimized_size'];
		$total_saved     = $wp_stats['saved_size'] + $folder_stats['saved_size'];
		$percent_saved   = $total_original > 0 ? ( $total_saved / $total_original ) * 100 : 0;

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			WP_CLI::line(
				wp_json_encode(
					[
						'media_library'  => [
							'count'          => $wp_stats['total_media'],
							'original_size'  => $wp_stats['original_size'],
							'optimized_size' => $wp_stats['optimized_size'],
							'saved_size'     => $wp_stats['saved_size'],
						],
						'custom_folders' => [
							'count'          => $folder_stats['total_media'],
							'original_size'  => $folder_stats['original_size'],
							'optimized_size' => $folder_stats['optimized_size'],
							'saved_size'     => $folder_stats['saved_size'],
						],
						'total'          => [
							'count'          => $total_media,
							'original_size'  => $total_original,
							'optimized_size' => $total_optimized,
							'saved_size'     => $total_saved,
							'percent_saved'  => round( $percent_saved, 2 ),
						],
					],
					JSON_PRETTY_PRINT
				)
			);
			return;
		}

		$items = [
			[
				'Context'        => 'Media Library',
				'Images'         => number_format_i18n( $wp_stats['total_media'] ),
				'Original Size'  => imagify_size_format( $wp_stats['original_size'] ),
				'Optimized Size' => imagify_size_format( $wp_stats['optimized_size'] ),
				'Saved'          => imagify_size_format( $wp_stats['saved_size'] ),
			],
		];

		if ( imagify_can_optimize_custom_folders() ) {
			$items[] = [
				'Context'        => 'Custom Folders',
				'Images'         => number_format_i18n( $folder_stats['total_media'] ),
				'Original Size'  => imagify_size_format( $folder_stats['original_size'] ),
				'Optimized Size' => imagify_size_format( $folder_stats['optimized_size'] ),
				'Saved'          => imagify_size_format( $folder_stats['saved_size'] ),
			];
		}

		$items[] = [
			'Context'        => 'TOTAL',
			'Images'         => number_format_i18n( $total_media ),
			'Original Size'  => imagify_size_format( $total_original ),
			'Optimized Size' => imagify_size_format( $total_optimized ),
			'Saved'          => imagify_size_format( $total_saved ) . ' (' . number_format_i18n( $percent_saved, 2 ) . '%)',
		];

		WP_CLI\Utils\format_items( $format, $items, [ 'Context', 'Images', 'Original Size', 'Optimized Size', 'Saved' ] );
	}

	/**
	 * Clear the restore queue
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear pending queue
	 *     wp imagify-restore clear-queue
	 *
	 * @when after_wp_load
	 */
	public function clear_queue( $args, $assoc_args ) {
		delete_transient( 'imagify_bulk_restore_queue' );
		WP_CLI::success( 'Restore queue cleared.' );
	}

	/**
	 * Clear all cache transients
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear cache
	 *     wp imagify-restore clear-cache
	 *
	 * @when after_wp_load
	 */
	public function clear_cache( $args, $assoc_args ) {
		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$bulk_restore->delete_transients_data();
		WP_CLI::success( 'Cache cleared successfully.' );
	}

	/**
	 * Restore images for a specific context
	 *
	 * @param string $context Context to restore (wp or custom-folders).
	 * @param array  $assoc_args Associative arguments.
	 */
	private function restore_context( $context, $assoc_args ) {
		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$dry_run      = isset( $assoc_args['dry-run'] );
		$skip_confirm = isset( $assoc_args['yes'] );

		// Get stats.
		$stats = $bulk_restore->get_context_stats( $context );

		if ( 0 === $stats['total_media'] ) {
			WP_CLI::warning( 'No optimized images found with available backups.' );
			return;
		}

		$context_name = 'wp' === $context ? 'Media Library' : 'Custom Folders';

		WP_CLI::log( sprintf( '%s Statistics:', $context_name ) );
		WP_CLI::log( sprintf( '  Images: %d', $stats['total_media'] ) );
		WP_CLI::log( sprintf( '  Original Size: %s', imagify_size_format( $stats['original_size'] ) ) );
		WP_CLI::log( sprintf( '  Optimized Size: %s', imagify_size_format( $stats['optimized_size'] ) ) );
		WP_CLI::log( sprintf( '  Saved: %s', imagify_size_format( $stats['saved_size'] ) ) );
		WP_CLI::log( '' );

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run: Would restore %d images from %s', $stats['total_media'], $context_name ) );
			return;
		}

		// Confirmation.
		if ( ! $skip_confirm ) {
			WP_CLI::confirm(
				sprintf(
					'Are you sure you want to restore %d images from %s? This action cannot be undone.',
					$stats['total_media'],
					$context_name
				)
			);
		}

		// Start restore.
		$result = $bulk_restore->run_bulk_restore( $context );

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['message'] );
		}

		$media_ids = $result['media_ids'];
		$total     = count( $media_ids );

		WP_CLI::log( sprintf( 'Starting restore of %d images...', $total ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Restoring images', $total );

		foreach ( $media_ids as $media_id ) {
			$bulk_restore->restore_media( $media_id, $context );
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( 'Successfully restored %d images from %s!', $total, $context_name ) );
	}
}

// Register WP-CLI command if available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'imagify-restore', 'Imagify_Bulk_Restore_CLI' );
}
