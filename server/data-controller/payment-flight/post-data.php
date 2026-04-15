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
$flightID = $_POST["flightID"];
$ticketNum = $_POST["ticketNum"];

if (!$userId) {
    http_response_code(401);
    $error = "error";
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($action == "payment") {
    $invoiceID = uniqid("I");
    $flightInvoiceID = uniqid("FI");
    $seatNum = getSeatNum($conn, $flightID) - $ticketNum;

    $sql = "INSERT INTO invoice(Id,User_id,Total) VALUES('$invoiceID', '$userId', '$totalPrice');";
    $sql .= "INSERT INTO flight_invoice(Id, Flight_id, Invoice_id, Num_Ticket)
    VALUES('$flightInvoiceID', '$flightID', '$invoiceID', '$ticketNum');";
    $sql .= "UPDATE flight SET NumSeat = '$seatNum' WHERE Id = '$flightID'";
    if ($conn->multi_query($sql) === TRUE) {
        $success = "success";
        echo json_encode($success, JSON_UNESCAPED_UNICODE);
    } else {
        $error = "error";
        echo json_encode($error, JSON_UNESCAPED_UNICODE);
    }

    $conn->close();
}

function getSeatNum($conn, $flightID) {
    $sql = "SELECT NumSeat FROM flight WHERE Id = '$flightID'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $seatNum = $row["NumSeat"];
    return $seatNum;
}