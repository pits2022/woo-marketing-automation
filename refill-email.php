<?php
require_once(__DIR__ . '/functions.php');

// --- Configuration ---
$INTERVAL            = 30;
$EMAIL_SUBJECT       = 'Hamarosan elfogy?';
$SENDY_CUSTOMER_LIST = $config['SENDY_CUSTOMER_LIST'];
$DEBUG_ORDER_ID      = $config['DEBUG_ORDER_ID'] ?: null;
$DEBUG_EMAIL         = $config['DEBUG_EMAIL'] ?: null;
// --- End configuration ---

$debug = isset($_GET['debug']);

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
            'key'     => '_refill_email_sent',
            'compare' => 'NOT EXISTS',
        ]],
    ]);
}

// --- Send emails ---
$sent   = 0;
$errors = 0;

foreach ($ORDERS_TO_PROCESS as $order) {
    $product_table = buildOrderProductsTable($order);
    if ($product_table === '') {
        error_log("refill-email: order #{$order->get_id()} has no processable items, skipped.");
        continue;
    }

    $to   = $debug ? $DEBUG_EMAIL : $order->get_billing_email();
    $name = $order->get_billing_first_name();

    if (!$debug && !isSendySubscribed($to, $SENDY_CUSTOMER_LIST, $sendy_url, $api_key)) {
        error_log("refill-email: {$to} not subscribed in Sendy, skipped (order #{$order->get_id()}).");
        continue;
    }

    $coupon_code = createCCFreeShipment($to);

    $encoded_email   = base64_encode($to);
    $encoded_list    = base64_encode($SENDY_CUSTOMER_LIST);
    $unsubscribe_url = $sendy_url . '/unsubscribe/' . $encoded_email . '/' . $encoded_list;

    $content = '
<p style="margin:0 0 20px 0; font-size:15px; color:#0f172a;">Lehet, hogy hamarosan elfogy amit rendeltél. Most egy ingyenes szállítás kupont is adunk, hogy könnyebb legyen a vásárlás.</p>'
. $product_table .
'<div style="margin:24px 0; padding:20px; background:#f8fafc; border-radius:6px; text-align:center;">
    <p style="margin:0 0 8px 0; font-size:15px; color:#0f172a;">Ingyenes szállítás kuponkódod:</p>
    <p style="margin:0 0 8px 0; font-size:24px; font-weight:bold; letter-spacing:2px; color:#0f172a;">' . esc_html($coupon_code) . '</p>
    <p style="margin:0; font-size:13px; color:#888;">7 napig érvényes, egyszer használható.</p>
</div>';

    emailSendGeneral($to, $name, $EMAIL_SUBJECT, $content, $unsubscribe_url);

    if (!$debug) {
        $order->update_meta_data('_refill_email_sent', current_time('mysql'));
        $order->save();
        error_log("refill-email: sent to {$to}, order #{$order->get_id()}, coupon: {$coupon_code}");
    }

    $sent++;
}

$mode = $debug ? ' (debug mód, cél: ' . $DEBUG_EMAIL . ')' : '';
echo "Kész{$mode}. Elküldve: {$sent}, hiba: {$errors}.";
