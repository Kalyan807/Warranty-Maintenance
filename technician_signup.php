<?php
// technician_signup.php - Technician Registration API
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
$host = "localhost";
$user = "root";
$pass = "";
$db = "warrantymaintenance";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Check and add password column if it doesn't exist
$checkPasswordCol = $conn->query("SHOW COLUMNS FROM technicians LIKE 'password'");
if ($checkPasswordCol && $checkPasswordCol->num_rows == 0) {
    $alterResult = $conn->query("ALTER TABLE technicians ADD COLUMN password VARCHAR(255) DEFAULT NULL");
    if (!$alterResult) {
        echo json_encode(["status" => "error", "message" => "Failed to setup password column: " . $conn->error]);
        exit;
    }
}

// Check and add address column if it doesn't exist (may already exist from original schema)
$checkAddressCol = $conn->query("SHOW COLUMNS FROM technicians LIKE 'address'");
if ($checkAddressCol && $checkAddressCol->num_rows == 0) {
    $conn->query("ALTER TABLE technicians ADD COLUMN address TEXT DEFAULT NULL");
}

// Verify password column exists before proceeding
$verifyCol = $conn->query("SHOW COLUMNS FROM technicians LIKE 'password'");
if (!$verifyCol || $verifyCol->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Database setup incomplete: password column missing. Please run setup_auth_tables.sql"]);
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
$specialization = trim($data["specialization"] ?? 'General');

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

// Check if email already exists in technicians table
$check = $conn->prepare("SELECT id FROM technicians WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert technician (using existing technicians table, adding password column)
$stmt = $conn->prepare("INSERT INTO technicians (name, email, phone, address, password, specialization, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("ssssss", $full_name, $email, $phone, $address, $hashed_password, $specialization);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Technician registration successful",
        "user_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $stmt->error]);
}

$conn->close();
?>