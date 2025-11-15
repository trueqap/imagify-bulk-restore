<?php
/**
 * Views Class
 *
 * Handles admin page rendering and registration
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );

/**
 * Imagify_Bulk_Restore_Views class.
 */
class Imagify_Bulk_Restore_Views {

	/**
	 * Class instance
	 *
	 * @var Imagify_Bulk_Restore_Views
	 */
	private static $instance;

	/**
	 * Get the singleton instance
	 *
	 * @return Imagify_Bulk_Restore_Views
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
	 * Initialize hooks
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'network_admin_menu', [ $this, 'add_network_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add admin menu for single site
	 */
	public function add_admin_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_media_page(
			__( 'Imagify Bulk Restore', 'imagify-bulk-restore' ),
			__( 'Bulk Restore', 'imagify-bulk-restore' ),
			'manage_options',
			'imagify-bulk-restore',
			[ $this, 'display_bulk_restore_page' ]
		);
	}

	/**
	 * Add network admin menu
	 */
	public function add_network_admin_menu() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		add_menu_page(
			__( 'Imagify Bulk Restore', 'imagify-bulk-restore' ),
			__( 'Bulk Restore', 'imagify-bulk-restore' ),
			'manage_network_options',
			'imagify-bulk-restore',
			[ $this, 'display_bulk_restore_page' ],
			'dashicons-image-rotate'
		);
	}

	/**
	 * Enqueue CSS and JavaScript assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on our admin page.
		if ( false === strpos( $hook, 'imagify-bulk-restore' ) ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'imagify-bulk-restore',
			IMAGIFY_BULK_RESTORE_URL . 'assets/css/bulk-restore.css',
			[],
			IMAGIFY_BULK_RESTORE_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'imagify-bulk-restore',
			IMAGIFY_BULK_RESTORE_URL . 'assets/js/bulk-restore.js',
			[ 'jquery' ],
			IMAGIFY_BULK_RESTORE_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'imagify-bulk-restore',
			'imagifyBulkRestore',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'imagify-bulk-restore' ),
				'labels'  => [
					'starting'            => __( 'Starting restore...', 'imagify-bulk-restore' ),
					'processing'          => __( 'Processing...', 'imagify-bulk-restore' ),
					'complete'            => __( 'Restore Complete!', 'imagify-bulk-restore' ),
					'error'               => __( 'An error occurred.', 'imagify-bulk-restore' ),
					'confirmRestore'      => __( 'Are you sure you want to restore all optimized images? This cannot be undone.', 'imagify-bulk-restore' ),
					'restoredImages'      => __( 'images restored', 'imagify-bulk-restore' ),
					'noImagesToRestore'   => __( 'No images to restore.', 'imagify-bulk-restore' ),
					'restoreInProgress'   => __( 'A restore operation is currently in progress.', 'imagify-bulk-restore' ),
				],
			]
		);
	}

	/**
	 * Display the bulk restore page
	 */
	public function display_bulk_restore_page() {
		$bulk_restore = Imagify_Bulk_Restore::get_instance();

		// Check for pending queue.
		$this->maybe_display_resume_notice();

		// Get statistics for both contexts.
		$wp_stats     = $bulk_restore->get_context_stats( 'wp' );
		$folder_stats = imagify_can_optimize_custom_folders()
			? $bulk_restore->get_context_stats( 'custom-folders' )
			: [
				'total_media'    => 0,
				'original_size'  => 0,
				'optimized_size' => 0,
				'saved_size'     => 0,
				'percent_saved'  => 0,
			];

		// Combined stats.
		$total_media    = $wp_stats['total_media'] + $folder_stats['total_media'];
		$original_size  = $wp_stats['original_size'] + $folder_stats['original_size'];
		$optimized_size = $wp_stats['optimized_size'] + $folder_stats['optimized_size'];
		$saved_size     = $original_size - $optimized_size;
		$percent_saved  = $original_size > 0 ? round( ( $saved_size / $original_size ) * 100, 2 ) : 0;

		// Get current progress if restore is running.
		$progress = $bulk_restore->get_restore_progress();

		// Prepare data for template.
		$data = [
			'wp_stats'              => $wp_stats,
			'folder_stats'          => $folder_stats,
			'total_media'           => $total_media,
			'original_size'         => $original_size,
			'optimized_size'        => $optimized_size,
			'saved_size'            => $saved_size,
			'percent_saved'         => $percent_saved,
			'original_human'        => imagify_size_format( $original_size ),
			'optimized_human'       => imagify_size_format( $optimized_size ),
			'saved_human'           => imagify_size_format( $saved_size ),
			'progress'              => $progress,
			'can_custom_folders'    => imagify_can_optimize_custom_folders(),
			'has_custom_folders'    => $folder_stats['total_media'] > 0,
		];

		// Load template.
		$this->load_template( 'page-bulk-restore', $data );
	}

	/**
	 * Load a template file
	 *
	 * @param string $template Template name (without .php extension).
	 * @param array  $data     Data to pass to the template.
	 */
	private function load_template( $template, $data = [] ) {
		$template_path = IMAGIFY_BULK_RESTORE_PATH . 'views/' . $template . '.php';

		if ( ! file_exists( $template_path ) ) {
			return;
		}

		// Extract data array to variables.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		include $template_path;
	}

	/**
	 * Get template HTML
	 *
	 * @param string $template Template name.
	 * @param array  $data     Data to pass to template.
	 * @return string Template HTML.
	 */
	public function get_template( $template, $data = [] ) {
		ob_start();
		$this->load_template( $template, $data );
		return ob_get_clean();
	}

	/**
	 * Display resume notice if there's a pending queue
	 */
	private function maybe_display_resume_notice() {
		$queue = get_transient( 'imagify_bulk_restore_queue' );

		if ( false === $queue || empty( $queue['items'] ) ) {
			return;
		}

		$remaining = count( $queue['items'] );
		$total     = isset( $queue['total'] ) ? $queue['total'] : $remaining;
		$processed = isset( $queue['processed'] ) ? $queue['processed'] : 0;
		$percent   = $total > 0 ? round( ( $processed / $total ) * 100, 1 ) : 0;
		?>
		<div id="imagify-resume-notice" class="notice notice-warning is-dismissible imagify-resume-notice" style="display: flex; align-items: center; gap: 15px; padding: 12px;">
			<span class="dashicons dashicons-warning" style="color: #f0b849; font-size: 24px; width: 24px; height: 24px; flex-shrink: 0;"></span>
			<div style="flex: 1;">
				<p style="margin: 0; font-weight: 600;">
					<?php
					printf(
						/* translators: 1: processed count, 2: total count, 3: percentage */
						esc_html__( 'Incomplete restore operation: %1$d of %2$d images restored (%3$s%%).', 'imagify-bulk-restore' ),
						$processed,
						$total,
						$percent
					);
					?>
				</p>
				<p style="margin: 5px 0 0 0; font-size: 13px; color: #646970;">
					<?php
					printf(
						/* translators: %d: remaining image count */
						esc_html( _n( '%d image remaining', '%d images remaining', $remaining, 'imagify-bulk-restore' ) ),
						$remaining
					);
					?>
				</p>
			</div>
			<div style="display: flex; gap: 10px; flex-shrink: 0;">
				<button type="button" class="button button-primary imagify-resume-restore-btn">
					<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Continue Restore', 'imagify-bulk-restore' ); ?>
				</button>
				<button type="button" class="button imagify-cancel-restore-btn">
					<span class="dashicons dashicons-no" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Cancel', 'imagify-bulk-restore' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
