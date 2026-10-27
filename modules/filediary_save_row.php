<?php
session_start();
include '../includes/db.php';
require_once __DIR__ . '/../includes/schema_extensions.php';
openregistry_ensure_file_diary_extensions($conn);

if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['row'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$rowData = $input['row'];
$username = $_SESSION["username"];

$file_ref_number = $rowData[0] ?? '';
$address_contact = $rowData[1] ?? '';
$file_title = $rowData[2] ?? '';
$file_name = $rowData[3] ?? '';
$date_opened = $rowData[4] ?? '';
$security_grading = $rowData[5] ?? '';

if (empty($file_ref_number) || empty($file_title) || empty($date_opened)) {
    echo json_encode(['success' => false, 'error' => 'File Ref Number, File Title, and Date Opened are required']);
    exit();
}

$sql = "INSERT INTO file_diary (file_ref_number, address_contact, file_title, file_name, date_opened, security_grading, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("sssssss",
    $file_ref_number,
    $address_contact,
    $file_title,
    $file_name,
    $date_opened,
    $security_grading,
    $username
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record saved successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save record: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
