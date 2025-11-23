/**
 * Azure Media Library AJAX
 *
 * Handles AJAX interactions in the WordPress Media Library for Azure Storage.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

(function($) {
	'use strict';

	var AzureMediaLibrary = {
		/**
		 * Initialize the Media Library enhancements.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind UI events.
		 */
		bindEvents: function() {
			var self = this;

			// Copy Azure URL to clipboard
			$(document).on('click', '.azure-copy-url', function(e) {
				e.preventDefault();
				var $link = $(this);
				var url = $link.data('url');
				var postId = $link.data('id');

				self.copyToClipboard(url, $link);
			});

			// Confirm bulk remove action
			$('select[name="action"], select[name="action2"]').on('change', function() {
				var $select = $(this);
				var action = $select.val();

				if (action === 'azure_remove') {
					var $form = $select.closest('form');
					$form.on('submit', function(e) {
						if (!confirm(azureMediaLibrary.i18n.confirmRemove)) {
							e.preventDefault();
							$select.val('-1');
							return false;
						}
					});
				}
			});

			// Add visual feedback for Azure status
			this.enhanceStatusIcons();
		},

		/**
		 * Copy text to clipboard.
		 *
		 * @param {string} text   Text to copy.
		 * @param {jQuery} $link  Link element that triggered the action.
		 */
		copyToClipboard: function(text, $link) {
			var self = this;

			// Modern clipboard API
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					self.showCopySuccess($link);
				}).catch(function(err) {
					console.error('Failed to copy URL:', err);
					self.fallbackCopy(text, $link);
				});
			} else {
				// Fallback for older browsers
				self.fallbackCopy(text, $link);
			}
		},

		/**
		 * Fallback copy method for older browsers.
		 *
		 * @param {string} text   Text to copy.
		 * @param {jQuery} $link  Link element that triggered the action.
		 */
		fallbackCopy: function(text, $link) {
			var $temp = $('<input>');
			$('body').append($temp);
			$temp.val(text).select();

			try {
				var successful = document.execCommand('copy');
				if (successful) {
					this.showCopySuccess($link);
				} else {
					this.showCopyError($link);
				}
			} catch (err) {
				console.error('Fallback copy failed:', err);
				this.showCopyError($link);
			}

			$temp.remove();
		},

		/**
		 * Show success message after copying.
		 *
		 * @param {jQuery} $link Link element.
		 */
		showCopySuccess: function($link) {
			var originalText = $link.text();
			$link.text(azureMediaLibrary.i18n.copied);
			$link.css('color', '#00a32a');

			setTimeout(function() {
				$link.text(originalText);
				$link.css('color', '');
			}, 2000);
		},

		/**
		 * Show error message if copying fails.
		 *
		 * @param {jQuery} $link Link element.
		 */
		showCopyError: function($link) {
			var originalText = $link.text();
			$link.text(azureMediaLibrary.i18n.copyFailed);
			$link.css('color', '#d63638');

			setTimeout(function() {
				$link.text(originalText);
				$link.css('color', '');
			}, 2000);
		},

		/**
		 * Enhance status icons with tooltips and visual improvements.
		 */
		enhanceStatusIcons: function() {
			// Add hover effects
			$('.azure-status').each(function() {
				var $status = $(this);
				$status.css({
					'display': 'inline-block',
					'padding': '2px 6px',
					'border-radius': '3px',
					'font-size': '12px'
				});

				// Add subtle background color on hover
				$status.hover(
					function() {
						$(this).css('background-color', '#f0f0f1');
					},
					function() {
						$(this).css('background-color', '');
					}
				);
			});

			// Add tooltip-like behavior
			$('.azure-status-offloaded').attr('title', azureMediaLibrary.i18n.offloadedTitle || 'File is offloaded to Azure Storage');
			$('.azure-status-pending').attr('title', azureMediaLibrary.i18n.pendingTitle || 'File is not yet offloaded');
			$('.azure-status-failed').attr('title', azureMediaLibrary.i18n.failedTitle || 'Upload to Azure failed');
		},

		/**
		 * Refresh status for a specific attachment.
		 *
		 * @param {int} postId Attachment ID.
		 */
		refreshStatus: function(postId) {
			// This could be used to refresh status via AJAX if needed
			// For now, we rely on page refresh for status updates
			console.log('Refreshing status for attachment:', postId);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AzureMediaLibrary.init();
	});

})(jQuery);
