<?php
// get_service_tracking.php - Get Service Tracking for User
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

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// Get the most recent issue for this user
$sql = "
    SELECT i.id, i.appliance, i.issue_description, i.status, 
           i.created_at, i.updated_at,
           t.id as tech_id, t.name as tech_name, t.phone as tech_phone,
           i.assigned_technician_id
    FROM issues i
    LEFT JOIN technicians t ON i.assigned_technician_id = t.id
    WHERE i.reported_by = ?
    ORDER BY i.created_at DESC
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No service requests found"]);
    exit;
}

$row = $result->fetch_assoc();

// Format dates
$createdAt = new DateTime($row['created_at']);
$reportedDate = $createdAt->format('M d, g:i A');

$assignedDate = null;
$inProgressDate = null;
$completedDate = null;
$expectedVisit = null;

// Determine progress based on status
$status = $row['status'];

if ($status === 'Assigned' || $status === 'In Progress' || $status === 'Resolved' || $status === 'Closed') {
    // If assigned, calculate assigned date (approximate based on updated_at for demo)
    $assignedDate = (new DateTime($row['created_at']))->modify('+1 hour 45 minutes')->format('M d, g:i A');
    $expectedVisit = (new DateTime($row['created_at']))->modify('+2 days')->format('M d, Y \a\t h:i A');
}

if ($status === 'In Progress' || $status === 'Resolved' || $status === 'Closed') {
    $inProgressDate = (new DateTime($row['created_at']))->modify('+2 days')->format('M d, g:i A');
}

if ($status === 'Resolved' || $status === 'Closed') {
    $completedDate = (new DateTime($row['updated_at']))->format('M d, g:i A');
}

// Build technician data
$technician = null;
if ($row['tech_id']) {
    $technician = [
        "id" => (int) $row['tech_id'],
        "name" => $row['tech_name'],
        "phone" => $row['tech_phone'] ?? "1234567890",
        "expectedVisit" => $expectedVisit
    ];
}

$service = [
    "id" => (int) $row['id'],
    "appliance" => $row['appliance'],
    "issue" => $row['issue_description'],
    "status" => $status,
    "reportedDate" => $reportedDate,
    "assignedDate" => $assignedDate,
    "inProgressDate" => $inProgressDate,
    "completedDate" => $completedDate,
    "technician" => $technician
];

echo json_encode([
    "status" => "success",
    "service" => $service
]);

$conn->close();
?>