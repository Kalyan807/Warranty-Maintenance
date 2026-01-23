<?php
// technician_login.php - Technician Login API
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database config
$host = "localhost";
$user = "root";
$pass = "";
$db = "warrantymaintenance";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Email and password are required"]);
    exit;
}

// Fetch technician by email
$stmt = $conn->prepare("SELECT id, name, email, password FROM technicians WHERE email = ?");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed"]);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}

$technician = $result->fetch_assoc();
$hashed = $technician['password'];

// Check if password column exists/has value
if (empty($hashed)) {
    echo json_encode(["status" => "error", "message" => "Account not set up for login. Please register first."]);
    exit;
}

// Verify password
if (password_verify($password, $hashed)) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $technician['id'];
    $_SESSION['user_email'] = $technician['email'];
    $_SESSION['user_name'] = $technician['name'];
    $_SESSION['role'] = 'TECHNICIAN';

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => $technician['id'],
            "full_name" => $technician['name'],
            "email" => $technician['email'],
            "role" => "TECHNICIAN"
        ]
    ]);
    exit;
} else {
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}
?>