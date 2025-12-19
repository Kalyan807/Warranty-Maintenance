<?php
// delete_warranty_record.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method. Use POST."
    ]);
    exit;
}

// DB Connection
$conn = new mysqli("localhost", "root", "", "warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// Read JSON or form-data
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input) || empty($input)) {
    $input = $_POST;
}

// Warranty record ID
$warranty_id = isset($input['warranty_id']) ? intval($input['warranty_id']) : 0;

if ($warranty_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "warranty_id is required"
    ]);
    exit;
}

// 🔎 Check warranty record exists
$check = $conn->prepare("SELECT id FROM warranty_records WHERE id = ?");
$check->bind_param("i", $warranty_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Warranty record not found"
    ]);
    exit;
}

// 🗑 Delete warranty record
$del = $conn->prepare("DELETE FROM warranty_records WHERE id = ?");
$del->bind_param("i", $warranty_id);

if ($del->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Warranty record deleted successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete warranty record"
    ]);
}
exit;
