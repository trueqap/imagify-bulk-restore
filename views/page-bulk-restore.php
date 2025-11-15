<?php
/**
 * Bulk Restore Admin Page Template
 *
 * @var array $data Template data containing statistics and progress information
 */

defined( 'ABSPATH' ) || die( 'Direct access not allowed.' );
?>

<div class="wrap imagify-settings imagify-bulk-restore-page">
	<h1 class="imagify-bulk-restore-title">
		<span class="dashicons dashicons-image-rotate"></span>
		<?php esc_html_e( 'Imagify Bulk Restore', 'imagify-bulk-restore' ); ?>
	</h1>

	<p class="imagify-bulk-restore-description">
		<?php esc_html_e( 'Restore all your optimized images back to their original state. This will remove all Imagify optimizations and restore the original files from backup.', 'imagify-bulk-restore' ); ?>
	</p>

	<?php if ( $progress['is_running'] ) : ?>
		<div class="notice notice-info imagify-bulk-restore-notice">
			<p>
				<strong><?php esc_html_e( 'Restore in Progress', 'imagify-bulk-restore' ); ?></strong><br>
				<?php esc_html_e( 'A bulk restore operation is currently running. Please wait for it to complete.', 'imagify-bulk-restore' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $progress['is_complete'] ) : ?>
		<div class="notice notice-success imagify-bulk-restore-notice is-dismissible" id="imagify-restore-complete-notice">
			<p>
				<strong><?php esc_html_e( 'Restore Complete!', 'imagify-bulk-restore' ); ?></strong><br>
				<?php
				printf(
					/* translators: %d: number of images */
					esc_html__( '%d images have been successfully restored to their original state.', 'imagify-bulk-restore' ),
					esc_html( $progress['processed'] )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="imagify-settings-section">
		<div class="imagify-columns">
			<!-- Statistics Overview -->
			<div class="imagify-col col-overview">
				<h2 class="imagify-h2-like">
					<span class="dashicons dashicons-chart-bar"></span>
					<?php esc_html_e( 'Overview', 'imagify-bulk-restore' ); ?>
				</h2>

				<div class="imagify-stat-box">
					<div class="imagify-stat-item">
						<div class="imagify-stat-label">
							<?php esc_html_e( 'Optimized Images with Backup', 'imagify-bulk-restore' ); ?>
						</div>
						<div class="imagify-stat-value" id="imagify-total-restorable">
							<?php echo esc_html( number_format_i18n( $total_media ) ); ?>
						</div>
					</div>

					<div class="imagify-stat-item">
						<div class="imagify-stat-label">
							<?php esc_html_e( 'Original Size', 'imagify-bulk-restore' ); ?>
						</div>
						<div class="imagify-stat-value">
							<?php echo esc_html( $original_human ); ?>
						</div>
					</div>

					<div class="imagify-stat-item">
						<div class="imagify-stat-label">
							<?php esc_html_e( 'Current Optimized Size', 'imagify-bulk-restore' ); ?>
						</div>
						<div class="imagify-stat-value">
							<?php echo esc_html( $optimized_human ); ?>
						</div>
					</div>

					<div class="imagify-stat-item imagify-stat-highlight">
						<div class="imagify-stat-label">
							<?php esc_html_e( 'Space Saved by Optimization', 'imagify-bulk-restore' ); ?>
						</div>
						<div class="imagify-stat-value">
							<?php echo esc_html( $saved_human ); ?>
							<span class="imagify-stat-percent">(<?php echo esc_html( number_format_i18n( $percent_saved, 2 ) ); ?>%)</span>
						</div>
					</div>
				</div>

				<?php if ( $wp_stats['total_media'] > 0 ) : ?>
					<div class="imagify-context-stats">
						<h3><?php esc_html_e( 'Media Library', 'imagify-bulk-restore' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: 1: number of images, 2: file size */
								esc_html__( '%1$d images • %2$s saved', 'imagify-bulk-restore' ),
								esc_html( number_format_i18n( $wp_stats['total_media'] ) ),
								esc_html( imagify_size_format( $wp_stats['saved_size'] ) )
							);
							?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( $can_custom_folders && $has_custom_folders ) : ?>
					<div class="imagify-context-stats">
						<h3><?php esc_html_e( 'Custom Folders', 'imagify-bulk-restore' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: 1: number of images, 2: file size */
								esc_html__( '%1$d files • %2$s saved', 'imagify-bulk-restore' ),
								esc_html( number_format_i18n( $folder_stats['total_media'] ) ),
								esc_html( imagify_size_format( $folder_stats['saved_size'] ) )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Restore Actions -->
			<div class="imagify-col col-actions">
				<h2 class="imagify-h2-like">
					<span class="dashicons dashicons-controls-play"></span>
					<?php esc_html_e( 'Restore Actions', 'imagify-bulk-restore' ); ?>
				</h2>

				<?php if ( $total_media > 0 ) : ?>
					<div class="imagify-restore-actions">
						<?php if ( $wp_stats['total_media'] > 0 ) : ?>
							<div class="imagify-restore-action-item">
								<?php wp_nonce_field( 'imagify-bulk-restore', 'imagify-bulk-restore-nonce' ); ?>
								<button
									type="button"
									class="button button-primary button-hero imagify-bulk-restore-btn"
									data-context="wp"
									<?php echo $progress['is_running'] ? 'disabled="disabled"' : ''; ?>
								>
									<span class="dashicons dashicons-image-rotate"></span>
									<span class="button-text">
										<?php
										printf(
											/* translators: %d: number of images */
											esc_html__( 'Restore Media Library (%d images)', 'imagify-bulk-restore' ),
											esc_html( $wp_stats['total_media'] )
										);
										?>
									</span>
								</button>
								<p class="description">
									<?php esc_html_e( 'Restore all optimized images in your WordPress Media Library.', 'imagify-bulk-restore' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<?php if ( $can_custom_folders && $has_custom_folders ) : ?>
							<div class="imagify-restore-action-item">
								<button
									type="button"
									class="button button-primary button-hero imagify-bulk-restore-btn"
									data-context="custom-folders"
									<?php echo $progress['is_running'] ? 'disabled="disabled"' : ''; ?>
								>
									<span class="dashicons dashicons-portfolio"></span>
									<span class="button-text">
										<?php
										printf(
											/* translators: %d: number of images */
											esc_html__( 'Restore Custom Folders (%d files)', 'imagify-bulk-restore' ),
											esc_html( $folder_stats['total_media'] )
										);
										?>
									</span>
								</button>
								<p class="description">
									<?php esc_html_e( 'Restore all optimized files in your custom folders.', 'imagify-bulk-restore' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<?php if ( $wp_stats['total_media'] > 0 && $has_custom_folders ) : ?>
							<div class="imagify-restore-action-item">
								<button
									type="button"
									class="button button-primary button-hero imagify-bulk-restore-btn imagify-restore-all-btn"
									data-context="all"
									<?php echo $progress['is_running'] ? 'disabled="disabled"' : ''; ?>
								>
									<span class="dashicons dashicons-update"></span>
									<span class="button-text">
										<?php
										printf(
											/* translators: %d: number of images */
											esc_html__( 'Restore Everything (%d items)', 'imagify-bulk-restore' ),
											esc_html( $total_media )
										);
										?>
									</span>
								</button>
								<p class="description">
									<?php esc_html_e( 'Restore all optimized images from both Media Library and Custom Folders.', 'imagify-bulk-restore' ); ?>
								</p>
							</div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="imagify-no-images-message">
						<p>
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'No optimized images found with available backups. Make sure backups are enabled in Imagify settings and you have optimized images.', 'imagify-bulk-restore' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<!-- Progress Bar (hidden by default) -->
				<div class="imagify-restore-progress-container" id="imagify-restore-progress" style="display: <?php echo $progress['is_running'] ? 'block' : 'none'; ?>;">
					<h3><?php esc_html_e( 'Restore Progress', 'imagify-bulk-restore' ); ?></h3>
					<div class="imagify-progress-bar">
						<div class="imagify-progress-bar-fill" id="imagify-progress-bar-fill" style="width: <?php echo esc_attr( $progress['percent'] ); ?>%;"></div>
					</div>
					<div class="imagify-progress-stats">
						<span id="imagify-progress-text">
							<span id="imagify-progress-processed"><?php echo esc_html( $progress['processed'] ); ?></span>
							<?php esc_html_e( 'of', 'imagify-bulk-restore' ); ?>
							<span id="imagify-progress-total"><?php echo esc_html( $progress['total'] ); ?></span>
							<?php esc_html_e( 'images restored', 'imagify-bulk-restore' ); ?>
							(<span id="imagify-progress-percent"><?php echo esc_html( number_format_i18n( $progress['percent'], 1 ) ); ?></span>%)
						</span>
					</div>
					<div style="display: flex; gap: 10px; justify-content: center; margin-top: 15px;">
						<button type="button" class="button button-large imagify-pause-restore-btn">
							<span class="dashicons dashicons-controls-pause"></span>
							<?php esc_html_e( 'Pause', 'imagify-bulk-restore' ); ?>
						</button>
						<button type="button" class="button button-primary button-large imagify-resume-continue-btn" style="display: none;">
							<span class="dashicons dashicons-controls-play"></span>
							<?php esc_html_e( 'Resume', 'imagify-bulk-restore' ); ?>
						</button>
					</div>
				</div>

				<!-- Warning Message -->
				<div class="imagify-restore-warning">
					<p>
						<span class="dashicons dashicons-warning"></span>
						<strong><?php esc_html_e( 'Warning:', 'imagify-bulk-restore' ); ?></strong>
						<?php esc_html_e( 'Restoring images will remove all optimizations and restore original files. Your images will return to their pre-optimization size. This action cannot be undone automatically.', 'imagify-bulk-restore' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Back to Imagify Link -->
	<div class="imagify-back-link">
		<a href="<?php echo esc_url( admin_url( 'upload.php?page=imagify-bulk-optimization' ) ); ?>" class="button">
			<span class="dashicons dashicons-arrow-left-alt"></span>
			<?php esc_html_e( 'Back to Imagify Bulk Optimization', 'imagify-bulk-restore' ); ?>
		</a>
	</div>
</div>
