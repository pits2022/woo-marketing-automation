<?php
defined( 'ABSPATH' ) || exit;

class WMA_Email {

	/**
	 * Render the global template with the given data and send via wp_mail.
	 *
	 * @param array $data {
	 *   string  $message            Content for [WMA_MESSAGE].
	 *   string  $list_id            Sendy list ID (used for unsubscribe URL).
	 *   string  $review_products    Pre-built HTML for [WMA_REVIEW_PRODUCTS].
	 *   string  $discount_products  Pre-built HTML for [WMA_DISCOUNT_PRODUCTS].
	 *   string  $coupon_percent_code  Coupon code for [WMA_COUPON_CODE_PERCENT].
	 *   string  $coupon_freeship_code Coupon code for [WMA_COUPON_CODE_FREESHIPMENT].
	 * }
	 */
	public static function send( string $to, string $name, string $subject, array $data ): bool {
		$template = WMA_Settings::get( 'email_template.html' ) ?? '';
		if ( ! $template ) {
			WMA_Logger::log( 'Email send skipped — no template configured.', 'WARNING' );
			return false;
		}

		$list_id     = $data['list_id'] ?? '';
		$unsub_url   = $list_id ? WMA_Sendy::unsubscribe_url( $to, $list_id ) : '#';
		$html        = self::render( $template, $data, $unsub_url );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];
		if ( $list_id ) {
			$headers[] = 'List-Unsubscribe: <' . $unsub_url . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		$result = wp_mail( $to, $subject, $html, $headers );
		if ( ! $result ) {
			WMA_Logger::log( "wp_mail failed: to={$to}, subject={$subject}", 'ERROR' );
		}
		return $result;
	}

	private static function render( string $template, array $data, string $unsub_url ): string {
		$coupon_percent  = $data['coupon_percent_code']  ?? '';
		$coupon_freeship = $data['coupon_freeship_code'] ?? '';

		$replacements = [
			'[WMA_MESSAGE]'               => $data['message'] ?? '',
			'[WMA_UNSUBSCRIBE_URL]'       => esc_url( $unsub_url ),
			'[WMA_REVIEW_PRODUCTS]'       => $data['review_products'] ?? '',
			'[WMA_DISCOUNT_PRODUCTS]'     => $data['discount_products'] ?? '',
			'[WMA_COUPON_CODE_PERCENT]'   => $coupon_percent ? self::coupon_html( $coupon_percent ) : '',
			'[WMA_COUPON_CODE_FREESHIPMENT]' => $coupon_freeship ? self::coupon_html( $coupon_freeship ) : '',
		];

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	private static function coupon_html( string $code ): string {
		return '<div style="text-align:center;margin:20px 0;">'
			. '<p style="margin-bottom:8px;">' . esc_html__( 'Your coupon code:', 'woo-marketing-automation' ) . '</p>'
			. '<strong style="font-size:22px;letter-spacing:2px;background:#f0f0f0;padding:10px 20px;border-radius:4px;display:inline-block;">'
			. esc_html( $code )
			. '</strong>'
			. '</div>';
	}

	public static function build_review_products( WC_Order $order ): string {
		$rows = '';
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$url  = esc_url( get_permalink( $product->get_id() ) );
			$name = esc_html( $item->get_name() );
			$qty  = (int) $item->get_quantity();
			$rows .= "<tr>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;'><a href='{$url}'>{$name}</a></td>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$qty}</td>"
				. "</tr>";
		}
		if ( ! $rows ) {
			return '';
		}
		$th_product = esc_html__( 'Product', 'woo-marketing-automation' );
		$th_qty     = esc_html__( 'Qty', 'woo-marketing-automation' );
		return "<table style='width:100%;border-collapse:collapse;'>"
			. "<thead><tr>"
			. "<th style='padding:8px;text-align:left;border-bottom:2px solid #ddd;'>{$th_product}</th>"
			. "<th style='padding:8px;text-align:center;border-bottom:2px solid #ddd;'>{$th_qty}</th>"
			. "</tr></thead>"
			. "<tbody>{$rows}</tbody>"
			. "</table>";
	}

	public static function build_discount_products( int $count ): string {
		$sale_ids    = wc_get_product_ids_on_sale();
		$products    = [];
		$seen_parents = [];

		foreach ( $sale_ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_on_sale() ) {
				continue;
			}
			$parent_id = $product->get_parent_id() ?: $id;
			if ( isset( $seen_parents[ $parent_id ] ) ) {
				continue;
			}
			$seen_parents[ $parent_id ] = true;

			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_sale_price();
			if ( $regular <= 0 ) {
				continue;
			}
			$products[] = [ 'product' => $product, 'ratio' => ( $regular - $sale ) / $regular ];
		}

		usort( $products, static fn( $a, $b ) => $b['ratio'] <=> $a['ratio'] );
		$products = array_slice( $products, 0, $count );

		if ( ! $products ) {
			return '';
		}

		$rows = '';
		foreach ( $products as $item ) {
			$product  = $item['product'];
			$url      = esc_url( get_permalink( $product->get_id() ) );
			$name     = esc_html( $product->get_name() );
			$regular  = wc_price( $product->get_regular_price() );
			$sale     = wc_price( $product->get_sale_price() );
			$pct      = round( $item['ratio'] * 100 );
			$img_id   = $product->get_image_id();
			$img_html = '';
			if ( $img_id ) {
				$img_url  = wp_get_attachment_image_url( $img_id, 'thumbnail' );
				$img_html = $img_url
					? "<img src='" . esc_url( $img_url ) . "' style='width:60px;height:60px;object-fit:cover;border-radius:4px;' alt=''>"
					: '';
			}
			$rows .= "<tr>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;width:70px;'>{$img_html}</td>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;'><a href='{$url}'>{$name}</a></td>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;text-decoration:line-through;color:#999;'>{$regular}</td>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;color:#c00;font-weight:bold;'>{$sale}</td>"
				. "<td style='padding:8px;border-bottom:1px solid #eee;'><span style='background:#c00;color:#fff;padding:2px 6px;border-radius:3px;font-size:12px;'>-{$pct}%</span></td>"
				. "</tr>";
		}

		$th_img      = '';
		$th_product  = esc_html__( 'Product', 'woo-marketing-automation' );
		$th_regular  = esc_html__( 'Regular', 'woo-marketing-automation' );
		$th_sale     = esc_html__( 'Sale', 'woo-marketing-automation' );
		$th_discount = esc_html__( 'Discount', 'woo-marketing-automation' );

		return "<table style='width:100%;border-collapse:collapse;'>"
			. "<thead><tr>"
			. "<th style='padding:8px;border-bottom:2px solid #ddd;'>{$th_img}</th>"
			. "<th style='padding:8px;text-align:left;border-bottom:2px solid #ddd;'>{$th_product}</th>"
			. "<th style='padding:8px;text-align:left;border-bottom:2px solid #ddd;'>{$th_regular}</th>"
			. "<th style='padding:8px;text-align:left;border-bottom:2px solid #ddd;'>{$th_sale}</th>"
			. "<th style='padding:8px;text-align:left;border-bottom:2px solid #ddd;'>{$th_discount}</th>"
			. "</tr></thead>"
			. "<tbody>{$rows}</tbody>"
			. "</table>";
	}
}
