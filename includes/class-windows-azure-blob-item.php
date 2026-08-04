<?php

/**
 * Microsoft Azure Storage blob item.
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

/**
 * Simple blob item class to maintain compatibility with the retired SDK's blob objects.
 *
 * @since 5.0.0
 */
class Windows_Azure_Blob_Item {

	/**
	 * Blob name.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Blob URL.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Blob properties.
	 *
	 * @since 5.0.0
	 *
	 * @var array
	 */
	private $properties;

	/**
	 * Windows_Azure_Blob_Item constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name       Blob name.
	 * @param string $url        Blob URL.
	 * @param array  $properties Blob properties.
	 */
	public function __construct( $name, $url = '', $properties = array() ) {
		$this->name       = $name;
		$this->url        = $url;
		$this->properties = $properties;
	}

	/**
	 * Return blob name.
	 *
	 * @since 5.0.0
	 *
	 * @return string Blob name.
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Return blob URL.
	 *
	 * @since 5.0.0
	 *
	 * @return string Blob URL.
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Return blob properties.
	 *
	 * @since 5.0.0
	 *
	 * @return array Blob properties.
	 */
	public function getProperties() {
		return $this->properties;
	}
}
