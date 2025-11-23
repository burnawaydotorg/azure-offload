<?php
/**
 * Azure Asset Manager
 *
 * Manages copying theme/plugin assets (CSS, JS, fonts, images) to Azure Storage.
 * Assets are COPIED (not moved) - local files remain for development.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Asset_Manager class.
 *
 * Handles copying and syncing assets to Azure Storage.
 */
class Azure_Asset_Manager {

	/**
	 * Storage client instance.
	 *
	 * @var Windows_Azure_Rest_Api_Client
	 */
	private $storage_client;

	/**
	 * Container for assets.
	 *
	 * @var string
	 */
	private $asset_container;

	/**
	 * Whether URL rewriting is enabled.
	 *
	 * @var bool
	 */
	private $url_rewrite_enabled;

	/**
	 * Supported asset file types.
	 *
	 * @var array
	 */
	private $supported_types = array(
		'css'   => 'text/css',
		'js'    => 'application/javascript',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'eot'   => 'application/vnd.ms-fontobject',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'jpg'   => 'image/jpeg',
		'jpeg'  => 'image/jpeg',
		'gif'   => 'image/gif',
		'webp'  => 'image/webp',
		'ico'   => 'image/x-icon',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_settings();
		$this->init_storage_client();

		// Hook into WordPress for URL rewriting if enabled.
		if ( $this->url_rewrite_enabled ) {
			add_filter( 'style_loader_src', array( $this, 'rewrite_asset_url' ), 10, 2 );
			add_filter( 'script_loader_src', array( $this, 'rewrite_asset_url' ), 10, 2 );
		}
	}

	/**
	 * Initialize settings.
	 *
	 * @return void
	 */
	private function init_settings() {
		// Asset container (defaults to 'assets' or main container).
		$this->asset_container = $this->get_option(
			'azure_asset_container',
			\Windows_Azure_Helper::get_default_container()
		);

		// URL rewriting enabled.
		$this->url_rewrite_enabled = $this->get_option( 'azure_asset_url_rewrite', false );
	}

	/**
	 * Get option with constant override support.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $default     Default value.
	 * @return mixed Option value.
	 */
	private function get_option( $option_name, $default = '' ) {
		$constant_name = 'MICROSOFT_' . strtoupper( $option_name );
		return defined( $constant_name ) ? constant( $constant_name ) : get_option( $option_name, $default );
	}

	/**
	 * Initialize Azure Storage client.
	 *
	 * @return void
	 */
	private function init_storage_client() {
		$credentials = \Windows_Azure_Config_Provider::get_account_credentials();

		if ( empty( $credentials['account_name'] ) || empty( $credentials['account_key'] ) ) {
			return;
		}

		$this->storage_client = new \Windows_Azure_Rest_Api_Client(
			$credentials['account_name'],
			$credentials['account_key']
		);
	}

	/**
	 * Scan directory for assets.
	 *
	 * @param string $directory Directory path.
	 * @param string $base_path Base path for relative calculations.
	 * @return array Array of asset files.
	 */
	public function scan_directory( $directory, $base_path = null ) {
		if ( null === $base_path ) {
			$base_path = $directory;
		}

		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$assets = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$extension = strtolower( pathinfo( $file->getPathname(), PATHINFO_EXTENSION ) );

			if ( ! isset( $this->supported_types[ $extension ] ) ) {
				continue;
			}

			// Skip minified source maps.
			if ( strpos( $file->getFilename(), '.map' ) !== false ) {
				continue;
			}

			$relative_path = str_replace( trailingslashit( $base_path ), '', $file->getPathname() );

			$assets[] = array(
				'local_path'    => $file->getPathname(),
				'relative_path' => $relative_path,
				'extension'     => $extension,
				'mime_type'     => $this->supported_types[ $extension ],
				'size'          => $file->getSize(),
				'modified'      => $file->getMTime(),
			);
		}

