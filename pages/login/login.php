<?php
header('Content-Type: application/json');
require '../../vendor/autoload.php';
use Firebase\JWT\JWT;

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_ie104";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get data from the POST request
$data = json_decode(file_get_contents('php://input'), true);
$emailOrPhone = $data['emailOrPhone'];
$password = $data['password'];

// Check if the user exists in the database
$sql = "SELECT * FROM user WHERE Email = ? OR Phone = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
$stmt->execute();
$result = $stmt->get_result();
$userExists = $result->num_rows > 0;

if ($userExists) {
    $row = $result->fetch_assoc();
    $storedPassword = $row['Password']; 
    // $passwordIsCorrect = password_verify($password, $storedPassword);
    $passwordIsCorrect = password_verify($password, $storedPassword);
    if ($passwordIsCorrect) {
        // User successfully logged in, return success message and user id
        // echo json_encode(array('success' => true, 'userId' => $row['Id']));
        // echo json_encode(array('success' => true));
        // Read the secret key from the .env file
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $secretKey = $_ENV['secret_key'];

        $issuedAt = new DateTimeImmutable();
        $expire = $issuedAt->modify('+100 hour')->getTimestamp(); // Token expires in 1 hour
        $servername = "localhost";

        // Create the JWT payload
        $payload = array(
            'iat' => $issuedAt->getTimestamp(),
            'iss' => $servername,
            'nbf' => $issuedAt->getTimestamp(),
            'exp' => $expire,
            "user_id" => $row['Id']
        );
        // Generate the JWT token
        $token = JWT::encode($payload, $secretKey, 'HS256');
        setcookie("jwt_token", $token, $expire, "/", "", false, true);
        echo json_encode(array('success' => true));

    } else {
        // Password is incorrect, return error message
        echo json_encode(array('success' => false));
    }
} else {
    // User does not exist, return error message
    echo json_encode(array('success' => false));
}
$stmt->close();
$conn->close();