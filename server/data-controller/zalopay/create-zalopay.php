<?php
require_once('../connect.php');

header('Content-Type: application/json');

// ZaloPay Sandbox Credentials
$app_id = 2554;
$key1 = 'sdngKKJmqEMzvh5QQcdD2A9XBSKUNaYn';
$endpoint = 'https://sb-openapi.zalopay.vn/v2/create';

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$invoiceId = isset($data['invoice_id']) ? trim((string)$data['invoice_id']) : '';
$serviceType = isset($data['service_type']) ? trim((string)$data['service_type']) : 'service';
$paymentMethod = isset($data['payment_method']) ? trim((string)$data['payment_method']) : '';
// $paymentMethod = 'CC';
$callbackUrl = trim((string)(getenv('ZALOPAY_CALLBACK_URL') ?: ''));

if ($invoiceId === '') {
    sendJson(['success' => false, 'message' => 'Missing invoice_id'], 400);
}

$stmt = $conn->prepare('SELECT User_id, Total, Status FROM invoice WHERE Id = ? LIMIT 1');
if (!$stmt) {
    sendJson(['success' => false, 'message' => 'Database error'], 500);
}

$stmt->bind_param('s', $invoiceId);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result ? $result->fetch_assoc() : null;

if (!$invoice) {
    sendJson(['success' => false, 'message' => 'Invoice not found'], 404);
}

if (!isset($invoice['Status']) || $invoice['Status'] !== 'PENDING') {
    sendJson(['success' => false, 'message' => 'Invoice is not pending'], 400);
}

$amount = (int)$invoice['Total'];
if ($amount <= 0) {
    sendJson(['success' => false, 'message' => 'Invalid invoice total'], 400);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/server/data-controller/zalopay')), '/');
$returnUrl = $scheme . '://' . $host . $scriptDir . '/zalopay-return.php';

$trans_id = date('ymd') . '_' . $invoiceId;
$app_time = (int) round(microtime(true) * 1000);
$preferredMethods = [];

if ($paymentMethod === 'ATM') {
    $preferredMethods = ['domestic_card', 'account'];
} elseif ($paymentMethod === 'CC') {
    $preferredMethods = ['international_card'];
} elseif ($paymentMethod === 'ZALOPAY') {
    $preferredMethods = ['zalopay_wallet'];
} elseif ($paymentMethod === 'VIETQR') {
    $preferredMethods = ['vietqr'];
} elseif ($paymentMethod === 'APPLEPAY') {
    $preferredMethods = ['applepay'];
}

$embed_data = json_encode([
    'preferred_payment_method' => $preferredMethods,
    'redirecturl' => $returnUrl,
], JSON_UNESCAPED_SLASHES);
$item = json_encode([
    [
        'itemid' => $invoiceId,
        'itemname' => strtoupper($serviceType) . ' invoice',
        'itemprice' => $amount,
        'itemquantity' => 1
    ]
], JSON_UNESCAPED_UNICODE);

$order = [
    'app_id' => $app_id,
    'app_trans_id' => $trans_id,
    'app_user' => (string)$invoice['User_id'],
    'app_time' => $app_time,
    'item' => $item,
    'embed_data' => $embed_data,
    'amount' => $amount,
    'description' => "Payment for {$serviceType} invoice #{$invoiceId}",
    'bank_code' => ''
];

if ($callbackUrl !== '') {
    $order['callback_url'] = $callbackUrl;
}

$dataToMac = $order['app_id'] . '|' . $order['app_trans_id'] . '|' . $order['app_user'] . '|' . $order['amount'] . '|' . $order['app_time'] . '|' . $order['embed_data'] . '|' . $order['item'];
$order['mac'] = hash_hmac('sha256', $dataToMac, $key1);

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    sendJson(['success' => false, 'message' => 'Cannot connect to ZaloPay: ' . $curlError], 502);
}

$result = json_decode($response, true);
if (!is_array($result)) {
    sendJson(['success' => false, 'message' => 'Invalid response from ZaloPay'], 502);
}

if (isset($result['return_code']) && (int)$result['return_code'] === 1 && !empty($result['order_url'])) {
    $conn->close();
    sendJson([
        'success' => true,
        'payment_url' => $result['order_url'],
        'invoice_id' => $invoiceId,
        'app_trans_id' => $trans_id
    ]);
} else {
    $conn->close();
    $message = $result['return_message'] ?? ('HTTP ' . $httpCode . ' from ZaloPay');
    sendJson(['success' => false, 'message' => $message], 400);
}