<?php
header("Content-Type: application/json");

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "warrantymaintenance";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Read input JSON
$data = json_decode(file_get_contents("php://input"), true);

$full_name = $data["full_name"] ?? '';
$email     = $data["email"] ?? '';
$phone     = $data["phone"] ?? '';
$password  = $data["password"] ?? '';
$confirm_password = $data["confirm_password"] ?? '';

// Check empty fields
if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Password match
if ($password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

// Email already exists?
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Registration successful",
        "user_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed"]);
}
?>
