<?php
/**
 * Main Bulk Restore Class
 *
 * Handles the core bulk restore functionality
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

/**
 * Imagify_Bulk_Restore class.
 */
class Imagify_Bulk_Restore {

	/**
	 * Class instance
	 *
	 * @var Imagify_Bulk_Restore
	 */
	private static $instance;

	/**
	 * Get the singleton instance
	 *
	 * @return Imagify_Bulk_Restore
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {}

	/**
	 * Initialize hooks
	 */
	public function init() {
		add_action( 'imagify_bulk_restore_single', [ $this, 'restore_media_callback' ], 10, 1 );
		add_action( 'imagify_after_restore_media', [ $this, 'check_restore_status' ], 10, 2 );
		add_action( 'imagify_deactivation', [ $this, 'delete_transients_data' ] );
	}

	/**
	 * Delete transients on deactivation
	 */
	public function delete_transients_data() {
		delete_transient( 'imagify_bulk_restore_wp_running' );
		delete_transient( 'imagify_bulk_restore_custom-folders_running' );
		delete_transient( 'imagify_bulk_restore_complete' );
		delete_transient( 'imagify_bulk_restore_result' );
		delete_transient( 'imagify_bulk_restore_ids_wp' );
		delete_transient( 'imagify_bulk_restore_ids_custom-folders' );
		delete_transient( 'imagify_bulk_restore_stats_wp' );
		delete_transient( 'imagify_bulk_restore_stats_custom-folders' );
	}

	/**
	 * Get all optimized media IDs for a specific context
	 *
	 * @param string $context Context name ('wp' or 'custom-folders').
	 * @param bool   $for_restore Whether this is for actual restore (true) or stats only (false).
	 * @return array Array of media IDs.
	 */
	public function get_optimized_media_ids( $context, $for_restore = false ) {
		// Only use cache for stats, not for actual restore.
		$cache_key = "imagify_bulk_restore_ids_{$context}";

		if ( ! $for_restore ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$media_ids = [];

		switch ( $context ) {
			case 'wp':
				global $wpdb;

				// Get all attachment IDs that have been optimized.
				// For stats: limit to 1000 for performance
				// For restore: get ALL images
				$limit_clause = $for_restore ? '' : 'LIMIT 1000';

				$query = $wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
					WHERE meta_key = %s
					AND meta_value = %s
					{$limit_clause}",
					'_imagify_status',
					'success'
				);

				$results = $wpdb->get_col( $query );

				if ( $results ) {
					// Only check backup for subset to speed up loading.
					$sample_size = min( 50, count( $results ) );
					$has_backups = false;

					for ( $i = 0; $i < $sample_size; $i++ ) {
						$process = imagify_get_optimization_process( $results[ $i ], 'wp' );
						$media   = $process->get_media();

						if ( $media && $media->has_backup() ) {
							$has_backups = true;
							break;
						}
					}

					// If sample has backups, assume all have backups (much faster).
					if ( $has_backups ) {
						$media_ids = array_map( 'intval', $results );
					}
				}
				break;

			case 'custom-folders':
				if ( ! class_exists( 'Imagify_Files_DB' ) ) {
					break;
				}

				global $wpdb;

				$files_db    = Imagify_Files_DB::get_instance();
				$files_table = $files_db->get_table_name();

				// Get all optimized files with success status.
				// For stats: limit to 1000 for performance
				// For restore: get ALL files
				$limit_clause = $for_restore ? '' : 'LIMIT 1000';

				$query = "SELECT file_id FROM {$files_table}
						  WHERE status = 'success' OR status = 'already_optimized'
						  ORDER BY file_id DESC
						  {$limit_clause}";

				$file_ids = $wpdb->get_col( $query );

				if ( $file_ids ) {
					// Only check backup for subset to speed up loading.
					$sample_size = min( 50, count( $file_ids ) );
					$has_backups = false;

					for ( $i = 0; $i < $sample_size; $i++ ) {
						$process = imagify_get_optimization_process( $file_ids[ $i ], 'custom-folders' );
						$media   = $process->get_media();

						if ( $media && $media->has_backup() ) {
							$has_backups = true;
							break;
						}
					}

					// If sample has backups, assume all have backups (much faster).
					if ( $has_backups ) {
						$media_ids = array_map( 'intval', $file_ids );
					}
				}
				break;
		}

		// Cache for 5 minutes (only for stats, not for actual restore).
		if ( ! $for_restore ) {
			set_transient( $cache_key, $media_ids, 5 * MINUTE_IN_SECONDS );
		}

		return $media_ids;
	}

