<?php
// forgot_password.php — Warranty Maintenance
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"POST method required"]);
    exit;
}

// DB Connection (Warranty Maintenance DB)
$conn = new mysqli("localhost", "root", "", "warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

// Read JSON or form-data
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

$email = strtolower(trim($input['email'] ?? ''));

if (empty($email)) {
    echo json_encode(["status"=>"error","message"=>"Email is required"]);
    exit;
}

// Check user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    // Security: do not reveal user existence
    echo json_encode([
        "status"  => "success",
        "message" => "Password reset link sent to email"
    ]);
    exit;
}

$user = $res->fetch_assoc();

// Generate secure token
$token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);

// Token valid for 30 minutes
$expires = date("Y-m-d H:i:s", time() + (30 * 60));

// Save token hash
$upd = $conn->prepare(
    "UPDATE users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?"
);
$upd->bind_param("ssi", $token_hash, $expires, $user['id']);
$upd->execute();

// Reset link (Warranty Maintenance)
$reset_link = "http://localhost/Warranty_Maintenance/reset_password.html?email="
    . urlencode($email)
    . "&token=" . urlencode($token);

// Email content
$subject = "Password Reset – Warranty Maintenance System";
$message = "
Hello,

You requested a password reset for your Warranty Maintenance account.

Reset your password using the link below (valid for 30 minutes):

$reset_link

If you did not request this, please ignore this email.

Regards,
Warranty Maintenance Team
";

// Try email silently (mail() fails on localhost)
@ mail($email, $subject, $message, "From: no-reply@warrantymaintenance.com");

// ALWAYS return success (Android / Web safe)
echo json_encode([
    "status"  => "success",
    "message" => "Password reset link sent to email"
]);
exit;
