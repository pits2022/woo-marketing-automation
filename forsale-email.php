<?php
require_once(__DIR__ . '/functions.php');

// --- Configuration ---
$INTERVAL            = 60;
$EMAIL_SUBJECT       = 'Akciós termékeink';
$SENDY_CUSTOMER_LIST = $config['SENDY_CUSTOMER_LIST'];
$DEBUG_ORDER_ID      = $config['DEBUG_ORDER_ID'] ?: null;
$DEBUG_EMAIL         = $config['DEBUG_EMAIL'] ?: null;
// --- End configuration ---

$debug = isset($_GET['debug']);

// Build once — same content for every recipient
$sale_table = buildTopDiscountedProductsTable();
$product_table = $sale_table !== ''
    ? '<p style="margin:0 0 20px 0; font-size:15px; color:#0f172a;">Most kedvező áron vásárolhatsz akciós termékeink közül.</p>' . $sale_table
    : '';
if ($product_table === '' && !$debug) {
    die('Nincs akciós termék, nincs mit küldeni.');
}

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
            'key'     => '_forsale_email_sent',
            'compare' => 'NOT EXISTS',
        ]],
    ]);
}

// --- Send emails ---
$sent   = 0;
$errors = 0;

foreach ($ORDERS_TO_PROCESS as $order) {
    $to   = $debug ? $DEBUG_EMAIL : $order->get_billing_email();
    $name = $order->get_billing_first_name();

    if (!$debug && !isSendySubscribed($to, $SENDY_CUSTOMER_LIST, $sendy_url, $api_key)) {
        error_log("forsale-email: {$to} not subscribed in Sendy, skipped (order #{$order->get_id()}).");
        continue;
    }

    $encoded_email   = base64_encode($to);
    $encoded_list    = base64_encode($SENDY_CUSTOMER_LIST);
    $unsubscribe_url = $sendy_url . '/unsubscribe/' . $encoded_email . '/' . $encoded_list;

    emailSendGeneral($to, $name, $EMAIL_SUBJECT, $product_table, $unsubscribe_url);

    if (!$debug) {
        $order->update_meta_data('_forsale_email_sent', current_time('mysql'));
        $order->save();
        error_log("forsale-email: sent to {$to}, order #{$order->get_id()}");
    }

    $sent++;
}

$mode = $debug ? ' (debug mód, cél: ' . $DEBUG_EMAIL . ')' : '';
echo "Kész{$mode}. Elküldve: {$sent}, hiba: {$errors}.";