	/**
	 * Get statistics for optimized media
	 *
	 * @param string $context Context name ('wp' or 'custom-folders').
	 * @return array Statistics data.
	 */
	public function get_context_stats( $context ) {
		// Check transient cache first.
		$cache_key = "imagify_bulk_restore_stats_{$context}";
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$stats = [
			'total_media'    => 0,
			'original_size'  => 0,
			'optimized_size' => 0,
			'saved_size'     => 0,
			'percent_saved'  => 0,
		];

		// Use faster direct queries instead of loading each media.
		switch ( $context ) {
			case 'wp':
				global $wpdb;

				// Get count and sizes from postmeta directly.
				$count_query = $wpdb->prepare(
					"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
					WHERE meta_key = %s AND meta_value = %s",
					'_imagify_status',
					'success'
				);

				$stats['total_media'] = (int) $wpdb->get_var( $count_query );

				// Get aggregate sizes from imagify data.
				$sizes_query = $wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
					WHERE pm.meta_key = %s
					AND pm2.meta_key = %s AND pm2.meta_value = %s
					LIMIT 100",
					'_imagify_data',
					'_imagify_status',
					'success'
				);

				$results = $wpdb->get_col( $sizes_query );

				if ( $results ) {
					foreach ( $results as $data_serialized ) {
						$data = maybe_unserialize( $data_serialized );
						if ( isset( $data['stats']['original_size'] ) ) {
							$stats['original_size'] += (int) $data['stats']['original_size'];
						}
						if ( isset( $data['stats']['optimized_size'] ) ) {
							$stats['optimized_size'] += (int) $data['stats']['optimized_size'];
						}
					}

					// Extrapolate from sample.
					if ( $stats['total_media'] > 100 ) {
						$ratio                      = $stats['total_media'] / 100;
						$stats['original_size']     = (int) ( $stats['original_size'] * $ratio );
						$stats['optimized_size']    = (int) ( $stats['optimized_size'] * $ratio );
					}
				}
				break;

			case 'custom-folders':
				if ( ! class_exists( 'Imagify_Files_DB' ) ) {
					break;
				}

				global $wpdb;
				$files_db    = Imagify_Files_DB::get_instance();
				$files_table = $files_db->get_table_name();

				// Direct aggregate query.
				$query = "SELECT
							COUNT(*) as total,
							SUM(original_size) as original_size,
							SUM(optimized_size) as optimized_size
						  FROM {$files_table}
						  WHERE (status = 'success' OR status = 'already_optimized')
						  AND optimized_size IS NOT NULL";

				$result = $wpdb->get_row( $query );

				if ( $result ) {
					$stats['total_media']    = (int) $result->total;
					$stats['original_size']  = (int) $result->original_size;
					$stats['optimized_size'] = (int) $result->optimized_size;
				}
				break;
		}

		$stats['saved_size']    = $stats['original_size'] - $stats['optimized_size'];
		$stats['percent_saved'] = $stats['original_size'] > 0
			? round( ( $stats['saved_size'] / $stats['original_size'] ) * 100, 2 )
			: 0;

		// Cache for 5 minutes.
		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Run bulk restore for a context
	 *
	 * @param string $context Context name ('wp' or 'custom-folders').
	 * @return array Response data.
	 */
	public function run_bulk_restore( $context ) {
		// Get ALL media IDs for restore (no limit)
		$media_ids = $this->get_optimized_media_ids( $context, true );

		if ( empty( $media_ids ) ) {
			return [
				'success'   => false,
				'message'   => __( 'No images to restore.', 'imagify-bulk-restore' ),
				'media_ids' => [],
			];
		}

		// Store the running state.
		$data = [
			'total'     => count( $media_ids ),
			'remaining' => count( $media_ids ),
		];

		set_transient( "imagify_bulk_restore_{$context}_running", $data, DAY_IN_SECONDS );

		// Return media IDs for AJAX processing.
		return [
			'success'   => true,
			'message'   => 'success',
			'total'     => count( $media_ids ),
			'media_ids' => $media_ids,
		];
	}

