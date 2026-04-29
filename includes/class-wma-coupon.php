<?php
defined( 'ABSPATH' ) || exit;

class WMA_Coupon {

	public static function create_percent( string $email, int $percent, int $expiry_days ): string|false {
		return self::create( self::generate_code( $email ), 'percent', $percent, $expiry_days );
	}

	public static function create_freeship( string $email, int $expiry_days ): string|false {
		return self::create( self::generate_code( $email ), 'free_shipping', 0, $expiry_days );
	}

	private static function generate_code( string $email ): string {
		$hash = strtoupper( substr( hash( 'sha256', $email . gmdate( 'Y-m-d' ) . wp_salt() ), 0, 16 ) );
		return 'WMA-' . substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 8 );
	}

	private static function create( string $code, string $discount_type, int $amount, int $expiry_days ): string|false {
		if ( wc_get_coupon_id_by_code( $code ) ) {
			return $code;
		}

		$coupon_id = wp_insert_post( [
			'post_title'  => $code,
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $coupon_id ) ) {
			WMA_Logger::log( 'Coupon creation failed: ' . $coupon_id->get_error_message(), 'ERROR' );
			return false;
		}

		$expiry = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( "+{$expiry_days} days" )
			->format( 'Y-m-d' );

		$meta = [
			'discount_type'      => $discount_type,
			'coupon_amount'      => $amount,
			'date_expires'       => strtotime( $expiry ),
			'usage_limit'        => 1,
			'individual_use'     => 'yes',
			'exclude_sale_items' => ( $discount_type === 'percent' ) ? 'yes' : 'no',
			'free_shipping'      => ( $discount_type === 'free_shipping' ) ? 'yes' : 'no',
		];

		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		return $code;
	}
}
