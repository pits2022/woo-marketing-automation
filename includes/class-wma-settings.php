<?php
defined( 'ABSPATH' ) || exit;

class WMA_Settings {

	private static ?array $settings_cache = null;

	public static function get( string $key = '' ): mixed {
		if ( self::$settings_cache === null ) {
			self::$settings_cache = get_option( 'wma_settings', [] ) ?: [];
		}
		$settings = self::$settings_cache;
		if ( $key === '' ) {
			return $settings;
		}
		$value = $settings;
		foreach ( explode( '.', $key ) as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return null;
			}
			$value = $value[ $part ];
		}
		return $value;
	}

	public static function update( array $settings ): bool {
		self::$settings_cache = $settings;
		return update_option( 'wma_settings', $settings );
	}

	public static function get_reactivation_emails(): array {
		return self::get( 'reactivation_emails' ) ?? [];
	}

	public static function get_reactivation_email( int $id ): ?array {
		foreach ( self::get_reactivation_emails() as $email ) {
			if ( (int) $email['id'] === $id ) {
				return $email;
			}
		}
		return null;
	}

	public static function save_reactivation_email( array $data ): void {
		$settings = self::get();
		$emails   = $settings['reactivation_emails'] ?? [];

		if ( isset( $data['id'] ) && (int) $data['id'] > 0 ) {
			foreach ( $emails as $i => $email ) {
				if ( (int) $email['id'] === (int) $data['id'] ) {
					$emails[ $i ]                    = $data;
					$settings['reactivation_emails'] = $emails;
					self::update( $settings );
					return;
				}
			}
		}

		$max_id          = array_reduce( $emails, static fn( $carry, $e ) => max( $carry, (int) $e['id'] ), 0 );
		$data['id']      = $max_id + 1;
		$emails[]        = $data;
		$settings['reactivation_emails'] = $emails;
		self::update( $settings );
	}

	public static function delete_reactivation_email( int $id ): void {
		$settings                        = self::get();
		$settings['reactivation_emails'] = array_values(
			array_filter(
				$settings['reactivation_emails'] ?? [],
				static fn( $e ) => (int) $e['id'] !== $id
			)
		);
		self::update( $settings );
	}

	public static function toggle_reactivation_email( int $id ): void {
		$settings = self::get();
		if ( ! isset( $settings['reactivation_emails'] ) || ! is_array( $settings['reactivation_emails'] ) ) {
			return;
		}
		foreach ( $settings['reactivation_emails'] as &$email ) {
			if ( (int) $email['id'] === $id ) {
				$email['enabled'] = ! $email['enabled'];
				break;
			}
		}
		unset( $email );
		self::update( $settings );
	}
}
