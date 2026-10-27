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

// Map the row data to database columns for subject_index table
$previous_file_title = $rowData[0] ?? '';
$previous_index = $rowData[1] ?? '';
$current_file_title = $rowData[2] ?? '';
$current_index = $rowData[3] ?? '';
$volume_number = $rowData[4] ?? '';
$status = $rowData[5] ?? '';

// Validate required fields
if (empty($current_file_title) || empty($current_index) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Current File Title, current index, and Status are required']);
    exit();
}

// Prepare SQL statement
$sql = "INSERT INTO subject_index (previous_file_title, previous_index, current_file_title, current_index, volume_number, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("sssssss", 
    $previous_file_title, 
    $previous_index,
    $current_file_title, 
    $current_index, 
    $volume_number, 
    $status, 
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

