<?php

require_once('../connect.php');
require_once('./transfer-bill-info.php');
require_once('../../../vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getUserIdFromJwtToken() {
    $token = $_COOKIE['jwt_token'] ?? null;
    if (!$token) {
        return null;
    }

    $secretKey = 'travelowkey_secret_key_please_change_2026';

    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return isset($decoded->user_id) ? (string)$decoded->user_id : null;
    } catch (Throwable $e) {
        return null;
    }
}

$action = $_POST["action"];

$transferBillInfo = new TransferBillInfo();
$transferBillInfo->ID = $_POST["taxiID"];
$transferBillInfo->startDate = $_POST["startDate"];
$transferBillInfo->startTime = $_POST["startTime"];
$transferBillInfo->endDate = $_POST["endDate"];
$transferBillInfo->endTime = $_POST["endTime"];
$transferBillInfo->totalPrice = $_POST["totalPrice"];

$userID = getUserIdFromJwtToken();

if (!$userID) {
    http_response_code(401);
    $error = "error";
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($action == "payment") {
    $invoiceID = uniqid("I");
    $taxiInvoiceID = uniqid("TI");

    $sql = "INSERT INTO invoice(Id,User_id,Total) VALUES('$invoiceID', '$userID', '$transferBillInfo->totalPrice');";
    $sql .= "INSERT INTO taxi_invoice(Id, Taxi_id, DepartureDay, TimeStart, ArrivalTime, TimeEnd, Invoice_id)
    VALUES('$taxiInvoiceID', '$transferBillInfo->ID', '$transferBillInfo->startDate', '$transferBillInfo->startTime', '$transferBillInfo->endDate', '$transferBillInfo->endTime', '$invoiceID');";
    $sql .= "UPDATE taxi SET `State` = 'Rented' WHERE Id = '$transferBillInfo->ID'";
    if ($conn->multi_query($sql) === TRUE) {
        $success = "success";
        echo json_encode($success, JSON_UNESCAPED_UNICODE);
    } else {
        $error = "error";
        echo json_encode($error, JSON_UNESCAPED_UNICODE);
    }

    $conn->close();
}