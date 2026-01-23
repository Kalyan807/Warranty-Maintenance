<?php
// get_notifications.php - Get User Notifications API
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

$notifications = [];
$id = 1;

// Function to calculate time ago
function timeAgo($datetime)
{
    $createdAt = new DateTime($datetime);
    $now = new DateTime();
    $diff = $now->diff($createdAt);

    if ($diff->days > 0) {
        return $diff->days . " day" . ($diff->days > 1 ? "s" : "") . " ago";
    } elseif ($diff->h > 0) {
        return $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
    } else {
        return $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
    }
}

// 1. Technician Assigned notifications
$assignedResult = $conn->query("
    SELECT i.appliance, t.name as tech_name, i.updated_at 
    FROM issues i
    JOIN technicians t ON i.assigned_technician_id = t.id
    WHERE i.status = 'Assigned' 
    ORDER BY i.updated_at DESC 
    LIMIT 3
");

while ($row = $assignedResult->fetch_assoc()) {
    $notifications[] = [
        "id" => $id++,
        "title" => "Technician Assigned",
        "message" => $row['tech_name'] . " has been assigned to fix your " . $row['appliance'],
        "time" => timeAgo($row['updated_at']),
        "type" => "assigned",
        "isRead" => false
    ];
}

// 2. Service Scheduled notifications (In Progress)
$scheduledResult = $conn->query("
    SELECT i.appliance, i.updated_at 
    FROM issues i
    WHERE i.status = 'In Progress' 
    ORDER BY i.updated_at DESC 
    LIMIT 3
");

while ($row = $scheduledResult->fetch_assoc()) {
    $scheduledDate = (new DateTime($row['updated_at']))->modify('+2 days')->format('M d, Y \\a\\t h:i A');
    $notifications[] = [
        "id" => $id++,
        "title" => "Service Scheduled",
        "message" => "Your service appointment is scheduled for " . $scheduledDate,
        "time" => timeAgo($row['updated_at']),
        "type" => "scheduled",
        "isRead" => false
    ];
}

// 3. Warranty Expiring Soon notifications
$expiryResult = $conn->query("
    SELECT appliance, expiry_date, DATEDIFF(expiry_date, CURDATE()) as days_left
    FROM warranty_records 
    WHERE expiry_date >= CURDATE() 
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY expiry_date ASC
    LIMIT 3
");

while ($row = $expiryResult->fetch_assoc()) {
    $notifications[] = [
        "id" => $id++,
        "title" => "Warranty Expiring Soon",
        "message" => "Your " . $row['appliance'] . " warranty expires in " . $row['days_left'] . " days",
        "time" => $row['days_left'] == 1 ? "1 day ago" : $row['days_left'] . " days ago",
        "type" => "warranty",
        "isRead" => false
    ];
}

// 4. Service Completed notifications
$completedResult = $conn->query("
    SELECT appliance, updated_at 
    FROM issues 
    WHERE status = 'Resolved' OR status = 'Closed'
    ORDER BY updated_at DESC 
    LIMIT 3
");

while ($row = $completedResult->fetch_assoc()) {
    $notifications[] = [
        "id" => $id++,
        "title" => "Service Completed",
        "message" => "Your " . $row['appliance'] . " service has been completed successfully",
        "time" => timeAgo($row['updated_at']),
        "type" => "completed",
        "isRead" => true
    ];
}

// Sort by read status (unread first), then by time
usort($notifications, function ($a, $b) {
    if ($a['isRead'] !== $b['isRead']) {
        return $a['isRead'] - $b['isRead'];
    }
    return 0;
});

// Count unread
$unreadCount = count(array_filter($notifications, function ($n) {
    return !$n['isRead'];
}));

echo json_encode([
    "notifications" => $notifications,
    "unreadCount" => $unreadCount
]);

$conn->close();
?>