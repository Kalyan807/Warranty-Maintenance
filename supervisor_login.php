<?php
// supervisor_login.php - Supervisor Login API
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
include("db.php");

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

// Fetch supervisor by email
$stmt = $conn->prepare("SELECT id, full_name, email, password FROM supervisors WHERE email = ?");
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

$supervisor = $result->fetch_assoc();
$hashed = $supervisor['password'];

// Verify password
if (password_verify($password, $hashed)) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $supervisor['id'];
    $_SESSION['user_email'] = $supervisor['email'];
    $_SESSION['user_name'] = $supervisor['full_name'];
    $_SESSION['role'] = 'SUPERVISOR';

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => $supervisor['id'],
            "full_name" => $supervisor['full_name'],
            "email" => $supervisor['email'],
            "role" => "SUPERVISOR"
        ]
    ]);
    exit;
} else {
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}
?>