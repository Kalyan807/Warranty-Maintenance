<?php
// get_user_appliances.php - Get User's Registered Appliances API
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

// Get user_id from query param (in production, use session/token)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Get all warranty records (appliances)
// In production, filter by user_id when user association is implemented
$sql = "
    SELECT id, appliance, model_number, expiry_date, document_path,
           DATEDIFF(expiry_date, CURDATE()) as warranty_days_left
    FROM warranty_records
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

$appliances = [];
while ($row = $result->fetch_assoc()) {
    // Generate a display name combining appliance and model
    $name = $row['appliance'];
    if ($row['model_number'] && $row['model_number'] !== '') {
        $name .= " - " . str_replace(['&quot;', '"'], '', $row['model_number']);
    }

    // Use document_path as image if it's an image file
    $imageUrl = "";
    if ($row['document_path']) {
        $ext = strtolower(pathinfo($row['document_path'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $imageUrl = $row['document_path'];
        }
    }

    $appliances[] = [
        "id" => (int) $row['id'],
        "name" => $name,
        "type" => $row['appliance'],
        "warrantyDaysLeft" => max(0, (int) $row['warranty_days_left']),
        "imageUrl" => $imageUrl
    ];
}

echo json_encode([
    "appliances" => $appliances,
    "registeredAppliances" => count($appliances)
]);

$conn->close();
?>