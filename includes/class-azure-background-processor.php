<?php
/**
 * Azure Background Processor
 *
 * Handles background processing of media offloading using Action Scheduler.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Background_Processor class.
 *
 * Integrates with Action Scheduler to queue and process media offloading tasks
 * without blocking the admin interface.
 */
class Azure_Background_Processor {

	/**
	 * Action Scheduler hook name for processing attachments.
	 *
	 * @var string
	 */
	const ACTION_HOOK = 'azure_process_attachment';

	/**
	 * Action Scheduler hook name for batch completion.
	 *
	 * @var string
	 */
	const BATCH_COMPLETE_HOOK = 'azure_batch_complete';

	/**
	 * Option key for storing batch progress.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION = 'azure_bulk_offload_progress';

	/**
	 * Maximum number of retries for failed uploads.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 3;

	/**
	 * Initialize the background processor.
	 */
	public function __construct() {
		// Check if Action Scheduler is available.
		if ( ! $this->is_action_scheduler_available() ) {
			add_action( 'admin_notices', array( $this, 'action_scheduler_missing_notice' ) );
			return;
		}

		// Register Action Scheduler hooks.
		add_action( self::ACTION_HOOK, array( $this, 'process_attachment' ), 10, 1 );
		add_action( self::BATCH_COMPLETE_HOOK, array( $this, 'batch_complete' ), 10, 1 );

		// Register AJAX handlers for progress tracking.
		add_action( 'wp_ajax_azure_get_progress', array( $this, 'ajax_get_progress' ) );
		add_action( 'wp_ajax_azure_pause_offload', array( $this, 'ajax_pause_offload' ) );
		add_action( 'wp_ajax_azure_resume_offload', array( $this, 'ajax_resume_offload' ) );
		add_action( 'wp_ajax_azure_cancel_offload', array( $this, 'ajax_cancel_offload' ) );
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool True if Action Scheduler is available, false otherwise.
	 */
	public function is_action_scheduler_available() {
		return function_exists( 'as_enqueue_async_action' ) && class_exists( 'ActionScheduler' );
	}

	/**
	 * Display admin notice if Action Scheduler is missing.
	 */
	public function action_scheduler_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Azure Media Offload:', 'windows-azure-storage' ); ?></strong>
				<?php esc_html_e( 'Action Scheduler is required for background processing but is not installed or active.', 'windows-azure-storage' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Please install and activate a plugin that includes Action Scheduler (e.g., WooCommerce) or disable background processing features.', 'windows-azure-storage' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Start a bulk offload operation.
	 *
	 * @param array $attachment_ids Array of attachment IDs to offload.
	 * @param array $options        Options for the offload operation.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function start_bulk_offload( $attachment_ids, $options = array() ) {
		if ( ! $this->is_action_scheduler_available() ) {
			return new WP_Error(
				'action_scheduler_missing',
				__( 'Action Scheduler is not available. Cannot start bulk offload.', 'windows-azure-storage' )
			);
		}

		if ( empty( $attachment_ids ) ) {
			return new WP_Error(
				'no_attachments',
				__( 'No attachments selected for offloading.', 'windows-azure-storage' )
			);
		}

		// Generate unique batch ID.
		$batch_id = 'batch_' . time() . '_' . wp_generate_password( 8, false );

		// Initialize progress tracking.
		$progress = array(
			'batch_id'   => $batch_id,
			'status'     => 'running',
			'total'      => count( $attachment_ids ),
			'processed'  => 0,
			'successful' => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'started_at' => time(),
			'options'    => $options,
			'errors'     => array(),
		);

		update_option( self::PROGRESS_OPTION, $progress, false );

		// Queue all attachments for processing.
		foreach ( $attachment_ids as $attachment_id ) {
			as_enqueue_async_action(
				self::ACTION_HOOK,
				array(
					'attachment_id' => $attachment_id,
					'batch_id'      => $batch_id,
					'options'       => $options,
				),
				'azure-offload'
			);
		}

		// Schedule batch completion check.
		as_schedule_single_action(
			time() + 30, // Check 30 seconds from now.
			self::BATCH_COMPLETE_HOOK,
			array( 'batch_id' => $batch_id ),
			'azure-offload'
		);

		return true;
	}

	/**
	 * Process a single attachment.
	 *
	 * @param array $args Arguments passed from Action Scheduler.
	 */
	public function process_attachment( $args ) {
		$attachment_id = $args['attachment_id'];
		$batch_id      = $args['batch_id'];
		$options       = isset( $args['options'] ) ? $args['options'] : array();

		// Get current progress.
		$progress = get_option( self::PROGRESS_OPTION, array() );

		// Check if batch is paused.
		if ( isset( $progress['status'] ) && 'paused' === $progress['status'] ) {
			// Re-queue for later processing.
			as_enqueue_async_action( self::ACTION_HOOK, $args, 'azure-offload' );
			return;
		}

		// Check if batch is cancelled.
		if ( isset( $progress['status'] ) && 'cancelled' === $progress['status'] ) {
			$progress['skipped']++;
			update_option( self::PROGRESS_OPTION, $progress, false );
			return;
		}

		// Verify attachment exists.
		if ( ! get_post( $attachment_id ) ) {
			$this->record_error( $batch_id, $attachment_id, 'Attachment not found' );
			return;
		}

		// Check if already offloaded.
		$already_offloaded = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' === $already_offloaded && empty( $options['force'] ) ) {
			$progress['skipped']++;
			update_option( self::PROGRESS_OPTION, $progress, false );
			return;
		}

		// Attempt to offload.
		$result = $this->offload_attachment( $attachment_id, $options );

		// Update progress.
		$progress['processed']++;

		if ( is_wp_error( $result ) ) {
			$progress['failed']++;
			$this->record_error( $batch_id, $attachment_id, $result->get_error_message() );
		} else {
			$progress['successful']++;
		}

		update_option( self::PROGRESS_OPTION, $progress, false );

		// Trigger progress update hook for extensions.
		do_action( 'azure_background_processor_progress', $progress, $attachment_id, $result );
	}

	/**
	 * Offload a single attachment to Azure.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $options       Offload options.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function offload_attachment( $attachment_id, $options = array() ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'file_not_found',
				sprintf( __( 'File not found: %s', 'windows-azure-storage' ), $file_path )
			);
		}

		try {
			// Get storage client.
			$credentials = \Windows_Azure_Config_Provider::get_account_credentials();
			if ( empty( $credentials['account_name'] ) || empty( $credentials['account_key'] ) ) {
				return new WP_Error( 'storage_credentials_missing', __( 'Azure storage credentials not configured', 'windows-azure-storage' ) );
			}

			$storage_client = new \Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
			if ( ! $storage_client ) {
				return new WP_Error( 'storage_client_error', __( 'Could not initialize storage client', 'windows-azure-storage' ) );
			}

			// Get blob name from file path.
			$upload_dir  = wp_upload_dir();
			$blob_name   = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $file_path );
			$blob_name   = str_replace( '\\', '/', $blob_name );

			// Get container name.
			$container_name = \Windows_Azure_Helper::get_default_container();

			// Upload file to Azure.
			$result = $storage_client->putBlob(
				$container_name,
				$blob_name,
				$file_path
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Upload thumbnails.
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $metadata['sizes'] ) ) {
				$file_dir = dirname( $file_path );

				foreach ( $metadata['sizes'] as $size => $size_data ) {
					$thumb_path = $file_dir . '/' . $size_data['file'];
					if ( file_exists( $thumb_path ) ) {
						$thumb_blob = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $thumb_path );
						$thumb_blob = str_replace( '\\', '/', $thumb_blob );

						$storage_client->putBlob(
							$container_name,
							$thumb_blob,
							$thumb_path
						);
					}
				}
			}

			// Update post meta.
			update_post_meta( $attachment_id, '_azure_offload_status', 'offloaded' );
			update_post_meta( $attachment_id, '_azure_offload_date', time() );
			update_post_meta( $attachment_id, '_azure_blob_url', \Windows_Azure_Helper::get_full_blob_url( $blob_name ) );

			// Optionally remove local files.
			if ( ! empty( $options['remove_local'] ) ) {
				$this->remove_local_files( $attachment_id, $file_path, $metadata );
			}

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'azure_upload_error',
				sprintf( __( 'Azure upload failed: %s', 'windows-azure-storage' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Remove local files after successful upload.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     Main file path.
	 * @param array  $metadata      Attachment metadata.
	 */
	private function remove_local_files( $attachment_id, $file_path, $metadata ) {
		// Delete main file.
		if ( file_exists( $file_path ) ) {
			@unlink( $file_path );
		}

		// Delete thumbnails.
		if ( ! empty( $metadata['sizes'] ) ) {
			$file_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size => $size_data ) {
				$thumb_path = $file_dir . '/' . $size_data['file'];
				if ( file_exists( $thumb_path ) ) {
					@unlink( $thumb_path );
				}
			}
		}

		// Mark as removed.
		update_post_meta( $attachment_id, '_azure_local_removed', true );
	}

