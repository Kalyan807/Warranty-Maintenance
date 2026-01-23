<?php
// supervisor_signup.php - Supervisor Registration API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST method required"]);
    exit;
}

// Database connection
include("db.php");

// Create supervisors table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS supervisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createTable)) {
    echo json_encode(["status" => "error", "message" => "Failed to create table: " . $conn->error]);
    exit;
}

// Read input JSON
$data = json_decode(file_get_contents("php://input"), true);

$full_name = trim($data["full_name"] ?? '');
$email = trim($data["email"] ?? '');
$phone = trim($data["phone"] ?? '');
$address = trim($data["address"] ?? '');
$password = $data["password"] ?? '';
$confirm_password = $data["confirm_password"] ?? '';

// Validate required fields
if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($password) || empty($confirm_password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit;
}

// Password match validation
if ($password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

// Password length validation
if (strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]);
    exit;
}

// Check if email already exists
$check = $conn->prepare("SELECT id FROM supervisors WHERE email = ?");
if (!$check) {
    echo json_encode(["status" => "error", "message" => "Prepare check failed: " . $conn->error]);
    exit;
}
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert supervisor
$stmt = $conn->prepare("INSERT INTO supervisors (full_name, email, phone, address, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare insert failed: " . $conn->error]);
    exit;
}
$stmt->bind_param("sssss", $full_name, $email, $phone, $address, $hashed_password);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Supervisor registration successful",
        "user_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $stmt->error]);
}

$conn->close();
?>