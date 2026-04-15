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

$action = $_GET['action'];

if($action == 'load-bill'){
    $userId = getUserIdFromJwtToken();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        $conn->close();
        exit;
    }

    $sql = "SELECT * FROM invoice WHERE `User_id` = ? ORDER BY `Id` DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } else{
        echo 'no-data';
    }

    $stmt->close();
    $conn->close();
}

if($action == 'get-bill-id'){
    $userId = getUserIdFromJwtToken();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        $conn->close();
        exit;
    }

    $invoiceID = $_GET["id"];

    $checkSql = "SELECT `Id` FROM invoice WHERE `Id` = ? AND `User_id` = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ss", $invoiceID, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo 'no-data';
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();

    $conn->query("SET @out_invoiceID = ''");
    $conn->query("CALL GetInvoiceID('$invoiceID', @out_invoiceID)");

    $result = $conn->query("SELECT @out_invoiceID as out_invoiceID");
    $row = $result->fetch_assoc();
    $out_invoiceID = $row['out_invoiceID'];

    echo $out_invoiceID;

    $conn->close();
}

if($action == 'load-account-info'){
    $userId = getUserIdFromJwtToken();
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        $conn->close();
        exit;
    }

    $sql = "SELECT * FROM user as t1 INNER JOIN passport as t2 ON t1.`Passport_id` = t2.`Id` WHERE t1.`Id` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } else{
        echo 'no-data';
    }

    $stmt->close();
    $conn->close();
}