	/**
	 * Record an error for a specific attachment.
	 *
	 * @param string $batch_id      Batch ID.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $error_message Error message.
	 */
	private function record_error( $batch_id, $attachment_id, $error_message ) {
		$progress = get_option( self::PROGRESS_OPTION, array() );

		$progress['failed']++;
		$progress['errors'][] = array(
			'attachment_id' => $attachment_id,
			'message'       => $error_message,
			'time'          => time(),
		);

		// Limit errors array to last 100 errors.
		if ( count( $progress['errors'] ) > 100 ) {
			$progress['errors'] = array_slice( $progress['errors'], -100 );
		}

		update_option( self::PROGRESS_OPTION, $progress, false );
	}

	/**
	 * Check if batch is complete.
	 *
	 * @param array $args Arguments from Action Scheduler.
	 */
	public function batch_complete( $args ) {
		$batch_id = $args['batch_id'];
		$progress = get_option( self::PROGRESS_OPTION, array() );

		// Check if all items processed.
		if ( $progress['processed'] >= $progress['total'] ) {
			$progress['status']       = 'completed';
			$progress['completed_at'] = time();
			update_option( self::PROGRESS_OPTION, $progress, false );

			// Trigger completion hook.
			do_action( 'azure_bulk_offload_complete', $batch_id, $progress );
		} else {
			// Re-schedule check for later.
			as_schedule_single_action(
				time() + 30,
				self::BATCH_COMPLETE_HOOK,
				array( 'batch_id' => $batch_id ),
				'azure-offload'
			);
		}
	}

