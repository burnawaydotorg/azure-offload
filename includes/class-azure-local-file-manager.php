<?php
/**
 * Azure Local File Manager
 *
 * Manages local file storage by removing files after offload and downloading
 * files from Azure when needed.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Local_File_Manager class.
 *
 * Handles local file removal and restoration from Azure.
 */
class Azure_Local_File_Manager {

	/**
	 * Remove local files for a single attachment.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $verify        Whether to verify Azure upload first.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function remove_local_files( $attachment_id, $verify = true ) {
		// Verify attachment exists.
		if ( ! get_post( $attachment_id ) ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Attachment not found.', 'windows-azure-storage' )
			);
		}

		// Check if offloaded to Azure.
		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return new WP_Error(
				'not_offloaded',
				__( 'File has not been offloaded to Azure.', 'windows-azure-storage' )
			);
		}

		// Verify file exists on Azure if requested.
		if ( $verify ) {
			$azure_url = get_post_meta( $attachment_id, '_azure_blob_url', true );
			if ( empty( $azure_url ) || ! $this->verify_azure_file_exists( $attachment_id ) ) {
				return new WP_Error(
					'azure_verification_failed',
					__( 'Could not verify file exists on Azure. Local file not removed.', 'windows-azure-storage' )
				);
			}
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			// Already removed or never existed.
			update_post_meta( $attachment_id, '_azure_local_removed', true );
			return true;
		}

		// Get metadata for thumbnails.
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Track removed files for rollback.
		$removed_files = array();

		// Delete main file.
		if ( @unlink( $file_path ) ) {
			$removed_files[] = $file_path;
		} else {
			return new WP_Error(
				'file_deletion_failed',
				sprintf( __( 'Failed to delete file: %s', 'windows-azure-storage' ), $file_path )
			);
		}

		// Delete thumbnails.
		if ( ! empty( $metadata['sizes'] ) ) {
			$file_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size => $size_data ) {
				$thumb_path = $file_dir . '/' . $size_data['file'];
				if ( file_exists( $thumb_path ) ) {
					if ( @unlink( $thumb_path ) ) {
						$removed_files[] = $thumb_path;
					}
				}
			}
		}

		// Delete original_image if exists (WP 5.3+ Big Image threshold).
		if ( ! empty( $metadata['original_image'] ) ) {
			$original_path = dirname( $file_path ) . '/' . $metadata['original_image'];
			if ( file_exists( $original_path ) ) {
				@unlink( $original_path );
				$removed_files[] = $original_path;
			}
		}

		// Mark as removed.
		update_post_meta( $attachment_id, '_azure_local_removed', true );
		update_post_meta( $attachment_id, '_azure_local_removed_date', time() );
		update_post_meta( $attachment_id, '_azure_removed_files', $removed_files );

		return true;
	}

	/**
	 * Download file from Azure to local storage.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $force         Force download even if local file exists.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function download_from_azure( $attachment_id, $force = false ) {
		// Verify attachment exists.
		if ( ! get_post( $attachment_id ) ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Attachment not found.', 'windows-azure-storage' )
			);
		}

		// Check if offloaded to Azure.
		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return new WP_Error(
				'not_offloaded',
				__( 'File has not been offloaded to Azure.', 'windows-azure-storage' )
			);
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $force && file_exists( $file_path ) ) {
			return new WP_Error(
				'file_exists',
				__( 'Local file already exists. Use force option to overwrite.', 'windows-azure-storage' )
			);
		}

		// Get Azure storage info.
		$media_info = get_post_meta( $attachment_id, 'windows_azure_storage_info', true );
		if ( empty( $media_info ) || empty( $media_info['blob'] ) || empty( $media_info['container'] ) ) {
			return new WP_Error(
				'no_azure_info',
				__( 'Azure storage information not found.', 'windows-azure-storage' )
			);
		}

		// Download main file.
		$result = $this->download_blob_to_file(
			$media_info['container'],
			$media_info['blob'],
			$file_path
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Download thumbnails.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && ! empty( $media_info['thumbnails'] ) ) {
			$file_dir = dirname( $file_path );

			foreach ( $media_info['thumbnails'] as $thumbnail_blob ) {
				$thumb_filename = basename( $thumbnail_blob );
				$thumb_path = $file_dir . '/' . $thumb_filename;

				$this->download_blob_to_file(
					$media_info['container'],
					$thumbnail_blob,
					$thumb_path
				);
			}
		}

		// Update meta.
		delete_post_meta( $attachment_id, '_azure_local_removed' );
		delete_post_meta( $attachment_id, '_azure_local_removed_date' );
		delete_post_meta( $attachment_id, '_azure_removed_files' );

		return true;
	}

	/**
	 * Download a blob to a local file.
	 *
	 * @param string $container Container name.
	 * @param string $blob_name Blob name.
	 * @param string $file_path Local file path.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function download_blob_to_file( $container, $blob_name, $file_path ) {
		// Get credentials.
		$credentials = \Windows_Azure_Config_Provider::get_account_credentials();
		if ( empty( $credentials['account_name'] ) || empty( $credentials['account_key'] ) ) {
			return new WP_Error(
				'storage_credentials_missing',
				__( 'Azure storage credentials not configured.', 'windows-azure-storage' )
			);
		}

		// Create storage client.
		$storage_client = new \Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );

		// Ensure directory exists.
		$dir = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		try {
			// Get blob content.
			$blob_content = $storage_client->getBlob( $container, $blob_name );

			if ( is_wp_error( $blob_content ) ) {
				return $blob_content;
			}

			// Write to file.
			$bytes_written = file_put_contents( $file_path, $blob_content );

			if ( false === $bytes_written ) {
				return new WP_Error(
					'file_write_failed',
					sprintf( __( 'Failed to write file: %s', 'windows-azure-storage' ), $file_path )
				);
			}

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'download_failed',
				sprintf( __( 'Failed to download from Azure: %s', 'windows-azure-storage' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Verify that a file exists on Azure.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if file exists on Azure.
	 */
	public function verify_azure_file_exists( $attachment_id ) {
		$media_info = get_post_meta( $attachment_id, 'windows_azure_storage_info', true );

		if ( empty( $media_info ) || empty( $media_info['blob'] ) || empty( $media_info['container'] ) ) {
			return false;
		}

		// Get credentials.
		$credentials = \Windows_Azure_Config_Provider::get_account_credentials();
		if ( empty( $credentials['account_name'] ) || empty( $credentials['account_key'] ) ) {
			return false;
		}

		// Create storage client.
		$storage_client = new \Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );

