<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) && empty($_SESSION['system_operator'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

include 'includes/db.php';

$data   = json_decode(file_get_contents("php://input"), true);
$table  = $data['table']  ?? '';
$sort   = $data['sort']   ?? '';
$search = $data['search'] ?? '';
$limit  = intval($data['limit'] ?? 50);

$hiddenForUsers = ['created_by', 'timestamp'];
$isAdmin = openregistry_is_admin() || openregistry_is_system_operator();

$allowedTables = [
    "master_register",
    "pensioners_register",
    "contractstaff_register",
    "semicurrent_records",
    "file_diary",
    "subject_index"
];

if (!in_array($table, $allowedTables)) {
    echo json_encode(['success' => false, 'error' => 'Invalid table selected.']);
    exit();
}

// ── Real column schema (from SHOW COLUMNS) ───────────────────────────────────
// master_register:        id, file_number, full_name, designation, date_opened, date_closed, status_id, created_by, timestamp
// contractstaff_register: serial_number, file_number, name_of_officer, title, status_id, created_by, timestamp
// subject_index:          id, previous_file_title, previous_index, current_file_title, current_index, volume_number, status_id, created_by, timestamp
// pensioners_register:    id, file_number, full_name, designation, date_opened, date_closed, created_by, timestamp
// semicurrent_records:    serial_number, file_reference_number, file_title, date_opened, date_closed, retention_period, action_category, action_date, location_no, temp_box_no, box_no, created_by, timestamp
// file_diary:             serial_number, file_ref_number, address_contact, file_title, file_name, date_opened, security_grading, created_by, timestamp

// tables that store status as status_id 
$tablesWithStatusId = ['master_register', 'pensioners_register', 'contractstaff_register', 'subject_index'];
$hasStatusId = in_array($table, $tablesWithStatusId);

// PK column per table 
$pkCol = [
    'master_register'        => 'id',
    'pensioners_register'    => 'id',
    'contractstaff_register' => 'serial_number',
    'semicurrent_records'    => 'serial_number',
    'file_diary'             => 'serial_number',
    'subject_index'          => 'id',
][$table] ?? 'id';

// build SELECT 
if ($hasStatusId) {
    // JOIN status_keywords; expose keyword as "status" column; never select t.status (dropped)
    $sql = "SELECT t.*, sk.keyword AS status
            FROM `$table` t
            LEFT JOIN status_keywords sk ON sk.id = t.status_id";
} else {
    $sql = "SELECT * FROM `$table` t";
}

// search 
$params = [];
$whereConditions = [];

if ($search !== '') {
    switch ($table) {
        case 'master_register':
            $whereConditions[] = "(t.full_name LIKE ? OR t.file_number LIKE ? OR t.designation LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
            break;
        case 'pensioners_register':
            $whereConditions[] = "(t.full_name LIKE ? OR t.file_number LIKE ? OR t.designation LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
            break;
        case 'contractstaff_register':
            $whereConditions[] = "(t.name_of_officer LIKE ? OR t.file_number LIKE ? OR t.title LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
            break;
        case 'semicurrent_records':
            $whereConditions[] = "(t.file_reference_number LIKE ? OR t.file_title LIKE ?)";
            $params = ["%$search%", "%$search%"];
            break;
        case 'file_diary':
            $whereConditions[] = "(t.file_ref_number LIKE ? OR t.file_title LIKE ? OR t.file_name LIKE ? OR t.address_contact LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
            break;
        case 'subject_index':
            $whereConditions[] = "(t.previous_file_title LIKE ? OR t.current_file_title LIKE ? OR t.previous_index LIKE ? OR t.current_index LIKE ? OR t.volume_number LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
            break;
    }
}

if (!empty($whereConditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
}

//  Sort 
// map sort keys safe ORDER BY expressions using only columns that exist per table
$nameSortCol = [
    'master_register'        => 't.full_name',
    'pensioners_register'    => 't.full_name',
    'contractstaff_register' => 't.name_of_officer',
    'semicurrent_records'    => 't.file_title',
    'file_diary'             => 't.file_title',
    'subject_index'          => 't.current_file_title',
][$table] ?? "t.$pkCol";

$fileNoSortCol = [
    'master_register'        => 't.file_number',
    'pensioners_register'    => 't.file_number',
    'contractstaff_register' => 't.file_number',
    'semicurrent_records'    => 't.file_reference_number',
    'file_diary'             => 't.file_ref_number',
    'subject_index'          => 't.current_index',
][$table] ?? "t.$pkCol";

$statusSortExpr = $hasStatusId ? 'sk.keyword' : "t.$pkCol";

$sortExprMap = [
    'name_asc'         => "$nameSortCol ASC",
    'name_desc'        => "$nameSortCol DESC",
    'file_number_asc'  => "$fileNoSortCol ASC",
    'date_desc'        => 't.timestamp DESC',
    'date_asc'         => 't.timestamp ASC',
    'status_asc'       => "$statusSortExpr ASC",
    'status_desc'      => "$statusSortExpr DESC",
];

if ($sort && isset($sortExprMap[$sort])) {
    $sql .= ' ORDER BY ' . $sortExprMap[$sort];
} else {
    // default is order by primary key ascending
    $sql .= " ORDER BY t.$pkCol ASC";
}

if ($limit > 0) {
    $sql .= " LIMIT $limit";
}

// execute
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error preparing query: ' . $conn->error]);
    $conn->close();
    exit();
}

if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => true, 'data' => [], 'columns' => [], 'totalRecords' => 0]);
    $stmt->close();
    $conn->close();
    exit();
}

$fields  = $result->fetch_fields();
$allCols = array_map(fn($f) => $f->name, $fields);

// filter columns:
// exclude raw status_id (resolved keyword is surfaced as "status")
// hide created_by / timestamp from non-admin viewers
if ($table === 'file_diary') {
    $columns = ['file_ref_number', 'file_title', 'date_opened', 'security_grading'];
} else {
    $columns = array_values(array_filter($allCols, function (string $col) use ($isAdmin, $hiddenForUsers): bool {
        if ($col === 'status_id') return false;
        if (!$isAdmin && in_array($col, $hiddenForUsers)) return false;
        return true;
    }));
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $filtered = [];
    foreach ($columns as $col) {
        $filtered[$col] = ($col === 'status' && ($row[$col] ?? null) === null)
            ? 'N/A'
            : ($row[$col] ?? '');
    }
    $rows[] = $filtered;
}

echo json_encode([
    'success'      => true,
    'data'         => $rows,
    'columns'      => $columns,
    'totalRecords' => count($rows),
    'isAdmin'      => $isAdmin,
]);

$stmt->close();
$conn->close();
