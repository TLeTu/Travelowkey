<?php
header('Content-Type: application/json');
require '../../vendor/autoload.php';
use Firebase\JWT\JWT;

mysqli_report(MYSQLI_REPORT_OFF);

try {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $dbPassword = "";
    $dbname = "db_ie104";

    // Create connection
    $conn = new mysqli($servername, $username, $dbPassword, $dbname);

    // Check connection
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => 'Database connection failed'));
        exit;
    }

    // Get data from the POST request
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data) || !isset($data['emailOrPhone']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'error' => 'Invalid request payload'));
        $conn->close();
        exit;
    }

    $emailOrPhone = trim($data['emailOrPhone']);
    $password = (string) $data['password'];

    // Check if the user exists in the database
    $sql = "SELECT * FROM user WHERE Email = ? OR Phone = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => 'Database query preparation failed'));
        $conn->close();
        exit;
    }

    $stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
    $stmt->execute();
    $result = $stmt->get_result();
    $userExists = $result->num_rows > 0;

    if ($userExists) {
        $row = $result->fetch_assoc();
        $storedPassword = $row['Password'];
        $passwordIsCorrect = password_verify($password, $storedPassword);

        if ($passwordIsCorrect) {
            // // Load secret from process environment first.
            // $secretKey = getenv('secret_key');
            // if (!is_string($secretKey) || $secretKey === '') {
            //     $secretKey = $_ENV['secret_key'] ?? $_SERVER['secret_key'] ?? '';
            // }

            // // Fallback for projects that keep secret_key in a root .env without phpdotenv.
            // if (!is_string($secretKey) || $secretKey === '') {
            //     $envFilePath = __DIR__ . '/../../.env';
            //     if (is_readable($envFilePath)) {
            //         $envValues = parse_ini_file($envFilePath, false, INI_SCANNER_RAW);
            //         if (is_array($envValues) && isset($envValues['secret_key'])) {
            //             $secretKey = trim((string) $envValues['secret_key']);
            //         }
            //     }
            // }

            // firebase/php-jwt v7 requires a sufficiently long secret for HS256.
            $secretKey = 'travelowkey_secret_key_please_change_2026';

            if (!is_string($secretKey) || $secretKey === '') {
                http_response_code(500);
                echo json_encode(array('success' => false, 'error' => 'Server auth configuration is missing'));
                $stmt->close();
                $conn->close();
                exit;
            }

            $issuedAt = new DateTimeImmutable();
            $expire = $issuedAt->modify('+100 hour')->getTimestamp();

            // Create the JWT payload
            $payload = array(
                'iat' => $issuedAt->getTimestamp(),
                'iss' => $servername,
                'nbf' => $issuedAt->getTimestamp(),
                'exp' => $expire,
                'user_id' => $row['Id']
            );

            // Generate the JWT token
            $token = JWT::encode($payload, $secretKey, 'HS256');
            setcookie('jwt_token', $token, $expire, '/', '', false, true);
            echo json_encode(array('success' => true));
        } else {
            // Password is incorrect, return error message
            echo json_encode(array('success' => false, 'error' => 'Invalid email/phone or password'));
        }
    } else {
        // User does not exist, return error message
        echo json_encode(array('success' => false, 'error' => 'User not found'));
    }

    $stmt->close();
    $conn->close();
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'Internal server error'));
}