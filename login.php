<?php
// Clean any previous output
ob_start();
ob_clean();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

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

// Fetch user by email
$stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed"]);
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Avoid leaking whether email exists
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}

$user = $result->fetch_assoc();
$hashed = $user['password'];

// Verify password
if (password_verify($password, $hashed)) {
    // Optional: regenerate session id
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];

    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "user" => [
            "id" => $user['id'],
            "full_name" => $user['full_name'],
            "email" => $user['email'],
            "role" => $user['role'] ?? 'USER'
        ]
    ]);
    exit;
} else {
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}
?>