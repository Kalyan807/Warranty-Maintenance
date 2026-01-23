<?php
// get_service_history.php - Get Service History API
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

// Optional filters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Get total count
$countResult = $conn->query("SELECT COUNT(*) as count FROM service_reports");
$totalServices = (int) $countResult->fetch_assoc()['count'];

// Get service history with issue and user info
$sql = "
    SELECT sr.id, i.appliance, 
           COALESCE(u.full_name, 'Customer') as customer_name,
           DATE_FORMAT(sr.created_at, '%Y-%m-%d') as date
    FROM service_reports sr
    JOIN issues i ON sr.issue_id = i.id
    LEFT JOIN users u ON i.reported_by = u.id
    ORDER BY sr.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$serviceHistory = [];
while ($row = $result->fetch_assoc()) {
    $serviceHistory[] = [
        "id" => (int) $row['id'],
        "applianceName" => $row['appliance'],
        "customerName" => $row['customer_name'],
        "date" => $row['date']
    ];
}

echo json_encode([
    "serviceHistory" => $serviceHistory,
    "totalServices" => $totalServices
]);

$conn->close();
?>