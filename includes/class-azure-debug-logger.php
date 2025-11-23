<?php
/**
 * Azure Debug Logger
 *
 * Provides debug logging functionality when WP_DEBUG and WP_DEBUG_LOG are enabled.
 * Logs are written to the plugin's logs directory.
 *
 * @package Windows_Azure_Storage
 * @since 6.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Azure_Debug_Logger class.
 *
 * Handles debug logging for Azure Storage operations.
 */
class Azure_Debug_Logger {

	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private static $log_file;

	/**
	 * Whether logging is enabled.
	 *
	 * @var bool
	 */
	private static $enabled;

	/**
	 * Initialize the logger.
	 *
	 * @return void
	 */
	public static function init() {
		// Check if WP_DEBUG and WP_DEBUG_LOG are enabled.
		self::$enabled = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

		if ( ! self::$enabled ) {
			return;
		}

		// Set log file path.
		$log_dir = MSFT_AZURE_PLUGIN_PATH . 'logs';
		self::$log_file = $log_dir . '/azure-debug.log';

		// Create logs directory if it doesn't exist.
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to prevent direct access.
			file_put_contents(
				$log_dir . '/.htaccess',
				"Order deny,allow\nDeny from all"
			);

			// Add index.php to prevent directory listing.
			file_put_contents(
				$log_dir . '/index.php',
				"<?php\n// Silence is golden."
			);
		}
	}

	/**
	 * Check if logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return self::$enabled;
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		self::log( 'DEBUG', $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( 'INFO', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'WARNING', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'ERROR', $message, $context );
	}

	/**
	 * Log a message.
	 *
	 * @param string $level   Log level (DEBUG, INFO, WARNING, ERROR).
	 * @param string $message Message to log.
	 * @param array  $context Optional context data.
	 * @return void
	 */
	private static function log( $level, $message, $context = array() ) {
		if ( ! self::$enabled ) {
			return;
		}

		// Format timestamp.
		$timestamp = gmdate( 'Y-m-d H:i:s' );

		// Format message with context.
		$log_message = sprintf(
			"[%s] %s: %s",
			$timestamp,
			$level,
			$message
		);

		// Add context if provided.
		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context, JSON_PRETTY_PRINT );
		}

		// Add newline.
		$log_message .= "\n";

		// Write to log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( self::$log_file, $log_message, FILE_APPEND );
	}

	/**
	 * Log an operation start.
	 *
	 * @param string $operation Operation name.
	 * @param array  $params    Operation parameters.
	 * @return void
	 */
	public static function log_operation_start( $operation, $params = array() ) {
		self::info(
			sprintf( 'Starting operation: %s', $operation ),
			array( 'params' => $params )
		);
	}

	/**
	 * Log an operation end.
	 *
	 * @param string $operation Operation name.
	 * @param bool   $success   Whether operation succeeded.
	 * @param mixed  $result    Operation result.
	 * @return void
	 */
	public static function log_operation_end( $operation, $success, $result = null ) {
		$level = $success ? 'info' : 'error';
		$status = $success ? 'completed successfully' : 'failed';

		self::$level(
			sprintf( 'Operation %s: %s', $operation, $status ),
			array( 'result' => $result )
		);
	}

	/**
	 * Log a WP_Error.
	 *
	 * @param WP_Error $wp_error WP_Error object.
	 * @param string   $context  Error context.
	 * @return void
	 */
	public static function log_wp_error( $wp_error, $context = '' ) {
		if ( ! is_wp_error( $wp_error ) ) {
			return;
		}

		self::error(
			sprintf(
				'WP_Error in %s: %s',
				$context,
				$wp_error->get_error_message()
			),
			array(
				'code' => $wp_error->get_error_code(),
				'data' => $wp_error->get_error_data(),
			)
		);
	}

	/**
	 * Log an API request.
	 *
	 * @param string $url     API URL.
	 * @param string $method  HTTP method.
	 * @param array  $headers Request headers.
	 * @param mixed  $body    Request body.
	 * @return void
	 */
	public static function log_api_request( $url, $method, $headers = array(), $body = null ) {
		// Sanitize headers to hide sensitive data.
		$safe_headers = $headers;
		if ( isset( $safe_headers['Authorization'] ) ) {
			$safe_headers['Authorization'] = '[REDACTED]';
		}

		self::debug(
			sprintf( 'API Request: %s %s', $method, $url ),
			array(
				'headers' => $safe_headers,
				'body'    => $body,
			)
		);
	}

	/**
	 * Log an API response.
	 *
	 * @param string $url           API URL.
	 * @param int    $response_code HTTP response code.
	 * @param mixed  $response_body Response body.
	 * @return void
	 */
	public static function log_api_response( $url, $response_code, $response_body = null ) {
		$level = ( $response_code >= 200 && $response_code < 300 ) ? 'debug' : 'warning';

		self::$level(
			sprintf( 'API Response: %d from %s', $response_code, $url ),
			array( 'body' => $response_body )
		);
	}

	/**
	 * Get log file path.
	 *
	 * @return string|null Log file path or null if logging disabled.
	 */
	public static function get_log_file() {
		return self::$enabled ? self::$log_file : null;
	}

	/**
	 * Get log file size.
	 *
	 * @return int Log file size in bytes, 0 if not exists or logging disabled.
	 */
	public static function get_log_size() {
		if ( ! self::$enabled || ! file_exists( self::$log_file ) ) {
			return 0;
		}

		return filesize( self::$log_file );
	}

	/**
	 * Clear the log file.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_log() {
		if ( ! self::$enabled || ! file_exists( self::$log_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( self::$log_file, '' );
	}

	/**
	 * Get last N lines from log file.
	 *
	 * @param int $lines Number of lines to retrieve.
	 * @return array Log lines.
	 */
	public static function get_recent_logs( $lines = 100 ) {
		if ( ! self::$enabled || ! file_exists( self::$log_file ) ) {
			return array();
		}

		// Read file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( self::$log_file );

		if ( false === $content ) {
			return array();
		}

		// Split into lines.
		$all_lines = explode( "\n", $content );

		// Remove empty lines.
		$all_lines = array_filter( $all_lines );

		// Get last N lines.
		return array_slice( $all_lines, -$lines );
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string Formatted size.
	 */
	public static function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}

// Initialize logger on plugin load.
add_action( 'plugins_loaded', array( 'Azure_Debug_Logger', 'init' ), 1 );
