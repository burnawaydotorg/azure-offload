<?php

/**
 * Microsoft Azure Storage REST API client.
 *
 * Version: 5.0.0
 * Author: Microsoft Open Technologies, Inc., 10up
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
 * PHP Version 8
 *
 * @category  WordPress_Plugin
 * @package   Windows_Azure_Storage_For_WordPress
 * @author    Microsoft Open Technologies, Inc. <msopentech@microsoft.com>
 * @copyright Microsoft Open Technologies, Inc.
 * @license   BSD-2-Clause, (http://www.opensource.org/licenses/bsd-license.php)
 * @link      http://www.microsoft.com
 * @since     5.0.0
 */

class Windows_Azure_Rest_Api_Client {

	/**
	 * Azure API version.
	 *
	 * @since 5.0.0
	 *
	 * @const string
	 */
	const API_VERSION = '2024-11-04';

	/**
	 * Blob API default request timeout.
	 *
	 * @since 4.0.0
	 *
	 * @const int
	 */
	const API_REQUEST_TIMEOUT = 1800;

	/**
	 * Blob API default bulk size for various operations.
	 *
	 * @since 4.0.0
	 *
	 * @const int
	 */
	const API_REQUEST_BULK_SIZE = 100;

	/**
	 * Maximum blob size uploaded with a single Put Blob request (in bytes).
	 * Larger files are uploaded in chunks via Put Block / Put Block List.
	 *
	 * @since 5.0.0
	 *
	 * @const int
	 */
	const API_SINGLE_PUT_BLOB_LIMIT = 67108864;

	/**
	 * Block size for chunked uploads (in bytes).
	 *
	 * @since 5.0.0
	 *
	 * @const int
	 */
	const API_PUT_BLOCK_SIZE = 4194304;

	/**
	 * Blob API endpoint pattern.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_BLOB_ENDPOINT = 'https://%s.blob.core.windows.net/';

	/**
	 * Azure API version header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_VERSION = 'x-ms-version';

	/**
	 * Azure API date header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_DATE = 'x-ms-date';

	/**
	 * Azure API blob public access header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_BLOB_PUBLIC_ACCESS = 'x-ms-blob-public-access';

	/**
	 * Azure API canonicalized.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_CANONICALIZED_HEADER_PREFIX = 'x-ms-';

	/**
	 * Last-Modified header.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_LAST_MODIFIED = 'last-modified';

	/**
	 * Azure API blob type header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_BLOB_TYPE = 'x-ms-blob-type';

	/**
	 * Azure API blob copy completion time header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_COMPLETION_TIME = 'x-ms-copy-completion-time';

	/**
	 * Azure API blob copy status description header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_STATUS_DESCRIPTION = 'x-ms-copy-status-description';

	/**
	 * Azure API blob copy id header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_ID = 'x-ms-copy-id';

	/**
	 * Azure API blob copy progress header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_PROGRESS = 'x-ms-copy-progress';

	/**
	 * Azure API blob copy source header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_SOURCE = 'x-ms-copy-source';

	/**
	 * Azure API blob copy status header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_COPY_STATUS = 'x-ms-copy-status';

	/**
	 * Azure API blob lease duration header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_LEASE_DURATION = 'x-ms-lease-duration';

	/**
	 * Azure API blob lease state header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_LEASE_STATE = 'x-ms-lease-state';

	/**
	 * Azure API blob lease status header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_LEASE_STATUS = 'x-ms-lease-status';

	/**
	 * Content-Length header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_LENGTH = 'content-length';

	/**
	 * Content-Type header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_TYPE = 'content-type';

	/**
	 * Etag header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_ETAG = 'etag';

	/**
	 * Content-MD5 header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_MD5 = 'content-md5';

	/**
	 * Content-Encoding header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_ENCODING = 'content-encoding';

	/**
	 * Content-Language header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_LANGUAGE = 'content-language';

	/**
	 * Content-Disposition header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CONTENT_DISPOSITION = 'content-disposition';

	/**
	 * Cache-Control header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_CACHE_CONTROL = 'cache-control';

	/**
	 * Azure API blob sequence number header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_BLOB_SEQUENCE_NUMBER = 'x-ms-blob-sequence-number';

	/**
	 * Azure API blob commited block count header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_BLOB_COMMITED_BLOCK_COUNT = 'x-ms-blob-committed-block-count';

	/**
	 * Azure API blob cache control property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CACHE_CONTROL = 'x-ms-blob-cache-control';

	/**
	 * Azure API blob content type property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CONTENT_TYPE = 'x-ms-blob-content-type';

	/**
	 * Azure API blob content MD5 property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CONTENT_MD5 = 'x-ms-blob-content-md5';

	/**
	 * Azure API blob content encoding property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CONTENT_ENCODING = 'x-ms-blob-content-encoding';

	/**
	 * Azure API blob content language property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CONTENT_LANGUAGE = 'x-ms-blob-content-language';

	/**
	 * Azure API blob content disposition property header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_BLOB_CONTENT_DISPOSITION = 'x-ms-blob-content-disposition';

	/**
	 * Azure API blob tier.
	 *
	 * @since 4.4.0
	 *
	 * @const string
	 */
	const API_HEADER_MS_ACCESS_TIER = 'x-ms-access-tier';