		try {
			// Check if blob exists by getting properties.
			$properties = $storage_client->get_blob_properties( $media_info['container'], $media_info['blob'] );

			return ! is_wp_error( $properties );

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Calculate disk space used by local files for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Size in bytes, 0 if files don't exist.
	 */
	public function calculate_local_size( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		$total_size = 0;

		// Main file.
		if ( file_exists( $file_path ) ) {
			$total_size += filesize( $file_path );
		}

		// Thumbnails.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) ) {
			$file_dir = dirname( $file_path );

			foreach ( $metadata['sizes'] as $size => $size_data ) {
				$thumb_path = $file_dir . '/' . $size_data['file'];
				if ( file_exists( $thumb_path ) ) {
					$total_size += filesize( $thumb_path );
				}
			}
		}

		// Original image.
		if ( ! empty( $metadata['original_image'] ) ) {
			$original_path = dirname( $file_path ) . '/' . $metadata['original_image'];
			if ( file_exists( $original_path ) ) {
				$total_size += filesize( $original_path );
			}
		}

		return $total_size;
	}

	/**
	 * Calculate total disk space that could be saved by removing local files.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Array with 'total_size', 'file_count', 'attachment_count'.
	 */
	public function calculate_potential_savings( $attachment_ids ) {
		$total_size = 0;
		$file_count = 0;
		$attachment_count = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
			$local_removed = get_post_meta( $attachment_id, '_azure_local_removed', true );

			// Only count offloaded files that still have local copies.
			if ( 'offloaded' === $status && ! $local_removed ) {
				$size = $this->calculate_local_size( $attachment_id );
				if ( $size > 0 ) {
					$total_size += $size;
					$attachment_count++;

					// Count files (main + thumbnails).
					$metadata = wp_get_attachment_metadata( $attachment_id );
					$file_count += 1; // Main file.
					if ( ! empty( $metadata['sizes'] ) ) {
						$file_count += count( $metadata['sizes'] );
					}
					if ( ! empty( $metadata['original_image'] ) ) {
						$file_count += 1;
					}
				}
			}
		}

		return array(
			'total_size'       => $total_size,
			'file_count'       => $file_count,
			'attachment_count' => $attachment_count,
		);
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @param int $bytes Size in bytes.
	 * @param int $precision Decimal precision.
	 * @return string Formatted size (e.g., "1.5 GB").
	 */
	public function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	/**
	 * Check if local files can be safely removed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array with 'can_remove' (bool) and 'reason' (string if false).
	 */
	public function can_remove_local_files( $attachment_id ) {
		// Check if offloaded.
		$status = get_post_meta( $attachment_id, '_azure_offload_status', true );
		if ( 'offloaded' !== $status ) {
			return array(
				'can_remove' => false,
				'reason'     => __( 'File has not been offloaded to Azure.', 'windows-azure-storage' ),
			);
		}

		// Check if already removed.
		$local_removed = get_post_meta( $attachment_id, '_azure_local_removed', true );
		if ( $local_removed ) {
			return array(
				'can_remove' => false,
				'reason'     => __( 'Local files already removed.', 'windows-azure-storage' ),
			);
		}

		// Check if file exists locally.
		$file_path = get_attached_file( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			return array(
				'can_remove' => false,
				'reason'     => __( 'Local file not found.', 'windows-azure-storage' ),
			);
		}

		// Verify Azure file exists.
		if ( ! $this->verify_azure_file_exists( $attachment_id ) ) {
			return array(
				'can_remove' => false,
				'reason'     => __( 'Could not verify file exists on Azure.', 'windows-azure-storage' ),
			);
		}

		return array(
			'can_remove' => true,
			'reason'     => '',
		);
	}

	/**
	 * Bulk remove local files.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @param bool  $verify         Whether to verify Azure upload first.
	 * @return array Array with 'removed', 'failed', 'errors'.
	 */
	public function bulk_remove_local_files( $attachment_ids, $verify = true ) {
		$removed = 0;
		$failed = 0;
		$errors = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$result = $this->remove_local_files( $attachment_id, $verify );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = array(
					'attachment_id' => $attachment_id,
					'message'       => $result->get_error_message(),
				);
			} else {
				$removed++;
			}
		}

		return array(
			'removed' => $removed,
			'failed'  => $failed,
			'errors'  => $errors,
		);
	}

	/**
	 * Bulk download from Azure.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @param bool  $force          Force download even if local file exists.
	 * @return array Array with 'downloaded', 'failed', 'errors'.
	 */
	public function bulk_download_from_azure( $attachment_ids, $force = false ) {
		$downloaded = 0;
		$failed = 0;
		$errors = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$result = $this->download_from_azure( $attachment_id, $force );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = array(
					'attachment_id' => $attachment_id,
					'message'       => $result->get_error_message(),
				);
			} else {
				$downloaded++;
			}
		}

		return array(
			'downloaded' => $downloaded,
			'failed'     => $failed,
			'errors'     => $errors,
		);
	}
}
