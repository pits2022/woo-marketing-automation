<?php
require_once('functions.php');

if (!isset($_POST['name']) || !isset($_POST['email']) || !isset($_POST['token'])) die();

$name  = $_POST['name'];
$token = $_POST['token'];
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if ($email === false) {
    error_log("signup.php: invalid email address submitted");
    echo 'Invalid email address.';
    die();
}

$verification = verifyTurnstileToken($token, $cf_secretKey);
if ($verification['success']) {
    error_log("CF Verification successful!");
} else {
    $errorCodes = $verification['error-codes'] ?? [];
    error_log("CF Verification failed. Errors: " . implode(', ', $errorCodes));
    echo "CF Verification failed. Errors: " . implode(', ', $errorCodes);
    die();
}

if (!allowedRcpt($email)) {
    error_log("signup.php: $email NOT allowed recipient!");
    echo "$email is not allowed recipient!";
    die();
}

if (!empty($_POST['nyeremenyjatek'])) {
    $nyeremenyjatek = "1";
    $list = $config['SENDY_RAFFLE_LIST'];
} else {
    $nyeremenyjatek = "";
}

$postdata = http_build_query([
    'name'    => $name,
    'email'   => $email,
    'list'    => $list,
    'boolean' => 'true',
    'api_key' => $api_key,
    'gdpr'    => 'true',
]);

$opts   = ['http' => ['method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => $postdata]];
$result = file_get_contents($sendy_url . '/subscribe', false, stream_context_create($opts));
error_log('signup.php Sendy result for ' . $email . ': ' . print_r($result, true));

if ($result == "1" && $nyeremenyjatek !== "1") {
    sendCC($email, $name);
} else {
    error_log("SENDY ERROR: {$email}, {$name}, nyeremenyjatek: {$nyeremenyjatek} / " . print_r($result, true));
}

echo $result;
