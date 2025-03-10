<?php

require_once "config/config.php"; // Ensure database connection
require_once "api/global_variables.php"; // Access global variables

file_put_contents("webhook_log.txt", "Webhook Hit\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uname = 'apidemoacct2023'; // API Username
    $pass = 'KC2kRPyIiwC0H2JPNyjHbD5YVG8RGZ'; // API Password

    // Capture raw POST data
    $postBody = file_get_contents("php://input");
    file_put_contents("webhook_log.txt", "Received Data:\n" . $postBody . "\n", FILE_APPEND);

    // Decode POST body
    $details = json_decode($postBody, true);

    if (!$details || !isset($details['data'])) {
        file_put_contents("webhook_log.txt", "Invalid Data Format!\n", FILE_APPEND);
        http_response_code(400);
        exit("Invalid Data Format");
    }

    // Extract session ID from webhook
    if (!isset($details['session_id'])) {
        file_put_contents("webhook_log.txt", "Session ID Missing!\n", FILE_APPEND);
        http_response_code(400);
        exit("Session ID Missing");
    }

    // ✅ Restore the session from your other API using the session ID
    session_id($details['session_id']);
    session_start();

    // Check if the global reference ID exists in the session
    if (!isset($_SESSION['GLOBAL_REFERENCE_ID'])) {
        file_put_contents("webhook_log.txt", "No Global Reference ID Found!\n", FILE_APPEND);
        http_response_code(400);
        exit("Reference ID Missing");
    }

    // ✅ Now use the global reference ID stored in your session
    $ref_id = $_SESSION['GLOBAL_REFERENCE_ID'];

    // Extract payment data
    $data = $details['data'];
    $t = $data['timestamp'];
    $getTrace = $data['traceNo']; // This is the PisoPay Reference Number
    $allAmount = $data['amount'];
    $transactionStatus = $data['status']; // Check payment status

    // ✅ Validate hash
    $auth = hash("sha256", $uname . ":" . $pass . $t);
    $auth = substr($auth, 0, 10);
    $hd1 = hash_hmac("sha256", $auth . $getTrace . $allAmount, $t);
    $hd2 = $data['hd'];

    if (!hash_equals($hd1, $hd2)) {
        file_put_contents("webhook_log.txt", "FAILED VALIDATION - Hash Mismatch!\n", FILE_APPEND);
        http_response_code(400);
        exit("FAILED VALIDATION");
    }

    // ✅ Process successful payment (Status 0 means payment successful)
    if ($transactionStatus === "0") {
        $query = "UPDATE tbl_billing_pisopay SET ispayed = 1 WHERE referenceId = ?";
        $stmt = mysqli_prepare($CN, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $ref_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            file_put_contents("webhook_log.txt", "Payment Verified & Updated: $ref_id\n", FILE_APPEND);
        } else {
            file_put_contents("webhook_log.txt", "Database Error: Failed to Update!\n", FILE_APPEND);
        }
    } else {
        file_put_contents("webhook_log.txt", "Payment Failed or Pending: $ref_id\n", FILE_APPEND);
    }

    // ✅ Always respond HTTP 200 to avoid retries from PisoPay
    http_response_code(200);
    echo "OK";

} else {
    http_response_code(405);
    exit();
}
?>
