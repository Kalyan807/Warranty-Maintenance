<?php
// request_reset.php
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

if (empty($email)) {
    echo json_encode(["status"=>"error","message"=>"Email is required"]); exit;
}

// Check user exists
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
$stmt->bind_param("s",$email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    // For privacy: respond success even if email not found
    echo json_encode(["status"=>"success","message"=>"If that email exists, a reset link was sent."]);
    exit;
}
$user = $res->fetch_assoc();
$user_id = $user['id'];

// Generate a secure token and its hash
$token = bin2hex(random_bytes(20)); // 40-char token
$token_hash = hash('sha256', $token);
$expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour

// Store token hash + expiry
$upd = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?");
$upd->bind_param("ssi", $token_hash, $expiry, $user_id);
$upd->execute();

// Build reset link — adjust hostname/path for your site
$reset_link = "http://localhost/warrantymaintenance/reset_password.html?email=" . urlencode($email) . "&token=" . $token;

// Send email
$subject = "Password reset request";
$body = "Hello " . htmlspecialchars($user['full_name']) . ",\n\n";
$body .= "We received a request to reset your password. Click the link below to reset it (valid 1 hour):\n\n";
$body .= $reset_link . "\n\nIf you did not request this, ignore this email.\n\nThanks.";

// Simple mail() — may not work on local XAMPP without mail setup.
// Replace/from address as needed.
$headers = "From: no-reply@example.com\r\nReply-To: no-reply@example.com\r\n";
@mail($email, $subject, $body, $headers);

// Better: use PHPMailer + SMTP in production (see notes below)

echo json_encode(["status"=>"success","message"=>"If that email exists, a reset link was sent."]);
exit;
?>
