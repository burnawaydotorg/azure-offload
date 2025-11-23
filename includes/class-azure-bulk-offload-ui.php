<?php
/**
 * Azure Bulk Offload UI
 *
 * Provides admin interface for bulk offload operations.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Bulk_Offload_UI class.
 *
 * Creates admin page for managing bulk offload operations.
 */
class Azure_Bulk_Offload_UI {

	/**
	 * Background processor instance.
	 *
	 * @var Azure_Background_Processor
	 */
	private $processor;

	/**
	 * Initialize the UI.
	 *
	 * @param Azure_Background_Processor $processor Background processor instance.
	 */
	public function __construct( $processor ) {
		$this->processor = $processor;

		// Register admin menu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Handle form submission.
		add_action( 'admin_post_azure_start_bulk_offload', array( $this, 'handle_start_offload' ) );
	}

	/**
	 * Register admin menu page.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'upload.php',
			__( 'Bulk Offload to Azure', 'windows-azure-storage' ),
			__( 'Bulk Offload to Azure', 'windows-azure-storage' ),
			'upload_files',
			'azure-bulk-offload',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin page.
		if ( 'media_page_azure-bulk-offload' !== $hook ) {
			return;
		}

		// Enqueue JavaScript.
		wp_enqueue_script(
			'azure-bulk-offload',
			plugins_url( 'assets/js/admin-bulk-offload.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			MSFT_AZURE_PLUGIN_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'azure-bulk-offload',
			'azureBulkOffload',
			array(
				'nonce'        => wp_create_nonce( 'azure-bulk-offload' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'i18n'         => array(
					'starting'         => __( 'Starting offload...', 'windows-azure-storage' ),
					'processing'       => __( 'Processing...', 'windows-azure-storage' ),
					'paused'           => __( 'Paused', 'windows-azure-storage' ),
					'completed'        => __( 'Completed!', 'windows-azure-storage' ),
					'cancelled'        => __( 'Cancelled', 'windows-azure-storage' ),
					'error'            => __( 'Error:', 'windows-azure-storage' ),
					'confirmCancel'    => __( 'Are you sure you want to cancel the offload operation?', 'windows-azure-storage' ),
					'itemsPerMinute'   => __( 'items/min', 'windows-azure-storage' ),
					'estimatedTime'    => __( 'Estimated time remaining:', 'windows-azure-storage' ),
					'minutes'          => __( 'minutes', 'windows-azure-storage' ),
					'seconds'          => __( 'seconds', 'windows-azure-storage' ),
				),
				'refreshInterval' => 2000, // Refresh every 2 seconds.
			)
		);

		// Enqueue CSS.
		wp_enqueue_style(
			'azure-bulk-offload',
			plugins_url( 'assets/css/admin-bulk-offload.css', dirname( __FILE__ ) ),
			array(),
			MSFT_AZURE_PLUGIN_VERSION
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// Get current progress.
		$progress = get_option( Azure_Background_Processor::PROGRESS_OPTION, array() );
		$has_active_offload = ! empty( $progress ) && isset( $progress['status'] ) && in_array( $progress['status'], array( 'running', 'paused' ), true );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Offload to Azure', 'windows-azure-storage' ); ?></h1>

			<?php if ( ! $this->processor->is_action_scheduler_available() ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Action Scheduler is required but not available.', 'windows-azure-storage' ); ?></strong>
					</p>
					<p>
						<?php esc_html_e( 'Please install and activate a plugin that includes Action Scheduler (e.g., WooCommerce) to use bulk offload features.', 'windows-azure-storage' ); ?>
					</p>
				</div>
			<?php else : ?>

				<?php if ( $has_active_offload ) : ?>
					<?php $this->render_progress_section( $progress ); ?>
				<?php else : ?>
					<?php $this->render_start_section(); ?>
				<?php endif; ?>

				<?php if ( ! empty( $progress ) && 'completed' === $progress['status'] ) : ?>
					<?php $this->render_completed_section( $progress ); ?>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render start offload section.
	 */
	private function render_start_section() {
		// Get counts.
		$total_attachments = $this->get_attachment_count();
		$offloaded_count   = $this->get_offloaded_count();
		$pending_count     = $total_attachments - $offloaded_count;

		?>
		<div class="azure-bulk-offload-start">
			<div class="card">
				<h2><?php esc_html_e( 'Media Library Status', 'windows-azure-storage' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Total Attachments:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $total_attachments ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Already Offloaded:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $offloaded_count ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Pending Offload:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Start Bulk Offload', 'windows-azure-storage' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'azure-bulk-offload', 'azure_bulk_offload_nonce' ); ?>
					<input type="hidden" name="action" value="azure_start_bulk_offload">

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="offload_target">
									<?php esc_html_e( 'Offload:', 'windows-azure-storage' ); ?>
								</label>
							</th>
							<td>
								<select name="offload_target" id="offload_target">
									<option value="pending"><?php esc_html_e( 'Pending Only (Recommended)', 'windows-azure-storage' ); ?></option>
									<option value="all"><?php esc_html_e( 'All Attachments (Force Re-upload)', 'windows-azure-storage' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose whether to offload only pending items or force re-upload of all items.', 'windows-azure-storage' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="remove_local">
									<?php esc_html_e( 'Remove Local Files:', 'windows-azure-storage' ); ?>
								</label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="remove_local" id="remove_local" value="1">
									<?php esc_html_e( 'Remove files from server after successful upload', 'windows-azure-storage' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'This will free up disk space but files must be downloaded from Azure if needed locally.', 'windows-azure-storage' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="limit">
									<?php esc_html_e( 'Limit:', 'windows-azure-storage' ); ?>
								</label>
							</th>
							<td>
								<input type="number" name="limit" id="limit" value="" min="1" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Optionally limit the number of items to process (leave blank for all).', 'windows-azure-storage' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Start Bulk Offload', 'windows-azure-storage' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render progress section.
	 *
	 * @param array $progress Progress data.
	 */
	private function render_progress_section( $progress ) {
		$percentage = $progress['total'] > 0 ? round( ( $progress['processed'] / $progress['total'] ) * 100, 1 ) : 0;
		$status_label = 'paused' === $progress['status'] ? __( 'Paused', 'windows-azure-storage' ) : __( 'Processing', 'windows-azure-storage' );

		?>
		<div class="azure-bulk-offload-progress" id="azure-offload-progress">
			<div class="card">
				<h2>
					<?php esc_html_e( 'Bulk Offload Progress', 'windows-azure-storage' ); ?>
					<span class="status-label status-<?php echo esc_attr( $progress['status'] ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</h2>

				<div class="progress-bar-wrapper">
					<div class="progress-bar">
						<div class="progress-fill" style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
					</div>
					<div class="progress-stats">
						<span class="progress-percentage"><?php echo esc_html( $percentage ); ?>%</span>
						<span class="progress-count">
							<?php
							printf(
								/* translators: 1: processed count, 2: total count */
								esc_html__( '%1$s of %2$s items', 'windows-azure-storage' ),
								'<span class="processed-count">' . esc_html( number_format_i18n( $progress['processed'] ) ) . '</span>',
								'<span class="total-count">' . esc_html( number_format_i18n( $progress['total'] ) ) . '</span>'
							);
							?>
						</span>
					</div>
				</div>

				<div class="progress-details">
					<table class="widefat">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Successful:', 'windows-azure-storage' ); ?></strong></td>
								<td class="successful-count"><?php echo esc_html( number_format_i18n( $progress['successful'] ) ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Failed:', 'windows-azure-storage' ); ?></strong></td>
								<td class="failed-count"><?php echo esc_html( number_format_i18n( $progress['failed'] ) ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Skipped:', 'windows-azure-storage' ); ?></strong></td>
								<td class="skipped-count"><?php echo esc_html( number_format_i18n( $progress['skipped'] ) ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="progress-actions">
					<?php if ( 'paused' === $progress['status'] ) : ?>
						<button type="button" class="button button-primary" id="resume-offload">
							<?php esc_html_e( 'Resume', 'windows-azure-storage' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button" id="pause-offload">
							<?php esc_html_e( 'Pause', 'windows-azure-storage' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="button button-secondary" id="cancel-offload">
						<?php esc_html_e( 'Cancel', 'windows-azure-storage' ); ?>
					</button>
				</div>

				<?php if ( ! empty( $progress['errors'] ) ) : ?>
					<div class="progress-errors">
						<h3><?php esc_html_e( 'Recent Errors', 'windows-azure-storage' ); ?></h3>
						<ul>
							<?php foreach ( array_slice( $progress['errors'], -5 ) as $error ) : ?>
								<li>
									<strong><?php esc_html_e( 'Attachment', 'windows-azure-storage' ); ?> #<?php echo esc_html( $error['attachment_id'] ); ?>:</strong>
									<?php echo esc_html( $error['message'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render completed section.
	 *
	 * @param array $progress Progress data.
	 */
	private function render_completed_section( $progress ) {
		$duration = isset( $progress['completed_at'] ) && isset( $progress['started_at'] )
			? $progress['completed_at'] - $progress['started_at']
			: 0;

		?>
		<div class="azure-bulk-offload-completed">
			<div class="card">
				<h2><?php esc_html_e( 'Last Completed Offload', 'windows-azure-storage' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Total Processed:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $progress['processed'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Successful:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $progress['successful'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Failed:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( number_format_i18n( $progress['failed'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Duration:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( human_time_diff( $progress['started_at'], $progress['completed_at'] ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Completed:', 'windows-azure-storage' ); ?></strong></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $progress['completed_at'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="clear-progress">
						<?php esc_html_e( 'Clear and Start New Offload', 'windows-azure-storage' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle start offload form submission.
	 */
	public function handle_start_offload() {
		// Verify nonce.
		if ( ! isset( $_POST['azure_bulk_offload_nonce'] ) || ! wp_verify_nonce( $_POST['azure_bulk_offload_nonce'], 'azure-bulk-offload' ) ) {
			wp_die( esc_html__( 'Security check failed', 'windows-azure-storage' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'windows-azure-storage' ) );
		}

		// Get form data.
		$offload_target = isset( $_POST['offload_target'] ) ? sanitize_text_field( $_POST['offload_target'] ) : 'pending';
		$remove_local   = isset( $_POST['remove_local'] );
		$limit          = isset( $_POST['limit'] ) && ! empty( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 0;

		// Get attachment IDs.
		$attachment_ids = $this->get_attachment_ids_for_offload( $offload_target, $limit );

		if ( empty( $attachment_ids ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'page'    => 'azure-bulk-offload',
						'message' => 'no_attachments',
					),
					admin_url( 'upload.php' )
				)
			);
			exit;
		}

		// Start bulk offload.
		$options = array(
			'force'        => 'all' === $offload_target,
			'remove_local' => $remove_local,
		);

		$result = $this->processor->start_bulk_offload( $attachment_ids, $options );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect back to the page.
		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'azure-bulk-offload',
					'message' => 'started',
				),
				admin_url( 'upload.php' )
			)
		);
		exit;
	}

	/**
	 * Get total attachment count.
	 *
	 * @return int Attachment count.
	 */
	private function get_attachment_count() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_mime_type' => 'image',
			)
		);

		return $query->found_posts;
	}

	/**
	 * Get offloaded attachment count.
	 *
	 * @return int Offloaded count.
	 */
	private function get_offloaded_count() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_mime_type' => 'image',
				'meta_query'     => array(
					array(
						'key'   => '_azure_offload_status',
						'value' => 'offloaded',
					),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Get attachment IDs for offload.
	 *
	 * @param string $target Target (pending or all).
	 * @param int    $limit  Optional limit.
	 * @return array Attachment IDs.
	 */
	private function get_attachment_ids_for_offload( $target, $limit = 0 ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'fields'         => 'ids',
			'post_mime_type' => 'image',
		);

		// If pending only, exclude already offloaded.
		if ( 'pending' === $target ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_azure_offload_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_azure_offload_status',
					'value'   => 'offloaded',
					'compare' => '!=',
				),
			);
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}
}
