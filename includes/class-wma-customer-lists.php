<?php
defined( 'ABSPATH' ) || exit;

class WMA_Customer_Lists {

	public static function init(): void {
		add_action( 'woocommerce_order_status_completed', [ self::class, 'schedule_list_additions' ] );
		add_action( 'wma_process_customer_lists',         [ self::class, 'process_list_additions' ] );
	}

	public static function schedule_list_additions( int $order_id ): void {
		wp_schedule_single_event( time(), 'wma_process_customer_lists', [ $order_id ] );
	}

	public static function process_list_additions( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$s              = WMA_Settings::get( 'customer_lists' ) ?? [];
		$customers_list = $s['customers_list'] ?? '';
		$vip_list       = $s['vip_customers_list'] ?? '';
		$vip_amount     = (float) ( $s['vip_customers_amount'] ?? 0 );
		$returning_list = $s['returning_customers_list'] ?? '';

		if ( ! $customers_list ) {
			WMA_Logger::log( "Order {$order_id}: customers_list not configured, skipping.", 'WARNING' );
			return;
		}

		$email = $order->get_billing_email();
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		$is_returning = $returning_list && self::has_previous_order( $email, $order_id );
		$is_vip       = $vip_list && $vip_amount > 0 && (float) $order->get_total() >= $vip_amount;

		WMA_Sendy::subscribe( $email, $name, $customers_list );

		if ( $is_vip ) {
			WMA_Sendy::subscribe( $email, $name, $vip_list );
		}
		if ( $is_returning ) {
			WMA_Sendy::subscribe( $email, $name, $returning_list );
		}

		WMA_Logger::log( "Order {$order_id}: {$email} added to lists (vip={$is_vip}, returning={$is_returning})." );
	}

	private static function has_previous_order( string $email, int $current_order_id ): bool {
		$orders = wc_get_orders( [
			'billing_email' => $email,
			'status'        => 'completed',
			'limit'         => 1,
			'exclude'       => [ $current_order_id ],
		] );
		return ! empty( $orders );
	}
}
