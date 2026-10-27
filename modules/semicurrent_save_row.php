<?php
session_start();
include '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['row'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$rowData = $input['row'];
$username = $_SESSION["username"];

// Map the row data to database columns for semicurrent_records table
$file_reference_number = $rowData[0] ?? '';
$file_title = $rowData[1] ?? '';
$date_opened = $rowData[2] ?? '';
$date_closed = $rowData[3] ?? '';
$retention_period = $rowData[4] ?? '';
$action_category = $rowData[5] ?? '';
$action_date = $rowData[6] ?? '';
$location_no = $rowData[7] ?? '';
$temp_box_no = $rowData[8] ?? '';
$box_no = $rowData[9] ?? '';

// Validate required fields
if (empty($file_reference_number) || empty($file_title) || empty($date_opened) || empty($action_date) || empty($box_no)) {
    echo json_encode(['success' => false, 'error' => 'File Reference Number, File Title, Date Opened, Action Date, and Box No are required']);
    exit();
}

// Prepare SQL statement
$sql = "INSERT INTO semicurrent_records (file_reference_number, file_title, date_opened, date_closed, retention_period, action_category, action_date, location_no, temp_box_no, box_no, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("sssssssssss", 
    $file_reference_number, 
    $file_title, 
    $date_opened, 
    $date_closed, 
    $retention_period, 
    $action_category, 
    $action_date, 
    $location_no, 
    $temp_box_no, 
    $box_no, 
    $username
);

// Execute the statement
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record saved successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save record: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
