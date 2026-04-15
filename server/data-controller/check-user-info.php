<?php
// Ensure we always return JSON
header('Content-Type: application/json');

require_once('./connect.php');
require_once('../../vendor/autoload.php'); // Adjust path if needed based on your folder structure

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$action = $_GET['action'] ?? '';

if($action == 'check-user-info'){
    
    // 1. Grab the token from the HttpOnly cookie
    $token = $_COOKIE['jwt_token'] ?? null;

    if (!$token) {
        echo json_encode(['error' => 'No token provided']);
        exit;
    }

    // 2. Load your secret key from the .env file (just like you did in login.php)
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['secret_key'];

    try {
        // 3. Decode the token to verify it hasn't been tampered with
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        
        // 4. Extract the user ID from the token payload safely
        $userId = $decoded->user_id;

        // 5. Secure Database Query using Prepared Statements
        $sql = "SELECT * FROM user WHERE `Id` = ?";
        $stmt = $conn->prepare($sql);
        
        // Bind the userId securely ("s" tells MySQL to treat it safely as a string)
        $stmt->bind_param("s", $userId); 
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Fetch the data exactly how the frontend expects it (an array of objects)
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'no-data']);
        }
        
        $stmt->close();

    } catch (Exception $e) {
        // If the token is expired or tampered with, it throws an error
        echo json_encode(['error' => 'Invalid or expired token']);
    }

    $conn->close();
}
?>