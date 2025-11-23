<?php
/**
 * Azure Container Copy Manager
 *
 * Manages copying media files between Azure Storage containers.
 * Useful for staging-to-production workflows and container migrations.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Container_Copy_Manager class.
 *
 * Handles copying blobs between Azure Storage containers.
 */
class Azure_Container_Copy_Manager {

	/**
	 * Storage client instance.
	 *
	 * @var Windows_Azure_Rest_Api_Client
	 */
	private $storage_client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_storage_client();
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
	 * Copy a single blob between containers.
	 *
	 * @param string $source_container      Source container name.
	 * @param string $source_blob           Source blob name.
	 * @param string $destination_container Destination container name.
	 * @param string $destination_blob      Destination blob name (optional, defaults to source blob name).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function copy_blob( $source_container, $source_blob, $destination_container, $destination_blob = null ) {
		if ( ! $this->storage_client ) {
			return new WP_Error(
				'no_storage_client',
				__( 'Azure Storage client not initialized. Check your credentials.', 'windows-azure-storage' )
			);
		}

		// Use source blob name if destination not specified.
		if ( null === $destination_blob ) {
			$destination_blob = $source_blob;
		}

		Azure_Debug_Logger::log_operation_start(
			'copy_blob',
			array(
				'source'      => $source_container . '/' . $source_blob,
				'destination' => $destination_container . '/' . $destination_blob,
			)
		);

		try {
			// Get source blob URL.
			$source_url = $this->storage_client->get_blob_url( $source_container, $source_blob );

			// Copy blob using Azure Copy Blob API.
			$result = $this->storage_client->copy_blob(
				$source_url,
				$destination_container,
				$destination_blob
			);

			if ( is_wp_error( $result ) ) {
				Azure_Debug_Logger::log_wp_error( $result, 'copy_blob' );
				return $result;
			}

			Azure_Debug_Logger::log_operation_end( 'copy_blob', true );

			return true;

		} catch ( Exception $e ) {
			$error = new WP_Error(
				'copy_blob_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to copy blob: %s', 'windows-azure-storage' ),
					$e->getMessage()
				)
			);

			Azure_Debug_Logger::log_wp_error( $error, 'copy_blob' );

