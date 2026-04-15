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

$action = $_POST['action'];

if($action == 'change-password'){
    $userId = getUserIdFromJwtToken();
    if (!$userId) {
        http_response_code(401);
        echo 'fail';
        $conn->close();
        exit;
    }

    $newPassword = $_POST['newPassword'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE user SET `Password` = ? WHERE `Id` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashedPassword, $userId);

    if ($stmt->execute() === TRUE) {
        echo "success";
    } else {
        echo "fail";
    }

    $stmt->close();
    $conn->close();
}


if($action == 'check-old-password'){
    $userId = getUserIdFromJwtToken();
    if (!$userId) {
        http_response_code(401);
        echo 'fail';
        $conn->close();
        exit;
    }

    $oldPassword = $_POST['oldPassword'];
    $sql = "SELECT `Password` FROM user WHERE `Id` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($oldPassword, $row['Password'])) {
            echo 'success';
        } else {
            echo 'fail';
        }
    } else {
        echo 'fail';
    }

    $stmt->close();
    $conn->close();
}