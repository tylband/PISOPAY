<?php

// Ensure session variables exist before assigning
if (!isset($_SESSION['GLOBAL_REFERENCE_ID'])) {
    $_SESSION['GLOBAL_REFERENCE_ID'] = '';
}
if (!isset($_SESSION['GLOBAL_TOTAL_AMOUNT'])) {
    $_SESSION['GLOBAL_TOTAL_AMOUNT'] = 0;
}
if (!isset($_SESSION['GLOBAL_BILLING_DETAILS'])) {
    $_SESSION['GLOBAL_BILLING_DETAILS'] = [];
}

// Assign session variables to global variables
$GLOBAL_REFERENCE_ID = $_SESSION['GLOBAL_REFERENCE_ID'];
$GLOBAL_TOTAL_AMOUNT = $_SESSION['GLOBAL_TOTAL_AMOUNT'];
$GLOBAL_BILLING_DETAILS = $_SESSION['GLOBAL_BILLING_DETAILS'];
?>