	/**
	 * Accept-Ranges header name.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const API_HEADER_ACCEPT_RANGES = 'accept-ranges';

	/**
	 * Container private access type.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const CONTAINER_VISIBILITY_PRIVATE = 'private';

	/**
	 * Container "container" access type (publicily browseable).
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const CONTAINER_VISIBILITY_CONTAINER = 'container';

	/**
	 * Container "blob: access type (direct blob access only).
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const CONTAINER_VISIBILITY_BLOB = 'blob';

	/**
	 * Azure API Append Blob type.
	 *
	 * @since 4.0.0
	 *
	 * @const string
	 */
	const APPEND_BLOB_TYPE = 'AppendBlob';

	/**
	 * Azure Storage account name.
	 *
	 * @since 4.0.0
	 *
	 * @var null|string
	 */
	protected $_account_name;

	/**
	 * Azure Storage access key.
	 *
	 * @since 4.0.0
	 *
	 * @var null|string
	 */
	protected $_access_key;

	/**
	 * List of headers which should be included when computing request signature.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	protected $_signature_headers;

	/**
	 * Windows_Azure_Rest_Api_Client constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param string $account_name Optional storage account name.
	 * @param string $access_key   Optional storage access key.
	 */
	public function __construct( $account_name = null, $access_key = null ) {
		$this->set_account_name( $account_name );
		$this->set_access_key( $access_key );
		$this->_signature_headers = array(
			'Content-Encoding',
			'Content-Language',
			'Content-Length',
			'Content-MD5',
			'Content-Type',
			'Date',
			'If-Modified-Since',
			'If-Match',
			'If-None-Match',
			'If-Unmodified-Since',
			'Range',
		);
	}

	/**
	 * Get storage account name.
	 *
	 * @since 4.0.0
	 *
	 * @return null|string Account name.
	 */
	public function get_account_name() {
		return $this->_account_name;
	}

	/**
	 * Set storage account name.
	 *
	 * @since 4.0.0
	 *
	 * @param null|string $account_name Storage account name.
	 *
	 * @return void
	 */
	public function set_account_name( $account_name ) {
		$this->_account_name = sanitize_text_field( $account_name );
	}

	/**
	 * Get storage access key.
	 *
	 * @since 4.0.0
	 *
	 * @return null|string Access key.
	 */
	public function get_access_key() {
		return $this->_access_key;
	}

	/**
	 * Set storage access key.
	 *
	 * @since 4.0.0
	 *
	 * @param null|string $access_key Storage access key.
	 *
	 * @return void
	 */
	public function set_access_key( $access_key ) {
		$this->_access_key = $access_key;
	}

	/**
	 * Make authenticated Azure REST API request.
	 *
	 * @since 5.0.0
	 *
	 * @param string $method HTTP method (GET, PUT, POST, DELETE, HEAD).
	 * @param string $url Full URL to request.
	 * @param array  $headers Additional headers.
	 * @param string|array $body Request body.
	 *
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	protected function make_request( $method, $url, $headers = array(), $body = '' ) {
		$date = gmdate( 'D, d M Y H:i:s T', time() );

		/*
		 * Build default headers.
		 *
		 * Note: only x-ms-date is sent. Per the Azure SharedKey specification, when
		 * x-ms-date is present the service treats the Date header as empty while
		 * validating the signature, so sending a real Date header (and signing its
		 * value) would make every request fail authentication.
		 */
		$default_headers = array(
			self::API_HEADER_MS_VERSION => self::API_VERSION,
			self::API_HEADER_MS_DATE    => $date,
		);

		// Merge with provided headers
		$headers = array_merge( $default_headers, $headers );

		// Add Content-Length for non-empty bodies
		if ( ! empty( $body ) && ! isset( $headers['Content-Length'] ) ) {
			$headers['Content-Length'] = is_array( $body ) ? 0 : strlen( $body );
		}

		// Generate authorization signature
		$headers['Authorization'] = $this->generate_auth_signature( $method, $url, $headers );

