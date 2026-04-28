<?php
$config = parse_ini_file(__DIR__ . '/.env');
if ($config === false) {
    error_log('FATAL: .env file missing or unreadable at ' . __DIR__ . '/.env');
    die('Configuration error.');
}

require_once($config['WP_LOAD_PATH']);

$sendy_url    = $config['SENDY_URL'];
$list         = $config['SENDY_LIST'];
$api_key      = $config['SENDY_API_KEY'];
$cf_secretKey = $config['CF_SECRET_KEY'];
$remote_ip    = $_SERVER['REMOTE_ADDR'];

function verifyTurnstileToken($token, $secretKey) {
	$url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	$data = [
        'secret' => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
	];
	$options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
	];
	$context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return json_decode($result, true);
}

function getGRCResponse($token, $remote_ip) {
    global $config;
    $secret_key = $config['GRC_SECRET_KEY'];
    $request_url = 'https://www.google.com/recaptcha/api/siteverify';
    $request_data = [
	'secret' => $secret_key,
	'remoteip' => $remote_ip,
	'response' => $token
	];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response_body = curl_exec($ch);
    curl_close ($ch);
    $response_data = json_decode($response_body, true);

    error_log("INFO - getGRCResponse: " . print_r($response_data, true));

    if ($response_data['success']) {
	if ($response_data['score'] > 0.5) {
	    return true;
	}
    } else {
	return false;
    }
}

function allowedRcpt($email) {
$allowed_rcpt = array('gmail.com', '.hu', '.de', '.ro', '.com', '.eu');
$allowed = false;

    foreach ($allowed_rcpt as $rcpt) {
	$pos = strpos($email, $rcpt);
	if ($pos !== false) {
	    $allowed = true;
	}
    }
return $allowed;
}

function emailSend($to, $name, $cc) {
    global $config;
    $subject = 'Köszönjük a feliratkozást!';
    $body = file_get_contents($config['SENDY_DIR'] . '/email.tpl');
    if ($name != "") {
	$body = str_replace('___NAME___', "Kedves $name!", $body);
    } else {
	$body = str_replace('___NAME___', "", $body);
    }
    $body = str_replace('___CODE___', $cc, $body);
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $subject, $body, $headers);
}

function emailSendGeneral($to, $name, $sub, $content, $unsubscribe_url) {
    global $config;
    $subject = $sub;
    $body = file_get_contents($config['SENDY_DIR'] . '/hirlevel.tpl');
    if ($name != "") {
	$body = str_replace('___NAME___', "Kedves $name!", $body);
    } else {
	$body = str_replace('___NAME___', "", $body);
    }
    $body = str_replace('__UNSUBSCRIBE_URL__', $unsubscribe_url, $body);
    $body = str_replace('[TARTALOM]', $content, $body);
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $subject, $body, $headers);
}

function createCC($email, $expiry = 30) {
    $date = date('Y-m-d');
    $cc = str_split(strtoupper(base64_encode(hash('sha256', $email . $date))), 8);
    $coupon_code = 'WMA-SUB-' . $cc[0] . '-' . $cc[1];
    $amount = '10';
    $discount_type = 'percent';

    $expiry_date = date('Y-m-d', strtotime($date . '+ ' . $expiry . ' days'));

    $coupon = array(
	'post_title'	=> $coupon_code,
	'post_content'	=> '',
	'post_status'	=> 'publish',
	'post_author'	=> 1,
	'post_type'	=> 'shop_coupon'
    );

    $new_coupon_id = wp_insert_post($coupon);

    update_post_meta($new_coupon_id, 'discount_type', $discount_type);
    update_post_meta($new_coupon_id, 'coupon_amount', $amount);
    update_post_meta($new_coupon_id, 'individual_use', 'yes');
    update_post_meta($new_coupon_id, 'product_ids', '');
    update_post_meta($new_coupon_id, 'exclude_product_ids', '');
    update_post_meta($new_coupon_id, 'usage_limit', '');
    update_post_meta($new_coupon_id, 'expiry_date', $expiry_date);
    update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
    update_post_meta($new_coupon_id, 'free_shipping', 'no');
    update_post_meta($new_coupon_id, 'exclude_sale_items', 'yes');

    return $coupon_code;
}

