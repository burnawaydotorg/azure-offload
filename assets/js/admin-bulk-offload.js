/**
 * Azure Bulk Offload Admin JavaScript
 *
 * Handles real-time progress updates and UI interactions for bulk offload operations.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

(function($) {
	'use strict';

	var AzureBulkOffload = {
		progressInterval: null,
		isPaused: false,

		/**
		 * Initialize the bulk offload UI.
		 */
		init: function() {
			this.bindEvents();

			// Start progress polling if there's an active offload
			if ($('#azure-offload-progress').length) {
				this.startProgressPolling();
			}
		},

		/**
		 * Bind UI events.
		 */
		bindEvents: function() {
			var self = this;

			// Pause button
			$(document).on('click', '#pause-offload', function(e) {
				e.preventDefault();
				self.pauseOffload();
			});

			// Resume button
			$(document).on('click', '#resume-offload', function(e) {
				e.preventDefault();
				self.resumeOffload();
			});

			// Cancel button
			$(document).on('click', '#cancel-offload', function(e) {
				e.preventDefault();
				if (confirm(azureBulkOffload.i18n.confirmCancel)) {
					self.cancelOffload();
				}
			});

			// Clear progress button
			$(document).on('click', '#clear-progress', function(e) {
				e.preventDefault();
				self.clearProgress();
			});
		},

		/**
		 * Start polling for progress updates.
		 */
		startProgressPolling: function() {
			var self = this;

			// Clear any existing interval
			if (this.progressInterval) {
				clearInterval(this.progressInterval);
			}

			// Poll for updates
			this.progressInterval = setInterval(function() {
				self.fetchProgress();
			}, azureBulkOffload.refreshInterval);

			// Fetch immediately
			this.fetchProgress();
		},

		/**
		 * Stop polling for progress updates.
		 */
		stopProgressPolling: function() {
			if (this.progressInterval) {
				clearInterval(this.progressInterval);
				this.progressInterval = null;
			}
		},

		/**
		 * Fetch current progress from server.
		 */
		fetchProgress: function() {
			var self = this;

			$.ajax({
				url: azureBulkOffload.ajaxUrl,
				type: 'POST',
				data: {
					action: 'azure_get_progress',
					nonce: azureBulkOffload.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						self.updateProgress(response.data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error('Failed to fetch progress:', textStatus, errorThrown);
				}
			});
		},

		/**
		 * Update progress UI.
		 *
		 * @param {Object} progress Progress data from server.
		 */
		updateProgress: function(progress) {
			var percentage = progress.total > 0 ? Math.round((progress.processed / progress.total) * 100 * 10) / 10 : 0;

			// Update progress bar
			$('.progress-fill').css('width', percentage + '%');
			$('.progress-percentage').text(percentage + '%');

			// Update counts
			$('.processed-count').text(this.formatNumber(progress.processed));
			$('.total-count').text(this.formatNumber(progress.total));
			$('.successful-count').text(this.formatNumber(progress.successful));
			$('.failed-count').text(this.formatNumber(progress.failed));
			$('.skipped-count').text(this.formatNumber(progress.skipped));

			// Update status
			if (progress.status === 'completed') {
				this.handleCompletion();
			} else if (progress.status === 'cancelled') {
				this.handleCancellation();
			} else if (progress.status === 'paused') {
				this.updateStatusLabel(azureBulkOffload.i18n.paused, 'paused');
			} else {
				this.updateStatusLabel(azureBulkOffload.i18n.processing, 'running');

				// Calculate and show estimated time
				if (progress.processed > 0 && progress.started_at) {
					this.updateEstimatedTime(progress);
				}
			}
		},

		/**
		 * Update status label.
		 *
		 * @param {string} label Status label text.
		 * @param {string} statusClass Status CSS class.
		 */
		updateStatusLabel: function(label, statusClass) {
			$('.status-label')
				.text(label)
				.removeClass('status-running status-paused status-completed status-cancelled')
				.addClass('status-' + statusClass);
		},

		/**
		 * Update estimated time remaining.
		 *
		 * @param {Object} progress Progress data.
		 */
		updateEstimatedTime: function(progress) {
			var currentTime = Math.floor(Date.now() / 1000);
			var elapsed = currentTime - progress.started_at;
			var rate = progress.processed / elapsed; // items per second
			var remaining = progress.total - progress.processed;
			var estimatedSeconds = remaining / rate;

			var timeText = '';
			if (estimatedSeconds > 60) {
				var minutes = Math.floor(estimatedSeconds / 60);
				timeText = minutes + ' ' + azureBulkOffload.i18n.minutes;
			} else {
				timeText = Math.floor(estimatedSeconds) + ' ' + azureBulkOffload.i18n.seconds;
			}

			// Update or create estimated time display
			var $estimate = $('.estimated-time');
			if ($estimate.length === 0) {
				$('.progress-stats').append(
					'<span class="estimated-time">' +
					azureBulkOffload.i18n.estimatedTime + ' ' + timeText +
					'</span>'
				);
			} else {
				$estimate.text(azureBulkOffload.i18n.estimatedTime + ' ' + timeText);
			}

			// Show processing rate
			var ratePerMinute = Math.round(rate * 60);
			var $rate = $('.processing-rate');
			if ($rate.length === 0) {
				$('.progress-stats').append(
					'<span class="processing-rate">' +
					ratePerMinute + ' ' + azureBulkOffload.i18n.itemsPerMinute +
					'</span>'
				);
			} else {
				$rate.text(ratePerMinute + ' ' + azureBulkOffload.i18n.itemsPerMinute);
			}
		},

		/**
		 * Handle completion of offload operation.
		 */
		handleCompletion: function() {
			this.stopProgressPolling();
			this.updateStatusLabel(azureBulkOffload.i18n.completed, 'completed');

			// Hide action buttons
			$('.progress-actions').fadeOut();

			// Reload page after 2 seconds to show completion summary
			setTimeout(function() {
				location.reload();
			}, 2000);
		},

		/**
		 * Handle cancellation of offload operation.
		 */
		handleCancellation: function() {
			this.stopProgressPolling();
			this.updateStatusLabel(azureBulkOffload.i18n.cancelled, 'cancelled');

			// Reload page after 2 seconds
			setTimeout(function() {
				location.reload();
			}, 2000);
		},

		/**
		 * Pause the offload operation.
		 */
		pauseOffload: function() {
			var self = this;

			$.ajax({
				url: azureBulkOffload.ajaxUrl,
				type: 'POST',
				data: {
					action: 'azure_pause_offload',
					nonce: azureBulkOffload.nonce
				},
				success: function(response) {
					if (response.success) {
						self.isPaused = true;
						$('#pause-offload').hide();
						$('#resume-offload').show();
						self.updateStatusLabel(azureBulkOffload.i18n.paused, 'paused');
					}
				}
			});
		},

		/**
		 * Resume the offload operation.
		 */
		resumeOffload: function() {
			var self = this;

			$.ajax({
				url: azureBulkOffload.ajaxUrl,
				type: 'POST',
				data: {
					action: 'azure_resume_offload',
					nonce: azureBulkOffload.nonce
				},
				success: function(response) {
					if (response.success) {
						self.isPaused = false;
						$('#resume-offload').hide();
						$('#pause-offload').show();
						self.updateStatusLabel(azureBulkOffload.i18n.processing, 'running');
					}
				}
			});
		},

		/**
		 * Cancel the offload operation.
		 */
		cancelOffload: function() {
			var self = this;

			$.ajax({
				url: azureBulkOffload.ajaxUrl,
				type: 'POST',
				data: {
					action: 'azure_cancel_offload',
					nonce: azureBulkOffload.nonce
				},
				success: function(response) {
					if (response.success) {
						self.handleCancellation();
					}
				}
			});
		},

		/**
		 * Clear progress and reload page.
		 */
		clearProgress: function() {
			location.reload();
		},

		/**
		 * Format number with localized thousands separator.
		 *
		 * @param {number} num Number to format.
		 * @return {string} Formatted number.
		 */
		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AzureBulkOffload.init();
	});

})(jQuery);
