<?php

/**
 * Microsoft Azure Storage REST API list containers response.
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
 * of conditions and the disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this
 * list of conditions  and the disclaimer in the documentation and/or
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
class Windows_Azure_List_Containers_Response extends Windows_Azure_Generic_List_Response {

	/**
	 * Windows_Azure_List_Containers_Response constructor.
	 *
	 * @param string|SimpleXMLElement $xml_response XML response from Azure.
	 * @param string $prefix Search prefix.
	 * @param int $max_results Max results per one request.
	 * @param string $path Unused.
	 * @param Windows_Azure_Rest_Api_Client|null $rest_api_client REST client used for lazy loading of further pages.
	 *
	 * @since 5.0.0
	 *
	 */
	public function __construct( $xml_response, $prefix = '', $max_results = Windows_Azure_Rest_Api_Client::API_REQUEST_BULK_SIZE, $path = '', $rest_api_client = null ) {
		// Parse XML response
		if ( is_string( $xml_response ) ) {
			// Azure list responses are prefixed with a UTF-8 BOM which breaks SimpleXML.
			$xml_response = preg_replace( '/^\xEF\xBB\xBF/', '', $xml_response );
			libxml_use_internal_errors( true );
			$xml = simplexml_load_string( $xml_response );
			if ( false === $xml ) {
				throw new Exception( 'Failed to parse XML response' );
			}
		} else {
			$xml = $xml_response;
		}

		// Extract next marker if present
		$next_marker = isset( $xml->NextMarker ) ? (string) $xml->NextMarker : '';

		parent::__construct( $next_marker, $prefix, $max_results, $path );

		$this->_rest_client = $rest_api_client;

		// Parse containers
		if ( isset( $xml->Containers->Container ) ) {
			foreach ( $xml->Containers->Container as $container ) {
				$this->_items[] = [ 'Name' => (string) $container->Name ];
			}
		}
	}

	/**
	 * Lazy loading of containers.
	 *
	 * @since 4.0.0
	 *
	 * @param string $prefix      Search prefix.
	 * @param int    $max_results Max API listing results.
	 * @param string $next_marker Offset marker.
	 * @param string $path        Optional path. Unused.
	 *
	 * @return null|WP_Error|Windows_Azure_List_Containers_Response Containers list iterator class or WP_Error on failure.
	 */
	protected function _list_items( $prefix, $max_results, $next_marker, $path ) {
		if ( ! $this->_rest_client instanceof Windows_Azure_Rest_Api_Client ) {
			return null;
		}

		return $this->_rest_client->list_containers( $prefix, $max_results, $next_marker );
	}
}
