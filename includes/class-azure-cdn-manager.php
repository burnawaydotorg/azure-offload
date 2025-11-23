<?php
/**
 * Azure CDN Manager
 *
 * Manages Azure CDN integration including cache purging, validation,
 * and automatic cache invalidation on media updates.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_CDN_Manager class.
 *
 * Handles Azure CDN operations and cache management.
 */
class Azure_CDN_Manager {

	/**
	 * Whether automatic CDN purge is enabled.
	 *
	 * @var bool
	 */
	private $auto_purge_enabled;

	/**
	 * CDN endpoint URL (CNAME).
	 *
	 * @var string
	 */
	private $cdn_endpoint;

	/**
	 * Azure CDN profile name.
	 *
	 * @var string
	 */
	private $cdn_profile;

	/**
	 * Azure CDN endpoint name.
	 *
	 * @var string
	 */
	private $cdn_endpoint_name;

	/**
	 * Azure subscription ID for CDN operations.
	 *
	 * @var string
	 */
	private $subscription_id;

	/**
	 * Azure resource group name.
	 *
	 * @var string
	 */
	private $resource_group;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Load CDN settings.
		$this->load_cdn_settings();

		// Hook into media updates if auto-purge is enabled.
		if ( $this->auto_purge_enabled ) {
			add_action( 'delete_attachment', array( $this, 'purge_on_delete' ), 10, 1 );
			add_action( 'wp_update_attachment_metadata', array( $this, 'purge_on_update' ), 10, 2 );
		}
	}

	/**
	 * Load CDN settings from WordPress options or constants.
	 *
	 * @return void
	 */
	private function load_cdn_settings() {
		$this->cdn_endpoint = \Windows_Azure_Helper::get_cname();
		$this->auto_purge_enabled = $this->get_option( 'azure_cdn_auto_purge', false );
		$this->cdn_profile = $this->get_option( 'azure_cdn_profile', '' );
		$this->cdn_endpoint_name = $this->get_option( 'azure_cdn_endpoint_name', '' );
		$this->subscription_id = $this->get_option( 'azure_subscription_id', '' );
		$this->resource_group = $this->get_option( 'azure_resource_group', '' );
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
	 * Check if CDN is configured and enabled.
	 *
	 * @return bool True if CDN is configured.
	 */
	public function is_cdn_enabled() {
		return ! empty( $this->cdn_endpoint );
	}

	/**
	 * Check if Azure CDN API credentials are configured.
	 *
	 * @return bool True if API credentials are available.
	 */
	public function has_cdn_api_credentials() {
		return ! empty( $this->subscription_id )
			&& ! empty( $this->resource_group )
			&& ! empty( $this->cdn_profile )
			&& ! empty( $this->cdn_endpoint_name );
	}

	/**
	 * Purge CDN cache for specific URLs.
	 *
	 * @param array $urls Array of URLs to purge.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function purge_urls( $urls ) {
		if ( ! $this->is_cdn_enabled() ) {
			return new WP_Error(
				'cdn_not_enabled',
				__( 'CDN is not enabled. Please configure a CDN endpoint in settings.', 'windows-azure-storage' )
			);
		}

		if ( ! $this->has_cdn_api_credentials() ) {
			return new WP_Error(
				'cdn_api_not_configured',
				__( 'Azure CDN API credentials not configured. Cache purge requires subscription ID, resource group, CDN profile, and endpoint name.', 'windows-azure-storage' )
			);
		}

		// Convert URLs to content paths (remove domain).
		$content_paths = array();
		foreach ( $urls as $url ) {
			$parsed = wp_parse_url( $url );
			if ( isset( $parsed['path'] ) ) {
				$content_paths[] = $parsed['path'];
			}
		}

		if ( empty( $content_paths ) ) {
			return new WP_Error(
				'invalid_urls',
				__( 'No valid URLs provided for purging.', 'windows-azure-storage' )
			);
		}

		// Call Azure CDN Purge API.
		$result = $this->call_azure_cdn_purge_api( $content_paths );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log purge operation.
		$this->log_purge_operation( $content_paths );

		return true;
	}

	/**
	 * Purge entire CDN cache.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function purge_all() {
		if ( ! $this->has_cdn_api_credentials() ) {
			return new WP_Error(
				'cdn_api_not_configured',
				__( 'Azure CDN API credentials not configured.', 'windows-azure-storage' )
			);
		}

		// Purge all content by using /* wildcard.
		$result = $this->call_azure_cdn_purge_api( array( '/*' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log purge operation.
		$this->log_purge_operation( array( 'ALL' ) );

		return true;
	}

	/**
	 * Call Azure CDN Purge REST API.
	 *
	 * @param array $content_paths Array of content paths to purge.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function call_azure_cdn_purge_api( $content_paths ) {
		// Azure CDN Purge API endpoint.
		$api_url = sprintf(
			'https://management.azure.com/subscriptions/%s/resourceGroups/%s/providers/Microsoft.Cdn/profiles/%s/endpoints/%s/purge?api-version=2021-06-01',
			$this->subscription_id,
			$this->resource_group,
			$this->cdn_profile,
			$this->cdn_endpoint_name
		);

		// Get Azure management token.
		$access_token = $this->get_azure_management_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Prepare request body.
		$body = wp_json_encode( array(
			'contentPaths' => $content_paths,
		) );

		// Make API request.
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Azure CDN Purge returns 202 Accepted for async operations.
		if ( 202 !== $response_code && 200 !== $response_code ) {
			$response_body = wp_remote_retrieve_body( $response );
			return new WP_Error(
				'cdn_purge_failed',
				sprintf(
					/* translators: %1$d: HTTP response code, %2$s: Response body */
					__( 'CDN purge failed with status %1$d: %2$s', 'windows-azure-storage' ),
					$response_code,
					$response_body
				)
			);
		}

		return true;
	}

	/**
	 * Get Azure management API access token.
	 *
	 * Uses Azure Active Directory authentication with service principal.
	 *
	 * @return string|WP_Error Access token or WP_Error on failure.
	 */
	private function get_azure_management_token() {
		// Get service principal credentials.
		$tenant_id = $this->get_option( 'azure_tenant_id', '' );
		$client_id = $this->get_option( 'azure_client_id', '' );
		$client_secret = $this->get_option( 'azure_client_secret', '' );

		if ( empty( $tenant_id ) || empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Azure service principal credentials not configured. CDN purge requires tenant ID, client ID, and client secret.', 'windows-azure-storage' )
			);
		}

		// Check if we have a cached token.
		$cached_token = get_transient( 'azure_management_token' );
		if ( false !== $cached_token ) {
			return $cached_token;
		}

		// Request new token from Azure AD.
		$token_url = sprintf(
			'https://login.microsoftonline.com/%s/oauth2/token',
			$tenant_id
		);

		$response = wp_remote_post( $token_url, array(
			'body' => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'resource'      => 'https://management.azure.com/',
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'token_request_failed',
				sprintf(
					/* translators: %d: HTTP response code */
					__( 'Failed to get Azure management token. HTTP %d', 'windows-azure-storage' ),
					$response_code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new WP_Error(
				'invalid_token_response',
				__( 'Invalid token response from Azure AD.', 'windows-azure-storage' )
			);
		}

		$access_token = $body['access_token'];
		$expires_in = isset( $body['expires_in'] ) ? intval( $body['expires_in'] ) - 60 : 3540; // 1 hour minus 60s buffer.

		// Cache token.
		set_transient( 'azure_management_token', $access_token, $expires_in );

		return $access_token;
	}

	/**
	 * Validate CDN endpoint by making a test request.
	 *
	 * @return bool|WP_Error True if endpoint is valid, WP_Error on failure.
	 */
	public function validate_cdn_endpoint() {
		if ( ! $this->is_cdn_enabled() ) {
			return new WP_Error(
				'cdn_not_enabled',
				__( 'No CDN endpoint configured.', 'windows-azure-storage' )
			);
		}

		// Make a HEAD request to the CDN endpoint.
		$test_url = trailingslashit( $this->cdn_endpoint );

		$response = wp_remote_head( $test_url, array(
			'timeout'     => 10,
			'redirection' => 5,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'cdn_unreachable',
				sprintf(
					/* translators: %s: Error message */
					__( 'CDN endpoint unreachable: %s', 'windows-azure-storage' ),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Accept 200, 403, 404 as valid (CDN is responding).
		if ( ! in_array( $response_code, array( 200, 403, 404 ), true ) ) {
			return new WP_Error(
				'cdn_invalid_response',
				sprintf(
					/* translators: %d: HTTP response code */
					__( 'CDN endpoint returned unexpected status: %d', 'windows-azure-storage' ),
					$response_code
				)
			);
		}

		return true;
	}

	/**
	 * Purge CDN cache when attachment is deleted.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function purge_on_delete( $post_id ) {
		$media_info = get_post_meta( $post_id, 'windows_azure_storage_info', true );

		if ( empty( $media_info ) || empty( $media_info['url'] ) ) {
			return;
		}

		// Collect all URLs to purge.
		$urls = array( $media_info['url'] );

		// Add thumbnail URLs.
		if ( ! empty( $media_info['thumbnails'] ) ) {
			$base_url = trailingslashit( WindowsAzureStorageUtil::get_storage_url_base( true ) );
			foreach ( $media_info['thumbnails'] as $thumbnail_blob ) {
				$urls[] = $base_url . $thumbnail_blob;
			}
		}

		// Purge URLs from CDN.
		$this->purge_urls( $urls );
	}

	/**
	 * Purge CDN cache when attachment metadata is updated.
	 *
	 * @param array $data          Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata.
	 */
	public function purge_on_update( $data, $attachment_id ) {
		$media_info = get_post_meta( $attachment_id, 'windows_azure_storage_info', true );

		if ( empty( $media_info ) || empty( $media_info['url'] ) ) {
			return $data;
		}

		// Purge main file URL.
		$this->purge_urls( array( $media_info['url'] ) );

		return $data;
	}

	/**
	 * Log CDN purge operation.
	 *
	 * @param array $content_paths Purged content paths.
	 * @return void
	 */
	private function log_purge_operation( $content_paths ) {
		$log_entry = array(
			'timestamp'     => time(),
			'content_paths' => $content_paths,
			'count'         => count( $content_paths ),
		);

		// Get existing log (keep last 100 entries).
		$log = get_option( 'azure_cdn_purge_log', array() );
		array_unshift( $log, $log_entry );
		$log = array_slice( $log, 0, 100 );

		update_option( 'azure_cdn_purge_log', $log, false );
	}

	/**
	 * Get CDN purge log.
	 *
	 * @param int $limit Number of log entries to retrieve.
	 * @return array Log entries.
	 */
	public function get_purge_log( $limit = 20 ) {
		$log = get_option( 'azure_cdn_purge_log', array() );
		return array_slice( $log, 0, $limit );
	}

	/**
	 * Get CDN statistics.
	 *
	 * @return array CDN stats.
	 */
	public function get_cdn_stats() {
		$log = get_option( 'azure_cdn_purge_log', array() );

		$total_purges = count( $log );
		$total_paths = 0;

		foreach ( $log as $entry ) {
			$total_paths += isset( $entry['count'] ) ? $entry['count'] : 0;
		}

		return array(
			'enabled'           => $this->is_cdn_enabled(),
			'endpoint'          => $this->cdn_endpoint,
			'auto_purge'        => $this->auto_purge_enabled,
			'total_purges'      => $total_purges,
			'total_paths'       => $total_paths,
			'last_purge'        => ! empty( $log ) ? $log[0]['timestamp'] : 0,
			'api_configured'    => $this->has_cdn_api_credentials(),
		);
	}
}
