<?php
/**
 * Azure Plugin Compatibility Manager
 *
 * Ensures compatibility with popular WordPress plugins like ShortPixel,
 * Regenerate Thumbnails, EWWW Image Optimizer, and others.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Plugin_Compatibility class.
 *
 * Handles compatibility with third-party WordPress plugins.
 */
class Azure_Plugin_Compatibility {

	/**
	 * File manager instance.
	 *
	 * @var Azure_Local_File_Manager
	 */
	private $file_manager;

	/**
	 * Background processor instance.
	 *
	 * @var Azure_Background_Processor
	 */
	private $background_processor;

	/**
	 * Whether ShortPixel is active.
	 *
	 * @var bool
	 */
	private $shortpixel_active = false;

	/**
	 * Whether EWWW Image Optimizer is active.
	 *
	 * @var bool
	 */
	private $ewww_active = false;

	/**
	 * Constructor.
	 *
	 * @param Azure_Background_Processor $background_processor Background processor instance.
	 */
	public function __construct( $background_processor ) {
		$this->background_processor = $background_processor;
		$this->file_manager = new Azure_Local_File_Manager();

		// Detect active plugins.
		$this->detect_active_plugins();

		// Initialize compatibility hooks.
		$this->init_hooks();
	}

	/**
	 * Detect active compatible plugins.
	 *
	 * @return void
	 */
	private function detect_active_plugins() {
		// ShortPixel Image Optimizer.
		$this->shortpixel_active = class_exists( 'WPShortPixel' ) || class_exists( 'ShortPixelAPI' );

		// EWWW Image Optimizer.
		$this->ewww_active = class_exists( 'EWWW_Image_Optimizer' ) || defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' );

		Azure_Debug_Logger::info(
			'Plugin compatibility detection',
			array(
				'shortpixel' => $this->shortpixel_active,
				'ewww'       => $this->ewww_active,
			)
		);
	}

	/**
	 * Initialize compatibility hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// ShortPixel integration.
		if ( $this->shortpixel_active ) {
			$this->init_shortpixel_hooks();
		}

		// EWWW Image Optimizer integration.
		if ( $this->ewww_active ) {
			$this->init_ewww_hooks();
		}

		// Regenerate Thumbnails (core or plugin).
		$this->init_regenerate_thumbnails_hooks();

		// WP-CLI Media Regenerate.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->init_wp_cli_hooks();
		}
	}

	/**
	 * Initialize ShortPixel hooks.
	 *
	 * @return void
	 */
	private function init_shortpixel_hooks() {
		// Before ShortPixel optimization: ensure local file exists.
		add_action( 'shortpixel_before_restore_image', array( $this, 'ensure_local_file_before_processing' ), 10, 1 );
		add_action( 'shortpixel_before_processing', array( $this, 'ensure_local_file_before_processing' ), 10, 1 );

		// After ShortPixel optimization: re-upload to Azure.
		add_action( 'shortpixel_after_processing', array( $this, 'reupload_after_shortpixel' ), 10, 2 );
		add_action( 'shortpixel_image_optimised', array( $this, 'reupload_after_shortpixel' ), 10, 2 );

		Azure_Debug_Logger::info( 'ShortPixel compatibility hooks initialized' );
	}

	/**
	 * Initialize EWWW Image Optimizer hooks.
	 *
	 * @return void
	 */
	private function init_ewww_hooks() {
		// Before EWWW optimization: ensure local file exists.
		add_filter( 'ewww_image_optimizer_pre_optimization', array( $this, 'ensure_local_file_before_processing' ), 10, 1 );

		// After EWWW optimization: re-upload to Azure.
		add_filter( 'ewww_image_optimizer_post_optimization', array( $this, 'reupload_after_ewww' ), 10, 2 );

		Azure_Debug_Logger::info( 'EWWW Image Optimizer compatibility hooks initialized' );
	}

	/**
	 * Initialize Regenerate Thumbnails hooks.
	 *
	 * @return void
	 */
	private function init_regenerate_thumbnails_hooks() {
		// Before regenerate: ensure local file exists.
		add_action( 'begin_generate_attachment_metadata', array( $this, 'ensure_local_file_before_processing' ), 10, 2 );

		// After regenerate: re-upload to Azure.
		add_filter( 'wp_update_attachment_metadata', array( $this, 'reupload_after_regenerate' ), 999, 2 );

		Azure_Debug_Logger::info( 'Regenerate Thumbnails compatibility hooks initialized' );
	}

	/**
	 * Initialize WP-CLI hooks.
	 *
	 * @return void
	 */
	private function init_wp_cli_hooks() {
		// WP-CLI media regenerate command.
		add_action( 'wp_cli_media_regenerate_begin', array( $this, 'ensure_local_file_before_processing' ), 10, 1 );

		Azure_Debug_Logger::info( 'WP-CLI compatibility hooks initialized' );
	}

