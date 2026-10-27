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

// Map the row data to database columns for pensioners_register table
$file_number = $rowData[0] ?? '';
$full_name = $rowData[1] ?? '';
$designation = $rowData[2] ?? '';
$date_opened = $rowData[3] ?? '';
$date_closed = $rowData[4] ?? '';

// Validate required fields
if (empty($file_number) || empty($full_name) || empty($designation) || empty($date_opened)) {
    echo json_encode(['success' => false, 'error' => 'File Number, Full Name, Designation, and Date Opened are required']);
    exit();
}

// Prepare SQL statement
$sql = "INSERT INTO pensioners_register (file_number, full_name, designation, date_opened, date_closed, created_by) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("ssssss", 
    $file_number, 
    $full_name, 
    $designation, 
    $date_opened, 
    $date_closed, 
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
