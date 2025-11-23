<?php
/**
 * Microsoft Azure Storage command line client.
 * Version: 4.0.0
 * Author: Microsoft Open Technologies, Inc.
 * Author URI: http://www.microsoft.com/
 * License: BSD-2-Clause
 *
 * Copyright (c) Microsoft Open Technologies, Inc.
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright notice, this list
 * of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this
 * list of conditions  and the following disclaimer in the documentation and/or
 * other materials provided with the distribution.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A  PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)  HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN
 * IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP Version 5
 *
 * @category  WordPress_Plugin
 * @package   Windows_Azure_Storage_For_WordPress
 * @author    Microsoft Open Technologies, Inc. <msopentech@microsoft.com>
 * @copyright Microsoft Open Technologies, Inc.
 * @license   BSD-2-Clause, (http://www.opensource.org/licenses/bsd-license.php)
 * @link      http://www.microsoft.com
 */
class Windows_Azure_Storage_CLI extends WP_CLI_Command {

	/**
	 * List containers.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--prefix=<prefix>]
	 * : List containers which names start with prefix.
	 *
	 * @subcommand containers-list
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage containers-list --prefix=demo
	 */
	public function list_containers( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'prefix' => '',
		) );

		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$format_args = array(
			'format' => 'table',
			'fields' => array( 'Name' ),
			'field'  => null,
		);

		$table      = new \WP_CLI\Formatter( $format_args );
		$containers = $client->list_containers( $assoc_args['prefix'] );

		if ( is_wp_error( $containers ) ) {
			WP_CLI::error( $containers->get_error_message() );
			exit;
		}
		$items = array();
		foreach ( $containers as $container ) {
			$items[] = $container;
		}

		if ( empty( $items ) ) {
			WP_CLI::warning( __( 'No containers found.', 'windows-azure-storage' ) );
			exit;
		}

		$table->display_items( $items );
	}

	/**
	 * Create container.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Container name.
	 *
	 * @subcommand container-create
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage container-create testcontainer
	 */
	public function create_container( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'Container name must be set.', 'windows-azure-storage' ) );
			exit;
		}

		list( $name ) = $args;
		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$result      = $client->create_container( $name, Windows_Azure_Rest_Api_Client::CONTAINER_VISIBILITY_BLOB );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			exit;
		}

		WP_CLI::success(
			sprintf(
				// translators: %s is container name.
				__( 'Created container with name "%s"', 'windows-azure-storage' ),
				$result
			)
		);
	}

	/**
	 * Get container properties.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Container name.
	 *
	 * @subcommand container-properties
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage container-properties testcontainer
	 */
	public function get_container_properties( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'Container name must be set.', 'windows-azure-storage' ) );
			exit;
		}

		list( $name ) = $args;
		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$result      = $client->get_container_properties( $name );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			exit;
		}

		$format_args = array(
			'format' => 'table',
			'fields' => array_keys( $result ),
			'field'  => null,
		);

		$table = new \WP_CLI\Formatter( $format_args );
		$table->display_item( $result );
	}

	/**
	 * Get container ACL.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Container name.
	 *
	 * @subcommand container-acl
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage container-acl testcontainer
	 */
	public function get_container_acl( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'Container name must be set.', 'windows-azure-storage' ) );
			exit;
		}

		list( $name ) = $args;
		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$result      = $client->get_container_acl( $name );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			exit;
		}

		WP_CLI::success(
			sprintf(
				// translators: %1$s is container name, %2$s is access policy.
				__( 'Container "%1$s" access policy set to: "%2$s"', 'windows-azure-storage' ),
				$name,
				$result
			)
		);
	}

	/**
	 * Delete blob from container.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <container>
	 * : Container name.
	 *
	 * <path>
	 * : Remote file path.
	 *
	 * @subcommand delete-blob
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage delete-blob testcontainer image1.png
	 */
	public function delete_blob( $args, $assoc_args ) {
		if ( empty( $args ) || count( $args ) < 2 ) {
			WP_CLI::error( __( 'Container and remote path must be set.', 'windows-azure-storage' ) );
			exit;
		}

		list( $container, $remote_path ) = $args;
		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$result      = $client->delete_blob( $container, $remote_path );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			exit;
		}

		WP_CLI::success(
			__( 'Blob has been deleted.', 'windows-azure-storage' )
		);
	}

	/**
	 * Get blob properties.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <container>
	 * : Container name.
	 *
	 * <remote_path>
	 * : Blob path.
	 *
	 * @subcommand blob-properties
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage blob-properties testcontainer image1.png
	 */
	public function get_blob_properties( $args, $assoc_args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::error( __( 'Container name and blob path must be set.', 'windows-azure-storage' ) );
			exit;
		}

		list( $container, $remote_path ) = $args;
		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$result      = $client->get_blob_properties( $container, $remote_path );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			exit;
		}

		$format_args = array(
			'format' => 'table',
			'fields' => array_keys( $result ),
			'field'  => null,
		);

		$table = new \WP_CLI\Formatter( $format_args );
		$table->display_item( $result );
	}

	/**
	 * List blobs in given container.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * --container=<name>
	 * : Container name.
	 *
	 * [--prefix=<prefix>]
	 * : List containers which names start with prefix.
	 *
	 * @subcommand blobs-list
	 *
	 * ## EXAMPLE
	 * wp windows-azure-storage blobs-list --container=test-container --prefix=demo
	 */
	public function list_blobs( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'prefix'    => '',
			'container' => '',
		) );

		if ( empty( $assoc_args['container'] ) ) {
			WP_CLI::error( __( 'Container name must be set.', 'windows-azure-storage' ) );
			exit;
		}

		$credentials = Windows_Azure_Config_Provider::get_account_credentials();
		$client      = new Windows_Azure_Rest_Api_Client( $credentials['account_name'], $credentials['account_key'] );
		$format_args = array(
			'format' => 'table',
			'fields' => array( 'Name' ),
			'field'  => null,
		);

		$table = new \WP_CLI\Formatter( $format_args );
		$blobs = $client->list_blobs( $assoc_args['container'], $assoc_args['prefix'] );

		if ( is_wp_error( $blobs ) ) {
			WP_CLI::error( $blobs->get_error_message() );
			exit;
		}
		$items = array();
		foreach ( $blobs as $blob ) {
			$items[] = [ 'Name' => $blob->getName() ];
		}

		if ( empty( $items ) ) {
			WP_CLI::warning( __( 'No blobs found.', 'windows-azure-storage' ) );
			exit;
		}

		$table->display_items( $items );
	}

	/**
	 * Bulk offload media to Azure Storage.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum number of attachments to offload. Default: all.
	 *
	 * [--force]
	 * : Force re-upload of already offloaded items.
	 *
	 * [--remove-local]
	 * : Remove local files after successful upload.
	 *
	 * [--pending-only]
	 * : Only offload items not yet offloaded. Default: true.
	 *
	 * @subcommand bulk-offload
	 *
	 * ## EXAMPLES
	 *
	 *     # Offload all pending media
	 *     wp windows-azure-storage bulk-offload
	 *
	 *     # Offload 100 items and remove local files
	 *     wp windows-azure-storage bulk-offload --limit=100 --remove-local
	 *
	 *     # Force re-upload all media
	 *     wp windows-azure-storage bulk-offload --force
	 */
	public function bulk_offload( $args, $assoc_args ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'limit'        => null,
			'force'        => false,
			'remove-local' => false,
			'pending-only' => true,
		) );

		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! class_exists( 'ActionScheduler' ) ) {
			WP_CLI::error( __( 'Action Scheduler is required for bulk offload. Please install WooCommerce or another plugin that includes Action Scheduler.', 'windows-azure-storage' ) );
			return;
		}

		// Build query arguments.
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => ! empty( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : -1,
			'fields'         => 'ids',
		);

		// Only get pending items if not forcing.
		if ( $assoc_args['pending-only'] && ! $assoc_args['force'] ) {
			$query_args['meta_query'] = array(
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

		WP_CLI::log( __( 'Querying attachments...', 'windows-azure-storage' ) );

		$attachment_query = new WP_Query( $query_args );
		$attachment_ids   = $attachment_query->posts;

		if ( empty( $attachment_ids ) ) {
			WP_CLI::warning( __( 'No attachments found to offload.', 'windows-azure-storage' ) );
			return;
		}

		WP_CLI::success(
			sprintf(
				// translators: %d is the number of attachments.
				__( 'Found %d attachments to offload.', 'windows-azure-storage' ),
				count( $attachment_ids )
			)
		);

		// Prepare options.
		$options = array(
			'force'        => $assoc_args['force'],
			'remove_local' => $assoc_args['remove-local'],
		);

		// Initialize background processor.
		$processor = new Azure_Background_Processor();

		// Start bulk offload.
		$result = $processor->start_bulk_offload( $attachment_ids, $options );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( __( 'Bulk offload started. Run "wp windows-azure-storage offload-status" to check progress.', 'windows-azure-storage' ) );
	}

	/**
	 * Check bulk offload status.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * @subcommand offload-status
	 *
	 * ## EXAMPLES
	 *
	 *     wp windows-azure-storage offload-status
	 */
	public function offload_status( $args, $assoc_args ) {
		$progress = get_option( 'azure_bulk_offload_progress', array() );

		if ( empty( $progress ) ) {
			WP_CLI::warning( __( 'No active bulk offload operation found.', 'windows-azure-storage' ) );
			return;
		}

		$percentage = $progress['total'] > 0 ? round( ( $progress['processed'] / $progress['total'] ) * 100, 1 ) : 0;

		WP_CLI::log( '' );
		WP_CLI::log( __( 'Bulk Offload Status:', 'windows-azure-storage' ) );
		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log(
			sprintf(
				// translators: %s is the status.
				__( 'Status:      %s', 'windows-azure-storage' ),
				strtoupper( $progress['status'] )
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %s is the percentage.
				__( 'Progress:    %s%%', 'windows-azure-storage' ),
				$percentage
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %1$d is processed count, %2$d is total count.
				__( 'Processed:   %1$d of %2$d', 'windows-azure-storage' ),
				$progress['processed'],
				$progress['total']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is successful count.
				__( 'Successful:  %d', 'windows-azure-storage' ),
				$progress['successful']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is failed count.
				__( 'Failed:      %d', 'windows-azure-storage' ),
				$progress['failed']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is skipped count.
				__( 'Skipped:     %d', 'windows-azure-storage' ),
				$progress['skipped']
			)
		);
		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log( '' );

		// Show recent errors if any.
		if ( ! empty( $progress['errors'] ) ) {
			$recent_errors = array_slice( $progress['errors'], -5 );
			WP_CLI::warning(
				sprintf(
					// translators: %d is error count.
					__( 'Recent errors (%d total):', 'windows-azure-storage' ),
					count( $progress['errors'] )
				)
			);
			foreach ( $recent_errors as $error ) {
				WP_CLI::log(
					sprintf(
						// translators: %1$d is attachment ID, %2$s is error message.
						__( '  - Attachment #%1$d: %2$s', 'windows-azure-storage' ),
						$error['attachment_id'],
						$error['message']
					)
				);
			}
			WP_CLI::log( '' );
		}

		if ( 'completed' === $progress['status'] ) {
			WP_CLI::success( __( 'Bulk offload completed!', 'windows-azure-storage' ) );
		}
	}

	/**
	 * Purge CDN cache for specific URLs.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <urls>...
	 * : One or more URLs to purge from CDN cache.
	 *
	 * @subcommand cdn-purge
	 *
	 * ## EXAMPLES
	 *
	 *     # Purge specific URLs
	 *     wp windows-azure-storage cdn-purge https://cdn.example.com/media/image.jpg
	 *
	 *     # Purge multiple URLs
	 *     wp windows-azure-storage cdn-purge https://cdn.example.com/img1.jpg https://cdn.example.com/img2.jpg
	 */
	public function cdn_purge( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'Please provide at least one URL to purge.', 'windows-azure-storage' ) );
			return;
		}

		$cdn_manager = new Azure_CDN_Manager();

		if ( ! $cdn_manager->is_cdn_enabled() ) {
			WP_CLI::error( __( 'CDN is not enabled. Please configure a CDN endpoint in settings.', 'windows-azure-storage' ) );
			return;
		}

		WP_CLI::log(
			sprintf(
				// translators: %d is the number of URLs.
				__( 'Purging %d URL(s) from CDN cache...', 'windows-azure-storage' ),
				count( $args )
			)
		);

		$result = $cdn_manager->purge_urls( $args );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( __( 'CDN cache purged successfully.', 'windows-azure-storage' ) );
	}

	/**
	 * Purge entire CDN cache.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * @subcommand cdn-purge-all
	 *
	 * ## EXAMPLES
	 *
	 *     # Purge all CDN cache (with confirmation)
	 *     wp windows-azure-storage cdn-purge-all
	 *
	 *     # Purge all CDN cache (skip confirmation)
	 *     wp windows-azure-storage cdn-purge-all --yes
	 */
	public function cdn_purge_all( $args, $assoc_args ) {
		$cdn_manager = new Azure_CDN_Manager();

		if ( ! $cdn_manager->is_cdn_enabled() ) {
			WP_CLI::error( __( 'CDN is not enabled. Please configure a CDN endpoint in settings.', 'windows-azure-storage' ) );
			return;
		}

		// Confirm before purging all.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( __( 'Are you sure you want to purge the entire CDN cache? This cannot be undone.', 'windows-azure-storage' ) );
		}

		WP_CLI::log( __( 'Purging entire CDN cache...', 'windows-azure-storage' ) );

		$result = $cdn_manager->purge_all();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( __( 'Entire CDN cache purged successfully.', 'windows-azure-storage' ) );
	}

	/**
	 * Validate CDN endpoint configuration.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * @subcommand cdn-validate
	 *
	 * ## EXAMPLES
	 *
	 *     wp windows-azure-storage cdn-validate
	 */
	public function cdn_validate( $args, $assoc_args ) {
		$cdn_manager = new Azure_CDN_Manager();

		if ( ! $cdn_manager->is_cdn_enabled() ) {
			WP_CLI::error( __( 'CDN is not enabled. Please configure a CDN endpoint in settings.', 'windows-azure-storage' ) );
			return;
		}

		WP_CLI::log( __( 'Validating CDN endpoint...', 'windows-azure-storage' ) );

		$result = $cdn_manager->validate_cdn_endpoint();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( __( 'CDN endpoint is valid and responding.', 'windows-azure-storage' ) );
	}

	/**
	 * Get CDN statistics and status.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * @subcommand cdn-status
	 *
	 * ## EXAMPLES
	 *
	 *     wp windows-azure-storage cdn-status
	 */
	public function cdn_status( $args, $assoc_args ) {
		$cdn_manager = new Azure_CDN_Manager();
		$stats = $cdn_manager->get_cdn_stats();

		WP_CLI::log( '' );
		WP_CLI::log( __( 'CDN Status:', 'windows-azure-storage' ) );
		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log(
			sprintf(
				// translators: %s is enabled/disabled status.
				__( 'Enabled:         %s', 'windows-azure-storage' ),
				$stats['enabled'] ? __( 'Yes', 'windows-azure-storage' ) : __( 'No', 'windows-azure-storage' )
			)
		);

		if ( $stats['enabled'] ) {
			WP_CLI::log(
				sprintf(
					// translators: %s is the CDN endpoint URL.
					__( 'Endpoint:        %s', 'windows-azure-storage' ),
					$stats['endpoint']
				)
			);
		}

		WP_CLI::log(
			sprintf(
				// translators: %s is enabled/disabled status.
				__( 'Auto Purge:      %s', 'windows-azure-storage' ),
				$stats['auto_purge'] ? __( 'Enabled', 'windows-azure-storage' ) : __( 'Disabled', 'windows-azure-storage' )
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %s is yes/no.
				__( 'API Configured:  %s', 'windows-azure-storage' ),
				$stats['api_configured'] ? __( 'Yes', 'windows-azure-storage' ) : __( 'No', 'windows-azure-storage' )
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is the number of purge operations.
				__( 'Total Purges:    %d', 'windows-azure-storage' ),
				$stats['total_purges']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is the number of paths purged.
				__( 'Total Paths:     %d', 'windows-azure-storage' ),
				$stats['total_paths']
			)
		);

		if ( $stats['last_purge'] > 0 ) {
			WP_CLI::log(
				sprintf(
					// translators: %s is the time ago.
					__( 'Last Purge:      %s ago', 'windows-azure-storage' ),
					human_time_diff( $stats['last_purge'], time() )
				)
			);
		}

		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log( '' );
	}

	/**
	 * Copy blobs between containers.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <source-container>
	 * : Source container name.
	 *
	 * <destination-container>
	 * : Destination container name.
	 *
	 * [--prefix=<prefix>]
	 * : Only copy blobs starting with this prefix.
	 *
	 * [--limit=<number>]
	 * : Maximum number of blobs to copy.
	 *
	 * [--update-meta]
	 * : Update WordPress post meta after copy.
	 *
	 * [--delete-source]
	 * : Delete source blobs after successful copy (use with caution).
	 *
	 * @subcommand container-copy
	 *
	 * ## EXAMPLES
	 *
	 *     # Copy all blobs from staging to production
	 *     wp windows-azure-storage container-copy staging-media production-media
	 *
	 *     # Copy only images directory
	 *     wp windows-azure-storage container-copy staging-media production-media --prefix=images/
	 *
	 *     # Copy 100 blobs and update WordPress meta
	 *     wp windows-azure-storage container-copy staging-media production-media --limit=100 --update-meta
	 */
	public function container_copy( $args, $assoc_args ) {
		list( $source_container, $destination_container ) = $args;

		$options = array(
			'prefix'        => isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : '',
			'limit'         => isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : -1,
			'update_meta'   => isset( $assoc_args['update-meta'] ),
			'delete_source' => isset( $assoc_args['delete-source'] ),
		);

		// Warning for delete-source.
		if ( $options['delete_source'] ) {
			WP_CLI::confirm(
				sprintf(
					// translators: %s is the container name.
					__( 'Are you sure you want to DELETE source blobs from "%s" after copying? This cannot be undone.', 'windows-azure-storage' ),
					$source_container
				)
			);
		}

		$copy_manager = new Azure_Container_Copy_Manager();

		WP_CLI::log(
			sprintf(
				// translators: %1$s is source container, %2$s is destination container.
				__( 'Copying blobs from "%1$s" to "%2$s"...', 'windows-azure-storage' ),
				$source_container,
				$destination_container
			)
		);

		$result = $copy_manager->copy_container( $source_container, $destination_container, $options );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success(
			sprintf(
				// translators: %1$d is number copied, %2$d is number failed.
				__( 'Copy complete: %1$d succeeded, %2$d failed.', 'windows-azure-storage' ),
				$result['copied'],
				$result['failed']
			)
		);

		// Show errors if any.
		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::warning( __( 'Errors:', 'windows-azure-storage' ) );
			foreach ( array_slice( $result['errors'], 0, 10 ) as $error ) {
				WP_CLI::log( '  - ' . $error );
			}
		}
	}

	/**
	 * Migrate media library to a new container.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <source-container>
	 * : Current container name.
	 *
	 * <destination-container>
	 * : New container name.
	 *
	 * [--update-default]
	 * : Update the default container setting in WordPress.
	 *
	 * @subcommand migrate-container
	 *
	 * ## EXAMPLES
	 *
	 *     # Migrate media library to new container
	 *     wp windows-azure-storage migrate-container old-media new-media
	 *
	 *     # Migrate and update default container setting
	 *     wp windows-azure-storage migrate-container old-media new-media --update-default
	 */
	public function migrate_container( $args, $assoc_args ) {
		list( $source_container, $destination_container ) = $args;

		$update_default = isset( $assoc_args['update-default'] );

		WP_CLI::confirm(
			sprintf(
				// translators: %1$s is source, %2$s is destination.
				__( 'This will copy all media from "%1$s" to "%2$s" and update WordPress post meta. Continue?', 'windows-azure-storage' ),
				$source_container,
				$destination_container
			)
		);

		$copy_manager = new Azure_Container_Copy_Manager();

		WP_CLI::log( __( 'Starting media library migration...', 'windows-azure-storage' ) );

		$result = $copy_manager->migrate_media_library(
			$source_container,
			$destination_container,
			$update_default
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success(
			sprintf(
				// translators: %d is number of files copied.
				__( 'Migration complete: %d files copied.', 'windows-azure-storage' ),
				$result['copied']
			)
		);

		if ( $update_default ) {
			WP_CLI::log( __( 'Default container updated in WordPress settings.', 'windows-azure-storage' ) );
		}
	}

	/**
	 * Get container statistics.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command options.
	 *
	 * @return void
	 *
	 * ## OPTIONS
	 *
	 * <container>
	 * : Container name.
	 *
	 * @subcommand container-stats
	 *
	 * ## EXAMPLES
	 *
	 *     wp windows-azure-storage container-stats my-media
	 */
	public function container_stats( $args, $assoc_args ) {
		list( $container ) = $args;

		$copy_manager = new Azure_Container_Copy_Manager();

		WP_CLI::log(
			sprintf(
				// translators: %s is container name.
				__( 'Getting statistics for container "%s"...', 'windows-azure-storage' ),
				$container
			)
		);

		$stats = $copy_manager->get_container_stats( $container );

		if ( is_wp_error( $stats ) ) {
			WP_CLI::error( $stats->get_error_message() );
			return;
		}

		WP_CLI::log( '' );
		WP_CLI::log( __( 'Container Statistics:', 'windows-azure-storage' ) );
		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log(
			sprintf(
				// translators: %s is container name.
				__( 'Container:   %s', 'windows-azure-storage' ),
				$stats['container']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %d is blob count.
				__( 'Blobs:       %d', 'windows-azure-storage' ),
				$stats['blob_count']
			)
		);
		WP_CLI::log(
			sprintf(
				// translators: %s is formatted size.
				__( 'Total Size:  %s', 'windows-azure-storage' ),
				$stats['formatted_size']
			)
		);
		WP_CLI::log( str_repeat( '=', 50 ) );
		WP_CLI::log( '' );
	}
}

WP_CLI::add_command( 'windows-azure-storage', 'Windows_Azure_Storage_CLI' );
