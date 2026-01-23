<?php
// report_issue.php - Report Issue API (matches Android's report_issue.php endpoint)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST method required"]);
    exit;
}

// DB connection
$conn = new mysqli("localhost", "root", "", "warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Read multipart form data
$appliance = trim($_POST['appliance'] ?? '');
$description = trim($_POST['description'] ?? '');
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

// Allowed appliance types
$allowed = ['Air Conditioner', 'Television', 'Fan'];

// Normalize appliance name
$appliance_lower = strtolower(str_replace(['_', '-'], ' ', $appliance));
$appliance_map = [
    'ac' => 'Air Conditioner',
    'air conditioner' => 'Air Conditioner',
    'tv' => 'Television',
    'television' => 'Television',
    'fan' => 'Fan'
];
$canonical_appliance = $appliance_map[$appliance_lower] ?? $appliance;

$errors = [];

// Validation
if (!in_array($canonical_appliance, $allowed, true)) {
    $errors[] = "Invalid appliance selected. Allowed: Air Conditioner, Television, Fan";
}

if ($description === '') {
    $errors[] = "Description is required";
}

if (!empty($errors)) {
    echo json_encode(["status" => "error", "errors" => $errors]);
    exit;
}

// Image Upload (optional)
$imagePath = null;
if (!empty($_FILES['image']['name'])) {
    $allowedTypes = ['image/jpeg', 'image/png'];

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) {
        echo json_encode(["status" => "error", "message" => "Only JPG or PNG images allowed"]);
        exit;
    }

    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "Image max size is 5MB"]);
        exit;
    }

    $uploadDir = __DIR__ . "/uploads/issues/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = $mime === 'image/png' ? 'png' : 'jpg';
    $filename = time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
    $target = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $imagePath = "uploads/issues/" . $filename;
    }
}

// Insert issue
$stmt = $conn->prepare(
    "INSERT INTO issues (appliance, issue_description, reported_by, status, created_at) 
     VALUES (?, ?, ?, 'Pending', NOW())"
);
$stmt->bind_param("ssi", $canonical_appliance, $description, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Issue reported successfully",
        "issue_id" => $stmt->insert_id
    ]);
} else {
    // Cleanup uploaded file on failure
    if ($imagePath && file_exists(__DIR__ . "/" . $imagePath)) {
        @unlink(__DIR__ . "/" . $imagePath);
    }
    echo json_encode([
        "status" => "error",
        "message" => "Failed to submit issue"
    ]);
}

$conn->close();
?>