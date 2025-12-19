<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"POST method required"]);
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

/* 🔹 Read JSON or form-data */
$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

// Inputs
$appliance   = trim($input['appliance'] ?? '');
$description = trim($input['description'] ?? '');

$allowed = ['Air Conditioner','Television','Fan'];
$errors = [];

// Validation
if (!in_array($appliance, $allowed, true)) {
    $errors[] = "Invalid appliance selected. Allowed: Air Conditioner, Television, Fan";
}

if ($description === '') {
    $errors[] = "Description is required";
}

if (!empty($errors)) {
    echo json_encode(["status"=>"error","errors"=>$errors]);
    exit;
}

/* 🔹 Image Upload */
$imagePath = null;
if (!empty($_FILES['image']['name'])) {

    $allowedTypes = ['image/jpeg','image/png'];
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        echo json_encode(["status"=>"error","message"=>"Only JPG or PNG allowed"]);
        exit;
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        echo json_encode(["status"=>"error","message"=>"Image max size is 5MB"]);
        exit;
    }

    $uploadDir = "uploads/issues/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = time() . "_" . $_FILES['image']['name'];
    $target   = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $imagePath = $target;
    }
}

// Insert
$stmt = $conn->prepare(
    "INSERT INTO issues (appliance, issue_description, status) VALUES (?, ?, 'Pending')"
);
$stmt->bind_param("ss", $appliance, $description);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Issue reported successfully"
    ]);
} else {
    echo json_encode([
        "status"=>"error",
        "message"=>"Failed to submit issue"
    ]);
}
