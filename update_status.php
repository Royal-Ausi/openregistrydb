<?php
session_start();
include 'includes/db.php';

// check for logged in session
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized access"]);
    exit();
}

$username = $_SESSION["username"];
$userRole = $_SESSION["role"] ?? 'user';
$userModules = $_SESSION["assigned_modules"] ?? [];

// get POST data
$data = json_decode(file_get_contents("php://input"), true);
$table = $data['table'] ?? '';
$recordId = $data['record_id'] ?? '';
$newStatus = $data['new_status'] ?? '';

// validate input
if (empty($table) || empty($recordId) || empty($newStatus)) {
    echo json_encode(["success" => false, "error" => "Missing required parameters"]);
    exit();
}

function tableHasColumn($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// define allowed tables
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

if (!tableHasColumn($conn, $table, 'status')) {
    echo json_encode(["success" => false, "error" => "The table '$table' does not have a status column. Please update the schema before changing status."]);
    exit();
}

// check if user has permission to update admin or assigned to table
if ($userRole !== 'admin') {
    // check if user is assigned to this table or the filtered views
    $allowedForFilteredViews = ['file_diary_filtered', 'semi_current_filtered'];
    $hasPermission = in_array($table, $userModules) || 
                    in_array('file_diary_filtered', $userModules) || 
                    in_array('semi_current_filtered', $userModules);
    
    if (!$hasPermission) {
        echo json_encode(["success" => false, "error" => "You don't have permission to update this table"]);
        exit();
    }
}

// define valid statuses for each table
$validStatuses = [
    "master_register" => ["Active", "Semi-active", "Inactive", "Transferred", "Closed", "Archived"],
    "contractstaff_register" => ["Active", "Semi-active", "Inactive", "Transferred", "Closed", "Archived"],
    "pensioners_register" => ["Active", "Semi-active", "Transferred", "Deceased"],
    "semicurrent_records" => ["Active", "Semi-active", "Transferred", "Archived"],
    "file_diary" => ["Active", "Semi-active", "Transferred", "Closed"],
    "subject_index" => ["Active", "Semi-active", "Inactive", "Transferred", "Closed", "Archived"]
];

if (!in_array($newStatus, $validStatuses[$table])) {
    echo json_encode(["success" => false, "error" => "Invalid status for this table"]);
    exit();
}

try {
    // update the status
    $sql = "UPDATE $table SET status = ?, timestamp = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("si", $newStatus, $recordId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    if ($stmt->affected_rows > 0) {
        // log the status change 
        $logSql = "INSERT INTO admin_logs (admin_user, action, table_name, record_id, old_value, new_value, timestamp) 
                   VALUES (?, 'status_update', ?, ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        
        if ($logStmt) {
            $oldStatus = ""; 
            $logStmt->bind_param("sssss", $username, $table, $recordId, $oldStatus, $newStatus);
            $logStmt->execute();
            $logStmt->close();
        }
        
        // determine which view the record will now appear in
        $viewInfo = "";
        if ($newStatus === 'Active') {
            $viewInfo = "This record will now appear in the File Diary (Active Records) view.";
        } elseif (in_array($newStatus, ['Semi-active', 'Inactive'])) {
            $viewInfo = "This record will now appear in the Semi-Current Records view.";
        } else {
            $viewInfo = "This record will no longer appear in the filtered views.";
        }
        
        echo json_encode([
            "success" => true, 
            "message" => "Status updated successfully. " . $viewInfo,
            "new_status" => $newStatus,
            "affected_rows" => $stmt->affected_rows,
            "view_info" => $viewInfo
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "error" => "No records were updated. Record may not exist."
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "error" => "Database error: " . $e->getMessage()
    ]);
}

$conn->close();
?>