		// Make request
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => apply_filters( 'azure_blob_operation_timeout', self::API_REQUEST_TIMEOUT ),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Generate Azure Storage authorization signature.
	 *
	 * @since 5.0.0
	 *
	 * @param string $method HTTP method.
	 * @param string $url Request URL.
	 * @param array  $headers Request headers.
	 *
	 * @return string Authorization header value.
	 */
	protected function generate_auth_signature( $method, $url, $headers ) {
		$signature_data   = array();
		$signature_data[] = strtoupper( $method );

		// Add signature headers
		foreach ( $this->_signature_headers as $header ) {
			$signature_data[] = isset( $headers[ $header ] ) ? $headers[ $header ] : '';
		}

		// Add canonicalized headers
		$signature_data[] = implode( "\n", $this->_build_canonicalized_headers( $headers ) );

		// Add canonicalized resource
		$signature_data[] = $this->_build_canonicalized_resource( $url, $this->_account_name );

		$string_to_sign = implode( "\n", $signature_data );
		$signature      = base64_encode( hash_hmac( 'sha256', $string_to_sign, base64_decode( $this->_access_key ), true ) );

		return 'SharedKey ' . $this->get_account_name() . ':' . $signature;
	}

	/**
	 * List containers.
	 *
	 * @param string $prefix List containers which names start with this prefix.
	 * @param int $max_results Max containers to return.
	 * @param bool $next_marker Next collection marker.
	 *
	 * @return Windows_Azure_List_Containers_Response|WP_Error List of containers of WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function list_containers( $prefix = '', $max_results = self::API_REQUEST_BULK_SIZE, $next_marker = false ) {
		$max_results = apply_filters( 'azure_blob_list_containers_max_results', $max_results );

		$query_params = array(
			'comp' => 'list',
		);

		if ( ! empty( $prefix ) ) {
			$query_params['prefix'] = $prefix;
		}

		if ( ! empty( $max_results ) ) {
			$query_params['maxresults'] = $max_results;
		}

		if ( $next_marker ) {
			$query_params['marker'] = $next_marker;
		}

		$url = sprintf(
			self::API_BLOB_ENDPOINT,
			$this->_account_name
		) . '?' . http_build_query( $query_params );

		$response = $this->make_request( 'GET', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		try {
			return new Windows_Azure_List_Containers_Response( $body, $prefix, $max_results, '', $this );
		} catch ( Exception $exception ) {
			return new \WP_Error( 500, $exception->getMessage() );
		}
	}

	/**
	 * Create new container.
	 *
	 * @param string $name Container name.
	 * @param string $visibility Container visibility.
	 *
	 * @return string|WP_Error New container name or WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function create_container( $name, $visibility = self::CONTAINER_VISIBILITY_BLOB ) {
		$name = sanitize_title_with_dashes( $name );

		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s?restype=container',
			$this->_account_name,
			$name
		);

		$headers = array();
		if ( in_array( $visibility, array( self::CONTAINER_VISIBILITY_BLOB, self::CONTAINER_VISIBILITY_CONTAINER ), true ) ) {
			$headers[ self::API_HEADER_BLOB_PUBLIC_ACCESS ] = $visibility;
		}

		$response = $this->make_request( 'PUT', $url, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 201 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		return $name;
	}

	/**
	 * Get container properties.
	 *
	 * @param string $name Container name.
	 *
	 * @return array|WP_Error Container properties array of WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function get_container_properties( $name ) {
		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s?restype=container',
			$this->_account_name,
			$name
		);

		$response = $this->make_request( 'HEAD', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$headers = wp_remote_retrieve_headers( $response );

		$properties = array(
			self::API_HEADER_LAST_MODIFIED  => isset( $headers[ self::API_HEADER_LAST_MODIFIED ] ) ? $headers[ self::API_HEADER_LAST_MODIFIED ] : '',
			self::API_HEADER_ETAG           => isset( $headers[ self::API_HEADER_ETAG ] ) ? $headers[ self::API_HEADER_ETAG ] : '',
			self::API_HEADER_LEASE_STATUS   => isset( $headers[ self::API_HEADER_LEASE_STATUS ] ) ? $headers[ self::API_HEADER_LEASE_STATUS ] : '',
			self::API_HEADER_LEASE_STATE    => isset( $headers[ self::API_HEADER_LEASE_STATE ] ) ? $headers[ self::API_HEADER_LEASE_STATE ] : '',
			self::API_HEADER_LEASE_DURATION => isset( $headers[ self::API_HEADER_LEASE_DURATION ] ) ? $headers[ self::API_HEADER_LEASE_DURATION ] : '',
		);

		return $properties;
	}

	/**
	 * Get container ACL.
	 *
	 * @param string $name Container name.
	 *
	 * @return string|WP_Error Container ACL string or WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function get_container_acl( $name ) {
		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s?restype=container&comp=acl',
			$this->_account_name,
			$name
		);

		$response = $this->make_request( 'GET', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$headers    = wp_remote_retrieve_headers( $response );
		$acl_header = isset( $headers[ self::API_HEADER_BLOB_PUBLIC_ACCESS ] ) ? $headers[ self::API_HEADER_BLOB_PUBLIC_ACCESS ] : '';

		if ( empty( $acl_header ) ) {
			$acl_header = self::CONTAINER_VISIBILITY_PRIVATE;
		}

		return $acl_header;
	}

	/**
	 * List blobs in container.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container   Container name.
	 * @param string $prefix      List blobs which names start with this prefix.
	 * @param int    $max_results Max blobs to return.
	 * @param bool   $next_marker Next collection marker.
	 *
	 * @return Windows_Azure_List_Blobs_Response|WP_Error Blobs list or WP_Error on failure.
	 */
	public function list_blobs( $container, $prefix = '', $max_results = self::API_REQUEST_BULK_SIZE, $next_marker = false ) {
		$max_results = apply_filters( 'azure_blob_list_blobs_max_results', $max_results );

		$query_params = array(
			'restype' => 'container',
			'comp'    => 'list',
		);

		if ( ! empty( $prefix ) ) {
			$query_params['prefix'] = $prefix;
		}

		if ( ! empty( $max_results ) ) {
			$query_params['maxresults'] = $max_results;
		}

		if ( $next_marker ) {
			$query_params['marker'] = $next_marker;
		}

		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s?%s',
			$this->_account_name,
			$container,
			http_build_query( $query_params )
		);

