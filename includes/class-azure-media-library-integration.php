<?php
/**
 * Azure Media Library Integration
 *
 * Enhances the WordPress Media Library with Azure Storage status indicators,
 * bulk actions, filters, and quick actions.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Media_Library_Integration class.
 *
 * Adds Azure Storage integration to the WordPress Media Library.
 */
class Azure_Media_Library_Integration {

	/**
	 * Background processor instance.
	 *
	 * @var Azure_Background_Processor
	 */
	private $background_processor;

	/**
	 * File manager instance.
	 *
	 * @var Azure_Local_File_Manager
	 */
	private $file_manager;

	/**
	 * Constructor.
	 *
	 * @param Azure_Background_Processor $background_processor Background processor instance.
	 */
	public function __construct( $background_processor ) {
		$this->background_processor = $background_processor;
		$this->file_manager = new Azure_Local_File_Manager();

		// Media Library list table customizations.
		add_filter( 'manage_media_columns', array( $this, 'add_azure_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_azure_column' ), 10, 2 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'add_sortable_columns' ) );

		// Bulk actions.
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );

		// Filter dropdown.
		add_action( 'restrict_manage_posts', array( $this, 'add_azure_filter' ) );
		add_filter( 'pre_get_posts', array( $this, 'filter_by_azure_status' ) );

		// Attachment edit screen meta box.
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_azure_meta_box' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_azure_copy_url', array( $this, 'ajax_copy_url' ) );
		add_action( 'wp_ajax_azure_view_on_azure', array( $this, 'ajax_view_on_azure' ) );

		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'display_bulk_action_notices' ) );
	}

	/**
	 * Add Azure column to Media Library.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_azure_column( $columns ) {
		$new_columns = array();

		// Insert Azure column after thumbnail.
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['azure_status'] = __( 'Azure Status', 'windows-azure-storage' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render Azure column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 */
	public function render_azure_column( $column_name, $post_id ) {
		if ( 'azure_status' !== $column_name ) {
			return;
		}

		$status = get_post_meta( $post_id, '_azure_offload_status', true );
		$azure_url = get_post_meta( $post_id, '_azure_blob_url', true );

		if ( 'offloaded' === $status ) {
			echo '<span class="azure-status azure-status-offloaded" title="' . esc_attr__( 'Offloaded to Azure', 'windows-azure-storage' ) . '">';
			echo '<span class="dashicons dashicons-cloud" style="color: #00a32a;"></span> ';
			echo esc_html__( 'Offloaded', 'windows-azure-storage' );
			echo '</span>';

			// Quick actions.
			if ( ! empty( $azure_url ) ) {
				echo '<div class="row-actions">';
				echo '<span class="azure-view">';
				echo '<a href="' . esc_url( $azure_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View on Azure', 'windows-azure-storage' ) . '</a> | ';
				echo '</span>';
				echo '<span class="azure-copy">';
				echo '<a href="#" class="azure-copy-url" data-url="' . esc_attr( $azure_url ) . '" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Copy URL', 'windows-azure-storage' ) . '</a>';
				echo '</span>';
				echo '</div>';
			}
		} elseif ( 'failed' === $status ) {
			echo '<span class="azure-status azure-status-failed" title="' . esc_attr__( 'Upload failed', 'windows-azure-storage' ) . '">';
			echo '<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ';
			echo esc_html__( 'Failed', 'windows-azure-storage' );
			echo '</span>';
		} else {
			echo '<span class="azure-status azure-status-pending" title="' . esc_attr__( 'Not yet offloaded', 'windows-azure-storage' ) . '">';
			echo '<span class="dashicons dashicons-minus" style="color: #999;"></span> ';
			echo esc_html__( 'Pending', 'windows-azure-storage' );
			echo '</span>';
		}
	}

	/**
	 * Add sortable columns.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function add_sortable_columns( $columns ) {
		$columns['azure_status'] = 'azure_status';
		return $columns;
	}

	/**
	 * Add bulk actions to Media Library.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( $actions ) {
		$actions['azure_offload'] = __( 'Offload to Azure', 'windows-azure-storage' );
		$actions['azure_remove'] = __( 'Remove from Azure', 'windows-azure-storage' );
		$actions['azure_remove_local'] = __( 'Remove Local Files', 'windows-azure-storage' );
		$actions['azure_download'] = __( 'Download from Azure', 'windows-azure-storage' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction     Action being performed.
	 * @param array  $post_ids     Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'azure_offload' === $doaction ) {
			if ( ! $this->background_processor->is_action_scheduler_available() ) {
				$redirect_to = add_query_arg( 'azure_bulk_error', 'no_scheduler', $redirect_to );
				return $redirect_to;
			}

			$result = $this->background_processor->start_bulk_offload( $post_ids, array() );

			if ( is_wp_error( $result ) ) {
				$redirect_to = add_query_arg( 'azure_bulk_error', 'failed', $redirect_to );
			} else {
				$redirect_to = add_query_arg( 'azure_bulk_offloaded', count( $post_ids ), $redirect_to );
			}
		} elseif ( 'azure_remove' === $doaction ) {
			$removed = 0;

			foreach ( $post_ids as $post_id ) {
				$status = get_post_meta( $post_id, '_azure_offload_status', true );

				if ( 'offloaded' === $status ) {
					$media_info = get_post_meta( $post_id, 'windows_azure_storage_info', true );

					if ( ! empty( $media_info ) ) {
						// Delete from Azure.
						$container_name = $media_info['container'];
						$blob_name      = $media_info['blob'];

						\Windows_Azure_Helper::delete_blob( $container_name, $blob_name );

						// Delete thumbnails.
						if ( ! empty( $media_info['thumbnails'] ) ) {
							foreach ( $media_info['thumbnails'] as $thumbnail_blob ) {
								\Windows_Azure_Helper::delete_blob( $container_name, $thumbnail_blob );
							}
						}

						// Update meta.
						delete_post_meta( $post_id, '_azure_offload_status' );
						delete_post_meta( $post_id, '_azure_blob_url' );
						delete_post_meta( $post_id, '_azure_offload_date' );
						delete_post_meta( $post_id, 'windows_azure_storage_info' );

						$removed++;
					}
				}
			}

			$redirect_to = add_query_arg( 'azure_bulk_removed', $removed, $redirect_to );
		} elseif ( 'azure_remove_local' === $doaction ) {
			$result = $this->file_manager->bulk_remove_local_files( $post_ids, true );

			if ( $result['removed'] > 0 ) {
				$redirect_to = add_query_arg( 'azure_local_removed', $result['removed'], $redirect_to );
			}

			if ( $result['failed'] > 0 ) {
				$redirect_to = add_query_arg( 'azure_local_remove_failed', $result['failed'], $redirect_to );
			}
		} elseif ( 'azure_download' === $doaction ) {
			$result = $this->file_manager->bulk_download_from_azure( $post_ids, false );

			if ( $result['downloaded'] > 0 ) {
				$redirect_to = add_query_arg( 'azure_downloaded', $result['downloaded'], $redirect_to );
			}

			if ( $result['failed'] > 0 ) {
				$redirect_to = add_query_arg( 'azure_download_failed', $result['failed'], $redirect_to );
			}
		}

		return $redirect_to;
	}

	/**
	 * Add Azure filter dropdown.
	 *
	 * @param string $post_type Post type.
	 */
	public function add_azure_filter( $post_type ) {
		if ( 'attachment' !== $post_type ) {
			return;
		}

		$current_filter = isset( $_GET['azure_status'] ) ? sanitize_text_field( wp_unslash( $_GET['azure_status'] ) ) : '';

		?>
		<select name="azure_status" id="azure-status-filter">
			<option value=""><?php esc_html_e( 'All Azure Statuses', 'windows-azure-storage' ); ?></option>
			<option value="offloaded" <?php selected( $current_filter, 'offloaded' ); ?>>
				<?php esc_html_e( 'Offloaded', 'windows-azure-storage' ); ?>
			</option>
			<option value="pending" <?php selected( $current_filter, 'pending' ); ?>>
				<?php esc_html_e( 'Pending', 'windows-azure-storage' ); ?>
			</option>
			<option value="failed" <?php selected( $current_filter, 'failed' ); ?>>
				<?php esc_html_e( 'Failed', 'windows-azure-storage' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Filter posts by Azure status.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function filter_by_azure_status( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'upload.php' !== $pagenow ) {
			return;
		}

		if ( ! isset( $_GET['azure_status'] ) || empty( $_GET['azure_status'] ) ) {
			return;
		}

		$azure_status = sanitize_text_field( wp_unslash( $_GET['azure_status'] ) );

		$meta_query = $query->get( 'meta_query' ) ?: array();

		if ( 'pending' === $azure_status ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_azure_offload_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_azure_offload_status',
					'value'   => array( 'offloaded', 'failed' ),
					'compare' => 'NOT IN',
				),
			);
		} else {
			$meta_query[] = array(
				'key'   => '_azure_offload_status',
				'value' => $azure_status,
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Add Azure meta box to attachment edit screen.
	 *
	 * @param WP_Post $post Attachment post object.
	 */
	public function add_azure_meta_box( $post ) {
		add_meta_box(
			'azure-storage-info',
			__( 'Azure Storage', 'windows-azure-storage' ),
			array( $this, 'render_azure_meta_box' ),
			'attachment',
			'side',
			'default'
		);
	}

	/**
	 * Render Azure meta box content.
	 *
	 * @param WP_Post $post Attachment post object.
	 */
	public function render_azure_meta_box( $post ) {
		$status = get_post_meta( $post->ID, '_azure_offload_status', true );
		$azure_url = get_post_meta( $post->ID, '_azure_blob_url', true );
		$offload_date = get_post_meta( $post->ID, '_azure_offload_date', true );
		$media_info = get_post_meta( $post->ID, 'windows_azure_storage_info', true );

		?>
		<div class="azure-meta-box">
			<p>
				<strong><?php esc_html_e( 'Status:', 'windows-azure-storage' ); ?></strong>
				<?php
				if ( 'offloaded' === $status ) {
					echo '<span style="color: #00a32a;">✓ ' . esc_html__( 'Offloaded', 'windows-azure-storage' ) . '</span>';
				} elseif ( 'failed' === $status ) {
					echo '<span style="color: #d63638;">✗ ' . esc_html__( 'Failed', 'windows-azure-storage' ) . '</span>';
				} else {
					echo '<span style="color: #999;">⏳ ' . esc_html__( 'Pending', 'windows-azure-storage' ) . '</span>';
				}
				?>
			</p>

			<?php if ( 'offloaded' === $status && ! empty( $azure_url ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Azure URL:', 'windows-azure-storage' ); ?></strong><br>
					<input type="text" readonly value="<?php echo esc_attr( $azure_url ); ?>" style="width: 100%; font-size: 11px;" onclick="this.select();">
				</p>

				<?php if ( $offload_date ) : ?>
					<p>
						<strong><?php esc_html_e( 'Offloaded:', 'windows-azure-storage' ); ?></strong><br>
						<?php echo esc_html( human_time_diff( $offload_date, time() ) . ' ' . __( 'ago', 'windows-azure-storage' ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $media_info ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Container:', 'windows-azure-storage' ); ?></strong><br>
						<code><?php echo esc_html( $media_info['container'] ); ?></code>
					</p>

					<p>
						<strong><?php esc_html_e( 'Blob Path:', 'windows-azure-storage' ); ?></strong><br>
						<code><?php echo esc_html( $media_info['blob'] ); ?></code>
					</p>

					<?php if ( ! empty( $media_info['thumbnails'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Thumbnails:', 'windows-azure-storage' ); ?></strong><br>
							<?php echo esc_html( count( $media_info['thumbnails'] ) . ' ' . __( 'sizes offloaded', 'windows-azure-storage' ) ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<p>
					<a href="<?php echo esc_url( $azure_url ); ?>" target="_blank" rel="noopener" class="button button-secondary">
						<?php esc_html_e( 'View on Azure', 'windows-azure-storage' ); ?>
					</a>
				</p>
			<?php elseif ( 'pending' === $status || empty( $status ) ) : ?>
				<p>
					<?php esc_html_e( 'This file has not been offloaded to Azure yet.', 'windows-azure-storage' ); ?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: Link to bulk offload page */
						esc_html__( 'You can offload it using the %s tool.', 'windows-azure-storage' ),
						'<a href="' . esc_url( admin_url( 'upload.php?page=azure-bulk-offload' ) ) . '">' . esc_html__( 'Bulk Offload', 'windows-azure-storage' ) . '</a>'
					);
					?>
				</p>
			<?php elseif ( 'failed' === $status ) : ?>
				<p style="color: #d63638;">
					<?php esc_html_e( 'The upload to Azure failed. Please try again or check the error logs.', 'windows-azure-storage' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Copy Azure URL to clipboard.
	 */
	public function ajax_copy_url() {
		check_ajax_referer( 'azure-media-library', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'windows-azure-storage' ) ) );
		}

		$azure_url = get_post_meta( $post_id, '_azure_blob_url', true );

		if ( empty( $azure_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Azure URL not found', 'windows-azure-storage' ) ) );
		}

		wp_send_json_success( array( 'url' => $azure_url ) );
	}

	/**
	 * AJAX handler: View on Azure.
	 */
	public function ajax_view_on_azure() {
		check_ajax_referer( 'azure-media-library', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'windows-azure-storage' ) ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'windows-azure-storage' ) ) );
		}

		$azure_url = get_post_meta( $post_id, '_azure_blob_url', true );

		if ( empty( $azure_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Azure URL not found', 'windows-azure-storage' ) ) );
		}

		wp_send_json_success( array( 'url' => $azure_url ) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on Media Library and attachment edit pages.
		if ( 'upload.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ( 'upload' !== $screen->id && 'attachment' !== $screen->id ) ) {
			return;
		}

		wp_enqueue_script(
			'azure-media-library',
			MSFT_AZURE_PLUGIN_URL . 'assets/js/media-library-ajax.js',
			array( 'jquery' ),
			MSFT_AZURE_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'azure-media-library',
			'azureMediaLibrary',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'azure-media-library' ),
				'i18n'    => array(
					'copied'       => __( 'URL copied to clipboard!', 'windows-azure-storage' ),
					'copyFailed'   => __( 'Failed to copy URL', 'windows-azure-storage' ),
					'confirmRemove' => __( 'Are you sure you want to remove these files from Azure? Local copies will remain.', 'windows-azure-storage' ),
				),
			)
		);
	}

	/**
	 * Display admin notices for bulk actions.
	 */
	public function display_bulk_action_notices() {
		if ( isset( $_GET['azure_bulk_offloaded'] ) ) {
			$count = intval( $_GET['azure_bulk_offloaded'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of files queued */
						esc_html( _n( '%d file queued for offloading to Azure.', '%d files queued for offloading to Azure.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'upload.php?page=azure-bulk-offload' ) ); ?>">
						<?php esc_html_e( 'View progress', 'windows-azure-storage' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_bulk_removed'] ) ) {
			$count = intval( $_GET['azure_bulk_removed'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of files removed */
						esc_html( _n( '%d file removed from Azure.', '%d files removed from Azure.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_bulk_error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['azure_bulk_error'] ) );
			$message = '';

			if ( 'no_scheduler' === $error ) {
				$message = __( 'Action Scheduler is required for bulk offloading. Please install WooCommerce or another plugin that includes Action Scheduler.', 'windows-azure-storage' );
			} else {
				$message = __( 'Failed to start bulk offload operation. Please try again.', 'windows-azure-storage' );
			}

			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_local_removed'] ) ) {
			$count = intval( $_GET['azure_local_removed'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of local files removed */
						esc_html( _n( '%d local file removed. Files remain on Azure.', '%d local files removed. Files remain on Azure.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_local_remove_failed'] ) ) {
			$count = intval( $_GET['azure_local_remove_failed'] );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of files that failed to remove */
						esc_html( _n( '%d file could not be removed from local storage.', '%d files could not be removed from local storage.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_downloaded'] ) ) {
			$count = intval( $_GET['azure_downloaded'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of files downloaded */
						esc_html( _n( '%d file downloaded from Azure to local storage.', '%d files downloaded from Azure to local storage.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['azure_download_failed'] ) ) {
			$count = intval( $_GET['azure_download_failed'] );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: Number of files that failed to download */
						esc_html( _n( '%d file could not be downloaded from Azure.', '%d files could not be downloaded from Azure.', $count, 'windows-azure-storage' ) ),
						esc_html( number_format_i18n( $count ) )
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}
