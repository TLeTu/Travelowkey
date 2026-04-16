<?php

require_once('../connect.php');
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

$totalPrice = isset($_POST['totalPrice']) ? (int)$_POST['totalPrice'] : 0;
$userId = getUserIdFromJwtToken();
$flightID = $_POST['flightID'] ?? '';
$ticketNum = isset($_POST['ticketNum']) ? (int)$_POST['ticketNum'] : 0;

if (!$userId) {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($action !== 'payment') {
    sendJson(['success' => false, 'message' => 'Invalid action'], 400);
}

if ($totalPrice <= 0 || $ticketNum <= 0 || $flightID === '') {
    sendJson(['success' => false, 'message' => 'Invalid payment data'], 400);
}

if ($action == "payment") {
    $invoiceID = uniqid('I');
    $flightInvoiceID = uniqid('FI');

    try {
        $conn->begin_transaction();

        // Status column must exist in invoice table (PENDING/PAID/FAILED)
        $stmtInvoice = $conn->prepare("INSERT INTO invoice(Id, User_id, Total, Status) VALUES(?, ?, ?, 'PENDING')");
        if (!$stmtInvoice) {
            throw new Exception($conn->error);
        }
        $stmtInvoice->bind_param('ssi', $invoiceID, $userId, $totalPrice);
        if (!$stmtInvoice->execute()) {
            throw new Exception($stmtInvoice->error);
        }

        $stmtFlightInvoice = $conn->prepare('INSERT INTO flight_invoice(Id, Flight_id, Invoice_id, Num_ticket) VALUES(?, ?, ?, ?)');
        if (!$stmtFlightInvoice) {
            throw new Exception($conn->error);
        }
        $stmtFlightInvoice->bind_param('sssi', $flightInvoiceID, $flightID, $invoiceID, $ticketNum);
        if (!$stmtFlightInvoice->execute()) {
            throw new Exception($stmtFlightInvoice->error);
        }

        $conn->commit();
        sendJson([
            'success' => true,
            'invoice_id' => $invoiceID,
            'amount' => $totalPrice
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