	/**
	 * Ensure local file exists before plugin processing.
	 *
	 * Downloads from Azure if file is offloaded and local copy doesn't exist.
	 *
	 * @param int|array $attachment_id Attachment ID or data array.
	 * @return int|array Unmodified attachment ID/data.
	 */
	public function ensure_local_file_before_processing( $attachment_id ) {
		// Handle array input (some hooks pass arrays).
		if ( is_array( $attachment_id ) ) {
			$id = isset( $attachment_id['ID'] ) ? $attachment_id['ID'] : 0;
		} else {
			$id = $attachment_id;
		}

		if ( ! $id ) {
			return $attachment_id;
		}

		// Check if file is offloaded to Azure.
		$status = get_post_meta( $id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return $attachment_id;
		}

		// Check if local file exists.
		$file_path = get_attached_file( $id );
		if ( file_exists( $file_path ) ) {
			Azure_Debug_Logger::debug(
				sprintf( 'Local file already exists for attachment #%d', $id )
			);
			return $attachment_id;
		}

		// Download from Azure.
		Azure_Debug_Logger::info(
			sprintf( 'Downloading attachment #%d from Azure for plugin processing', $id )
		);

		$result = $this->file_manager->download_from_azure( $id, true );

		if ( is_wp_error( $result ) ) {
			Azure_Debug_Logger::log_wp_error( $result, 'ensure_local_file_before_processing' );
		} else {
			Azure_Debug_Logger::info(
				sprintf( 'Successfully downloaded attachment #%d for processing', $id )
			);
		}

		return $attachment_id;
	}

	/**
	 * Re-upload to Azure after ShortPixel optimization.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $result        Optimization result.
	 * @return void
	 */
	public function reupload_after_shortpixel( $attachment_id, $result = array() ) {
		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return;
		}

		Azure_Debug_Logger::info(
			sprintf( 'Re-uploading attachment #%d to Azure after ShortPixel optimization', $attachment_id ),
			array( 'result' => $result )
		);

		// Re-upload using background processor.
		$this->background_processor->push_to_queue( array(
			'attachment_id' => $attachment_id,
			'action'        => 'offload',
			'force'         => true, // Force re-upload.
		) );

		$this->background_processor->save()->dispatch();
	}

	/**
	 * Re-upload to Azure after EWWW optimization.
	 *
	 * @param array $file         File data.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified file data.
	 */
	public function reupload_after_ewww( $file, $attachment_id ) {
		if ( ! $attachment_id ) {
			return $file;
		}

		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return $file;
		}

		Azure_Debug_Logger::info(
			sprintf( 'Re-uploading attachment #%d to Azure after EWWW optimization', $attachment_id )
		);

		// Re-upload using background processor.
		$this->background_processor->push_to_queue( array(
			'attachment_id' => $attachment_id,
			'action'        => 'offload',
			'force'         => true,
		) );

		$this->background_processor->save()->dispatch();

		return $file;
	}

	/**
	 * Re-upload to Azure after thumbnail regeneration.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata.
	 */
	public function reupload_after_regenerate( $metadata, $attachment_id ) {
		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return $metadata;
		}

		// Check if this is actually a regenerate operation (not initial upload).
		$existing_meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $existing_meta ) ) {
			return $metadata; // Initial upload, not regenerate.
		}

		Azure_Debug_Logger::info(
			sprintf( 'Re-uploading attachment #%d to Azure after thumbnail regeneration', $attachment_id )
		);

		// Re-upload using background processor.
		$this->background_processor->push_to_queue( array(
			'attachment_id' => $attachment_id,
			'action'        => 'offload',
			'force'         => true,
		) );

		$this->background_processor->save()->dispatch();

		return $metadata;
	}

	/**
	 * Get compatible plugins status.
	 *
	 * @return array Plugin compatibility status.
	 */
	public function get_compatibility_status() {
		$plugins = array();

		// ShortPixel.
		if ( $this->shortpixel_active ) {
			$version = defined( 'SHORTPIXEL_IMAGE_OPTIMISER_VERSION' )
				? SHORTPIXEL_IMAGE_OPTIMISER_VERSION
				: 'unknown';

			$plugins['shortpixel'] = array(
				'name'       => 'ShortPixel Image Optimizer',
				'active'     => true,
				'compatible' => true,
				'version'    => $version,
			);
		}

		// EWWW Image Optimizer.
		if ( $this->ewww_active ) {
			$version = defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' )
				? EWWW_IMAGE_OPTIMIZER_VERSION
				: 'unknown';

			$plugins['ewww'] = array(
				'name'       => 'EWWW Image Optimizer',
				'active'     => true,
				'compatible' => true,
				'version'    => $version,
			);
		}

		// Regenerate Thumbnails.
		if ( class_exists( 'RegenerateThumbnails' ) || function_exists( 'regenerate_thumbnails' ) ) {
			$plugins['regenerate_thumbnails'] = array(
				'name'       => 'Regenerate Thumbnails',
				'active'     => true,
				'compatible' => true,
				'version'    => 'detected',
			);
		}

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$plugins['wp_cli'] = array(
				'name'       => 'WP-CLI',
				'active'     => true,
				'compatible' => true,
				'version'    => WP_CLI_VERSION ?? 'unknown',
			);
		}

		return $plugins;
	}

	/**
	 * Display admin notice about plugin compatibility.
	 *
	 * @return void
	 */
	public function display_compatibility_notice() {
		$compatible_plugins = $this->get_compatibility_status();

		if ( empty( $compatible_plugins ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Azure Storage Plugin Compatibility:', 'windows-azure-storage' ); ?></strong>
				<?php
				$plugin_names = array_column( $compatible_plugins, 'name' );
				printf(
					/* translators: %s: Comma-separated list of plugin names */
					esc_html__( 'Compatible with: %s', 'windows-azure-storage' ),
					esc_html( implode( ', ', $plugin_names ) )
				);
				?>
			</p>
		</div>
		<?php
	}
}
