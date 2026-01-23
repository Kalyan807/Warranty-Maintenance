<?php
// get_task_details.php - Get Task Details by Issue ID
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "GET method required"]);
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Get task_id from query parameter
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if ($task_id <= 0) {
    echo json_encode(["status" => "error", "message" => "task_id is required"]);
    exit;
}

// Fetch task details with customer info
$sql = "SELECT i.id, i.appliance, i.issue_description, i.status, 
               i.supervisor_comment, i.created_at,
               u.full_name as customer_name, u.phone as customer_phone,
               u.address as customer_address
        FROM issues i
        LEFT JOIN users u ON i.reported_by = u.id
        WHERE i.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Task not found"]);
    exit;
}

$row = $result->fetch_assoc();

// Calculate priority based on status and age
$createdDate = new DateTime($row['created_at']);
$now = new DateTime();
$diff = $now->diff($createdDate)->days;

$priority = "Low";
if ($row['status'] === 'Pending' && $diff > 3) {
    $priority = "High";
} elseif ($row['status'] === 'Pending' || $row['status'] === 'In Progress') {
    $priority = "Medium";
}

// Return task details in format expected by Android app
echo json_encode([
    "id" => (int) $row['id'],
    "applianceName" => $row['appliance'],
    "issueDescription" => $row['issue_description'],
    "priority" => $priority,
    "address" => $row['customer_address'] ?? "Address not provided",
    "customerName" => $row['customer_name'] ?? "Unknown",
    "customerPhone" => $row['customer_phone'] ?? "Not available",
    "supervisorNotes" => $row['supervisor_comment'] ?? "No notes provided"
]);

$conn->close();
?>