function sendCC($email, $name = '') {
    if (allowedRcpt($email)) {
	$cc = createCC($email);
	emailSend($email, $name, $cc);
    } else {
	error_log("ERROR - sendCC: $email NOT allowed recipient!");
    }
}

function createCCFreeShipment($email) {
    $date = date('Y-m-d');
    $cc = str_split(strtoupper(base64_encode(hash('sha256', $email . $date . 'fs'))), 8);
    $coupon_code = 'WMA-FS-' . $cc[0] . '-' . $cc[1];

    $expiry_date = date('Y-m-d', strtotime($date . '+ 7 days'));

    $coupon = array(
	'post_title'   => $coupon_code,
	'post_content' => '',
	'post_status'  => 'publish',
	'post_author'  => 1,
	'post_type'    => 'shop_coupon'
    );

    $new_coupon_id = wp_insert_post($coupon);

    update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
    update_post_meta($new_coupon_id, 'coupon_amount', '0');
    update_post_meta($new_coupon_id, 'free_shipping', 'yes');
    update_post_meta($new_coupon_id, 'individual_use', 'yes');
    update_post_meta($new_coupon_id, 'usage_limit', '1');
    update_post_meta($new_coupon_id, 'expiry_date', $expiry_date);
    update_post_meta($new_coupon_id, 'product_ids', '');
    update_post_meta($new_coupon_id, 'exclude_product_ids', '');

    return $coupon_code;
}

function buildOrderProductsTable(WC_Order $order): string {
    $rows = '';
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $name      = $product->get_name();
        $url       = get_permalink($product->get_id());
        $image_id  = $product->get_image_id();
        $image_url = $image_id
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
                <a href="' . esc_url($url) . '" target="_blank"
                   style="display:inline-block; background-color:#54b435; color:#ffffff;
                          text-decoration:none; padding:8px 20px; border-radius:4px;
                          font-size:14px; font-weight:bold;">Megrendelem</a>
            </td>
        </tr>';
    }

    if ($rows === '') return '';

    return '
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
    <tbody>' . $rows . '
    </tbody>
</table>';
}

function buildTopDiscountedProductsTable(int $limit = 10): string {
    $on_sale_ids = wc_get_product_ids_on_sale();
    $discounted  = [];

    foreach ($on_sale_ids as $id) {
        $product = wc_get_product($id);
        if (!$product) continue;

        $display_id = $product->get_type() === 'variation'
            ? $product->get_parent_id()
            : $product->get_id();

        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_sale_price();

        if ($regular <= 0 || $sale <= 0 || $sale >= $regular) continue;

        $ratio = ($regular - $sale) / $regular;

        if (!isset($discounted[$display_id]) || $ratio > $discounted[$display_id]['ratio']) {
            $discounted[$display_id] = [
                'product' => wc_get_product($display_id),
                'ratio'   => $ratio,
            ];
        }
    }

    usort($discounted, fn($a, $b) => $b['ratio'] <=> $a['ratio']);
    $top = array_slice($discounted, 0, $limit);

    if (empty($top)) return '';

    $rows = '';
    foreach ($top as $item) {
        $product = $item['product'];
        if (!$product) continue;

        $pct       = round($item['ratio'] * 100);
        $name      = $product->get_name();
        $url       = get_permalink($product->get_id());
        $image_id  = $product->get_image_id();
        $image_url = $image_id
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
            <td style="padding:12px; border-bottom:1px solid #eee; vertical-align:middle; text-align:center; white-space:nowrap; font-size:15px; font-weight:bold; color:#e63946;">
                -' . $pct . '%
            </td>
            <td style="padding:12px; border-bottom:1px solid #eee; vertical-align:middle; text-align:center; white-space:nowrap;">
                <a href="' . esc_url($url) . '" target="_blank"
                   style="display:inline-block; background-color:#54b435; color:#ffffff;
                          text-decoration:none; padding:8px 20px; border-radius:4px;
                          font-size:14px; font-weight:bold;">Megrendelem</a>
            </td>
        </tr>';
    }

    return '
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
    <tbody>' . $rows . '
    </tbody>
</table>';
}

function isSendySubscribed(string $email, string $list, string $sendy_url, string $api_key): bool {
    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query(['api_key' => $api_key, 'list_id' => $list, 'email' => $email]),
    ]];
    $result = @file_get_contents($sendy_url . '/api/subscribers/subscription-status.php', false, stream_context_create($opts));
    return $result === 'Subscribed';
}
