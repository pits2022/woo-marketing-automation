<?php
defined( 'ABSPATH' ) || exit;

class WMA_Logger {

	private static string $log_file = '';

	public static function log( string $message, string $level = 'INFO' ): void {
		if ( self::$log_file === '' ) {
			self::$log_file = WP_CONTENT_DIR . '/wma-debug.log';
		}
		$line = sprintf(
			'[%s] [%s] %s' . PHP_EOL,
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( $level ),
			$message
		);
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $line, 3, self::$log_file );
	}
}
