<?php
defined( 'ABSPATH' ) || exit;

class WMA_Cron {

	public static function init(): void {
		add_action( 'wma_daily_cron', [ self::class, 'run_daily' ] );
	}

	public static function run_daily(): void {
		foreach ( WMA_Settings::get_reactivation_emails() as $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			self::process( $config );
		}
	}

	private static function process( array $config ): void {
		$email_id    = (int) ( $config['id'] ?? 0 );
		$wait_period = (int) ( $config['wait_period'] ?? 0 );
		$subject     = $config['email_subject'] ?? '';
		$meta_key    = '_wma_email_' . $email_id . '_sent';

		if ( ! $email_id || ! $wait_period || ! $subject ) {
			WMA_Logger::log( "Reactivation email {$email_id}: skipped — missing id, wait_period, or subject.", 'WARNING' );
			return;
		}

		$target = gmdate( 'Y-m-d', strtotime( "-{$wait_period} days" ) );
		$page   = 1;
		$limit  = 50;
		$customers_list = WMA_Settings::get( 'customer_lists.customers_list' ) ?? '';

		while ( true ) {
			$orders = wc_get_orders( [
				'limit'          => $limit,
				'paged'          => $page,
				'status'         => 'completed',
				'date_completed' => $target . '...' . $target,
				'meta_key'       => $meta_key,
				'meta_compare'   => 'NOT EXISTS',
			] );

			if ( empty( $orders ) ) {
				break;
			}

			foreach ( $orders as $order ) {
				$to   = $order->get_billing_email();
				$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

				if ( $customers_list && ! WMA_Sendy::is_subscribed( $to, $customers_list ) ) {
					WMA_Logger::log( "Reactivation {$email_id}: {$to} not subscribed — skipping." );
					continue;
				}

				$data = self::build_data( $config, $order, $to, $customers_list );
				$sent = WMA_Email::send( $to, $name, $subject, $data );

				if ( $sent ) {
					$order->update_meta_data( $meta_key, current_time( 'mysql' ) );
					$order->save();
					WMA_Logger::log( "Reactivation {$email_id} sent to {$to} (order #{$order->get_id()})." );
				}
			}

			$page++;
		}
	}

	private static function build_data( array $config, WC_Order $order, string $email, string $list_id ): array {
		$data = [
			'message' => wp_kses_post( $config['email_message'] ?? '' ),
			'list_id' => $list_id,
		];

		if ( ! empty( $config['include_review_products'] ) ) {
			$data['review_products'] = WMA_Email::build_review_products( $order );
		}

		$top = (int) ( $config['top_sale_products'] ?? 0 );
		if ( $top > 0 ) {
			$data['discount_products'] = WMA_Email::build_discount_products( $top );
		}

		$pct = (int) ( $config['coupon_percent'] ?? 0 );
		if ( $pct > 0 ) {
			$expiry = (int) ( $config['coupon_expiry_percent'] ?? 30 );
			$code   = WMA_Coupon::create_percent( $email, $pct, $expiry );
			if ( $code ) {
				$data['coupon_percent_code'] = $code;
			}
		}

		$freeship_expiry = (int) ( $config['coupon_expiry_freeship'] ?? 0 );
		if ( $freeship_expiry > 0 ) {
			$code = WMA_Coupon::create_freeship( $email, $freeship_expiry );
			if ( $code ) {
				$data['coupon_freeship_code'] = $code;
			}
		}

		return $data;
	}
}
