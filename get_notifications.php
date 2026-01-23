<?php
// get_notifications.php - Get Notifications API (supports both User and Technician)
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
include("db.php");

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

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : 0;
$supervisor_id = isset($_GET['supervisor_id']) ? intval($_GET['supervisor_id']) : 0;

// Determine notification mode
$isSupervisor = $supervisor_id > 0;
$isTechnician = $technician_id > 0;

if ($isSupervisor) {
    // ========== SUPERVISOR NOTIFICATIONS ==========

    // 1. New Pending Issues (not yet assigned)
    $pendingResult = $conn->query("
        SELECT i.id, i.appliance, i.issue_description, u.full_name as reported_by, i.created_at
        FROM issues i
        LEFT JOIN users u ON i.reported_by = u.id
        WHERE i.status = 'Pending'
        AND i.assigned_technician_id IS NULL
        ORDER BY i.created_at DESC
        LIMIT 5
    ");

    while ($row = $pendingResult->fetch_assoc()) {
        $notifications[] = [
            "id" => $id++,
            "title" => "New Issue Reported",
            "message" => ($row['reported_by'] ?? 'A user') . " reported an issue with " . $row['appliance'] . ": " . substr($row['issue_description'], 0, 50) . "...",
            "time" => timeAgo($row['created_at']),
            "type" => "issue",
            "isRead" => false
        ];
    }

    // 2. Recently Assigned Tasks (In Progress)
    $assignedResult = $conn->query("
        SELECT i.appliance, t.name as tech_name, i.updated_at
        FROM issues i
        JOIN technicians t ON i.assigned_technician_id = t.id
        WHERE i.status = 'In Progress'
        ORDER BY i.updated_at DESC
        LIMIT 3
    ");

    while ($row = $assignedResult->fetch_assoc()) {
        $notifications[] = [
            "id" => $id++,
            "title" => "Task In Progress",
            "message" => $row['tech_name'] . " is working on " . $row['appliance'] . " repair",
            "time" => timeAgo($row['updated_at']),
            "type" => "assigned",
            "isRead" => false
        ];
    }

    // 3. Recently Completed Tasks
    $completedResult = $conn->query("
        SELECT i.appliance, t.name as tech_name, i.updated_at
        FROM issues i
        JOIN technicians t ON i.assigned_technician_id = t.id
        WHERE (i.status = 'Resolved' OR i.status = 'Closed')
        ORDER BY i.updated_at DESC
        LIMIT 3
    ");

    while ($row = $completedResult->fetch_assoc()) {
        $notifications[] = [
            "id" => $id++,
            "title" => "Task Completed",
            "message" => $row['tech_name'] . " completed the " . $row['appliance'] . " service",
            "time" => timeAgo($row['updated_at']),
            "type" => "completed",
            "isRead" => true
        ];
    }

    // 4. Warranty Expiring Soon
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
            "message" => $row['appliance'] . " warranty expires in " . $row['days_left'] . " days",
            "time" => $row['days_left'] == 1 ? "1 day left" : $row['days_left'] . " days left",
            "type" => "warranty",
            "isRead" => false
        ];
    }

    // 5. Summary notification
    $summaryResult = $conn->query("SELECT COUNT(*) as count FROM issues WHERE status = 'Pending'");
    $pendingCount = $summaryResult->fetch_assoc()['count'];
    if ($pendingCount > 0) {
        $notifications[] = [
            "id" => $id++,
            "title" => "Pending Issues",
            "message" => "You have " . $pendingCount . " pending issue(s) that need assignment",
            "time" => "Now",
            "type" => "reminder",
            "isRead" => false
        ];
    }

} else if ($isTechnician) {
    // ========== TECHNICIAN NOTIFICATIONS ==========

    // 1. New Task Assigned notifications (tasks assigned to this technician)
    $newTasksResult = $conn->query("
        SELECT i.id, i.appliance, i.issue_description, u.full_name as customer_name, 
               u.address, i.created_at, i.updated_at
        FROM issues i
        LEFT JOIN users u ON i.reported_by = u.id
        WHERE i.assigned_technician_id = $technician_id
        AND i.status = 'In Progress'
        ORDER BY i.updated_at DESC 
        LIMIT 5
    ");

    while ($row = $newTasksResult->fetch_assoc()) {
        $notifications[] = [
            "id" => $id++,
            "title" => "New Task Assigned",
            "message" => "You have been assigned to fix " . $row['appliance'] . " for " . ($row['customer_name'] ?? 'Customer'),
            "time" => timeAgo($row['updated_at']),
            "type" => "assigned",
            "isRead" => false
        ];
    }

    // 2. Pending Tasks reminder
    $pendingResult = $conn->query("
        SELECT COUNT(*) as pending_count
        FROM issues 
        WHERE assigned_technician_id = $technician_id
        AND status = 'In Progress'
    ");
    $pendingRow = $pendingResult->fetch_assoc();
    if ($pendingRow['pending_count'] > 0) {
        $notifications[] = [
            "id" => $id++,
            "title" => "Pending Tasks",
            "message" => "You have " . $pendingRow['pending_count'] . " task(s) in progress",
            "time" => "Now",
            "type" => "reminder",
            "isRead" => false
        ];
    }

    // 3. Recently Completed Tasks
    $completedResult = $conn->query("
        SELECT i.appliance, i.updated_at, u.full_name as customer_name
        FROM issues i
        LEFT JOIN users u ON i.reported_by = u.id
        WHERE i.assigned_technician_id = $technician_id
        AND (i.status = 'Resolved' OR i.status = 'Closed')
        ORDER BY i.updated_at DESC 
        LIMIT 3
    ");

    while ($row = $completedResult->fetch_assoc()) {
        $notifications[] = [
            "id" => $id++,
            "title" => "Task Completed",
            "message" => "You completed the " . $row['appliance'] . " service for " . ($row['customer_name'] ?? 'Customer'),
            "time" => timeAgo($row['updated_at']),
            "type" => "completed",
            "isRead" => true
        ];
    }

} else {
    // ========== USER NOTIFICATIONS ==========

    // Build user filter clause
    $user_filter = $user_id > 0 ? "AND i.reported_by = $user_id" : "";

    // 1. Technician Assigned notifications
    $assignedResult = $conn->query("
        SELECT i.appliance, t.name as tech_name, i.updated_at 
        FROM issues i
        JOIN technicians t ON i.assigned_technician_id = t.id
        WHERE i.status = 'In Progress' 
        AND i.assigned_technician_id IS NOT NULL
        $user_filter
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

    // 2. Service Scheduled notifications
    $scheduledResult = $conn->query("
        SELECT i.appliance, i.updated_at 
        FROM issues i
        WHERE i.status = 'In Progress' 
        $user_filter
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

    // 3. Warranty Expiring Soon notifications (global - doesn't depend on user)
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
            "time" => $row['days_left'] == 1 ? "1 day left" : $row['days_left'] . " days left",
            "type" => "warranty",
            "isRead" => false
        ];
    }

    // 4. Service Completed notifications
    $completedResult = $conn->query("
        SELECT i.appliance, i.updated_at 
        FROM issues i
        WHERE (i.status = 'Resolved' OR i.status = 'Closed')
        $user_filter
        ORDER BY i.updated_at DESC 
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
}

// Sort by read status (unread first)
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