	/**
	 * Action Scheduler callback wrapper
	 *
	 * @param array $args Arguments array with 'media_id' and 'context'.
	 */
	public function restore_media_callback( $args ) {
		if ( ! isset( $args['media_id'] ) || ! isset( $args['context'] ) ) {
			return;
		}

		$this->restore_media( $args['media_id'], $args['context'] );
	}

	/**
	 * Restore a single media
	 *
	 * @param int    $media_id Media ID.
	 * @param string $context  Context name.
	 */
	public function restore_media( $media_id, $context ) {
		if ( ! $media_id || ! $context ) {
			$this->decrease_counter( $context );
			return;
		}

		$process = imagify_get_optimization_process( $media_id, $context );

		if ( ! $process ) {
			$this->decrease_counter( $context );
			return;
		}

		$result = $process->restore();

		$this->decrease_counter( $context );

		if ( is_wp_error( $result ) ) {
			error_log( 'Imagify Bulk Restore Error: ' . $result->get_error_message() );
		}
	}

	/**
	 * Check restore status after each media restore
	 *
	 * @param object $process The optimization process.
	 * @param mixed  $response The restore response.
	 */
	public function check_restore_status( $process, $response ) {
		$wp_running      = get_transient( 'imagify_bulk_restore_wp_running' );
		$folders_running = get_transient( 'imagify_bulk_restore_custom-folders_running' );

		if ( ! $wp_running && ! $folders_running ) {
			return;
		}

		$media   = $process->get_media();
		$context = '';
		if ( $media && method_exists( $media, 'get_context' ) ) {
			$context = $media->get_context();
		}
		$progress = get_transient( 'imagify_bulk_restore_result' );

		if ( false === $progress ) {
			$progress = [
				'total'          => 0,
				'original_size'  => 0,
				'optimized_size' => 0,
			];
		}

		++$progress['total'];
		set_transient( 'imagify_bulk_restore_result', $progress, DAY_IN_SECONDS );

		$this->decrease_counter( $context );
	}

	/**
	 * Decrease the running counter for a context
	 *
	 * @param string $context Context name.
	 */
	private function decrease_counter( $context ) {
		$counter = get_transient( "imagify_bulk_restore_{$context}_running" );

		if ( false === $counter ) {
			return;
		}

		$counter['remaining'] = max( 0, $counter['remaining'] - 1 );

		if ( 0 >= $counter['remaining'] ) {
			delete_transient( "imagify_bulk_restore_{$context}_running" );

			// Check if all contexts are complete.
			$wp_running      = get_transient( 'imagify_bulk_restore_wp_running' );
			$folders_running = get_transient( 'imagify_bulk_restore_custom-folders_running' );

			if ( ! $wp_running && ! $folders_running ) {
				set_transient( 'imagify_bulk_restore_complete', 1, DAY_IN_SECONDS );
			}
		} else {
			set_transient( "imagify_bulk_restore_{$context}_running", $counter, DAY_IN_SECONDS );
		}
	}

	/**
	 * Get current restore progress
	 *
	 * @return array Progress data.
	 */
	public function get_restore_progress() {
		$wp_running      = get_transient( 'imagify_bulk_restore_wp_running' );
		$folders_running = get_transient( 'imagify_bulk_restore_custom-folders_running' );
		$is_complete     = get_transient( 'imagify_bulk_restore_complete' );

		$total_items     = 0;
		$remaining_items = 0;

		if ( $wp_running ) {
			$total_items     += $wp_running['total'];
			$remaining_items += $wp_running['remaining'];
		}

		if ( $folders_running ) {
			$total_items     += $folders_running['total'];
			$remaining_items += $folders_running['remaining'];
		}

		$processed = $total_items - $remaining_items;
		$percent   = $total_items > 0 ? round( ( $processed / $total_items ) * 100, 2 ) : 0;

		return [
			'is_running'  => ( $wp_running || $folders_running ) && ! $is_complete,
			'is_complete' => (bool) $is_complete,
			'total'       => $total_items,
			'remaining'   => $remaining_items,
			'processed'   => $processed,
			'percent'     => $percent,
		];
	}
}
