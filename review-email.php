<?php
require_once(__DIR__ . '/functions.php');

// --- Configuration ---
$INTERVAL              = 7;
$EMAIL_SUBJECT         = 'Elégedett vagy a termékekkel?';
$SENDY_CUSTOMER_LIST   = $config['SENDY_CUSTOMER_LIST'];
$DEBUG_ORDER_ID        = $config['DEBUG_ORDER_ID'] ?: null;
$DEBUG_EMAIL           = $config['DEBUG_EMAIL'] ?: null;
// --- End configuration ---

$debug = isset($_GET['debug']);

function buildProductTable(WC_Order $order): string {
    $rows = '';
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }

        $name       = $product->get_name();
        $url        = get_permalink($product->get_id());
        $review_url = $url . '#reviews';
        $image_id   = $product->get_image_id();
        $image_url  = $image_id
            ? wp_get_attachment_image_url($image_id, 'thumbnail')
            : wc_placeholder_img_src('thumbnail');

        $rows .= '
        <tr>
            <td style="padding:12px; border-bottom:1px solid #eee; width:90px; vertical-align:middle;">
                <a href="' . esc_url($url) . '" target="_blank">
                    <img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '"
                         style="width:80px; height:80px; object-fit:cover; border:0; display:block;">
                </a>
            </td>
            <td style="padding:12px; border-bottom:1px solid #eee; vertical-align:middle; font-size:15px; color:#0f172a;">
                <a href="' . esc_url($url) . '" target="_blank"
                   style="color:#0f172a; text-decoration:none;">' . esc_html($name) . '</a>
            </td>
            <td style="padding:12px; border-bottom:1px solid #eee; vertical-align:middle; text-align:center; white-space:nowrap;">
                <a href="' . esc_url($review_url) . '" target="_blank"
                   style="display:inline-block; background-color:#54b435; color:#ffffff;
                          text-decoration:none; padding:8px 20px; border-radius:4px;
                          font-size:14px; font-weight:bold;">Ajánlom</a>
            </td>
        </tr>';
    }

    if ($rows === '') {
        return '';
    }

    return '
<p style="margin:0 0 12px 0;">Reméljük, elégedett vagy termékeinkkel!</p>
<p style="margin:0 0 20px 0;">Kérünk segítsd más vásárlóink döntését, és értékeld a megrendelt termékeket:</p>
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
    <tbody>' . $rows . '
    </tbody>
</table>
<p style="margin:20px 0 0 0; font-size:13px; color:#888;">Köszönjük, hogy megosztod véleményedet!</p>';
}

// --- Collect orders to process ---
if ($debug) {
    if (!$DEBUG_ORDER_ID) {
        die('$DEBUG_ORDER_ID nincs beállítva. Szerkeszd a scriptet!');
    }
    $order = wc_get_order($DEBUG_ORDER_ID);
    if (!$order) {
        die("Nem található megrendelés: #{$DEBUG_ORDER_ID}");
    }
    $ORDERS_TO_PROCESS = [$order];
} else {
    $target_date = date('Y-m-d', strtotime("-{$INTERVAL} days"));
    $ORDERS_TO_PROCESS = wc_get_orders([
        'status'         => 'completed',
        'limit'          => -1,
        'date_completed' => $target_date,
        'meta_query'     => [[
            'key'     => '_review_email_sent',
            'compare' => 'NOT EXISTS',
        ]],
    ]);
}

// --- Send emails ---
$sent   = 0;
$errors = 0;

foreach ($ORDERS_TO_PROCESS as $order) {
    $content = buildProductTable($order);
    if ($content === '') {
        error_log("review-email: order #{$order->get_id()} has no processable items, skipped.");
        continue;
    }

    $to   = $debug ? $DEBUG_EMAIL : $order->get_billing_email();
    $name = $order->get_billing_first_name();
    $encoded_email = base64_encode($to);
    $encoded_list  = base64_encode($SENDY_CUSTOMER_LIST);
    $unsubscribe_url = $sendy_url . "/unsubscribe/" . $encoded_email . "/" . $encoded_list;

    if (!$debug && !isSendySubscribed($to, $SENDY_CUSTOMER_LIST, $sendy_url, $api_key)) {
        error_log("review-email: {$to} not subscribed in Sendy, skipped (order #{$order->get_id()}).");
        continue;
    }

    emailSendGeneral($to, $name, $EMAIL_SUBJECT, $content, $unsubscribe_url);

    if (!$debug) {
        $order->update_meta_data('_review_email_sent', current_time('mysql'));
        $order->save();
        error_log("review-email: sent to {$to}, order #{$order->get_id()}");
    }

    $sent++;
}

$mode = $debug ? ' (debug mód, cél: ' . $DEBUG_EMAIL . ')' : '';
echo "Kész{$mode}. Elküldve: {$sent}, hiba: {$errors}.";
