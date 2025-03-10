<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

session_start();
require_once 'business_config_local.php';
require_once 'global_variables.php';

$method = $_SERVER['REQUEST_METHOD'];
$response = ['status' => 'error', 'message' => 'Invalid request'];

if (!$CN) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $params = json_decode($json, true);

    if (!isset($params['billingId'], $params['amount']) || !is_array($params['billingId']) || !is_array($params['amount'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid input format']);
        exit;
    }

    $billingIds = array_map('trim', $params['billingId']);
    $amounts = array_map('trim', $params['amount']);

    if (count($billingIds) !== count($amounts)) {
        echo json_encode(['status' => 'error', 'message' => 'Billing IDs and amounts must match']);
        exit;
    }

    $referenceId = 'REF' . time() . rand(100, 999);
    $_SESSION['GLOBAL_REFERENCE_ID'] = $referenceId;
    $_SESSION['GLOBAL_BILLING_DETAILS'] = [];
    $_SESSION['GLOBAL_TOTAL_AMOUNT'] = 0;

    $success = true;
    $totalAmount = 0;
    $billingDetails = [];

    $query = "INSERT INTO tbl_billing_pisopay (billingId, Amount, referenceId) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($CN, $query);

    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: Failed to prepare statement']);
        exit;
    }

    foreach ($billingIds as $index => $billingId) {
        $billingId = filter_var($billingId, FILTER_SANITIZE_STRING);
        $amount = filter_var($amounts[$index], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        mysqli_stmt_bind_param($stmt, "sss", $billingId, $amount, $referenceId);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
            break;
        }

        $billingDetails[] = ["name" => $billingId, "price" => $amount, "quantity" => 1];
        $totalAmount += $amount;
    }

    mysqli_stmt_close($stmt);

    if (!$success) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: Failed to insert billing data']);
        exit;
    }

    $_SESSION['GLOBAL_BILLING_DETAILS'] = $billingDetails;
    $_SESSION['GLOBAL_TOTAL_AMOUNT'] = $totalAmount;
    session_write_close();

    file_put_contents("pisopay_debug_log.txt", "Checkout API Session Data:\n" . print_r($_SESSION, true), FILE_APPEND);
    file_put_contents("pisopay_debug_log.txt", "SESSION ID: " . print_r(session_id(), true) . "\n", FILE_APPEND);
    // Call PisoPay API
    $ch = curl_init("https://sakatamalaybalay/api/pisopay/index.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'session_id' => session_id(), // 🔹 Make sure this matches the key in index.php
        'billing_details' => $_SESSION['GLOBAL_BILLING_DETAILS'],
        'amount' => $_SESSION['GLOBAL_TOTAL_AMOUNT'],
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $pisopayResponse = curl_exec($ch);
    curl_close($ch);

    
    // Debugging: Log the response
    file_put_contents("pisopay_debug_log.txt", "Checkout.php - PisoPay Response: " . print_r($pisopayResponse, true) . "\n", FILE_APPEND);
    
    $decodedResponse = json_decode($pisopayResponse, true);

    if (isset($decodedResponse['payment_url'])) {
        echo json_encode(['status' => 'success', 'payment_url' => $decodedResponse['payment_url']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid PisoPay response']);
    }
}

mysqli_close($CN);
?>
