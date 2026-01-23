<?php
// update_issue_status.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method. Use POST."]);
    exit;
}

// Optional supervisor auth stub (uncomment & implement in production)
// $sup_token = $_SERVER['HTTP_X_SUPERVISOR_TOKEN'] ?? '';
// if ($sup_token !== 'SOME_SECRET_TOKEN') { echo json_encode(["status"=>"error","message"=>"Unauthorized"]); exit; }

$host = "localhost";
$user = "root";
$pass = "";
$db = "warrantymaintenance";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Read JSON or form
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input) || empty($input))
    $input = $_POST;

// Support both snake_case (legacy) and camelCase (from Android app)
$issue_id = isset($input['issue_id']) ? intval($input['issue_id']) : (isset($input['issueId']) ? intval($input['issueId']) : 0);
$status = isset($input['status']) ? trim($input['status']) : '';
$comment = isset($input['comment']) ? trim($input['comment']) : (isset($input['notes']) ? trim($input['notes']) : null);
$assigned_technician_id = null;

// Support both field names for technician ID
if (isset($input['assigned_technician_id']) && is_numeric($input['assigned_technician_id'])) {
    $assigned_technician_id = intval($input['assigned_technician_id']);
} elseif (isset($input['technicianId']) && is_numeric($input['technicianId'])) {
    $assigned_technician_id = intval($input['technicianId']);
}

// If technicianId is provided but status is empty, default to "In Progress" (assigning task)
if ($assigned_technician_id !== null && $status === '') {
    $status = 'In Progress';
}

if ($issue_id <= 0) {
    echo json_encode(["status" => "error", "message" => "issue_id is required"]);
    exit;
}
if ($status === '') {
    echo json_encode(["status" => "error", "message" => "status is required"]);
    exit;
}

// Allowed statuses (include "Pending" and "Assigned" for flexibility)
$allowed = ["Pending", "Open", "Assigned", "In Progress", "Resolved", "Closed"];
if (!in_array($status, $allowed)) {
    echo json_encode(["status" => "error", "message" => "Invalid status. Allowed: " . implode(", ", $allowed)]);
    exit;
}

// Verify issue exists
$chk = $conn->prepare("SELECT id FROM issues WHERE id = ?");
$chk->bind_param("i", $issue_id);
$chk->execute();
$res = $chk->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Issue not found"]);
    exit;
}

// Build update query dynamically
$fields = ["status = ?"];
$types = "s";
$params = [$status];

if ($assigned_technician_id !== null) {
    $fields[] = "assigned_technician_id = ?";
    $types .= "i";
    $params[] = $assigned_technician_id;
}
if ($comment !== null && $comment !== '') {
    $fields[] = "supervisor_comment = ?";
    $types .= "s";
    $params[] = $comment;
}
$fields_sql = implode(", ", $fields);

$sql = "UPDATE issues SET $fields_sql, updated_at = NOW() WHERE id = ?";
$types .= "i";
$params[] = $issue_id;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database prepare failed: " . $conn->error]);
    exit;
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Optionally log a status-change history table here
    echo json_encode(["status" => "success", "message" => "Issue updated"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update issue: " . $stmt->error]);
}
exit;
?>