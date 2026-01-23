<?php
// get_technician_dashboard.php - Technician Dashboard Data API
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

// Get technician_id from query param (required)
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 0;
if ($technician_id <= 0) {
    echo json_encode(["status" => "error", "message" => "technician_id is required"]);
    exit;
}

// Count assigned tasks (Pending + In Progress)
$assignedResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE assigned_technician_id = $technician_id 
    AND (status = 'Pending' OR status = 'In Progress')
");
$assigned = (int) $assignedResult->fetch_assoc()['count'];

// Count completed tasks
$completedResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE assigned_technician_id = $technician_id 
    AND (status = 'Resolved' OR status = 'Closed')
");
$completed = (int) $completedResult->fetch_assoc()['count'];

// Count today's tasks
$todayResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE assigned_technician_id = $technician_id 
    AND DATE(updated_at) = CURDATE()
    AND status = 'In Progress'
");
$today = (int) $todayResult->fetch_assoc()['count'];

// Today's schedule - show all in-progress tasks assigned to this technician
$scheduleResult = $conn->query("
    SELECT i.appliance, i.issue_description, 
           COALESCE(u.full_name, 'Customer') as customer_name,
           COALESCE(u.address, 'Address not provided') as address,
           DATE_FORMAT(i.updated_at, '%h:%i %p') as time
    FROM issues i
    LEFT JOIN users u ON i.reported_by = u.id
    WHERE i.assigned_technician_id = $technician_id
    AND i.status = 'In Progress'
    ORDER BY i.updated_at DESC
    LIMIT 5
");

$todaysSchedule = [];
while ($row = $scheduleResult->fetch_assoc()) {
    $todaysSchedule[] = [
        "applianceName" => $row['appliance'],
        "issueDescription" => $row['issue_description'],
        "customerName" => $row['customer_name'],
        "address" => $row['address'],
        "time" => $row['time']
    ];
}

// Assigned tasks (not yet started or in progress)
$assignedTasksResult = $conn->query("
    SELECT i.id, i.appliance, i.issue_description,
           COALESCE(u.address, 'Address not provided') as address,
           DATE_FORMAT(i.created_at, '%Y-%m-%d %H:%i') as date_time
    FROM issues i
    LEFT JOIN users u ON i.reported_by = u.id
    WHERE i.assigned_technician_id = $technician_id
    AND (i.status = 'Pending' OR i.status = 'In Progress')
    ORDER BY i.created_at DESC
    LIMIT 10
");

$assignedTasks = [];
while ($row = $assignedTasksResult->fetch_assoc()) {
    $assignedTasks[] = [
        "id" => (int) $row['id'],
        "applianceName" => $row['appliance'],
        "issueDescription" => $row['issue_description'],
        "address" => $row['address'],
        "dateTime" => $row['date_time']
    ];
}

// Completed tasks
$completedTasksResult = $conn->query("
    SELECT i.appliance, i.issue_description,
           DATE_FORMAT(i.updated_at, '%Y-%m-%d') as date
    FROM issues i
    WHERE i.assigned_technician_id = $technician_id
    AND (i.status = 'Resolved' OR i.status = 'Closed')
    ORDER BY i.updated_at DESC
    LIMIT 10
");

$completedTasks = [];
while ($row = $completedTasksResult->fetch_assoc()) {
    $completedTasks[] = [
        "applianceName" => $row['appliance'],
        "issueDescription" => $row['issue_description'],
        "date" => $row['date']
    ];
}

echo json_encode([
    "assigned" => $assigned,
    "completed" => $completed,
    "today" => $today,
    "todaysSchedule" => $todaysSchedule,
    "assignedTasks" => $assignedTasks,
    "completedTasks" => $completedTasks
]);

$conn->close();
?>