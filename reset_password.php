<?php
// reset_password.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method"]);
    exit;
}

$host="localhost"; $user="root"; $pass=""; $db="warrantymaintenance";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]); exit;
}

// read input (JSON or form)
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input) || empty($input)) $input = $_POST;

$email = strtolower(trim($input['email'] ?? ''));
$token = $input['token'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

if (empty($email) || empty($token) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(["status"=>"error","message"=>"All fields are required"]); exit;
}
if ($new_password !== $confirm_password) {
    echo json_encode(["status"=>"error","message"=>"Passwords do not match"]); exit;
}
if (strlen($new_password) < 6) {
    echo json_encode(["status"=>"error","message"=>"Password should be at least 6 characters"]); exit;
}

// Fetch user and token hash
$stmt = $conn->prepare("SELECT id, reset_token_hash, reset_expires FROM users WHERE email = ?");
$stmt->bind_param("s",$email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Invalid token or email"]); exit;
}
$user = $res->fetch_assoc();
if (empty($user['reset_token_hash']) || empty($user['reset_expires'])) {
    echo json_encode(["status"=>"error","message"=>"Invalid or expired token"]); exit;
}

// Verify expiry
if (strtotime($user['reset_expires']) < time()) {
    echo json_encode(["status"=>"error","message"=>"Token expired"]); exit;
}

// Verify token hash
$token_hash = hash('sha256', $token);
if (!hash_equals($user['reset_token_hash'], $token_hash)) {
    echo json_encode(["status"=>"error","message"=>"Invalid token"]); exit;
}

// All good — update password and clear reset fields
$new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE users SET password = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?");
$upd->bind_param("si", $new_hashed, $user['id']);
if ($upd->execute()) {
    echo json_encode(["status"=>"success","message"=>"Password updated successfully"]);
    exit;
} else {
    echo json_encode(["status"=>"error","message"=>"Failed to update password"]);
    exit;
}
?>
