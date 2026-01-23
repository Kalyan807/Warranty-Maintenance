<?php
// dashboard_summary.php - Supervisor Dashboard Summary API
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
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Get total appliances (from warranty_records, fallback to issues count)
$totalAppliances = 0;
$appliancesResult = $conn->query("SELECT COUNT(*) as count FROM warranty_records");
if ($appliancesResult && $row = $appliancesResult->fetch_assoc()) {
    $totalAppliances = (int) $row['count'];
}
// If no warranty records, count unique appliances from issues
if ($totalAppliances == 0) {
    $issueApplianceResult = $conn->query("SELECT COUNT(DISTINCT appliance) as count FROM issues");
    if ($issueApplianceResult && $row = $issueApplianceResult->fetch_assoc()) {
        $totalAppliances = (int) $row['count'];
    }
}

// Get total technicians
$totalTechnicians = 0;
$techniciansResult = $conn->query("SELECT COUNT(*) as count FROM technicians");
if ($techniciansResult && $row = $techniciansResult->fetch_assoc()) {
    $totalTechnicians = (int) $row['count'];
}

// Get pending issues
$pendingIssues = 0;
$pendingResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Pending'");
if ($pendingResult && $row = $pendingResult->fetch_assoc()) {
    $pendingIssues = (int) $row['count'];
}

// Get in-progress issues (assigned to technicians)
$inProgressIssues = 0;
$inProgressResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'In Progress'");
if ($inProgressResult && $row = $inProgressResult->fetch_assoc()) {
    $inProgressIssues = (int) $row['count'];
}

// Get warranties expiring in next 30 days
$warrantyExpiry = 0;
$expiryResult = $conn->query("
    SELECT COUNT(*) as count FROM warranty_records 
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
if ($expiryResult && $row = $expiryResult->fetch_assoc()) {
    $warrantyExpiry = (int) $row['count'];
}

// Get total issues for debugging
$totalIssues = 0;
$totalIssuesResult = $conn->query("SELECT COUNT(*) as count FROM issues");
if ($totalIssuesResult && $row = $totalIssuesResult->fetch_assoc()) {
    $totalIssues = (int) $row['count'];
}

echo json_encode([
    "totalAppliances" => $totalAppliances,
    "totalTechnicians" => $totalTechnicians,
    "pendingIssues" => $pendingIssues,
    "warrantyExpiry" => $warrantyExpiry,
    "inProgressIssues" => $inProgressIssues,
    "totalIssues" => $totalIssues
]);

$conn->close();
?>