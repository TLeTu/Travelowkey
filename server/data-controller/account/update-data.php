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

function refValues(array &$arr) {
    $refs = array();
    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}


if($action == 'update-account-info'){
    $data = json_decode($_POST['data'] ?? '', true);
    $changeInfoNames = json_decode($_POST['changeInfoNames'] ?? '', true);
    $userId = getUserIdFromJwtToken();

    if (!$userId) {
        http_response_code(401);
        echo "fail";
        $conn->close();
        exit;
    }

    if (!is_array($data) || !is_array($changeInfoNames)) {
        echo "fail";
        $conn->close();
        exit;
    }

    $allowedColumns = array(
        'Name' => 't1.`Name`',
        'Sex' => 't1.`Sex`',
        'Birthday' => 't1.`Birthday`',
        'Email' => 't1.`Email`',
        'Nationality' => 't1.`Nationality`',
        'Phone' => 't1.`Phone`',
        'Nation' => 't2.`Nation`',
        'Expiration' => 't2.`Expiration`'
    );

    $setClauses = array();
    $bindValues = array();

    foreach ($changeInfoNames as $name) {
        if (!isset($allowedColumns[$name]) || !array_key_exists($name, $data)) {
            continue;
        }

        $setClauses[] = $allowedColumns[$name] . ' = ?';
        $bindValues[] = (string)$data[$name];
    }

    if (count($setClauses) === 0) {
        echo "success";
        $conn->close();
        exit;
    }

    $sql = "UPDATE user as t1
        INNER JOIN passport as t2
        ON t1.`Passport_id` = t2.`Id`
        SET " . implode(', ', $setClauses) . "
        WHERE t1.`Id` = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "fail";
        $conn->close();
        exit;
    }

    $types = str_repeat('s', count($bindValues) + 1);
    $params = array_merge(array($types), $bindValues, array($userId));
    call_user_func_array(array($stmt, 'bind_param'), refValues($params));
    $ok = $stmt->execute();

    if ($ok === TRUE) {
        echo "success";
    } else {
        echo "fail";
    }

    $stmt->close();
    $conn->close();
}