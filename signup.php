<?php
require_once ('functions.php');
if (! isset($_POST['name']) || ! isset( $_POST['email']) || ! isset( $_POST['token'])) die();

$name = $_POST['name'];
$email = $_POST['email'];
$token = $_POST['token'];

$verification = verifyTurnstileToken($token, $cf_secretKey);
if ($verification['success']) {
    error_log("CF Verification successful!");
} else {
    $errorCodes = $verification['error-codes'] ?? [];
    error_log("CF Verification failed. Errors: " . implode(', ', $errorCodes));
    echo "CF Verification failed. Errors: " . implode(', ', $errorCodes);
    die();
}

if (! allowedRcpt($email)) {
    error_log("signup.php: $email NOT allowed recipient!");
    echo "$email is not allowed recipient!";
    die();
}

if (isset($_POST['nyeremenyjatek'])) {
    $nyeremenyjatek = "1";
} else {
    $nyeremenyjatek = "";
}

$result = 0;

if ($nyeremenyjatek == "1") {
    $list = $config['SENDY_RAFFLE_LIST'];
}
//subscribe
$postdata = http_build_query(
    array(
    'name' => $name,
    'email' => $email,
    'list' => $list,
    'boolean' => 'true',
    'api_key' => $api_key,
    'gdpr' => 'true'
    )
);
$subscriber_country['country_name'] = '""';

$opts = array('http' => array('method'  => 'POST', 'header'  => 'Content-type: application/x-www-form-urlencoded', 'content' => $postdata));
$context  = stream_context_create($opts);
$result = file_get_contents($sendy_url . '/subscribe', false, $context);
error_log(var_dump($result));
if ($result == "1" && $nyeremenyjatek !== "1") {
    sendCC($email, $name);
} else {
    error_log("SENDY ERROR: " . $email . ", " . $name . ", nyeremenyjatek: $nyeremenyjatek, " . $subscriber_country['country_name'] . " / " . print_r($result, true));
}

echo $result;


