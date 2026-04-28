<?php
require_once(__DIR__ . '/functions.php');

// --- Configuration ---
$INTERVAL            = 90;
$EMAIL_SUBJECT       = 'Rég jártál nálunk!';
$SENDY_CUSTOMER_LIST = $config['SENDY_CUSTOMER_LIST'];
$DEBUG_ORDER_ID      = $config['DEBUG_ORDER_ID'] ?: null;
$DEBUG_EMAIL         = $config['DEBUG_EMAIL'] ?: null;
// --- End configuration ---

$debug = isset($_GET['debug']);

// Build sale table once — same for every recipient
$sale_table = buildTopDiscountedProductsTable();

// --- Collect orders to process ---
if ($debug) {
    if (!$DEBUG_ORDER_ID) {
        die('$DEBUG_ORDER_ID nincs beállítva. Állítsd be a .env-ben!');
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
            'key'     => '_final_email_sent',
            'compare' => 'NOT EXISTS',
        ]],
    ]);
}

// --- Send emails ---
$sent   = 0;
$errors = 0;

foreach ($ORDERS_TO_PROCESS as $order) {
    $order_table = buildOrderProductsTable($order);
    if ($order_table === '') {
        error_log("final-email: order #{$order->get_id()} has no processable items, skipped.");
        continue;
    }

    $to   = $debug ? $DEBUG_EMAIL : $order->get_billing_email();
    $name = $order->get_billing_first_name();

    if (!$debug && !isSendySubscribed($to, $SENDY_CUSTOMER_LIST, $sendy_url, $api_key)) {
        error_log("final-email: {$to} not subscribed in Sendy, skipped (order #{$order->get_id()}).");
        continue;
    }

    $coupon_code = createCC($to, 7);

    $encoded_email   = base64_encode($to);
    $encoded_list    = base64_encode($SENDY_CUSTOMER_LIST);
    $unsubscribe_url = $sendy_url . '/unsubscribe/' . $encoded_email . '/' . $encoded_list;

    $content = '
<p style="margin:0 0 24px 0; font-size:15px; color:#0f172a;">Rég jártál nálunk! Ismételd meg korábbi rendelésedet, vagy vásárolj akciós termékeink közül. Küldünk egy 10%-os kedvezmény kupont, hogy könnyebb legyen a vásárlás.</p>

<p style="margin:0 0 12px 0; font-size:16px; font-weight:bold; color:#0f172a;">Korábbi rendelésed:</p>'
. $order_table;

    if ($sale_table !== '') {
        $content .= '
<p style="margin:24px 0 12px 0; font-size:16px; font-weight:bold; color:#0f172a;">Akciós termékeink:</p>'
        . $sale_table;
    }

    $content .= '
<div style="margin:24px 0; padding:20px; background:#f8fafc; border-radius:6px; text-align:center;">
    <p style="margin:0 0 8px 0; font-size:15px; color:#0f172a;">10%-os kedvezmény kuponkódod:</p>
    <p style="margin:0 0 8px 0; font-size:24px; font-weight:bold; letter-spacing:2px; color:#0f172a;">' . esc_html($coupon_code) . '</p>
    <p style="margin:0; font-size:13px; color:#888;">7 napig érvényes, egyszer használható.</p>
</div>';

    emailSendGeneral($to, $name, $EMAIL_SUBJECT, $content, $unsubscribe_url);

    if (!$debug) {
        $order->update_meta_data('_final_email_sent', current_time('mysql'));
        $order->save();
        error_log("final-email: sent to {$to}, order #{$order->get_id()}, coupon: {$coupon_code}");
    }

    $sent++;
}

$mode = $debug ? ' (debug mód, cél: ' . $DEBUG_EMAIL . ')' : '';
echo "Kész{$mode}. Elküldve: {$sent}, hiba: {$errors}.";