		$response = $this->make_request( 'GET', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		try {
			return new Windows_Azure_List_Blobs_Response( $body, $prefix, $max_results, $container, $this );
		} catch ( Exception $exception ) {
			return new \WP_Error( 500, $exception->getMessage() );
		}
	}

	/**
	 * Delete blob from container.
	 *
	 * @param string $container Container name.
	 * @param string $remote_path Remote blob path.
	 *
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function delete_blob( $container, $remote_path ) {
		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s',
			$this->_account_name,
			$container,
			$remote_path
		);

		$response = $this->make_request( 'DELETE', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 202 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		return true;
	}

	/**
	 * Get blob properties.
	 *
	 * @param string $container Container name.
	 * @param string $remote_path Remote blob path.
	 *
	 * @return array|WP_Error Blob properties array or WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function get_blob_properties( $container, $remote_path ) {
		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s',
			$this->_account_name,
			$container,
			$remote_path
		);

		$response = $this->make_request( 'HEAD', $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$headers = wp_remote_retrieve_headers( $response );

		$properties = array(
			self::API_HEADER_LAST_MODIFIED             => isset( $headers[ self::API_HEADER_LAST_MODIFIED ] ) ? $headers[ self::API_HEADER_LAST_MODIFIED ] : '',
			self::API_HEADER_BLOB_TYPE                 => isset( $headers[ self::API_HEADER_BLOB_TYPE ] ) ? $headers[ self::API_HEADER_BLOB_TYPE ] : '',
			self::API_HEADER_COPY_COMPLETION_TIME      => isset( $headers[ self::API_HEADER_COPY_COMPLETION_TIME ] ) ? $headers[ self::API_HEADER_COPY_COMPLETION_TIME ] : '',
			self::API_HEADER_COPY_STATUS_DESCRIPTION   => isset( $headers[ self::API_HEADER_COPY_STATUS_DESCRIPTION ] ) ? $headers[ self::API_HEADER_COPY_STATUS_DESCRIPTION ] : '',
			self::API_HEADER_COPY_ID                   => isset( $headers[ self::API_HEADER_COPY_ID ] ) ? $headers[ self::API_HEADER_COPY_ID ] : '',
			self::API_HEADER_COPY_PROGRESS             => isset( $headers[ self::API_HEADER_COPY_PROGRESS ] ) ? $headers[ self::API_HEADER_COPY_PROGRESS ] : '',
			self::API_HEADER_COPY_SOURCE               => isset( $headers[ self::API_HEADER_COPY_SOURCE ] ) ? $headers[ self::API_HEADER_COPY_SOURCE ] : '',
			self::API_HEADER_COPY_STATUS               => isset( $headers[ self::API_HEADER_COPY_STATUS ] ) ? $headers[ self::API_HEADER_COPY_STATUS ] : '',
			self::API_HEADER_LEASE_DURATION            => isset( $headers[ self::API_HEADER_LEASE_DURATION ] ) ? $headers[ self::API_HEADER_LEASE_DURATION ] : '',
			self::API_HEADER_LEASE_STATE               => isset( $headers[ self::API_HEADER_LEASE_STATE ] ) ? $headers[ self::API_HEADER_LEASE_STATE ] : '',
			self::API_HEADER_LEASE_STATUS              => isset( $headers[ self::API_HEADER_LEASE_STATUS ] ) ? $headers[ self::API_HEADER_LEASE_STATUS ] : '',
			self::API_HEADER_CONTENT_LENGTH            => isset( $headers[ self::API_HEADER_CONTENT_LENGTH ] ) ? $headers[ self::API_HEADER_CONTENT_LENGTH ] : '',
			self::API_HEADER_CONTENT_TYPE              => isset( $headers[ self::API_HEADER_CONTENT_TYPE ] ) ? $headers[ self::API_HEADER_CONTENT_TYPE ] : '',
			self::API_HEADER_ETAG                      => isset( $headers[ self::API_HEADER_ETAG ] ) ? $headers[ self::API_HEADER_ETAG ] : '',
			self::API_HEADER_CONTENT_MD5               => isset( $headers[ self::API_HEADER_CONTENT_MD5 ] ) ? $headers[ self::API_HEADER_CONTENT_MD5 ] : '',
			self::API_HEADER_CONTENT_ENCODING          => isset( $headers[ self::API_HEADER_CONTENT_ENCODING ] ) ? $headers[ self::API_HEADER_CONTENT_ENCODING ] : '',
			self::API_HEADER_CONTENT_LANGUAGE          => isset( $headers[ self::API_HEADER_CONTENT_LANGUAGE ] ) ? $headers[ self::API_HEADER_CONTENT_LANGUAGE ] : '',
			self::API_HEADER_CONTENT_DISPOSITION       => isset( $headers[ self::API_HEADER_CONTENT_DISPOSITION ] ) ? $headers[ self::API_HEADER_CONTENT_DISPOSITION ] : '',
			self::API_HEADER_CACHE_CONTROL             => isset( $headers[ self::API_HEADER_CACHE_CONTROL ] ) ? $headers[ self::API_HEADER_CACHE_CONTROL ] : '',
			self::API_HEADER_BLOB_SEQUENCE_NUMBER      => isset( $headers[ self::API_HEADER_BLOB_SEQUENCE_NUMBER ] ) ? $headers[ self::API_HEADER_BLOB_SEQUENCE_NUMBER ] : '',
			self::API_HEADER_BLOB_COMMITED_BLOCK_COUNT => isset( $headers[ self::API_HEADER_BLOB_COMMITED_BLOCK_COUNT ] ) ? $headers[ self::API_HEADER_BLOB_COMMITED_BLOCK_COUNT ] : '',
		);

		return $properties;
	}

	/**
	 * Put blob properties.
	 *
	 * @param string $container Container name.
	 * @param string $remote_path Remote blob path.
	 * @param array $properties Array with properties.
	 *
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 * @since 4.0.0
	 *
	 */
	public function put_blob_properties( $container, $remote_path, array $properties = array() ) {
		$properties = apply_filters( 'windows_azure_storage_blob_properties', $properties, $container, $remote_path );

		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s?comp=properties',
			$this->_account_name,
			$container,
			$remote_path
		);