			return $error;
		}
	}

	/**
	 * Copy multiple blobs between containers.
	 *
	 * @param string $source_container      Source container name.
	 * @param string $destination_container Destination container name.
	 * @param array  $options               Copy options.
	 * @return array Results with 'copied', 'failed', 'errors'.
	 */
	public function bulk_copy( $source_container, $destination_container, $options = array() ) {
		$defaults = array(
			'prefix'        => '',           // Only copy blobs starting with this prefix.
			'update_meta'   => true,         // Update WordPress post meta after copy.
			'delete_source' => false,        // Delete source blobs after successful copy.
			'limit'         => -1,           // Max number of blobs to copy, -1 for unlimited.
		);

		$options = wp_parse_args( $options, $defaults );

		Azure_Debug_Logger::log_operation_start(
			'bulk_copy',
			array(
				'source'      => $source_container,
				'destination' => $destination_container,
				'options'     => $options,
			)
		);

		// List blobs in source container.
		$blobs_response = $this->storage_client->list_blobs(
			$source_container,
			$options['prefix'],
			$options['limit'] > 0 ? $options['limit'] : 5000
		);

		if ( is_wp_error( $blobs_response ) ) {
			Azure_Debug_Logger::log_wp_error( $blobs_response, 'bulk_copy' );
			return array(
				'copied' => 0,
				'failed' => 0,
				'errors' => array( $blobs_response->get_error_message() ),
			);
		}

		$blobs = array();
		foreach ( $blobs_response as $blob ) {
			$blobs[] = $blob->getName();

			// Check limit.
			if ( $options['limit'] > 0 && count( $blobs ) >= $options['limit'] ) {
				break;
			}
		}

		// Copy each blob.
		$copied = 0;
		$failed = 0;
		$errors = array();

		foreach ( $blobs as $blob_name ) {
			$result = $this->copy_blob(
				$source_container,
				$blob_name,
				$destination_container,
				$blob_name
			);

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = sprintf(
					/* translators: %1$s: Blob name, %2$s: Error message */
					__( 'Failed to copy %1$s: %2$s', 'windows-azure-storage' ),
					$blob_name,
					$result->get_error_message()
				);
				continue;
			}

			$copied++;

			// Update WordPress post meta if requested.
			if ( $options['update_meta'] ) {
				$this->update_post_meta_for_blob( $blob_name, $source_container, $destination_container );
			}

			// Delete source blob if requested.
			if ( $options['delete_source'] ) {
				\Windows_Azure_Helper::delete_blob( $source_container, $blob_name );
			}
		}

		Azure_Debug_Logger::log_operation_end(
			'bulk_copy',
			true,
			array(
				'copied' => $copied,
				'failed' => $failed,
			)
		);

		return array(
			'copied' => $copied,
			'failed' => $failed,
			'errors' => $errors,
		);
	}

	/**
	 * Update WordPress post meta for a blob after container migration.
	 *
	 * @param string $blob_name             Blob name.
	 * @param string $source_container      Old container name.
	 * @param string $destination_container New container name.
	 * @return void
	 */
	private function update_post_meta_for_blob( $blob_name, $source_container, $destination_container ) {
		global $wpdb;

		// Find attachments with this blob.
		$query = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = 'windows_azure_storage_info'
			AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( '"blob":"' . $blob_name . '"' ) . '%'
		);

		$post_ids = $wpdb->get_col( $query );

		foreach ( $post_ids as $post_id ) {
			$media_info = get_post_meta( $post_id, 'windows_azure_storage_info', true );

			if ( empty( $media_info ) || $media_info['container'] !== $source_container ) {
				continue;
			}

			// Update container name.
			$media_info['container'] = $destination_container;

			// Update blob URL.
			$media_info['url'] = \Windows_Azure_Helper::get_full_blob_url(
				$media_info['blob'],
				$destination_container
			);

			// Update meta.
			update_post_meta( $post_id, 'windows_azure_storage_info', $media_info );
			update_post_meta( $post_id, '_azure_blob_url', $media_info['url'] );

			Azure_Debug_Logger::debug(
				sprintf( 'Updated post meta for attachment #%d after container migration', $post_id ),
				array(
					'old_container' => $source_container,
					'new_container' => $destination_container,
					'blob'          => $blob_name,
				)
			);
		}
	}

	/**
	 * Copy container (entire container to another).
	 *
	 * @param string $source_container      Source container name.
	 * @param string $destination_container Destination container name.
	 * @param array  $options               Copy options.
	 * @return array|WP_Error Results array or WP_Error on failure.
	 */
	public function copy_container( $source_container, $destination_container, $options = array() ) {
		// Check if destination container exists, create if not.
		$containers = $this->storage_client->list_containers();

		$destination_exists = false;
		foreach ( $containers as $container ) {
			if ( $container->getName() === $destination_container ) {
				$destination_exists = true;
				break;
			}
		}

		if ( ! $destination_exists ) {
			$create_result = $this->storage_client->create_container( $destination_container );

			if ( is_wp_error( $create_result ) ) {
				return $create_result;
			}

			Azure_Debug_Logger::info(
				sprintf( 'Created destination container: %s', $destination_container )
			);
		}

		// Perform bulk copy.
		return $this->bulk_copy( $source_container, $destination_container, $options );
	}

	/**
	 * Get container copy progress.
	 *
	 * @return array Progress information.
	 */
	public function get_copy_progress() {
		return get_option( 'azure_container_copy_progress', array() );
	}

	/**
	 * Clear container copy progress.
	 *
	 * @return bool True on success.
	 */
	public function clear_copy_progress() {
		return delete_option( 'azure_container_copy_progress' );
	}

	/**
	 * Migrate media library to new container.
	 *
	 * Copies all media library items to a new container and updates WordPress meta.
	 *
	 * @param string $source_container      Current container.
	 * @param string $destination_container New container.
	 * @param bool   $update_default        Whether to update default container setting.
	 * @return array|WP_Error Results or error.
	 */
	public function migrate_media_library( $source_container, $destination_container, $update_default = false ) {
		Azure_Debug_Logger::log_operation_start(
			'migrate_media_library',
			array(
				'source'         => $source_container,
				'destination'    => $destination_container,
				'update_default' => $update_default,
			)
		);

		// Copy all blobs.
		$result = $this->copy_container(
			$source_container,
			$destination_container,
			array(
				'update_meta'   => true,
				'delete_source' => false, // Don't delete source during migration for safety.
			)
		);

		if ( is_wp_error( $result ) ) {
			Azure_Debug_Logger::log_wp_error( $result, 'migrate_media_library' );
			return $result;
		}

		// Update default container if requested.
		if ( $update_default && ! defined( 'MICROSOFT_AZURE_CONTAINER' ) ) {
			update_option( 'default_azure_storage_account_container_name', $destination_container );

			Azure_Debug_Logger::info(
				sprintf( 'Updated default container to: %s', $destination_container )
			);
		}

		Azure_Debug_Logger::log_operation_end( 'migrate_media_library', true, $result );

		return $result;
	}

	/**
	 * Verify blob exists in destination container.
	 *
	 * @param string $container Container name.
	 * @param string $blob_name Blob name.
	 * @return bool True if blob exists.
	 */
	public function verify_blob_exists( $container, $blob_name ) {
		try {
			$properties = $this->storage_client->get_blob_properties( $container, $blob_name );
			return ! is_wp_error( $properties );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get container statistics.
	 *
	 * @param string $container Container name.
	 * @return array|WP_Error Container stats or error.
	 */
	public function get_container_stats( $container ) {
		try {
			$blobs = $this->storage_client->list_blobs( $container, '', 10000 );

			if ( is_wp_error( $blobs ) ) {
				return $blobs;
			}

			$blob_count = 0;
			$total_size = 0;

			foreach ( $blobs as $blob ) {
				$blob_count++;
				$properties = $blob->getProperties();
				$total_size += isset( $properties['Content-Length'] ) ? intval( $properties['Content-Length'] ) : 0;
			}

			return array(
				'container'  => $container,
				'blob_count' => $blob_count,
				'total_size' => $total_size,
				'formatted_size' => $this->format_bytes( $total_size ),
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'stats_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to get container stats: %s', 'windows-azure-storage' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @param int $bytes Size in bytes.
	 * @return string Formatted size.
	 */
	private function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
