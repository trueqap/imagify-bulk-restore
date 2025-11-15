<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for bulk restore operations
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

/**
 * Imagify_Bulk_Restore_Ajax class.
 */
class Imagify_Bulk_Restore_Ajax {

	/**
	 * Class instance
	 *
	 * @var Imagify_Bulk_Restore_Ajax
	 */
	private static $instance;

	/**
	 * Get the singleton instance
	 *
	 * @return Imagify_Bulk_Restore_Ajax
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {}

	/**
	 * Initialize AJAX hooks
	 */
	public function init() {
		add_action( 'wp_ajax_imagify_bulk_restore_start', [ $this, 'bulk_restore_start_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_process_chunk', [ $this, 'bulk_restore_process_chunk_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_process_batch', [ $this, 'bulk_restore_process_batch_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_get_progress', [ $this, 'bulk_restore_get_progress_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_get_stats', [ $this, 'bulk_restore_get_stats_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_clear_complete', [ $this, 'bulk_restore_clear_complete_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_clear_cache', [ $this, 'bulk_restore_clear_cache_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_get_queue', [ $this, 'bulk_restore_get_queue_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_cancel_queue', [ $this, 'bulk_restore_cancel_queue_callback' ] );
		add_action( 'wp_ajax_imagify_bulk_restore_save_queue', [ $this, 'bulk_restore_save_queue_callback' ] );
	}

	/**
	 * Start bulk restore operation
	 */
	public function bulk_restore_start_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		// Get context from request.
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : '';

		if ( ! in_array( $context, [ 'wp', 'custom-folders' ], true ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid context.', 'imagify-bulk-restore' ),
			] );
		}

		// Check if context is valid for user environment.
		if ( 'custom-folders' === $context && ! imagify_can_optimize_custom_folders() ) {
			wp_send_json_error( [
				'message' => __( 'Custom folders optimization is not available.', 'imagify-bulk-restore' ),
			] );
		}

		// Run the bulk restore.
		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$result       = $bulk_restore->run_bulk_restore( $context );

		if ( ! $result['success'] ) {
			wp_send_json_error( [
				'message' => $result['message'],
			] );
		}

		wp_send_json_success( [
			'message'   => __( 'Bulk restore started successfully.', 'imagify-bulk-restore' ),
			'total'     => $result['total'],
			'media_ids' => $result['media_ids'],
		] );
	}

	/**
	 * Process a chunk of images (AJAX-based processing)
	 */
	public function bulk_restore_process_chunk_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		// Get media ID and context from request.
		$media_id = isset( $_POST['media_id'] ) ? intval( $_POST['media_id'] ) : 0;
		$context  = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : '';

		if ( ! $media_id || ! in_array( $context, [ 'wp', 'custom-folders' ], true ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid parameters.', 'imagify-bulk-restore' ),
			] );
		}

		// Process this single image.
		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$bulk_restore->restore_media( $media_id, $context );

		wp_send_json_success( [
			'media_id' => $media_id,
			'message'  => __( 'Image restored successfully.', 'imagify-bulk-restore' ),
		] );
	}

	/**
	 * Process a batch of images (10 at a time for better performance)
	 */
	public function bulk_restore_process_batch_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		// Get batch from request.
		$batch = isset( $_POST['batch'] ) ? json_decode( wp_unslash( $_POST['batch'] ), true ) : [];

		if ( empty( $batch ) || ! is_array( $batch ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid batch data.', 'imagify-bulk-restore' ),
			] );
		}

		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$processed    = 0;
		$errors       = 0;

		// Process each image in the batch.
		foreach ( $batch as $item ) {
			if ( ! isset( $item['id'] ) || ! isset( $item['context'] ) ) {
				$errors++;
				continue;
			}

			try {
				$bulk_restore->restore_media( $item['id'], $item['context'] );
				$processed++;
			} catch ( Exception $e ) {
				$errors++;
			}
		}

		wp_send_json_success( [
			'processed' => $processed,
			'errors'    => $errors,
			'total'     => count( $batch ),
			'message'   => sprintf(
				/* translators: %d: number of images */
				__( 'Processed %d images in batch.', 'imagify-bulk-restore' ),
				$processed
			),
		] );
	}

	/**
	 * Get current restore progress
	 */
	public function bulk_restore_get_progress_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$progress     = $bulk_restore->get_restore_progress();

		wp_send_json_success( $progress );
	}

	/**
	 * Get statistics for the bulk restore page
	 */
	public function bulk_restore_get_stats_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		$context = isset( $_GET['context'] ) ? sanitize_text_field( wp_unslash( $_GET['context'] ) ) : 'wp';

		if ( ! in_array( $context, [ 'wp', 'custom-folders' ], true ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid context.', 'imagify-bulk-restore' ),
			] );
		}

		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$stats        = $bulk_restore->get_context_stats( $context );

		wp_send_json_success( $stats );
	}

	/**
	 * Clear the complete flag after user acknowledges
	 */
	public function bulk_restore_clear_complete_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		delete_transient( 'imagify_bulk_restore_complete' );
		delete_transient( 'imagify_bulk_restore_result' );

		wp_send_json_success();
	}

	/**
	 * Clear all cache transients
	 */
	public function bulk_restore_clear_cache_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		$bulk_restore = Imagify_Bulk_Restore::get_instance();
		$bulk_restore->delete_transients_data();

		wp_send_json_success( [
			'message' => __( 'Cache cleared successfully.', 'imagify-bulk-restore' ),
		] );
	}

	/**
	 * Get saved restore queue (for resume)
	 */
	public function bulk_restore_get_queue_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		$queue = get_transient( 'imagify_bulk_restore_queue' );

		if ( false === $queue || empty( $queue['items'] ) ) {
			wp_send_json_error( [
				'message' => __( 'No restore queue found.', 'imagify-bulk-restore' ),
			] );
		}

		wp_send_json_success( [
			'queue'     => $queue['items'],
			'total'     => $queue['total'],
			'processed' => $queue['processed'],
			'remaining' => count( $queue['items'] ),
		] );
	}

	/**
	 * Cancel saved restore queue
	 */
	public function bulk_restore_cancel_queue_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		delete_transient( 'imagify_bulk_restore_queue' );

		wp_send_json_success( [
			'message' => __( 'Restore queue cancelled.', 'imagify-bulk-restore' ),
		] );
	}

	/**
	 * Save current restore queue state
	 */
	public function bulk_restore_save_queue_callback() {
		// Verify nonce.
		check_ajax_referer( 'imagify-bulk-restore', 'nonce' );

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', 'imagify-bulk-restore' ),
			] );
		}

		$queue     = isset( $_POST['queue'] ) ? json_decode( wp_unslash( $_POST['queue'] ), true ) : [];
		$total     = isset( $_POST['total'] ) ? intval( $_POST['total'] ) : 0;
		$processed = isset( $_POST['processed'] ) ? intval( $_POST['processed'] ) : 0;

		if ( empty( $queue ) ) {
			delete_transient( 'imagify_bulk_restore_queue' );
			wp_send_json_success( [
				'message' => __( 'Queue is empty, nothing to save.', 'imagify-bulk-restore' ),
			] );
		}

		set_transient(
			'imagify_bulk_restore_queue',
			[
				'items'     => $queue,
				'total'     => $total,
				'processed' => $processed,
				'timestamp' => time(),
			],
			DAY_IN_SECONDS
		);

		wp_send_json_success( [
			'message'   => __( 'Queue saved successfully.', 'imagify-bulk-restore' ),
			'remaining' => count( $queue ),
		] );
	}
}
