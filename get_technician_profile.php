<?php
// get_technician_profile.php - Get Technician Profile API
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

// Get technician_id from query param
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 1;

// Get technician basic info
$stmt = $conn->prepare("SELECT name, specialization, experience_years FROM technicians WHERE id = ?");
$stmt->bind_param("i", $technician_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Technician not found"]);
    exit;
}

$tech = $result->fetch_assoc();

// Count completed tasks
$completedResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE assigned_technician_id = $technician_id 
    AND (status = 'Resolved' OR status = 'Closed')
");
$completedTasks = (int) $completedResult->fetch_assoc()['count'];

// Count total assigned tasks
$totalResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE assigned_technician_id = $technician_id
");
$totalTasks = (int) $totalResult->fetch_assoc()['count'];

// Calculate success rate
$successRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 100;

// Mock rating (in production, fetch from ratings table)
$rating = 4.0 + (rand(0, 10) / 10);

echo json_encode([
    "name" => $tech['name'],
    "specialization" => $tech['specialization'],
    "rating" => round($rating, 1),
    "completedTasks" => $completedTasks,
    "experience" => (int) $tech['experience_years'],
    "successRate" => $successRate
]);

$conn->close();
?>