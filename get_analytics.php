<?php
// get_analytics.php - Analytics Data API
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

// Total issues
$totalResult = $conn->query("SELECT COUNT(*) as count FROM issues");
$totalIssues = (int) $totalResult->fetch_assoc()['count'];

// Issues this month vs last month (for change calculation)
$thisMonthResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
");
$thisMonth = (int) $thisMonthResult->fetch_assoc()['count'];

$lastMonthResult = $conn->query("
    SELECT COUNT(*) as count FROM issues 
    WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
    AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");
$lastMonth = (int) $lastMonthResult->fetch_assoc()['count'];
$issuesChange = $thisMonth - $lastMonth;

// Resolved issues
$resolvedResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Resolved' OR status = 'Closed'");
$resolvedIssues = (int) $resolvedResult->fetch_assoc()['count'];

// Resolution rate
$resolutionRate = $totalIssues > 0 ? round(($resolvedIssues / $totalIssues) * 100) : 0;

// Issues by status
$completedResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Resolved' OR status = 'Closed'");
$completed = (int) $completedResult->fetch_assoc()['count'];

$inProgressResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'In Progress'");
$inProgress = (int) $inProgressResult->fetch_assoc()['count'];

$pendingResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Pending'");
$pending = (int) $pendingResult->fetch_assoc()['count'];

// Technician performance
$techResult = $conn->query("
    SELECT t.name, 
           COUNT(sr.id) as tasks_completed,
           COALESCE(AVG(4.5), 4.5) as rating
    FROM technicians t
    LEFT JOIN issues i ON t.id = i.assigned_technician_id
    LEFT JOIN service_reports sr ON i.id = sr.issue_id
    GROUP BY t.id, t.name
    ORDER BY tasks_completed DESC
    LIMIT 10
");

$technicianPerformance = [];
while ($row = $techResult->fetch_assoc()) {
    $technicianPerformance[] = [
        "name" => $row['name'],
        "tasksCompleted" => (int) $row['tasks_completed'],
        "rating" => round((float) $row['rating'], 1)
    ];
}

// Monthly service count (last 12 months)
$monthlyServiceCount = [];
for ($i = 11; $i >= 0; $i--) {
    $monthResult = $conn->query("
        SELECT COUNT(*) as count FROM service_reports 
        WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL $i MONTH))
        AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL $i MONTH))
    ");
    $monthlyServiceCount[] = (int) $monthResult->fetch_assoc()['count'];
}

echo json_encode([
    "totalIssues" => $totalIssues,
    "issuesChange" => $issuesChange,
    "resolvedIssues" => $resolvedIssues,
    "resolutionRate" => $resolutionRate,
    "issuesByStatus" => [
        "completed" => $completed,
        "inProgress" => $inProgress,
        "pending" => $pending
    ],
    "technicianPerformance" => $technicianPerformance,
    "monthlyServiceCount" => $monthlyServiceCount
]);

$conn->close();
?>