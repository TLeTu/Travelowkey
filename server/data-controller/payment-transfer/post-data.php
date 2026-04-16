<?php

require_once('../connect.php');
require_once('./transfer-bill-info.php');
require_once('../../../vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

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

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

$transferBillInfo = new TransferBillInfo();
$transferBillInfo->ID = $_POST['taxiID'] ?? '';
$transferBillInfo->startDate = $_POST['startDate'] ?? '';
$transferBillInfo->startTime = $_POST['startTime'] ?? '';
$transferBillInfo->endDate = $_POST['endDate'] ?? '';
$transferBillInfo->endTime = $_POST['endTime'] ?? '';
$transferBillInfo->totalPrice = isset($_POST['totalPrice']) ? (int)$_POST['totalPrice'] : 0;

$userID = getUserIdFromJwtToken();

if (!$userID) {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($action !== 'payment') {
    sendJson(['success' => false, 'message' => 'Invalid action'], 400);
}

if (
    $transferBillInfo->totalPrice <= 0 ||
    $transferBillInfo->ID === '' ||
    $transferBillInfo->startDate === '' ||
    $transferBillInfo->startTime === '' ||
    $transferBillInfo->endDate === '' ||
    $transferBillInfo->endTime === ''
) {
    sendJson(['success' => false, 'message' => 'Invalid payment data'], 400);
}

if ($action == "payment") {
    $invoiceID = uniqid('I');
    $taxiInvoiceID = uniqid('TI');

    try {
        $conn->begin_transaction();

        // Status column must exist in invoice table (PENDING/PAID/FAILED)
        $stmtInvoice = $conn->prepare("INSERT INTO invoice(Id, User_id, Total, Status) VALUES(?, ?, ?, 'PENDING')");
        if (!$stmtInvoice) {
            throw new Exception($conn->error);
        }
        $stmtInvoice->bind_param('ssi', $invoiceID, $userID, $transferBillInfo->totalPrice);
        if (!$stmtInvoice->execute()) {
            throw new Exception($stmtInvoice->error);
        }

        $stmtTaxiInvoice = $conn->prepare('INSERT INTO taxi_invoice(Id, Taxi_id, DepartureDay, TimeStart, ArrivalTime, TimeEnd, Invoice_id) VALUES(?, ?, ?, ?, ?, ?, ?)');
        if (!$stmtTaxiInvoice) {
            throw new Exception($conn->error);
        }
        $stmtTaxiInvoice->bind_param(
            'sssssss',
            $taxiInvoiceID,
            $transferBillInfo->ID,
            $transferBillInfo->startDate,
            $transferBillInfo->startTime,
            $transferBillInfo->endDate,
            $transferBillInfo->endTime,
            $invoiceID
        );
        if (!$stmtTaxiInvoice->execute()) {
            throw new Exception($stmtTaxiInvoice->error);
        }

        $conn->commit();
        sendJson([
            'success' => true,
            'invoice_id' => $invoiceID,
            'amount' => $transferBillInfo->totalPrice
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        sendJson(['success' => false, 'message' => 'Cannot create pending invoice'], 500);
    } finally {
        $conn->close();
    }
}

$conn->close();
sendJson(['success' => false, 'message' => 'Unhandled request'], 400);