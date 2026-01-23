<?php
// get_issues.php - Get Issues List API
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
include('db.php');

// Optional filters
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : null;

// Build query
$sql = "SELECT i.id, i.appliance, i.issue_description, i.status, 
               i.created_at, u.full_name as reported_by_name, u.address as user_address,
               t.name as technician_name
        FROM issues i
        LEFT JOIN users u ON i.reported_by = u.id
        LEFT JOIN technicians t ON i.assigned_technician_id = t.id";

$where = [];
$params = [];
$types = "";

if ($status !== null && $status !== '') {
    $where[] = "i.status = ?";
    $types .= "s";
    $params[] = $status;
}

if ($technician_id !== null && $technician_id > 0) {
    $where[] = "i.assigned_technician_id = ?";
    $types .= "i";
    $params[] = $technician_id;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$issues = [];
while ($row = $result->fetch_assoc()) {
    // Determine priority based on status and age
    $createdDate = new DateTime($row['created_at']);
    $now = new DateTime();
    $diff = $now->diff($createdDate)->days;

    $priority = "Low";
    if ($row['status'] === 'Pending' && $diff > 3) {
        $priority = "High";
    } elseif ($row['status'] === 'Pending') {
        $priority = "Medium";
    } elseif ($row['status'] === 'In Progress') {
        $priority = "Medium";
    }

    $issues[] = [
        "id" => (int) $row['id'],
        "applianceName" => $row['appliance'],
        "issueDescription" => $row['issue_description'],
        "priority" => $priority,
        "status" => $row['status'],
        "reportedBy" => $row['reported_by_name'] ?? "Unknown",
        "reportedDate" => date("Y-m-d", strtotime($row['created_at'])),
        "userAddress" => $row['user_address'] ?? null,
        "technicianName" => $row['technician_name'] ?? null
    ];
}

echo json_encode(["issues" => $issues]);

$conn->close();
?>