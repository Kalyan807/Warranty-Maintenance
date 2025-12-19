<?php
// view_issues.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method. Use GET."]);
    exit;
}

$host="localhost"; $user="root"; $pass=""; $db="warrantymaintenance";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

// Optional query params: id, status, assigned, limit, offset, search
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$assigned = isset($_GET['assigned']) ? intval($_GET['assigned']) : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : null;
$limit = isset($_GET['limit']) ? max(1,intval($_GET['limit'])) : 50;
$offset = isset($_GET['offset']) ? max(0,intval($_GET['offset'])) : 0;

if ($id !== null) {
    $stmt = $conn->prepare("SELECT id, appliance, model_number, reported_by, issue_description, status, assigned_technician_id, supervisor_comment, created_at, updated_at FROM issues WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(["status"=>"error","message"=>"Issue not found"]);
        exit;
    }
    $issue = $res->fetch_assoc();
    echo json_encode(["status"=>"success","issue"=>$issue]);
    exit;
}

// Build dynamic WHERE clause
$where = [];
$params = [];
$types = "";

if ($status !== null && $status !== '') {
    $where[] = "status = ?";
    $types .= "s";
    $params[] = $status;
}
if ($assigned !== null && $assigned > 0) {
    $where[] = "assigned_technician_id = ?";
    $types .= "i";
    $params[] = $assigned;
}
if ($search !== null && $search !== '') {
    // search in appliance, model_number, and description
    $where[] = "(appliance LIKE ? OR model_number LIKE ? OR issue_description LIKE ?)";
    $types .= "sss";
    $like = "%".$search."%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$where_sql = "";
if (!empty($where)) $where_sql = "WHERE " . implode(" AND ", $where);

// total count
$count_sql = "SELECT COUNT(*) AS cnt FROM issues $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types !== "") {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_res = $count_stmt->get_result()->fetch_assoc();
$total = intval($count_res['cnt']);

// fetch rows
$sql = "SELECT id, appliance, model_number, reported_by, issue_description, status, assigned_technician_id, supervisor_comment, created_at, updated_at
        FROM issues $where_sql
        ORDER BY updated_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($types === "") {
    $stmt->bind_param("ii", $limit, $offset);
} else {
    // bind dynamic + limit/offset
    $types_with_limits = $types . "ii";
    $params_with_limits = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($types_with_limits, ...$params_with_limits);
}
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    "status"=>"success",
    "total"=>$total,
    "count"=>count($items),
    "issues"=>$items
]);
exit;
?>
