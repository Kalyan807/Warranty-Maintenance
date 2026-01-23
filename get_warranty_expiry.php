<?php
// get_warranty_expiry.php - Get Warranties Expiring Soon API
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

// Get days parameter (default 90 days)
$days = isset($_GET['days']) ? intval($_GET['days']) : 90;

// Get warranties expiring within specified days
$sql = "
    SELECT id, appliance, model_number, expiry_date,
           DATEDIFF(expiry_date, CURDATE()) as days_left
    FROM warranty_records
    WHERE expiry_date >= CURDATE() 
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY expiry_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $days);
$stmt->execute();
$result = $stmt->get_result();

$warranties = [];
while ($row = $result->fetch_assoc()) {
    $warranties[] = [
        "id" => (int) $row['id'],
        "applianceName" => $row['appliance'],
        "ownerName" => "Owner", // In production, link to user table
        "expiryDate" => $row['expiry_date'],
        "daysLeft" => (int) $row['days_left']
    ];
}

echo json_encode(["warranties" => $warranties]);

$conn->close();
?>