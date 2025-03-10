<?php
require_once "config/config.php";
require_once "module/MainProcess.php";
require_once "api/global_variables.php";

// ✅ Read JSON input
$json = file_get_contents("php://input");
$params = json_decode($json, true);

if (!isset($params['session_id'])) { // 🔹 Fix incorrect key 'session_ids' → should be 'session_id'
    echo json_encode(['status' => 'error', 'message' => 'Session ID missing']);
    exit;
}

// ✅ Restore session BEFORE calling session_start()
session_id($params['session_id']);
session_start();

file_put_contents("pisopay_debug_log.txt", "Restored Session ID: " . session_id() . "\n", FILE_APPEND);

use PHP\module\MainProcess as Checkout;

$f = new Checkout();

// ✅ Ensure session variables exist
$_SESSION['GLOBAL_TOTAL_AMOUNT'] = $_SESSION['GLOBAL_TOTAL_AMOUNT'] ?? 0;
$_SESSION['GLOBAL_BILLING_DETAILS'] = $_SESSION['GLOBAL_BILLING_DETAILS'] ?? [];

$amount = $_SESSION['GLOBAL_TOTAL_AMOUNT'];
$details = $_SESSION['GLOBAL_BILLING_DETAILS'];

$delivery_fees = 0;
$merchant_trace_no = "thi" . rand(0, 99999);
$customer_name = "John Doe";
$customer_email = "kayerain97@gmail.com";
$customer_phone = "09060684607";
$processor_name = "online payment";
$merchantCallbackUrl = "webhook/receive.php";
$callbackUrl = "https://devcbh.com/response";

// ✅ Retain PisoPay's session generation method
$session_id = $f->sessionGenerate();

$arrayPostData = [
    'session_id' => $session_id,
    "branch_code" => "",
    "amount" => $amount,
    "delivery_fees" => $delivery_fees,
    "transaction_type" => "",
    "processor_name" => $processor_name,
    "customer_name" => $customer_name,
    "customer_email" => $customer_email,
    "customer_phone" => $customer_phone,
    "customer_address" => "PH",
    "merchant_trace_no" => $merchant_trace_no,
    "merchant_callback_url" => $merchantCallbackUrl,
    "callback_url" => $callbackUrl,
    "ip_address" => "192.168.123.1",
    "expiry_date" => "2025-10-01 00:00:00"
];

// ✅ Log session details
file_put_contents("pisopay_debug_log.txt", "Index.php Session Data:\n" . print_r($_SESSION, true), FILE_APPEND);
file_put_contents("pisopay_debug_log.txt", "Amount from session: " . $amount . "\n", FILE_APPEND);
file_put_contents("pisopay_debug_log.txt", "Billing Details: " . print_r($details, true) . "\n", FILE_APPEND);

// ✅ Generate Token & Log Response
$token = $f->generateToken($details, $arrayPostData);
file_put_contents("pisopay_debug_log.txt", "Token Response: " . $token . "\n", FILE_APPEND);

$data = json_decode($token, true); // Decode as associative array

if (isset($data['data']['url'])) {
    $url = $data['data']['url'];
    file_put_contents("pisopay_debug_log.txt", "URL: " . $url . "\n", FILE_APPEND);

    // ✅ Return JSON response
    echo json_encode(["status" => "success", "payment_url" => $url]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to generate payment URL"]);
}
?>
