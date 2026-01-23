<?php
// get_technicians.php - Get Technicians List API
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

// Get all technicians with their current assignment status
$sql = "
    SELECT t.id, t.name, t.specialization, t.experience_years,
           (SELECT COUNT(*) FROM issues i WHERE i.assigned_technician_id = t.id AND i.status = 'In Progress') as active_tasks
    FROM technicians t
    ORDER BY t.name ASC
";

$result = $conn->query($sql);

$technicians = [];
while ($row = $result->fetch_assoc()) {
    // Determine status based on active tasks
    $status = $row['active_tasks'] > 0 ? "Busy" : "Available";

    // Calculate a mock rating (in production, this would come from a ratings table)
    $rating = 4.0 + (rand(0, 10) / 10);

    $technicians[] = [
        "id" => (int) $row['id'],
        "name" => $row['name'],
        "specialization" => $row['specialization'],
        "experience" => (int) $row['experience_years'],
        "status" => $status,
        "rating" => round($rating, 1)
    ];
}

echo json_encode(["technicians" => $technicians]);

$conn->close();
?>