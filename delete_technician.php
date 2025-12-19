<?php
// delete_technician.php
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

$technician_id = isset($input['technician_id']) ? intval($input['technician_id']) : 0;

if ($technician_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "technician_id is required"
    ]);
    exit;
}

// 🔎 Check technician exists
$check = $conn->prepare("SELECT id FROM technicians WHERE id = ?");
$check->bind_param("i", $technician_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Technician not found"
    ]);
    exit;
}

// 🗑 Delete technician
$del = $conn->prepare("DELETE FROM technicians WHERE id = ?");
$del->bind_param("i", $technician_id);

if ($del->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Technician deleted successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete technician"
    ]);
}
exit;
