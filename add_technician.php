<?php
// add_technician.php (improved, more tolerant validation)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method. Use POST."]);
    exit;
}

$host = "localhost"; $user = "root"; $pass = ""; $db = "warrantymaintenance";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

function clean($v) {
    return trim(htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
}

// Read fields (multipart/form-data expected)
$name = clean($_POST['name'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$email = clean($_POST['email'] ?? '');
$experience_raw = trim($_POST['experience'] ?? ''); // may be empty
$specialization_raw = trim($_POST['specialization'] ?? '');
$address = clean($_POST['address'] ?? '');

// Normalize & validate specialization (accept short codes)
$spec_map = [
    'ac' => 'AC Technician',
    'ac technician' => 'AC Technician',
    'ac_technician' => 'AC Technician',
    'tv' => 'TV Technician',
    'tv technician' => 'TV Technician',
    'fan' => 'Fan Technician',
    'fan technician' => 'Fan Technician'
];

$spec_key = strtolower(str_replace(['-','/'], ' ', $specialization_raw));
$spec_key = preg_replace('/\s+/', ' ', trim($spec_key));

$canonical_specialization = $spec_map[$spec_key] ?? null;

$errors = [];

// Validate name
if ($name === '') $errors[] = "Name is required.";

// Phone: allow + and digits, 7-15 digits (strip + for length check)
if ($phone === '') {
    $errors[] = "Phone number is required.";
} else {
    $phone_digits = preg_replace('/\D+/', '', $phone);
    if (!preg_match('/^[0-9]{7,15}$/', $phone_digits)) {
        $errors[] = "Phone must be 7-15 digits (only digits allowed).";
    } else {
        // normalize phone to digits-only for DB (or keep original if you prefer)
        $phone = $phone_digits;
    }
}

// Email
if ($email === '') $errors[] = "Email is required.";
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

// Experience: optional -> default 0, but if provided must be numeric & non-negative
if ($experience_raw === '') {
    $experience = 0;
} else {
    if (!is_numeric($experience_raw) || intval($experience_raw) < 0) {
        $errors[] = "Experience must be a non-negative number.";
    } else {
        $experience = intval($experience_raw);
    }
}

// Specialization
if ($specialization_raw === '') {
    $errors[] = "Specialization is required.";
} elseif ($canonical_specialization === null) {
    $errors[] = "Invalid specialization. Allowed: AC Technician, TV Technician, Fan Technician (or use ac/tv/fan).";
} else {
    $specialization = $canonical_specialization;
}

// Address
if ($address === '') $errors[] = "Address is required.";

// File handling (id_proof)
$idProofPath = null;
if (!isset($_FILES['id_proof']) || $_FILES['id_proof']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "ID proof file is required.";
} else {
    $f = $_FILES['id_proof'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error (code {$f['error']}).";
    } else {
        $maxBytes = 2 * 1024 * 1024;
        if ($f['size'] > $maxBytes) $errors[] = "ID proof must be <= 2 MB.";
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if (!array_key_exists($mime, $allowed_mimes)) {
            $errors[] = "ID proof must be JPG, PNG, or PDF.";
        } else {
            $uploadDir = __DIR__.'/uploads/technician_ids';
            if (!is_dir($uploadDir) && !mkdir($uploadDir,0755,true)) {
                $errors[] = "Failed to create upload directory.";
            } else {
                $ext = $allowed_mimes[$mime];
                $filename = 'id_'.time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
                $destination = $uploadDir.'/'.$filename;
                if (!move_uploaded_file($f['tmp_name'],$destination)) {
                    $errors[] = "Failed to save uploaded file.";
                } else {
                    $idProofPath = 'uploads/technician_ids/'.$filename;
                }
            }
        }
    }
}

// If any errors, return them and echo back received values (useful for debugging)
if (!empty($errors)) {
    // cleanup saved file if any
    if ($idProofPath && file_exists(__DIR__.'/'.$idProofPath)) @unlink(__DIR__.'/'.$idProofPath);
    echo json_encode([
        "status"=>"error",
        "errors"=>$errors,
        "received" => [
            "name"=>$name,
            "phone"=>$phone,
            "email"=>$email,
            "experience_raw"=>$experience_raw,
            "specialization_raw"=>$specialization_raw,
            "normalized_specialization"=>$canonical_specialization,
            "address"=>$address
        ]
    ]);
    exit;
}

// Insert into DB
$sql = "INSERT INTO technicians (name, phone, email, experience_years, specialization, address, id_proof_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    if ($idProofPath && file_exists(__DIR__.'/'.$idProofPath)) @unlink(__DIR__.'/'.$idProofPath);
    echo json_encode(["status"=>"error","message"=>"Database prepare failed: ".$conn->error]);
    exit;
}

$stmt->bind_param("ssissss", $name, $phone, $email, $experience, $specialization, $address, $idProofPath);
$ok = $stmt->execute();

if ($ok) {
    echo json_encode(["status"=>"success","message"=>"Technician saved","technician_id"=>$stmt->insert_id]);
} else {
    if ($idProofPath && file_exists(__DIR__.'/'.$idProofPath)) @unlink(__DIR__.'/'.$idProofPath);
    echo json_encode(["status"=>"error","message"=>"Failed to save technician: ".$stmt->error]);
}
?>

