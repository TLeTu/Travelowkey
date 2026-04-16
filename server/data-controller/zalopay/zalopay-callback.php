<?php
require_once('../connect.php');

header('Content-Type: application/json');

$key2 = 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf';

function finalizeBookingByInvoice(mysqli $conn, string $invoiceId): bool {
    $stmtBus = $conn->prepare('SELECT Bus_id, Num_ticket FROM bus_invoice WHERE Invoice_id = ? LIMIT 1');
    $stmtBus->bind_param('s', $invoiceId);
    $stmtBus->execute();
    $busResult = $stmtBus->get_result();
    $busInvoice = $busResult ? $busResult->fetch_assoc() : null;
    if ($busInvoice) {
        $numTicket = (int)$busInvoice['Num_ticket'];
        $stmtUpdateBus = $conn->prepare('UPDATE bus SET NumSeat = GREATEST(NumSeat - ?, 0) WHERE Id = ?');
        $stmtUpdateBus->bind_param('is', $numTicket, $busInvoice['Bus_id']);
        return $stmtUpdateBus->execute();
    }

    $stmtFlight = $conn->prepare('SELECT Flight_id, Num_ticket FROM flight_invoice WHERE Invoice_id = ? LIMIT 1');
    $stmtFlight->bind_param('s', $invoiceId);
    $stmtFlight->execute();
    $flightResult = $stmtFlight->get_result();
    $flightInvoice = $flightResult ? $flightResult->fetch_assoc() : null;
    if ($flightInvoice) {
        $numTicket = (int)$flightInvoice['Num_ticket'];
        $stmtUpdateFlight = $conn->prepare('UPDATE flight SET NumSeat = GREATEST(NumSeat - ?, 0) WHERE Id = ?');
        $stmtUpdateFlight->bind_param('is', $numTicket, $flightInvoice['Flight_id']);
        return $stmtUpdateFlight->execute();
    }

    $stmtRoom = $conn->prepare('SELECT Room_id FROM room_invoice WHERE Invoice_id = ? LIMIT 1');
    $stmtRoom->bind_param('s', $invoiceId);
    $stmtRoom->execute();
    $roomResult = $stmtRoom->get_result();
    $roomInvoice = $roomResult ? $roomResult->fetch_assoc() : null;
    if ($roomInvoice) {
        $stmtUpdateRoom = $conn->prepare("UPDATE room SET `State` = 'Rented' WHERE Id = ?");
        $stmtUpdateRoom->bind_param('s', $roomInvoice['Room_id']);
        return $stmtUpdateRoom->execute();
    }

    $stmtTaxi = $conn->prepare('SELECT Taxi_id FROM taxi_invoice WHERE Invoice_id = ? LIMIT 1');
    $stmtTaxi->bind_param('s', $invoiceId);
    $stmtTaxi->execute();
    $taxiResult = $stmtTaxi->get_result();
    $taxiInvoice = $taxiResult ? $taxiResult->fetch_assoc() : null;
    if ($taxiInvoice) {
        $stmtUpdateTaxi = $conn->prepare("UPDATE taxi SET `State` = 'Rented' WHERE Id = ?");
        $stmtUpdateTaxi->bind_param('s', $taxiInvoice['Taxi_id']);
        return $stmtUpdateTaxi->execute();
    }

    return false;
}

function updateInvoiceStatus(mysqli $conn, string $invoiceId, string $status): bool {
    $stmt = $conn->prepare('UPDATE invoice SET Status = ? WHERE Id = ?');
    $stmt->bind_param('ss', $status, $invoiceId);
    return $stmt->execute();
}

function parseInvoiceIdFromTransId(string $appTransId): string {
    $parts = explode('_', $appTransId, 2);
    return $parts[1] ?? '';
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['return_code' => 0, 'return_message' => 'Invalid payload'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dataStr = $payload['data'] ?? '';
$reqMac = $payload['mac'] ?? '';

if ($dataStr === '' || $reqMac === '') {
    http_response_code(400);
    echo json_encode(['return_code' => 0, 'return_message' => 'Missing data or mac'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mac = hash_hmac('sha256', $dataStr, $key2);
if (!hash_equals($mac, $reqMac)) {
    http_response_code(200);
    echo json_encode(['return_code' => -1, 'return_message' => 'mac not equal'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dataJson = json_decode($dataStr, true);
if (!is_array($dataJson)) {
    http_response_code(200);
    echo json_encode(['return_code' => 0, 'return_message' => 'Invalid callback data'], JSON_UNESCAPED_UNICODE);
    exit;
}

$invoiceId = parseInvoiceIdFromTransId((string)($dataJson['app_trans_id'] ?? ''));
if ($invoiceId === '') {
    http_response_code(200);
    echo json_encode(['return_code' => 0, 'return_message' => 'Missing invoice id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtInvoice = $conn->prepare('SELECT Status FROM invoice WHERE Id = ? LIMIT 1');
$stmtInvoice->bind_param('s', $invoiceId);
$stmtInvoice->execute();
$invoiceResult = $stmtInvoice->get_result();
$invoice = $invoiceResult ? $invoiceResult->fetch_assoc() : null;

if (!$invoice) {
    http_response_code(200);
    echo json_encode(['return_code' => 0, 'return_message' => 'Invoice not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($invoice['Status'] === 'PAID') {
    http_response_code(200);
    echo json_encode(['return_code' => 2, 'return_message' => 'already processed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->begin_transaction();

    if (!updateInvoiceStatus($conn, $invoiceId, 'PAID')) {
        throw new Exception('Cannot update invoice status.');
    }

    if (!finalizeBookingByInvoice($conn, $invoiceId)) {
        throw new Exception('Cannot finalize booking state.');
    }

    $conn->commit();
    http_response_code(200);
    echo json_encode(['return_code' => 1, 'return_message' => 'success'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(200);
    echo json_encode(['return_code' => 0, 'return_message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}