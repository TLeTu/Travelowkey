<?php
require_once('../connect.php');

$app_id = 2554;
$key1 = 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn';
$key2 = 'trMrHtvjo6myautxDUiAcYsVtaeQ8nhf';
 $queryEndpoint = 'https://sb-openapi.zalopay.vn/v2/query';

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

function getProjectBasePath(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return dirname(dirname(dirname(dirname($scriptName))));
}

function queryZaloPayTransactionStatus(int $appId, string $appTransId, string $key1, string $endpoint): ?array {
    if ($appTransId === '') {
        return null;
    }

    $postData = [
        'app_id' => $appId,
        'app_trans_id' => $appTransId,
    ];
    $data = $postData['app_id'] . '|' . $postData['app_trans_id'] . '|' . $key1;
    $postData['mac'] = hash_hmac('sha256', $data, $key1);

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false || $response === '') {
        return null;
    }

    $result = json_decode($response, true);
    return is_array($result) ? $result : null;
}

function markInvoiceByPaymentResult(mysqli $conn, string $invoiceId, bool $isPaymentSuccess): array {
    if ($isPaymentSuccess) {
        $stmtInvoice = $conn->prepare('SELECT Id, Status FROM invoice WHERE Id = ? LIMIT 1');
        $stmtInvoice->bind_param('s', $invoiceId);
        $stmtInvoice->execute();
        $invoiceResult = $stmtInvoice->get_result();
        $invoice = $invoiceResult ? $invoiceResult->fetch_assoc() : null;

        if (!$invoice) {
            return [false, 'Payment Failed!', 'Invoice not found.'];
        }

        if ($invoice['Status'] === 'PAID') {
            return [true, 'ZaloPay Payment Successful!', 'This invoice was already confirmed before.'];
        }

        try {
            $conn->begin_transaction();

            if (!updateInvoiceStatus($conn, $invoiceId, 'PAID')) {
                throw new Exception('Cannot update invoice status to PAID.');
            }

            if (!finalizeBookingByInvoice($conn, $invoiceId)) {
                throw new Exception('Cannot finalize booking resource state.');
            }

            $conn->commit();
            return [true, 'ZaloPay Payment Successful!', 'Your booking has been confirmed.'];
        } catch (Throwable $e) {
            $conn->rollback();
            return [false, 'Payment Processing Error!', 'Payment was received but booking finalization failed. Please contact support.'];
        }
    }

    $stmtInvoice = $conn->prepare('SELECT Id, Status FROM invoice WHERE Id = ? LIMIT 1');
    $stmtInvoice->bind_param('s', $invoiceId);
    $stmtInvoice->execute();
    $invoiceResult = $stmtInvoice->get_result();
    $invoice = $invoiceResult ? $invoiceResult->fetch_assoc() : null;

    if (!$invoice) {
        return [false, 'Payment Failed!', 'Invoice not found.'];
    }

    if ($invoice['Status'] !== 'PAID') {
        updateInvoiceStatus($conn, $invoiceId, 'FAILED');
    }
    return [false, 'Payment Failed or Canceled!', 'Status: non-success'];
}

function getInvoiceStatus(mysqli $conn, string $invoiceId): ?string {
    $stmtInvoice = $conn->prepare('SELECT Status FROM invoice WHERE Id = ? LIMIT 1');
    $stmtInvoice->bind_param('s', $invoiceId);
    $stmtInvoice->execute();
    $invoiceResult = $stmtInvoice->get_result();
    $invoice = $invoiceResult ? $invoiceResult->fetch_assoc() : null;
    return $invoice['Status'] ?? null;
}

// 1. Gather variables from the URL
$appid = $_GET['appid'] ?? '';
$apptransid = $_GET['apptransid'] ?? '';
$pmcid = $_GET['pmcid'] ?? '';
$bankcode = $_GET['bankcode'] ?? '';
$amount = $_GET['amount'] ?? '';
$discountamount = $_GET['discountamount'] ?? '';
$status = $_GET['status'] ?? '';
$mac_from_zalopay = $_GET['checksum'] ?? ($_GET['mac'] ?? '');

$dataToMac = $appid . "|" . $apptransid . "|" . $pmcid . "|" . $bankcode . "|" . $amount . "|" . $discountamount . "|" . $status;
$my_mac = hash_hmac("sha256", $dataToMac, $key2);
$isValidSignature = hash_equals($my_mac, $mac_from_zalopay);

$invoiceId = parseInvoiceIdFromTransId((string)$apptransid);
$title = '';
$message = '';
$isSuccess = false;

if (!$isValidSignature) {
    $title = 'Security Warning!';
    $message = 'Invalid signature. Data may have been tampered with.';
} elseif ($invoiceId === '') {
    $title = 'Payment Failed!';
    $message = 'Invalid apptransid. Cannot resolve invoice.';
} else {
    $isPaymentSuccess = ((string)$status === '1');
    $currentInvoiceStatus = getInvoiceStatus($conn, $invoiceId);

    if ($currentInvoiceStatus === 'PAID') {
        $isSuccess = true;
        $title = 'ZaloPay Payment Successful!';
        $message = 'This invoice was already confirmed before.';
    } elseif ($isPaymentSuccess) {
        [$isSuccess, $title, $message] = markInvoiceByPaymentResult($conn, $invoiceId, true);
    } else {
        $queryResult = queryZaloPayTransactionStatus($app_id, (string)$apptransid, $key1, $queryEndpoint);
        $queryCode = isset($queryResult['return_code']) ? (int)$queryResult['return_code'] : -999;

        if ($queryCode === 1) {
            [$isSuccess, $title, $message] = markInvoiceByPaymentResult($conn, $invoiceId, true);
            if ($isSuccess) {
                $message .= ' (Verified by ZaloPay query API.)';
            }
        } elseif ($queryCode === 3) {
            $title = 'Payment Processing!';
            $message = 'ZaloPay is still processing this order. Please try again shortly.';
        } else {
            [$isSuccess, $title, $message] = markInvoiceByPaymentResult($conn, $invoiceId, false);
            if ($title === 'Payment Failed or Canceled!') {
                $message = 'Status: ' . htmlspecialchars((string)$status);
            }
        }
    }
}

$projectBase = getProjectBasePath();
$accountUrl = $projectBase . '/pages/account/index.html?nav=bill-pane';
$homeUrl = $projectBase . '/pages/main/index.html';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZaloPay Result</title>
</head>
<body style="text-align: center; font-family: sans-serif; margin-top: 50px;">

    <?php
    $titleColor = $isSuccess ? 'green' : 'red';
    echo "<h1 style='color: {$titleColor};'>" . htmlspecialchars($title) . "</h1>";
    echo "<p>" . $message . "</p>";
    if ($invoiceId !== '') {
        echo "<p>Invoice ID: " . htmlspecialchars($invoiceId) . "</p>";
    }
    if ($apptransid !== '') {
        echo "<p>Order ID: " . htmlspecialchars($apptransid) . "</p>";
    }
    if ((int)$amount > 0) {
        echo "<p>Amount: " . number_format((int)$amount, 0, ',', '.') . " VND</p>";
    }
    ?>

    <br><br>
    <a href="<?php echo htmlspecialchars($accountUrl); ?>">Go to My Bills</a>
    <br><br>
    <a href="<?php echo htmlspecialchars($homeUrl); ?>">Return to Dashboard</a>
</body>
</html>