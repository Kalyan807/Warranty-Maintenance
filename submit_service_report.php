<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"POST method required"]);
    exit;
}

// DB
$conn = new mysqli("localhost","root","","warrantymaintenance");
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"DB connection failed"]);
    exit;
}

// Inputs
$issue_id        = $_POST['issue_id'] ?? '';
$work_done       = trim($_POST['work_done'] ?? '');
$parts_replaced  = trim($_POST['parts_replaced'] ?? 'None');
$service_cost    = $_POST['service_cost'] ?? '';
$notes           = trim($_POST['notes'] ?? '');

$errors = [];

if (!$issue_id) $errors[] = "Issue ID is required";
if ($work_done === '') $errors[] = "Work done is required";
if ($service_cost === '' || !is_numeric($service_cost)) {
    $errors[] = "Valid service cost required";
}

if (!empty($errors)) {
    echo json_encode(["status"=>"error","errors"=>$errors]);
    exit;
}

// Upload directory
$uploadDir = "uploads/service_reports/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Upload helper
function uploadImage($field, $dir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg','image/png'];
    if (!in_array($_FILES[$field]['type'], $allowed)) return null;

    $name = time().'_'.$field.'_'.basename($_FILES[$field]['name']);
    $path = $dir.$name;
    move_uploaded_file($_FILES[$field]['tmp_name'], $path);
    return $path;
}

// Photos
$before_photo = uploadImage('before_photo', $uploadDir);
$after_photo  = uploadImage('after_photo', $uploadDir);

// Additional photos (multiple)
$extraPhotos = [];
if (!empty($_FILES['additional_photos']['name'][0])) {
    foreach ($_FILES['additional_photos']['tmp_name'] as $i => $tmp) {
        if ($_FILES['additional_photos']['error'][$i] === UPLOAD_ERR_OK) {
            $name = time().'_extra_'.$i.'_'.basename($_FILES['additional_photos']['name'][$i]);
            $path = $uploadDir.$name;
            move_uploaded_file($tmp, $path);
            $extraPhotos[] = $path;
        }
    }
}
$extraPhotosJson = json_encode($extraPhotos);

// Insert
$stmt = $conn->prepare("
    INSERT INTO service_reports
    (issue_id, work_done, parts_replaced, service_cost, before_photo, after_photo, additional_photos, notes)
    VALUES (?,?,?,?,?,?,?,?)
");
$stmt->bind_param(
    "issdssss",
    $issue_id,
    $work_done,
    $parts_replaced,
    $service_cost,
    $before_photo,
    $after_photo,
    $extraPhotosJson,
    $notes
);

if ($stmt->execute()) {

    // Update issue status to Resolved
    $conn->query("UPDATE issues SET status='Resolved' WHERE id=$issue_id");

    echo json_encode([
        "status"=>"success",
        "message"=>"Service report submitted successfully"
    ]);
} else {
    echo json_encode([
        "status"=>"error",
        "message"=>"Failed to submit service report"
    ]);
}
