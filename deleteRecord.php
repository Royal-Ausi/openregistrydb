<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
include 'includes/db.php';

if (!isset($_SESSION["username"]) && !openregistry_is_system_operator()) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized access"]);
    exit();
}

$username = $_SESSION["username"] ?? 'system_operator';

if (!openregistry_is_admin() && !openregistry_is_system_operator()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Only administrators can delete records"]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$recordId = $data['record_id'] ?? '';
$table = $data['table'] ?? '';

// Validate input
if (empty($recordId) || empty($table)) {
    echo json_encode(["success" => false, "error" => "Missing required parameters"]);
    exit();
}

// Define allowed tables
$allowedTables = [
    "master_register",
    "pensioners_register",
    "contractstaff_register",
    "semicurrent_records",
    "file_diary",
    "subject_index"
];

if (!in_array($table, $allowedTables)) {
    echo json_encode(["success" => false, "error" => "Invalid table"]);
    exit();
}

// Validate record ID is numeric
if (!is_numeric($recordId)) {
    echo json_encode(["success" => false, "error" => "Invalid record ID"]);
    exit();
}

$pkColMap = [
    'master_register'        => 'id',
    'pensioners_register'    => 'id',
    'contractstaff_register' => 'serial_number',
    'semicurrent_records'    => 'serial_number',
    'file_diary'             => 'serial_number',
    'subject_index'          => 'id',
];
$pkCol = $pkColMap[$table] ?? 'id';

try {
    // Check if record exists
    $checkSql = "SELECT * FROM $table WHERE {$pkCol} = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception("Failed to prepare check statement: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $recordId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(["success" => false, "error" => "Record not found"]);
        exit();
    }
    
    $record = $result->fetch_assoc();
    $checkStmt->close();
    
    // Delete the record
    $deleteSql = "DELETE FROM $table WHERE {$pkCol} = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if (!$deleteStmt) {
        throw new Exception("Failed to prepare delete statement: " . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $recordId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Failed to delete record: " . $deleteStmt->error);
    }
    
    $deleteStmt->close();
    
    // Log the deletion (if admin_logs table exists)
    $logSql = "INSERT INTO admin_logs (admin_user, action, table_name, record_id, old_value, new_value, timestamp) 
               VALUES (?, 'delete_record', ?, ?, ?, ?, NOW())";
    $logStmt = $conn->prepare($logSql);
    
    if ($logStmt) {
        $oldValue = json_encode($record); // Store the deleted record data
        $newValue = "DELETED";
        $logStmt->bind_param("ssiss", $username, $table, $recordId, $oldValue, $newValue);
        $logStmt->execute();
        $logStmt->close();
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Record deleted successfully",
        "record_id" => $recordId,
        "table" => $table
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

