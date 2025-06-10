<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Requested-With');

// Include the database configuration
require_once 'business_config.php';

// Get the JSON data from the request body and decode it
$json = file_get_contents('php://input');
$params = json_decode($json);

// Validate input data
if (!$params || !isset($params->BUSINESSNUMBER)) {
    $response = array('status' => 'error', 'message' => 'Invalid input');
    echo json_encode($response);
    exit;
}

// Validate and sanitize the input value (BUSINESSNUMBER)
$BUSINESSNUMBER = filter_var($params->BUSINESSNUMBER, FILTER_SANITIZE_STRING);

// Get the user's IP address using the getClientIP function
$userIP = filter_var(getClientIP(), FILTER_SANITIZE_STRING);
$deviceMAC = filter_var(getMacAddress(), FILTER_SANITIZE_STRING);



if ($BUSINESSNUMBER !== mysqli_real_escape_string($CN, $BUSINESSNUMBER)) {
    // Handle SQL injection error
    $response = array('status' => 'error', 'message' => 'Potential SQL injection detected');
    echo json_encode($response);
    exit;
}

// Check if BUSINESSNUMBER contains a dash
if (strpos($BUSINESSNUMBER, '-') !== false) {
    $query = "SELECT permitId FROM tblbusinesspermitissuance WHERE issueNo = ?";
} else {
    $query = "SELECT bp.permitId FROM tblbusinesspermit bp WHERE bp.permitNo=?";
}

$stmt1 = mysqli_prepare($CN, $query);
if (!$stmt1) {
    $response = array('status' => 'error', 'message' => 'Failed to prepare statement: ' . mysqli_error($CN));
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt1, "s", $BUSINESSNUMBER);
mysqli_stmt_execute($stmt1);
mysqli_stmt_bind_result($stmt1, $permitId);

$permitIds = array();
while (mysqli_stmt_fetch($stmt1)) {
    $permitIds[] = $permitId;
}

if (!empty($permitIds)) {
    // Prepare the third SQL query to retrieve billing records using the current date
    $currentDate = date("Y-m-d");
    $query3 = "CALL spBusinessBillingRecordsFind(?, ?)";

    $stmt2 = mysqli_prepare($CN, $query3);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "ss", $permitIds[0], $currentDate);

        $businessList = array();
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_bind_result($stmt2, $billingId, $typeId, $typeName, $amountDue, $amountDueDt, $billingName, $billingRemarks, $referenceId3, $penaltyAmount);

        while (mysqli_stmt_fetch($stmt2)) {
            $businessList[] = array(
                'billingId' => $billingId,
                'typeId' => $typeId,
                'typeName' => $typeName,
                'amountDue' => $amountDue,
                'amountDueDt' => $amountDueDt,
                'billingName' => $billingName,
                'billingRemarks' => $billingRemarks,
                'referenceId3' => $referenceId3,
                'penaltyAmount' => $penaltyAmount,
            );
        }

        $response = array('status' => 'success', 'businessList' => $businessList);
    } else {
        $response = array('status' => 'error', 'message' => 'Failed to prepare statement for query3: ' . mysqli_error($CN));
    }

    if ($stmt2) {
        mysqli_stmt_close($stmt2);
    }
} else {
    $response = array('status' => 'error', 'message' => 'No records found for the given BUSINESSNUMBER');
}

// Close statements and connection
mysqli_stmt_close($stmt1);
mysqli_close($CN);

echo json_encode($response);

// Function to get client IP
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    return $ipaddress;
}

// Function to get device MAC address
function getMacAddress() {
    $macAddress = '';
  
    // Check the operating system
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        exec('ipconfig /all', $output);
        foreach ($output as $line) {
            if (preg_match('/Physical[^:]+: (.*)/', $line, $matches)) {
                $macAddress = $matches[1];
                break;
            }
        }
    } else {
        // Unix-based system
        exec('ifconfig', $output);
        foreach ($output as $line) {
            if (preg_match('/HWaddr\s(\S+)/', $line, $matches)) {
                $macAddress = $matches[1];
                break;
            }
        }
    }
  
    return $macAddress;
}
