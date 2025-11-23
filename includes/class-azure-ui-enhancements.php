<?php
/**
 * Azure UI Enhancements
 *
 * Provides improved user interface and user experience for Azure Storage plugin.
 * Includes dashboard widgets, quick stats, and streamlined workflows.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_UI_Enhancements class.
 *
 * Handles UI/UX improvements for the plugin.
 */
class Azure_UI_Enhancements {

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
	 * CDN manager instance.
	 *
	 * @var Azure_CDN_Manager
	 */
	private $cdn_manager;

	/**
	 * Constructor.
	 *
	 * @param Azure_Background_Processor $background_processor Background processor instance.
	 */
	public function __construct( $background_processor ) {
		$this->background_processor = $background_processor;
		$this->file_manager = new Azure_Local_File_Manager();
		$this->cdn_manager = new Azure_CDN_Manager();

		// Add dashboard widget.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		// Add admin bar menu.
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );

		// Enqueue admin styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		// Add quick actions to media library.
		add_filter( 'media_row_actions', array( $this, 'add_media_row_actions' ), 10, 2 );
	}

	/**
	 * Add dashboard widget.
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'azure_storage_stats',
			__( 'Azure Storage Overview', 'windows-azure-storage' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		// Get offload statistics.
		$offloaded_count = $this->get_offloaded_count();
		$pending_count = $this->get_pending_count();
		$failed_count = $this->get_failed_count();
		$total_count = $this->get_total_media_count();

		// Get disk space stats.
		$space_stats = $this->get_disk_space_stats();

		// Get CDN stats.
		$cdn_stats = $this->cdn_manager->get_cdn_stats();

		// Get background job progress.
		$progress = $this->background_processor->get_progress();

		?>
		<div class="azure-dashboard-widget">
			<div class="azure-stats-grid">
				<!-- Offload Stats -->
				<div class="azure-stat-card">
					<h4><?php esc_html_e( 'Media Offloading', 'windows-azure-storage' ); ?></h4>
					<div class="azure-stat-value"><?php echo esc_html( number_format_i18n( $offloaded_count ) ); ?></div>
					<div class="azure-stat-label"><?php esc_html_e( 'Files on Azure', 'windows-azure-storage' ); ?></div>
					<div class="azure-stat-progress">
						<?php
						$percentage = $total_count > 0 ? round( ( $offloaded_count / $total_count ) * 100 ) : 0;
						?>
						<div class="progress-bar" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
					</div>
					<div class="azure-stat-meta">
						<?php
						printf(
							/* translators: %1$d offloaded, %2$d total */
							esc_html__( '%1$d of %2$d files (%3$d%%)', 'windows-azure-storage' ),
							esc_html( number_format_i18n( $offloaded_count ) ),
							esc_html( number_format_i18n( $total_count ) ),
							esc_html( $percentage )
						);
						?>
					</div>
				</div>

				<!-- Disk Space Savings -->
				<div class="azure-stat-card">
					<h4><?php esc_html_e( 'Disk Space Saved', 'windows-azure-storage' ); ?></h4>
					<div class="azure-stat-value"><?php echo esc_html( $space_stats['saved_formatted'] ); ?></div>
					<div class="azure-stat-label"><?php esc_html_e( 'Local Storage Freed', 'windows-azure-storage' ); ?></div>
					<div class="azure-stat-meta">
						<?php
						printf(
							/* translators: %s: formatted size */
							esc_html__( 'Potential savings: %s', 'windows-azure-storage' ),
							esc_html( $space_stats['potential_formatted'] )
						);
						?>
					</div>
				</div>

				<!-- CDN Stats -->
				<?php if ( $cdn_stats['enabled'] ) : ?>
				<div class="azure-stat-card">
					<h4><?php esc_html_e( 'CDN Cache', 'windows-azure-storage' ); ?></h4>
					<div class="azure-stat-value"><?php echo esc_html( number_format_i18n( $cdn_stats['total_purges'] ) ); ?></div>
					<div class="azure-stat-label"><?php esc_html_e( 'Total Purges', 'windows-azure-storage' ); ?></div>
					<div class="azure-stat-meta">
						<?php if ( $cdn_stats['last_purge'] > 0 ) : ?>
							<?php
							printf(
								/* translators: %s: time ago */
								esc_html__( 'Last purge: %s ago', 'windows-azure-storage' ),
								esc_html( human_time_diff( $cdn_stats['last_purge'], time() ) )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'No purges yet', 'windows-azure-storage' ); ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Queue Status -->
				<?php if ( 'active' === $progress['status'] || 'paused' === $progress['status'] ) : ?>
				<div class="azure-stat-card azure-stat-warning">
					<h4><?php esc_html_e( 'Background Jobs', 'windows-azure-storage' ); ?></h4>
					<div class="azure-stat-value"><?php echo esc_html( $progress['progress'] . '%' ); ?></div>
					<div class="azure-stat-label">
						<?php
						printf(
							/* translators: %1$d processed, %2$d total */
							esc_html__( '%1$d of %2$d items', 'windows-azure-storage' ),
							esc_html( $progress['processed'] ),
							esc_html( $progress['total'] )
						);
						?>
					</div>
					<div class="azure-stat-progress">
						<div class="progress-bar progress-bar-animated" style="width: <?php echo esc_attr( $progress['progress'] ); ?>%;"></div>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- Quick Actions -->
			<div class="azure-quick-actions">
				<h4><?php esc_html_e( 'Quick Actions', 'windows-azure-storage' ); ?></h4>
				<div class="azure-action-buttons">
					<a href="<?php echo esc_url( admin_url( 'upload.php?page=azure-bulk-offload' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Bulk Offload', 'windows-azure-storage' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=windows-azure-storage-plugin-options' ) ); ?>" class="button">
						<?php esc_html_e( 'Settings', 'windows-azure-storage' ); ?>
					</a>
					<?php if ( $pending_count > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'upload.php?azure_status=pending' ) ); ?>" class="button">
						<?php
						printf(
							/* translators: %d: number of pending files */
							esc_html__( 'View %d Pending', 'windows-azure-storage' ),
							esc_html( $pending_count )
						);
						?>
					</a>
					<?php endif; ?>
					<?php if ( $failed_count > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'upload.php?azure_status=failed' ) ); ?>" class="button button-secondary">
						<?php
						printf(
							/* translators: %d: number of failed files */
							esc_html__( 'View %d Failed', 'windows-azure-storage' ),
							esc_html( $failed_count )
						);
						?>
					</a>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( Azure_Debug_Logger::is_enabled() ) : ?>
			<!-- Debug Info -->
			<div class="azure-debug-info">
				<p>
					<strong><?php esc_html_e( 'Debug Mode:', 'windows-azure-storage' ); ?></strong>
					<?php esc_html_e( 'Enabled', 'windows-azure-storage' ); ?>
					&mdash;
					<a href="#" onclick="navigator.clipboard.writeText('<?php echo esc_js( Azure_Debug_Logger::get_log_file() ); ?>'); return false;">
						<?php esc_html_e( 'Copy log path', 'windows-azure-storage' ); ?>
					</a>
				</p>
			</div>
			<?php endif; ?>
		</div>

		<style>
			.azure-dashboard-widget {
				margin: -12px;
				padding: 12px;
			}
			.azure-stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 16px;
				margin-bottom: 20px;
			}
			.azure-stat-card {
				background: #f8f9fa;
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 16px;
			}
			.azure-stat-card h4 {
				margin: 0 0 8px 0;
				font-size: 13px;
				color: #666;
				font-weight: 600;
				text-transform: uppercase;
			}
			.azure-stat-value {
				font-size: 28px;
				font-weight: bold;
				color: #2271b1;
				margin-bottom: 4px;
			}
			.azure-stat-label {
				font-size: 12px;
				color: #666;
				margin-bottom: 8px;
			}
			.azure-stat-progress {
				background: #e0e0e0;
				height: 6px;
				border-radius: 3px;
				overflow: hidden;
				margin: 8px 0;
			}
			.azure-stat-progress .progress-bar {
				background: #2271b1;
				height: 100%;
				transition: width 0.3s ease;
			}
			.azure-stat-progress .progress-bar-animated {
				background: linear-gradient(90deg, #2271b1, #72aee6, #2271b1);
				background-size: 200% 100%;
				animation: progress-animation 2s linear infinite;
			}
			@keyframes progress-animation {
				0% { background-position: 0% 0%; }
				100% { background-position: 200% 0%; }
			}
			.azure-stat-meta {
				font-size: 11px;
				color: #888;
			}
			.azure-stat-warning {
				border-color: #f0ad4e;
				background: #fff9e6;
			}
			.azure-quick-actions h4 {
				margin: 0 0 12px 0;
				font-size: 14px;
			}
			.azure-action-buttons {
				display: flex;
				gap: 8px;
				flex-wrap: wrap;
			}
			.azure-debug-info {
				margin-top: 16px;
				padding-top: 16px;
				border-top: 1px solid #ddd;
				font-size: 12px;
				color: #666;
			}
		</style>
		<?php
	}

	/**
	 * Add admin bar menu.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$progress = $this->background_processor->get_progress();

		$wp_admin_bar->add_node( array(
			'id'    => 'azure-storage',
			'title' => '<span class="ab-icon dashicons dashicons-cloud"></span> Azure Storage',
			'href'  => admin_url( 'upload.php?page=azure-bulk-offload' ),
		) );

		// Add progress submenu if active.
		if ( 'active' === $progress['status'] ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'azure-storage',
				'id'     => 'azure-progress',
				'title'  => sprintf(
					/* translators: %d: progress percentage */
					__( 'Offloading: %d%%', 'windows-azure-storage' ),
					$progress['progress']
				),
				'href'   => admin_url( 'upload.php?page=azure-bulk-offload' ),
			) );
		}

		$wp_admin_bar->add_node( array(
			'parent' => 'azure-storage',
			'id'     => 'azure-bulk-offload',
			'title'  => __( 'Bulk Offload', 'windows-azure-storage' ),
			'href'   => admin_url( 'upload.php?page=azure-bulk-offload' ),
		) );

		$wp_admin_bar->add_node( array(
			'parent' => 'azure-storage',
			'id'     => 'azure-settings',
			'title'  => __( 'Settings', 'windows-azure-storage' ),
			'href'   => admin_url( 'options-general.php?page=windows-azure-storage-plugin-options' ),
		) );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	public function enqueue_admin_styles() {
		wp_add_inline_style( 'wp-admin', '
			#wp-admin-bar-azure-storage .ab-icon:before {
				content: "\f176";
				top: 2px;
			}
			#wp-admin-bar-azure-progress {
				background: rgba(240, 173, 78, 0.1);
			}
		' );
	}

	/**
	 * Add quick actions to media library rows.
	 *
	 * @param array   $actions Current actions.
	 * @param WP_Post $post    Post object.
	 * @return array Modified actions.
	 */
	public function add_media_row_actions( $actions, $post ) {
		$status = get_post_meta( $post->ID, '_azure_offload_status', true );

		// Add "Offload to Azure" action for non-offloaded files.
		if ( 'offloaded' !== $status ) {
			$actions['azure_offload'] = sprintf(
				'<a href="#" class="azure-quick-offload" data-id="%d">%s</a>',
				$post->ID,
				__( 'Offload to Azure', 'windows-azure-storage' )
			);
		}

		return $actions;
	}

	/**
	 * Get count of offloaded media.
	 *
	 * @return int Count.
	 */
	private function get_offloaded_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = '_azure_offload_status'
			AND meta_value = 'offloaded'"
		);
	}

	/**
	 * Get count of pending media.
	 *
	 * @return int Count.
	 */
	private function get_pending_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_azure_offload_status'
			WHERE p.post_type = 'attachment'
			AND (pm.meta_value IS NULL OR pm.meta_value NOT IN ('offloaded', 'failed'))"
		);
	}

	/**
	 * Get count of failed media.
	 *
	 * @return int Count.
	 */
	private function get_failed_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = '_azure_offload_status'
			AND meta_value = 'failed'"
		);
	}

	/**
	 * Get total media count.
	 *
	 * @return int Count.
	 */
	private function get_total_media_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'"
		);
	}

	/**
	 * Get disk space statistics.
	 *
	 * @return array Space stats.
	 */
	private function get_disk_space_stats() {
		global $wpdb;

		// Get offloaded files with local removed.
		$removed_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_azure_local_removed'
			AND meta_value = '1'"
		);

		$saved = 0;
		foreach ( $removed_ids as $id ) {
			$saved += $this->file_manager->calculate_local_size( $id );
		}

		// Get potential savings (offloaded but still local).
		$offloaded_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_azure_offload_status'
			AND meta_value = 'offloaded'"
		);

		$potential = 0;
		foreach ( $offloaded_ids as $id ) {
			$local_removed = get_post_meta( $id, '_azure_local_removed', true );
			if ( ! $local_removed ) {
				$potential += $this->file_manager->calculate_local_size( $id );
			}
		}

		return array(
			'saved'               => $saved,
			'saved_formatted'     => size_format( $saved, 2 ),
			'potential'           => $potential,
			'potential_formatted' => size_format( $potential, 2 ),
		);
	}
}
