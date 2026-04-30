<?php
defined( 'ABSPATH' ) || exit;

class WMA_Sendy {

	public static function subscribe( string $email, string $name, string $list_id ): bool {
		$settings = WMA_Settings::get( 'sendy' );
		$base_url = trailingslashit( $settings['url'] ?? '' );
		$api_key  = $settings['api_key'] ?? '';

		if ( ! $base_url || ! $api_key || ! $list_id ) {
			WMA_Logger::log( 'Sendy subscribe skipped — missing config.', 'WARNING' );
			return false;
		}

		$response = wp_remote_post( $base_url . 'subscribe', [
			'timeout' => 15,
			'body'    => [
				'api_key' => $api_key,
				'email'   => $email,
				'name'    => $name,
				'list'    => $list_id,
				'boolean' => 'true',
			],
		] );

		if ( is_wp_error( $response ) ) {
			WMA_Logger::log( 'Sendy subscribe error: ' . $response->get_error_message(), 'ERROR' );
			return false;
		}

		$body    = trim( wp_remote_retrieve_body( $response ) );
		$success = ( $body === '1' );

		if ( ! $success ) {
			WMA_Logger::log( "Sendy subscribe failed for {$email} on list {$list_id}: {$body}", 'WARNING' );
		}

		return $success;
	}

	public static function is_subscribed( string $email, string $list_id ): bool {
		$settings = WMA_Settings::get( 'sendy' );
		$base_url = trailingslashit( $settings['url'] ?? '' );
		$api_key  = $settings['api_key'] ?? '';

		if ( ! $base_url || ! $api_key || ! $list_id ) {
			return false;
		}

		$response = wp_remote_post( $base_url . 'api/subscribers/subscription-status.php', [
			'timeout' => 15,
			'body'    => [
				'api_key' => $api_key,
				'email'   => $email,
				'list_id' => $list_id,
			],
		] );

		if ( is_wp_error( $response ) ) {
			WMA_Logger::log( 'Sendy is_subscribed error: ' . $response->get_error_message(), 'ERROR' );
			return false;
		}

		return trim( wp_remote_retrieve_body( $response ) ) === 'Subscribed';
	}

	public static function subscribe_async( string $email, string $name, string $list_id ): void {
		wp_schedule_single_event( time(), 'wma_sendy_subscribe', [ $email, $name, $list_id ] );
	}

	public static function handle_async_subscribe( string $email, string $name, string $list_id ): void {
		self::subscribe( $email, $name, $list_id );
	}

	public static function unsubscribe_url( string $email, string $list_id ): string {
		$base_url = trailingslashit( WMA_Settings::get( 'sendy.url' ) ?? '' );
		return $base_url . 'unsubscribe/' . base64_encode( $email ) . '/' . base64_encode( $list_id );
	}
}

add_action( 'wma_sendy_subscribe', [ 'WMA_Sendy', 'handle_async_subscribe' ], 10, 3 );
