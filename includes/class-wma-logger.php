<?php
defined( 'ABSPATH' ) || exit;

class WMA_Logger {

	private static string $log_file = '';

	private static function init_log_dir(): void {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/wma-logs';

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		if ( ! file_exists( $log_dir . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_dir . '/index.php', '<?php' . PHP_EOL . '// Silence is golden.' . PHP_EOL );
		}

		if ( ! file_exists( $log_dir . '/.htaccess' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_dir . '/.htaccess', 'Deny from all' . PHP_EOL );
		}

		self::$log_file = $log_dir . '/wma-debug.log';
	}

	public static function log( string $message, string $level = 'INFO' ): void {
		if ( self::$log_file === '' ) {
			self::init_log_dir();
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
