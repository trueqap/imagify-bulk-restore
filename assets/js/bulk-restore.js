(function($, window, document, undefined) {
	'use strict';

	// Suppress Chrome extension errors
	window.addEventListener('error', function(e) {
		if (e.message && e.message.indexOf('message channel closed') > -1) {
			e.preventDefault();
			return true;
		}
	});

	var ImagifyBulkRestore = {
		/**
		 * Current restore queue
		 */
		mediaQueue: [],
		currentContext: null,
		totalItems: 0,
		processedItems: 0,
		isProcessing: false,
		isPaused: false,
		autoSaveInterval: null,

		/**
		 * Initialize the bulk restore functionality
		 */
		init: function() {
			console.log('[Imagify Bulk Restore] Initializing...');
			this.bindEvents();
			this.checkForPendingQueue();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Restore button click handler
			$(document).on('click', '.imagify-bulk-restore-btn', function(e) {
				e.preventDefault();

				var $button = $(this);
				var context = $button.data('context');

				self.handleRestoreClick($button, context);
			});

			// Resume restore button

			// Resume continue button (from progress bar)
			$(document).on('click', '.imagify-resume-continue-btn', function(e) {
				e.preventDefault();
				self.resumeContinue();
			});
			$(document).on('click', '.imagify-resume-restore-btn', function(e) {
				e.preventDefault();
				self.resumeRestore();
			});

			// Pause restore button
			$(document).on('click', '.imagify-pause-restore-btn', function(e) {
				e.preventDefault();
				self.pauseRestore();
			});

			// Cancel restore button
			$(document).on('click', '.imagify-cancel-restore-btn', function(e) {
				e.preventDefault();
				self.cancelQueue();
			});

			// Complete notice dismiss handler
			$(document).on('click', '#imagify-restore-complete-notice .notice-dismiss', function() {
				self.clearCompleteStatus();
			});

			// Save queue before page unload
			$(window).on('beforeunload', function() {
				if (self.mediaQueue.length > 0 && self.isProcessing) {
					self.saveQueueState(false); // Synchronous save on unload
					return 'A restore operation is in progress. Are you sure you want to leave?';
				}
			});
		},

		/**
		 * Check for pending queue on page load
		 */
		checkForPendingQueue: function() {
			var self = this;

			console.log('[Imagify Bulk Restore] Checking for pending queue...');

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_get_queue',
					nonce: imagifyBulkRestore.nonce
				},
				success: function(response) {
					if (response.success && response.data.queue) {
						console.log('[Imagify Bulk Restore] Found pending queue:', response.data);
					} else {
						console.log('[Imagify Bulk Restore] No pending queue found');
					}
				},
				error: function() {
					console.log('[Imagify Bulk Restore] No pending queue');
				}
			});
		},

		/**
		 * Resume restore from saved queue
		 */
		resumeRestore: function() {
			var self = this;

			console.log('[Imagify Bulk Restore] Resuming restore...');

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_get_queue',
					nonce: imagifyBulkRestore.nonce
				},
				success: function(response) {
					if (response.success && response.data.queue) {
						console.log('[Imagify Bulk Restore] Loaded queue:', response.data);

						self.mediaQueue = response.data.queue;
						self.totalItems = response.data.total;
						self.processedItems = response.data.processed;

						// Hide resume notice
						$('#imagify-resume-notice').slideUp();

						// Show progress bar and start processing
						self.showProgressBar();
						self.startAutoSave();
						self.processQueue();
					}
				},
				error: function() {
					alert(imagifyBulkRestore.labels.error);
				}
			});
		},

		/**
		 * Cancel pending queue
		 */
		cancelQueue: function() {
			var self = this;

			if (!confirm('Are you sure you want to cancel the pending restore operation?')) {
				return;
			}

			console.log('[Imagify Bulk Restore] Cancelling queue...');

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_cancel_queue',
					nonce: imagifyBulkRestore.nonce
				},
				success: function(response) {
					if (response.success) {
						console.log('[Imagify Bulk Restore] Queue cancelled');
						$('#imagify-resume-notice').slideUp();
					}
				}
			});
		},

		/**
		 * Handle restore button click
		 */
		handleRestoreClick: function($button, context) {
			var self = this;

			console.log('[Imagify Bulk Restore] Button clicked, context:', context);

			// Confirm action
			if (!confirm(imagifyBulkRestore.labels.confirmRestore)) {
				console.log('[Imagify Bulk Restore] User cancelled confirmation');
				return;
			}

			console.log('[Imagify Bulk Restore] User confirmed restore');

			// Disable button
			$button.prop('disabled', true);
			$button.find('.button-text').text(imagifyBulkRestore.labels.starting);

			// Determine which contexts to restore
			var contexts = [];
			if (context === 'all') {
				contexts = ['wp', 'custom-folders'];
			} else {
				contexts = [context];
			}

			console.log('[Imagify Bulk Restore] Contexts to restore:', contexts);

			// Start restore for each context
			self.startRestoreForContexts(contexts);
		},

		/**
		 * Start restore for multiple contexts
		 */
		startRestoreForContexts: function(contexts) {
			var self = this;
			var allMediaIds = [];
			var completed = 0;

			console.log('[Imagify Bulk Restore] Starting restore for contexts:', contexts);

			contexts.forEach(function(ctx) {
				console.log('[Imagify Bulk Restore] Fetching media IDs for context:', ctx);

				$.ajax({
					url: imagifyBulkRestore.ajaxurl,
					type: 'POST',
					data: {
						action: 'imagify_bulk_restore_start',
						context: ctx,
						nonce: imagifyBulkRestore.nonce
					},
					success: function(response) {
						console.log('[Imagify Bulk Restore] AJAX response for context ' + ctx + ':', response);

						if (response.success && response.data.media_ids) {
							console.log('[Imagify Bulk Restore] Found ' + response.data.media_ids.length + ' media IDs for context ' + ctx);
							allMediaIds = allMediaIds.concat(response.data.media_ids.map(function(id) {
								return { id: id, context: ctx };
							}));
						} else {
							console.warn('[Imagify Bulk Restore] No media_ids in response for context ' + ctx);
						}

						completed++;
						console.log('[Imagify Bulk Restore] Completed ' + completed + ' of ' + contexts.length + ' contexts');

						if (completed === contexts.length) {
							console.log('[Imagify Bulk Restore] All contexts fetched. Total media IDs:', allMediaIds.length);

							if (allMediaIds.length > 0) {
								self.mediaQueue = allMediaIds;
								self.totalItems = allMediaIds.length;
								self.processedItems = 0;
								self.showProgressBar();
								self.startAutoSave();
								self.processQueue();
							} else {
								console.error('[Imagify Bulk Restore] No media IDs to restore');
								alert(imagifyBulkRestore.labels.error);
								$('.imagify-bulk-restore-btn').prop('disabled', false);
							}
						}
					},
					error: function(jqXHR, textStatus, errorThrown) {
						console.error('[Imagify Bulk Restore] AJAX error for context ' + ctx + ':', textStatus, errorThrown);
						console.error('[Imagify Bulk Restore] Response:', jqXHR.responseText);

						completed++;
						if (completed === contexts.length) {
							alert(imagifyBulkRestore.labels.error);
							$('.imagify-bulk-restore-btn').prop('disabled', false);
						}
					}
				});
			});
		},

		/**
		 * Start auto-save interval (every 5 seconds)
		 */
		startAutoSave: function() {
			var self = this;

			if (self.autoSaveInterval) {
				clearInterval(self.autoSaveInterval);
			}

			self.autoSaveInterval = setInterval(function() {
				if (self.mediaQueue.length > 0) {
					self.saveQueueState(true);
				}
			}, 5000); // Save every 5 seconds
		},

		/**
		 * Stop auto-save interval
		 */
		stopAutoSave: function() {
			if (this.autoSaveInterval) {
				clearInterval(this.autoSaveInterval);
				this.autoSaveInterval = null;
			}
		},

		/**
		 * Save queue state to server
		 */
		saveQueueState: function(async) {
			var self = this;

			if (self.mediaQueue.length === 0) {
				return;
			}

			console.log('[Imagify Bulk Restore] Saving queue state:', self.mediaQueue.length, 'items remaining');

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				async: async !== false,
				data: {
					action: 'imagify_bulk_restore_save_queue',
					queue: JSON.stringify(self.mediaQueue),
					total: self.totalItems,
					processed: self.processedItems,
					nonce: imagifyBulkRestore.nonce
				},
				success: function(response) {
					if (response.success) {
						console.log('[Imagify Bulk Restore] Queue saved successfully');
					}
				}
			});
		},

		/**
		 * Clear saved queue
		 */
		clearQueueState: function() {
			var self = this;

			console.log('[Imagify Bulk Restore] Clearing queue state');

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_cancel_queue',
					nonce: imagifyBulkRestore.nonce
				}
			});
		},

		/**
		 * Process the queue (batch of 10 images at a time)
		 */
		processQueue: function() {
			var self = this;

			// Check if paused
			if (self.isPaused) {
				console.log('[Imagify Bulk Restore] Restore is paused');
				return;
			}

			if (self.mediaQueue.length === 0) {
				// All done!
				console.log('[Imagify Bulk Restore] Queue empty, restore complete!');
				self.isProcessing = false;
				self.stopAutoSave();
				self.clearQueueState();
				self.showCompleteMessage(self.processedItems);
				self.hideProgressBar();
				self.refreshPageStats();
				return;
			}

			if (self.isProcessing) {
				console.log('[Imagify Bulk Restore] Already processing, skipping...');
				return;
			}

			self.isProcessing = true;

			// Process batch of 10 images at once for better performance
			var batchSize = 10;
			var batch = [];
			for (var i = 0; i < batchSize && self.mediaQueue.length > 0; i++) {
				batch.push(self.mediaQueue.shift());
			}

			console.log('[Imagify Bulk Restore] Processing batch of ' + batch.length + ' items, remaining:', self.mediaQueue.length);

			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_process_batch',
					batch: JSON.stringify(batch),
					nonce: imagifyBulkRestore.nonce
				},
				success: function(response) {
					console.log('[Imagify Bulk Restore] Processed batch successfully:', response);
					self.processedItems += batch.length;
					self.updateProgress();
					self.isProcessing = false;

					// Check if paused before processing next batch
					if (!self.isPaused) {
						self.processQueue(); // Process next batch
					} else {
						console.log('[Imagify Bulk Restore] Paused after batch completion');
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error('[Imagify Bulk Restore] Error processing batch:', textStatus, errorThrown);
					console.error('[Imagify Bulk Restore] Response:', jqXHR.responseText);
					self.processedItems += batch.length;
					self.updateProgress();
					self.isProcessing = false;

					// Check if paused before processing next batch
					if (!self.isPaused) {
						self.processQueue(); // Continue even on error
					} else {
						console.log('[Imagify Bulk Restore] Paused after batch error');
					}
				}
			});
		},

		/**
		 * Update progress display
		 */
		updateProgress: function() {
			var percent = this.totalItems > 0 ? (this.processedItems / this.totalItems) * 100 : 0;

			console.log('[Imagify Bulk Restore] Progress:', this.processedItems, '/', this.totalItems, '(' + percent.toFixed(1) + '%)');

			$('#imagify-progress-bar-fill').css('width', percent + '%');
			$('#imagify-progress-processed').text(this.processedItems);
			$('#imagify-progress-total').text(this.totalItems);
			$('#imagify-progress-percent').text(percent.toFixed(1));
		},

		/**
		 * Show progress bar
		 */
		showProgressBar: function() {
			console.log('[Imagify Bulk Restore] Showing progress bar');
			$('#imagify-restore-progress').slideDown();
			$('.imagify-bulk-restore-btn').prop('disabled', true);
			this.updateProgress();
		},

		/**
		 * Hide progress bar
		 */
		hideProgressBar: function() {
			console.log('[Imagify Bulk Restore] Hiding progress bar');
			$('#imagify-restore-progress').slideUp();
			$('.imagify-bulk-restore-btn').prop('disabled', false);
		},

		/**
		 * Show completion message
		 */
		showCompleteMessage: function(count) {
			console.log('[Imagify Bulk Restore] Showing completion message for', count, 'images');

			// Create notice if it doesn't exist
			if ($('#imagify-restore-complete-notice').length === 0) {
				var $notice = $('<div>', {
					id: 'imagify-restore-complete-notice',
					class: 'notice notice-success is-dismissible'
				}).html(
					'<p><strong>' + imagifyBulkRestore.labels.complete + '</strong><br>' +
					count + ' ' + imagifyBulkRestore.labels.restoredImages + '</p>'
				);

				$('.imagify-bulk-restore-page h1').after($notice);
			}

			// Reset progress bar
			$('#imagify-progress-bar-fill').css('width', '0%');
			$('#imagify-progress-processed').text('0');
			$('#imagify-progress-total').text('0');
			$('#imagify-progress-percent').text('0');
		},

		/**
		 * Refresh page statistics after restore completes
		 */
		refreshPageStats: function() {
			console.log('[Imagify Bulk Restore] Refreshing page in 2 seconds...');
		setTimeout(function() {
			window.location.reload();
		}, 2000);
	},

		/**
		 * Pause restore operation
		 */
		pauseRestore: function() {
			var self = this;
			console.log('[Imagify Bulk Restore] Pausing restore...');
			self.isPaused = true;
			self.saveQueueState(true);

			// Toggle button visibility
			$('.imagify-pause-restore-btn').hide();
			$('.imagify-resume-continue-btn').show();
		},

		/**
		 * Resume from pause (continue current session)
		 */
		resumeContinue: function() {
			var self = this;
			console.log('[Imagify Bulk Restore] Resuming from pause...');
			console.log('[Imagify Bulk Restore] Current state - isPaused:', self.isPaused, 'isProcessing:', self.isProcessing, 'queue length:', self.mediaQueue.length);

			self.isPaused = false;
			self.isProcessing = false; // Reset processing flag to allow queue to continue

			// Toggle button visibility
			$('.imagify-resume-continue-btn').hide();
			$('.imagify-pause-restore-btn').show();

			console.log('[Imagify Bulk Restore] Calling processQueue() to resume...');
			self.processQueue();
		},

		/**
		 * Clear complete status flag
		 */
		clearCompleteStatus: function() {
			console.log('[Imagify Bulk Restore] Clearing complete status');
			$.ajax({
				url: imagifyBulkRestore.ajaxurl,
				type: 'POST',
				data: {
					action: 'imagify_bulk_restore_clear_complete',
					nonce: imagifyBulkRestore.nonce
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		console.log('[Imagify Bulk Restore] Document ready, initializing...');
		ImagifyBulkRestore.init();
	});

	// Expose to window for debugging
	window.ImagifyBulkRestore = ImagifyBulkRestore;

})(jQuery, window, document);
