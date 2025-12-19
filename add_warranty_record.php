<?php
// add_warranty_record.php (tolerant/diagnostic version)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method. POST required."]);
    exit;
}

// DB Connection
$host="localhost"; $user="root"; $pass=""; $db="warrantymaintenance";
$conn = new mysqli($host,$user,$pass,$db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

function clean($v){ return trim(htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')); }

// Read POST (multipart/form-data expected)
$raw_appliance = clean($_POST['appliance'] ?? ($_POST['appliance_name'] ?? ''));
$model_number  = clean($_POST['model_number'] ?? ($_POST['model'] ?? ''));
$purchase_date = clean($_POST['purchase_date'] ?? '');
$expiry_date   = clean($_POST['expiry_date'] ?? '');
$frequency     = clean($_POST['maintenance_frequency'] ?? ($_POST['frequency'] ?? ''));
$notes         = clean($_POST['notes'] ?? '');

// Normalize appliance keys (accept many common variants)
$appliance_key = strtolower($raw_appliance);
$appliance_key = str_replace(['_','-','/'], ' ', $appliance_key);
$appliance_key = preg_replace('/\s+/', ' ', trim($appliance_key));

$appliance_map = [
    'ac' => 'Air Conditioner',
    'air conditioner' => 'Air Conditioner',
    'airconditioner' => 'Air Conditioner',
    'air_conditioner' => 'Air Conditioner',
    'air-conditioner' => 'Air Conditioner',
    'aircondition' => 'Air Conditioner',

    'tv' => 'Television',
    'television' => 'Television',
    'tele' => 'Television',
    'tv set' => 'Television',

    'fan' => 'Fan',
    'ceiling fan' => 'Fan',
    'table fan' => 'Fan'
];

$canonical_appliance = $appliance_map[$appliance_key] ?? null;

// Validate/normalize dates: accept YYYY-MM-DD or common formats via strtotime
function parse_date($s) {
    $s = trim($s);
    if ($s === '') return null;
    // First try Y-m-d
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if ($d && $d->format('Y-m-d') === $s) return $d->format('Y-m-d');
    // Try strtotime
    $ts = strtotime($s);
    if ($ts !== false) return date('Y-m-d', $ts);
    return null;
}
$purchase_date_parsed = parse_date($purchase_date);
$expiry_date_parsed   = parse_date($expiry_date);

// Validation
$errors = [];

if ($raw_appliance === '') $errors[] = "Appliance Name is required.";
elseif ($canonical_appliance === null) {
    $errors[] = "Invalid appliance. Allowed: Air Conditioner, Television, Fan.";
}

if ($model_number === '') $errors[] = "Model Number is required.";

if ($purchase_date === '') $errors[] = "Purchase Date is required.";
elseif ($purchase_date_parsed === null) $errors[] = "Purchase Date format invalid. Use YYYY-MM-DD or a valid date.";

if ($expiry_date === '') $errors[] = "Warranty Expiry Date is required.";
elseif ($expiry_date_parsed === null) $errors[] = "Warranty Expiry Date format invalid. Use YYYY-MM-DD or a valid date.";

if ($frequency === '') $errors[] = "Maintenance Frequency is required.";

// File upload handling
$documentPath = null;
if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "Document (Warranty Card / Purchase Bill) is required.";
} else {
    $file = $_FILES['document'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Document upload failed (error code {$file['error']}).";
    } else {
        $maxBytes = 3 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $errors[] = "Document must be ≤ 3 MB.";
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMime = [
            "image/jpeg"=>"jpg",
            "image/png"=>"png",
            "application/pdf"=>"pdf"
        ];
        if (!array_key_exists($mime, $allowedMime)) {
            $errors[] = "Document must be JPG, PNG, or PDF.";
        } else {
            $uploadDir = __DIR__."/uploads/warranty_docs";
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $errors[] = "Failed to create upload directory.";
            } else {
                $ext = $allowedMime[$mime];
                $filename = "doc_".time()."_".bin2hex(random_bytes(6)).".".$ext;
                $destination = $uploadDir."/".$filename;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = "Failed to save uploaded document.";
                } else {
                    $documentPath = "uploads/warranty_docs/".$filename;
                }
            }
        }
    }
}

// If errors, return them and the received values (helps debug client)
if (!empty($errors)) {
    if ($documentPath && file_exists(__DIR__."/".$documentPath)) @unlink(__DIR__."/".$documentPath);
    echo json_encode([
        "status" => "error",
        "errors" => $errors,
        "received" => [
            "raw_appliance" => $raw_appliance,
            "mapped_appliance_key" => $appliance_key,
            "canonical_appliance" => $canonical_appliance,
            "model_number" => $model_number,
            "purchase_date" => $purchase_date,
            "purchase_date_parsed" => $purchase_date_parsed,
            "expiry_date" => $expiry_date,
            "expiry_date_parsed" => $expiry_date_parsed,
            "maintenance_frequency" => $frequency,
            "notes" => $notes
        ]
    ]);
    exit;
}

// Insert into DB
$sql = "INSERT INTO warranty_records
      (appliance, model_number, purchase_date, expiry_date, maintenance_frequency, notes, document_path, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    if ($documentPath && file_exists(__DIR__."/".$documentPath)) @unlink(__DIR__."/".$documentPath);
    echo json_encode(["status"=>"error","message"=>"Database prepare failed: ".$conn->error]);
    exit;
}
$appliance_final = $canonical_appliance;
$stmt->bind_param("sssssss",
    $appliance_final, $model_number, $purchase_date_parsed, $expiry_date_parsed, $frequency, $notes, $documentPath
);
if ($stmt->execute()) {
    echo json_encode(["status"=>"success","message"=>"Warranty record added","record_id"=>$stmt->insert_id]);
} else {
    if ($documentPath && file_exists(__DIR__."/".$documentPath)) @unlink(__DIR__."/".$documentPath);
    echo json_encode(["status"=>"error","message"=>"Database insert failed: ".$stmt->error]);
}
?>