		$headers = array();

		// Map properties to headers
		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CACHE_CONTROL ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CACHE_CONTROL ] = $properties[ self::API_HEADER_MS_BLOB_CACHE_CONTROL ];
		}

		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CONTENT_TYPE ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CONTENT_TYPE ] = $properties[ self::API_HEADER_MS_BLOB_CONTENT_TYPE ];
		}

		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CONTENT_MD5 ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CONTENT_MD5 ] = $properties[ self::API_HEADER_MS_BLOB_CONTENT_MD5 ];
		}

		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CONTENT_ENCODING ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CONTENT_ENCODING ] = $properties[ self::API_HEADER_MS_BLOB_CONTENT_ENCODING ];
		}

		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CONTENT_LANGUAGE ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CONTENT_LANGUAGE ] = $properties[ self::API_HEADER_MS_BLOB_CONTENT_LANGUAGE ];
		}

		if ( isset( $properties[ self::API_HEADER_MS_BLOB_CONTENT_DISPOSITION ] ) ) {
			$headers[ self::API_HEADER_MS_BLOB_CONTENT_DISPOSITION ] = $properties[ self::API_HEADER_MS_BLOB_CONTENT_DISPOSITION ];
		}

		$response = $this->make_request( 'PUT', $url, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		// Handle access tier separately if provided
		if ( isset( $properties[ self::API_HEADER_MS_ACCESS_TIER ] ) ) {
			$tier_url = sprintf(
				self::API_BLOB_ENDPOINT . '%s/%s?comp=tier',
				$this->_account_name,
				$container,
				$remote_path
			);

			$tier_headers = array(
				self::API_HEADER_MS_ACCESS_TIER => $properties[ self::API_HEADER_MS_ACCESS_TIER ],
			);

			$tier_response = $this->make_request( 'PUT', $tier_url, $tier_headers );

			if ( is_wp_error( $tier_response ) ) {
				return $tier_response;
			}

			$tier_status_code = wp_remote_retrieve_response_code( $tier_response );
			if ( $tier_status_code !== 200 && $tier_status_code !== 202 ) {
				return new \WP_Error(
					$tier_status_code,
					wp_remote_retrieve_response_message( $tier_response )
				);
			}
		}

		return true;
	}

	// @formatter:off
	/**
	 * Sanitize blobs names. Make sure their names are unique.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container Container name.
	 * @param array  $files     File names structure. Expected:
	 *                          {
	 *                              $prefix_mask_1 => {
	 *                                  $local_path_1_1 => $remote_path_1_1,
	 *                                  $local_path_1_n => $remote_path_1_n
	 *                              },
	 *                              $prefix_mask_n => {
	 *                                  $local_path_n_1 => $remote_path_n_1,
	 *                                  $local_path_n_n => $remote_path_n_n
	 *                              },
	 *                          }
	 *
	 * @return array|WP_Error Sanitized blobs names or WP_Error on failure.
	 */
	// @formatter:on
	public function sanitize_blobs_names( $container, array $files = array() ) {
		if ( empty( $files ) ) {
			return $files;
		}
		$cycles        = 0;
		$was_sanitized = false;
		foreach ( $files as $prefix_group => &$group_contents ) {
			$cycles = 0;
			do {
				$sanitized_group_contents = $this->_sanitize_remote_paths( $container, $prefix_group, $group_contents );
				if ( is_wp_error( $sanitized_group_contents ) ) {
					continue;
				}

				$was_sanitized = $sanitized_group_contents !== $group_contents;
				if ( $was_sanitized ) {
					$group_contents = $sanitized_group_contents;
				}
				$cycles++;
			} while ( $was_sanitized && 5 > $cycles );
		}

		if ( 5 === $cycles && $was_sanitized ) {
			return new WP_Error( -100, __( 'Unable to safely sanitize blob names.', 'windows-azure-storage' ) );
		} else {
			return $files;
		}
	}

	// @formatter:off
	/**
	 * Put blobs on Azure Storage account.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container Container name.
	 * @param array  $files     File names structure. Should be sainitized before exporting. Expected:
	 *                          {
	 *                              $prefix_mask_1 => {
	 *                                  $local_path_1_1 => $remote_path_1_1,
	 *                                  $local_path_1_n => $remote_path_1_n
	 *                              },
	 *                              $prefix_mask_n => {
	 *                                  $local_path_n_1 => $remote_path_n_1,
	 *                                  $local_path_n_n => $remote_path_n_n
	 *                              },
	 *                          }
	 *
	 * @return bool|array True if files collection is empty or array with blobs and put operation responses.
	 */
	// @formatter:on
	public function put_blobs( $container, array $files = array() ) {
		if ( empty( $files ) ) {
			return true;
		}

		$all_contents = array();
		foreach ( $files as $group_contents ) {
			$all_contents += $group_contents;
		}

		foreach ( $all_contents as $local_path => &$remote_path ) {
			$remote_path = $this->put_blob( $container, $local_path, $remote_path );
		}

		return $all_contents;
	}

	/**
	 * Put blob on Azure Storage account.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container                Container name.
	 * @param string $local_path               Local path.
	 * @param string $remote_path              Remote path.
	 * @param bool   $force_direct_file_access Whether to force direct file access.
	 * @param string $content_type             File content type
	 *
	 * @return bool|string|WP_Error Newly put blob URI or WP_Error|false on failure.
	 */
	public function put_blob( $container, $local_path, $remote_path, $force_direct_file_access = false, $content_type = 'application/octet-stream' ) {
		$contents_provider = new Windows_Azure_File_Contents_Provider( $local_path, null );
		$is_valid          = $contents_provider->is_valid();

		if ( ! $is_valid || is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		$file_path = $contents_provider->get_file_path();
		$file_size = filesize( $file_path );

		/**
		 * Filter the size above which uploads switch to chunked Put Block uploads.
		 *
		 * @since 5.0.0
		 *
		 * @param int $limit Single-request upload limit in bytes.
		 */
		$single_put_limit = apply_filters( 'azure_blob_single_put_blob_limit', self::API_SINGLE_PUT_BLOB_LIMIT );

		if ( $file_size > $single_put_limit ) {
			return $this->_put_blob_blocks( $container, $file_path, $remote_path, $content_type );
		}

		$url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s',
			$this->_account_name,
			$container,
			$remote_path
		);

		// Header names must match $_signature_headers exactly so they are signed.
		$headers = array(
			self::API_HEADER_BLOB_TYPE => 'BlockBlob',
			'Content-Type'             => $content_type,
			'Content-Length'           => $file_size,
		);

		// Read file contents
		$blob_content = file_get_contents( $file_path );

		$response = $this->make_request( 'PUT', $url, $headers, $blob_content );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 201 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		return $this->_build_api_endpoint_url( $container . '/' . $remote_path );
	}

	/**
	 * Upload a blob in chunks using Put Block / Put Block List so large files
	 * are never loaded into memory at once.
	 *
	 * @since 5.0.0
	 *
	 * @param string $container    Container name.
	 * @param string $file_path    Local file path.
	 * @param string $remote_path  Remote path.
	 * @param string $content_type File content type.
	 *
	 * @return string|WP_Error Newly put blob URI or WP_Error on failure.
	 */
	protected function _put_blob_blocks( $container, $file_path, $remote_path, $content_type ) {
		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return new \WP_Error( -1, __( 'Unable to open file for reading.', 'windows-azure-storage' ) );
		}

		$block_ids = array();
		$index     = 0;

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, self::API_PUT_BLOCK_SIZE );
			if ( false === $chunk ) {
				fclose( $handle );

				return new \WP_Error( -1, __( 'Unable to read file chunk.', 'windows-azure-storage' ) );
			}

			if ( '' === $chunk ) {
				break;
			}

			// All block ids must have the same length before encoding.
			$block_id = base64_encode( sprintf( 'block-%08d', $index ) );

			$url = sprintf(
				self::API_BLOB_ENDPOINT . '%s/%s?%s',
				$this->_account_name,
				$container,
				$remote_path,
				http_build_query(
					array(
						'comp'    => 'block',
						'blockid' => $block_id,
					)
				)
			);

			$response = $this->make_request( 'PUT', $url, array( 'Content-Length' => strlen( $chunk ) ), $chunk );

			if ( is_wp_error( $response ) ) {
				fclose( $handle );

				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 201 !== $status_code ) {
				fclose( $handle );

				return new \WP_Error(
					$status_code,
					wp_remote_retrieve_response_message( $response )
				);
			}

			$block_ids[] = $block_id;
			$index++;
		}

		fclose( $handle );

		$block_list = '<?xml version="1.0" encoding="utf-8"?><BlockList>';
		foreach ( $block_ids as $block_id ) {
			$block_list .= '<Latest>' . $block_id . '</Latest>';
		}
		$block_list .= '</BlockList>';

		$commit_url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s?comp=blocklist',
			$this->_account_name,
			$container,
			$remote_path
		);

		$commit_headers = array(
			'Content-Type'                        => 'application/xml',
			'Content-Length'                      => strlen( $block_list ),
			self::API_HEADER_MS_BLOB_CONTENT_TYPE => $content_type,
		);

		$response = $this->make_request( 'PUT', $commit_url, $commit_headers, $block_list );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		return $this->_build_api_endpoint_url( $container . '/' . $remote_path );
	}

	/**
	 * Copy blob within same container on Azure Storage account.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container                Container name.
	 * @param string $source_path              Source path.
	 * @param string $destination_path         Destination path.
	 *
	 * @return bool|string|WP_Error Newly put blob URI or WP_Error|false on failure.
	 */
	public function copy_blob( $container, $source_path, $destination_path ) {
		$destination_url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s',
			$this->_account_name,
			$container,
			$destination_path
		);

		$source_url = sprintf(
			self::API_BLOB_ENDPOINT . '%s/%s',
			$this->_account_name,
			$container,
			$source_path
		);

		$headers = array(
			self::API_HEADER_COPY_SOURCE => $source_url,
		);

		$response = $this->make_request( 'PUT', $destination_url, $headers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 202 ) {
			return new \WP_Error(
				$status_code,
				wp_remote_retrieve_response_message( $response )
			);
		}

		$destination_path = '/' . ltrim( $destination_path, '/' );

		return $this->_build_api_endpoint_url( $container . $destination_path );
	}

	/**
	 * Return Blob API endpoint URL.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path URI path.
	 *
	 * @return string|WP_Error Endpoint URL or WP_Error on failure.
	 */
	protected function _build_api_endpoint_url( $path = '' ) {
		if ( empty( $this->_account_name ) ) {
			return new WP_Error( -1, __( 'Storage account name not set.', 'windows-azure-storage' ) );
		}

		$endpoint_url = sprintf( self::API_BLOB_ENDPOINT, $this->_account_name );

		return $endpoint_url . trim( $path );
	}

	/**
	 * Build canonicalized headers collection.
	 *
	 * @since 4.0.0
	 *
	 * @param array $headers Headers.
	 *
	 * @return array Canonicalized headers structure.
	 */
	protected function _build_canonicalized_headers( array $headers ) {
		$normalized_headers    = array();
		$canonicalized_headers = array();

		foreach ( $headers as $header => $value ) {
			$header = strtolower( $header );
			$header = ltrim( $header );
			if ( 0 !== strpos( $header, self::API_CANONICALIZED_HEADER_PREFIX ) ) {
				continue;
			}

			$value                         = str_replace( "\r\n", ' ', $value );
			$value                         = rtrim( $value );
			$normalized_headers[ $header ] = $value;
		}

		ksort( $normalized_headers );

		foreach ( $normalized_headers as $key => $value ) {
			$canonicalized_headers[] = $key . ':' . $value;
		}

		return $canonicalized_headers;
	}

	/**
	 * Build canonicalized resource string.
	 *
	 * @since 4.0.0
	 *
	 * @param string $url          Endpoint URL.
	 * @param string $account_name Storage account name.
	 *
	 * @return string Canonicalized resource string.
	 */
	protected function _build_canonicalized_resource( $url, $account_name ) {
		/** @var $parsed_url array */
		$parsed_url             = parse_url( $url );
		$canonicalized_resource = '/' . $account_name . ( isset( $parsed_url['path'] ) ? $parsed_url['path'] : '' );

		if ( isset( $parsed_url['query'] ) ) {
			$query = array();
			parse_str( $parsed_url['query'], $query );
			array_change_key_case( $query, CASE_LOWER );
			ksort( $query );

			foreach ( $query as $key => $value ) {
				$values = explode( ',', $value );
				sort( $values );
				$canonicalized_resource .= "\n" . $key . ':' . rawurldecode( implode( ',', $values ) );
			}
		}

		return $canonicalized_resource;
	}

	/**
	 * Sanitize remote paths. Check if given paths exist and append unique suffix when necessary.
	 *
	 * @since 4.0.0
	 *
	 * @param string $container      Container to check paths against.
	 * @param string $prefix_group   Prefix check group.
	 * @param array  $group_contents Group contents.
	 *
	 * @return array|WP_Error Sanitized remote paths array or WP_Error on failure.
	 */
	protected function _sanitize_remote_paths( $container, $prefix_group, $group_contents ) {
		if ( ! is_array( $group_contents ) ) {
			return new \WP_Error( -1, __( 'Error when sanitizing filename.', 'windows-azure-storage' ) );
		}

		$remote_paths = array_flip( $group_contents );
		$blobs        = $this->list_blobs( $container, $prefix_group );

		if ( is_wp_error( $blobs ) ) {
			return $blobs;
		}

		$needs_sanitization = array();
		foreach ( $blobs as $blob ) {
			$blob_name = $blob->getName();
			if ( isset( $remote_paths[ $blob_name ] ) ) {
				$needs_sanitization[] = $blob_name;
				unset( $remote_paths[ $blob_name ] );

				// quit early as $blob is an Iterator instance with lazy loading.
				if ( 0 === count( $remote_paths ) ) {
					break;
				}
			}
		}

		if ( empty( $needs_sanitization ) ) {
			return $group_contents;
		}

		$sanitized_names = array();
		$remote_paths    = array_flip( $group_contents );

		foreach ( $needs_sanitization as $item ) {
			$info     = pathinfo( $item );
			$dirname  = isset( $info['dirname'] ) ? ltrim( $info['dirname'], '.' ) : '';
			$new_name = ! empty( $dirname ) ? trailingslashit( $dirname ) : '';
			$new_name .= $info['filename'] . '-' . uniqid( '', false );
			$new_name .= isset( $info['extension'] ) ? '.' . $info['extension'] : '';
			$sanitized_names[ $item ] = $new_name;
		}

		foreach ( $sanitized_names as $original_path => $fixed_path ) {
			if ( ! isset( $remote_paths[ $original_path ] ) ) {
				continue;
			}

			$index                    = $remote_paths[ $original_path ];
			$group_contents[ $index ] = $fixed_path;
		}

		return $group_contents;
	}
}
