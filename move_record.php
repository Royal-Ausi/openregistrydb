<?php

session_start();
require_once __DIR__ . '/includes/auth.php';
include 'includes/db.php';

if (!openregistry_is_admin() && !openregistry_is_system_operator()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorised access']);
    exit();
}

$data             = json_decode(file_get_contents('php://input'), true);
$sourceTable      = $data['source_table']      ?? '';
$destinationTable = $data['destination_table'] ?? '';
$recordId         = $data['record_id']         ?? '';

if ($sourceTable === '' || $destinationTable === '' || $recordId === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$allowedTables = ['master_register', 'pensioners_register', 'contractstaff_register', 'semicurrent_records', 'file_diary'];

if (!in_array($sourceTable, $allowedTables, true) || !in_array($destinationTable, $allowedTables, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid table']);
    exit();
}
if ($sourceTable === $destinationTable) {
    echo json_encode(['success' => false, 'error' => 'Source and destination cannot be the same']);
    exit();
}

// PK column per table
$pkMap = [
    'master_register'        => 'id',
    'pensioners_register'    => 'id',
    'contractstaff_register' => 'serial_number',
    'semicurrent_records'    => 'serial_number',
    'file_diary'             => 'serial_number',
];

// tables using status_id instead of status varchar
$tablesWithStatusId = ['master_register', 'contractstaff_register', 'subject_index'];

// field definitions per table — status-carrying tables use status_id
$fieldMappings = [
    'master_register' => [
        'file_number', 'full_name', 'designation', 'date_opened', 'date_closed', 'status_id',
    ],
    'pensioners_register' => [
        'file_number', 'full_name', 'designation', 'date_opened', 'date_closed',
    ],
    'contractstaff_register' => [
        'file_number', 'name_of_officer', 'title', 'status_id',
    ],
    'semicurrent_records' => [
        'file_reference_number', 'file_title', 'date_opened', 'date_closed',
        'retention_period', 'action_category', 'action_date',
        'location_no', 'temp_box_no', 'box_no',
    ],
    'file_diary' => [
        'file_ref_number', 'address_contact', 'file_title', 'file_name',
        'date_opened', 'security_grading',
    ],
];

$sourceFields = $fieldMappings[$sourceTable]      ?? [];
$destFields   = $fieldMappings[$destinationTable] ?? [];

if (empty($sourceFields) || empty($destFields)) {
    echo json_encode(['success' => false, 'error' => 'Field mapping not defined for one or both tables']);
    exit();
}

try {
    $conn->begin_transaction();

    // fetch source record
    $sourcePk = $pkMap[$sourceTable] ?? 'id';
    $srcStmt  = $conn->prepare("SELECT * FROM {$sourceTable} WHERE {$sourcePk} = ?");
    if (!$srcStmt) throw new Exception('Prepare source failed: ' . $conn->error);

    $srcStmt->bind_param('s', $recordId);
    $srcStmt->execute();
    $srcResult = $srcStmt->get_result();
    if ($srcResult->num_rows === 0) throw new Exception('Record not found in source table');
    $record = $srcResult->fetch_assoc();
    $srcStmt->close();

    // build shared field list 
    $commonFields = array_intersect($destFields, $sourceFields);
    if (empty($commonFields)) {
        // if no direct overlap, carry whatever destination expects that source has
        $commonFields = array_filter($destFields, fn($f) => array_key_exists($f, $record));
    }
    $commonFields = array_values($commonFields);

    // collect values
    $insertValues = [];
    foreach ($commonFields as $field) {
        $insertValues[] = $record[$field] ?? null;
    }
    $insertValues[] = $_SESSION['username']; // created_by

    $placeholders = implode(', ', array_fill(0, count($commonFields) + 1, '?'));
    $colList      = implode(', ', $commonFields) . ', created_by';
    $insertSql    = "INSERT INTO {$destinationTable} ({$colList}) VALUES ({$placeholders})";

    $insStmt = $conn->prepare($insertSql);
    if (!$insStmt) throw new Exception('Prepare insert failed: ' . $conn->error);

    $types = str_repeat('s', count($insertValues));
    $insStmt->bind_param($types, ...$insertValues);
    if (!$insStmt->execute()) throw new Exception('Insert failed: ' . $insStmt->error);
    $newRecordId = $conn->insert_id;
    $insStmt->close();

    // delete from source
    $delStmt = $conn->prepare("DELETE FROM {$sourceTable} WHERE {$sourcePk} = ?");
    if (!$delStmt) throw new Exception('Prepare delete failed: ' . $conn->error);
    $delStmt->bind_param('s', $recordId);
    if (!$delStmt->execute()) throw new Exception('Delete failed: ' . $delStmt->error);
    $delStmt->close();

    $logSql = 'INSERT INTO admin_logs (admin_user, action, table_name, record_id, old_value, new_value, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())';
    $logStmt = $conn->prepare($logSql);
    if ($logStmt) {
        $admin    = $_SESSION['username'];
        $action   = 'record_move';
        $oldVal   = "{$sourceTable}:{$recordId}";
        $newVal   = "{$destinationTable}:{$newRecordId}";
        $logStmt->bind_param('ssssss', $admin, $action, $sourceTable, $recordId, $oldVal, $newVal);
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success'           => true,
        'message'           => 'Record moved successfully',
        'source_table'      => $sourceTable,
        'destination_table' => $destinationTable,
        'old_record_id'     => $recordId,
        'new_record_id'     => $newRecordId,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Could not move record: ' . $e->getMessage()]);
}

$conn->close();
