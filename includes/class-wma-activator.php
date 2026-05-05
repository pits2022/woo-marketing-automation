<?php
defined( 'ABSPATH' ) || exit;

class WMA_Activator {

	public static function activate(): void {
		if ( ! get_option( 'wma_settings' ) ) {
			update_option( 'wma_settings', self::default_settings(), false );
		}
		if ( ! wp_next_scheduled( 'wma_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'wma_daily_cron' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wma_daily_cron' );
	}

	public static function uninstall(): void {
		delete_option( 'wma_settings' );
	}

	private static function default_settings(): array {
		return [
			'sendy' => [
				'url'           => '',
				'api_key'       => '',
				'cf_site_key'   => '',
				'cf_secret_key' => '',
			],
			'customer_lists' => [
				'customers_list'           => '',
				'vip_customers_list'       => '',
				'vip_customers_amount'     => '',
				'returning_customers_list' => '',
			],
			'email_template' => [
				'html' => self::default_template_html(),
			],
			'welcome_email' => [
				'subject' => '',
				'message' => '',
			],
			'reactivation_emails' => self::default_reactivation_emails(),
		];
	}

	private static function default_reactivation_emails(): array {
		return [
			[
				'id'                      => 1,
				'name'                    => __( 'Review Request', 'woo-marketing-automation' ),
				'enabled'                 => true,
				'wait_period'             => 7,
				'email_subject'           => '',
				'email_message'           => '',
				'include_review_products' => true,
				'top_sale_products'       => 0,
				'coupon_percent'          => 0,
				'coupon_expiry_percent'   => 30,
				'coupon_expiry_freeship'  => 0,
			],
			[
				'id'                      => 2,
				'name'                    => __( 'Refill Offer', 'woo-marketing-automation' ),
				'enabled'                 => true,
				'wait_period'             => 30,
				'email_subject'           => '',
				'email_message'           => '',
				'include_review_products' => false,
				'top_sale_products'       => 0,
				'coupon_percent'          => 0,
				'coupon_expiry_percent'   => 30,
				'coupon_expiry_freeship'  => 7,
			],
			[
				'id'                      => 3,
				'name'                    => __( 'For-Sale Products', 'woo-marketing-automation' ),
				'enabled'                 => true,
				'wait_period'             => 60,
				'email_subject'           => '',
				'email_message'           => '',
				'include_review_products' => false,
				'top_sale_products'       => 10,
				'coupon_percent'          => 0,
				'coupon_expiry_percent'   => 30,
				'coupon_expiry_freeship'  => 0,
			],
			[
				'id'                      => 4,
				'name'                    => __( 'Final Notification', 'woo-marketing-automation' ),
				'enabled'                 => true,
				'wait_period'             => 90,
				'email_subject'           => '',
				'email_message'           => '',
				'include_review_products' => false,
				'top_sale_products'       => 10,
				'coupon_percent'          => 10,
				'coupon_expiry_percent'   => 7,
				'coupon_expiry_freeship'  => 0,
			],
		];
	}

	private static function default_template_html(): string {
		return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;">
<tr><td style="padding:30px;">

[WMA_MESSAGE]

[WMA_REVIEW_PRODUCTS]

[WMA_DISCOUNT_PRODUCTS]

[WMA_COUPON_CODE_PERCENT]

[WMA_COUPON_CODE_FREESHIPMENT]

<p style="margin-top:30px;font-size:12px;color:#999;border-top:1px solid #eee;padding-top:15px;">
<a href="[WMA_UNSUBSCRIBE_URL]" style="color:#999;">' . esc_html__( 'Unsubscribe', 'woo-marketing-automation' ) . '</a>
</p>

</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
	}
}
