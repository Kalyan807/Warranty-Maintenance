<?php
// get_issues.php - Get Issues List API
// Clean any previous output and start fresh
ob_start();
ob_clean();

// Suppress warnings that could corrupt JSON output
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json; charset=UTF-8");
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

try {
    // Database connection
    include("db.php");

    // Set UTF-8 charset for connection
    $conn->set_charset("utf8mb4");

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
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Query prepare failed", "issues" => []]);
        exit;
    }

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

        // Clean and encode strings properly
        $appliance = $row['appliance'] ?? "Unknown";
        $description = $row['issue_description'] ?? "";
        $reportedBy = $row['reported_by_name'] ?? "Unknown";
        $userAddress = $row['user_address'] ?? null;
        $technicianName = $row['technician_name'] ?? null;

        $issues[] = [
            "id" => (int) $row['id'],
            "applianceName" => mb_convert_encoding($appliance, 'UTF-8', 'UTF-8'),
            "issueDescription" => mb_convert_encoding($description, 'UTF-8', 'UTF-8'),
            "priority" => $priority,
            "status" => $row['status'] ?? "Pending",
            "reportedBy" => mb_convert_encoding($reportedBy, 'UTF-8', 'UTF-8'),
            "reportedDate" => date("Y-m-d", strtotime($row['created_at'])),
            "userAddress" => $userAddress ? mb_convert_encoding($userAddress, 'UTF-8', 'UTF-8') : null,
            "technicianName" => $technicianName ? mb_convert_encoding($technicianName, 'UTF-8', 'UTF-8') : null
        ];
    }

    $conn->close();

    // Use JSON_UNESCAPED_UNICODE to properly handle special characters
    echo json_encode(["issues" => $issues], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage(), "issues" => []]);
}
?>