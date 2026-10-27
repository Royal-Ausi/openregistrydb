<?php
session_start();
include '../includes/db.php';
require_once __DIR__ . '/../includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);
openregistry_ensure_register_columns($conn);

// check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['row'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$rowData = $input['row'];
$username = $_SESSION["username"];

$file_number = $rowData[0] ?? '';
$name_of_officer = $rowData[1] ?? '';
$title = $rowData[2] ?? '';
$date_opened = $rowData[3] ?? '';
$status = $rowData[4] ?? '';

// validate required fields
if (empty($file_number) || empty($name_of_officer) || empty($title)) {
    echo json_encode(['success' => false, 'error' => 'File Number, Name of Officer, and Title are required']);
    exit();
}

if ($date_opened !== '' && $date_opened !== null) {
    $date_opened = trim($date_opened);
}

$status_id = null;
if ($status !== '') {
    $statusStmt = $conn->prepare('SELECT id FROM status_keywords WHERE LOWER(keyword) = ? LIMIT 1');
    if ($statusStmt) {
        $statusKey = strtolower(trim($status));
        $statusStmt->bind_param('s', $statusKey);
        $statusStmt->execute();
        $statusRow = $statusStmt->get_result()->fetch_assoc();
        $statusStmt->close();
        if ($statusRow) {
            $status_id = (int)$statusRow['id'];
        }
    }
}

// prepare SQL statement
$sql = "INSERT INTO contractstaff_register (file_number, name_of_officer, title, date_opened, status_id, created_by) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

// bind parameters
$stmt->bind_param("ssssis", 
    $file_number, 
    $name_of_officer, 
    $title, 
    $date_opened, 
    $status_id, 
    $username
);

// execute the statement
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record saved successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save record: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