		return $assets;
	}

	/**
	 * Copy asset to Azure.
	 *
	 * @param string $local_path    Local file path.
	 * @param string $blob_path     Blob path (optional, auto-generated from local path).
	 * @param array  $options       Copy options.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function copy_asset( $local_path, $blob_path = null, $options = array() ) {
		if ( ! $this->storage_client ) {
			return new WP_Error(
				'no_storage_client',
				__( 'Azure Storage client not initialized.', 'windows-azure-storage' )
			);
		}

		if ( ! file_exists( $local_path ) ) {
			return new WP_Error(
				'file_not_found',
				sprintf(
					/* translators: %s: File path */
					__( 'File not found: %s', 'windows-azure-storage' ),
					$local_path
				)
			);
		}

		// Auto-generate blob path if not provided.
		if ( null === $blob_path ) {
			$blob_path = $this->generate_blob_path( $local_path );
		}

		$defaults = array(
			'cache_control' => 'public, max-age=31536000', // 1 year for assets.
			'content_type'  => $this->get_mime_type( $local_path ),
		);

		$options = wp_parse_args( $options, $defaults );

		Azure_Debug_Logger::log_operation_start(
			'copy_asset',
			array(
				'local'     => $local_path,
				'blob'      => $blob_path,
				'container' => $this->asset_container,
			)
		);

		try {
			// Upload to Azure.
			\Windows_Azure_Helper::put_media_to_blob_storage(
				$this->asset_container,
				$blob_path,
				$local_path,
				$options['content_type'],
				$options['cache_control']
			);

			Azure_Debug_Logger::log_operation_end( 'copy_asset', true );

			return true;

		} catch ( Exception $e ) {
			$error = new WP_Error(
				'copy_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to copy asset: %s', 'windows-azure-storage' ),
					$e->getMessage()
				)
			);

			Azure_Debug_Logger::log_wp_error( $error, 'copy_asset' );

			return $error;
		}
	}

	/**
	 * Sync assets from a directory.
	 *
	 * @param string $directory    Directory to sync.
	 * @param array  $options      Sync options.
	 * @return array Results with 'copied', 'skipped', 'failed'.
	 */
	public function sync_directory( $directory, $options = array() ) {
		$defaults = array(
			'force'        => false,  // Copy all files even if unchanged.
			'dry_run'      => false,  // Don't actually copy, just report what would be copied.
			'prefix'       => '',     // Blob path prefix.
		);

		$options = wp_parse_args( $options, $defaults );

		Azure_Debug_Logger::log_operation_start(
			'sync_directory',
			array(
				'directory' => $directory,
				'options'   => $options,
			)
		);

		// Scan directory.
		$assets = $this->scan_directory( $directory );

		$copied = 0;
		$skipped = 0;
		$failed = 0;
		$errors = array();

		foreach ( $assets as $asset ) {
			$blob_path = $options['prefix'] . $asset['relative_path'];

			// Check if file needs syncing (skip if unchanged and not forced).
			if ( ! $options['force'] && ! $options['dry_run'] ) {
				if ( $this->is_asset_synced( $asset, $blob_path ) ) {
					$skipped++;
					continue;
				}
			}

			// Dry run - just count.
			if ( $options['dry_run'] ) {
				$copied++;
				Azure_Debug_Logger::debug(
					sprintf( '[DRY RUN] Would copy: %s', $asset['relative_path'] )
				);
				continue;
			}

			// Copy asset.
			$result = $this->copy_asset( $asset['local_path'], $blob_path );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = sprintf(
					/* translators: %1$s: File path, %2$s: Error message */
					__( '%1$s: %2$s', 'windows-azure-storage' ),
					$asset['relative_path'],
					$result->get_error_message()
				);
				continue;
			}

			$copied++;

			// Track synced asset.
			$this->mark_asset_synced( $asset, $blob_path );
		}

		Azure_Debug_Logger::log_operation_end(
			'sync_directory',
			true,
			array(
				'copied'  => $copied,
				'skipped' => $skipped,
				'failed'  => $failed,
			)
		);

		return array(
			'copied'  => $copied,
			'skipped' => $skipped,
			'failed'  => $failed,
			'errors'  => $errors,
			'total'   => count( $assets ),
		);
	}

	/**
	 * Sync active theme assets.
	 *
	 * @param array $options Sync options.
	 * @return array Sync results.
	 */
	public function sync_theme_assets( $options = array() ) {
		$theme_dir = get_stylesheet_directory();
		$theme_slug = get_stylesheet();

		$options['prefix'] = isset( $options['prefix'] )
			? $options['prefix']
			: 'themes/' . $theme_slug . '/';

		return $this->sync_directory( $theme_dir, $options );
	}

	/**
	 * Sync plugin assets.
	 *
	 * @param string $plugin_slug Plugin directory name.
	 * @param array  $options     Sync options.
	 * @return array|WP_Error Sync results or error.
	 */
	public function sync_plugin_assets( $plugin_slug, $options = array() ) {
		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error(
				'plugin_not_found',
				sprintf(
					/* translators: %s: Plugin slug */
					__( 'Plugin directory not found: %s', 'windows-azure-storage' ),
					$plugin_slug
				)
			);
		}

		$options['prefix'] = isset( $options['prefix'] )
			? $options['prefix']
			: 'plugins/' . $plugin_slug . '/';

		return $this->sync_directory( $plugin_dir, $options );
	}

	/**
	 * Rewrite asset URL to use Azure CDN.
	 *
	 * @param string $src    Asset URL.
	 * @param string $handle Asset handle.
	 * @return string Modified URL.
	 */
	public function rewrite_asset_url( $src, $handle ) {
		// Only rewrite local URLs.
		$site_url = trailingslashit( site_url() );
		if ( strpos( $src, $site_url ) !== 0 ) {
			return $src;
		}

		// Get relative path.
		$relative = str_replace( $site_url, '', $src );

		// Check if asset is synced to Azure.
		$sync_data = get_option( 'azure_synced_assets', array() );
		$asset_key = md5( $relative );

		if ( ! isset( $sync_data[ $asset_key ] ) ) {
			return $src; // Not synced, use local.
		}

		// Build Azure URL.
		$blob_path = $sync_data[ $asset_key ]['blob_path'];
		$azure_url = \Windows_Azure_Helper::get_full_blob_url( $blob_path, $this->asset_container );

		Azure_Debug_Logger::debug(
			sprintf( 'Rewrote asset URL: %s → %s', $src, $azure_url )
		);

		return $azure_url;
	}

	/**
	 * Check if asset is already synced and unchanged.
	 *
	 * @param array  $asset     Asset data.
	 * @param string $blob_path Blob path.
	 * @return bool True if synced and unchanged.
	 */
	private function is_asset_synced( $asset, $blob_path ) {
		$sync_data = get_option( 'azure_synced_assets', array() );
		$asset_key = md5( $asset['relative_path'] );

		if ( ! isset( $sync_data[ $asset_key ] ) ) {
			return false;
		}

		$synced = $sync_data[ $asset_key ];

		// Compare modification time and size.
		return $synced['modified'] >= $asset['modified']
			&& $synced['size'] === $asset['size'];
	}

	/**
	 * Mark asset as synced.
	 *
	 * @param array  $asset     Asset data.
	 * @param string $blob_path Blob path.
	 * @return void
	 */
	private function mark_asset_synced( $asset, $blob_path ) {
		$sync_data = get_option( 'azure_synced_assets', array() );
		$asset_key = md5( $asset['relative_path'] );

		$sync_data[ $asset_key ] = array(
			'blob_path' => $blob_path,
			'size'      => $asset['size'],
			'modified'  => $asset['modified'],
			'synced_at' => time(),
		);

		update_option( 'azure_synced_assets', $sync_data, false );
	}

	/**
	 * Generate blob path from local file path.
	 *
	 * @param string $local_path Local file path.
	 * @return string Blob path.
	 */
	private function generate_blob_path( $local_path ) {
		// Remove ABSPATH and wp-content prefix.
		$content_dir = trailingslashit( WP_CONTENT_DIR );

		if ( strpos( $local_path, $content_dir ) === 0 ) {
			return str_replace( $content_dir, '', $local_path );
		}

		// Fallback: just use filename.
		return basename( $local_path );
	}

	/**
	 * Get MIME type for file.
	 *
	 * @param string $file_path File path.
	 * @return string MIME type.
	 */
	private function get_mime_type( $file_path ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return isset( $this->supported_types[ $extension ] )
			? $this->supported_types[ $extension ]
			: 'application/octet-stream';
	}

	/**
	 * Clear sync data.
	 *
	 * @return bool True on success.
	 */
	public function clear_sync_data() {
		return delete_option( 'azure_synced_assets' );
	}

	/**
	 * Get sync statistics.
	 *
	 * @return array Sync stats.
	 */
	public function get_sync_stats() {
		$sync_data = get_option( 'azure_synced_assets', array() );

		$total_synced = count( $sync_data );
		$last_sync = 0;

		foreach ( $sync_data as $asset ) {
			if ( $asset['synced_at'] > $last_sync ) {
				$last_sync = $asset['synced_at'];
			}
		}

		return array(
			'total_synced'     => $total_synced,
			'last_sync'        => $last_sync,
			'url_rewrite'      => $this->url_rewrite_enabled,
			'asset_container'  => $this->asset_container,
		);
	}
}
