<?php

require_once('../connect.php');
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

$totalPrice = $_POST["totalPrice"];
$userId = getUserIdFromJwtToken();
$roomID = $_POST["roomID"];
$checkIn = $_POST["checkIn"];
$checkOut = $_POST["checkOut"];

if (!$userId) {
    http_response_code(401);
    $error = "error";
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($action == "payment") {
    $invoiceID = uniqid("I");
    $roomInvoiceID = uniqid("RI");
    $sql = "INSERT INTO invoice(Id,User_id,Total) VALUES('$invoiceID', '$userId', '$totalPrice');";
    $sql .= "INSERT INTO room_invoice(Id, Room_id, Check_in, Check_out, Invoice_id)
    VALUES('$roomInvoiceID', '$roomID', '$checkIn', '$checkOut', '$invoiceID');";
    $sql .= "UPDATE room SET `State` = 'Rented' WHERE Id = '$roomID'";
    if ($conn->multi_query($sql) === TRUE) {
        $success = "success";
        echo json_encode($success, JSON_UNESCAPED_UNICODE);
    } else {
        $error = "error";
        echo json_encode($error, JSON_UNESCAPED_UNICODE);
    }

    $conn->close();
}