	/**
	 * AJAX handler: Get current progress.
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'azure-bulk-offload', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );

		if ( empty( $progress ) ) {
			wp_send_json_error( array( 'message' => __( 'No active offload operation', 'windows-azure-storage' ) ) );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX handler: Pause offload operation.
	 */
	public function ajax_pause_offload() {
		check_ajax_referer( 'azure-bulk-offload', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );

		if ( empty( $progress ) ) {
			wp_send_json_error( array( 'message' => __( 'No active offload operation', 'windows-azure-storage' ) ) );
		}

		$progress['status'] = 'paused';
		update_option( self::PROGRESS_OPTION, $progress, false );

		wp_send_json_success( array( 'message' => __( 'Offload paused', 'windows-azure-storage' ) ) );
	}

	/**
	 * AJAX handler: Resume offload operation.
	 */
	public function ajax_resume_offload() {
		check_ajax_referer( 'azure-bulk-offload', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );

		if ( empty( $progress ) ) {
			wp_send_json_error( array( 'message' => __( 'No active offload operation', 'windows-azure-storage' ) ) );
		}

		$progress['status'] = 'running';
		update_option( self::PROGRESS_OPTION, $progress, false );

		wp_send_json_success( array( 'message' => __( 'Offload resumed', 'windows-azure-storage' ) ) );
	}

	/**
	 * AJAX handler: Cancel offload operation.
	 */
	public function ajax_cancel_offload() {
		check_ajax_referer( 'azure-bulk-offload', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );

		if ( empty( $progress ) ) {
			wp_send_json_error( array( 'message' => __( 'No active offload operation', 'windows-azure-storage' ) ) );
		}

		$progress['status']       = 'cancelled';
		$progress['cancelled_at'] = time();
		update_option( self::PROGRESS_OPTION, $progress, false );

		wp_send_json_success( array( 'message' => __( 'Offload cancelled', 'windows-azure-storage' ) ) );
	}
}
