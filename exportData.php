<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
include 'includes/db.php';

if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo "Unauthorized access";
    exit();
}

if (!openregistry_can_export()) {
    http_response_code(403);
    echo "Export is not permitted for this account";
    exit();
}

if (($_POST['action'] ?? '') !== 'export') {
    http_response_code(400);
    echo "Invalid request";
    exit();
}

$table        = $_POST['table']         ?? '';
$limit        = $_POST['limit']         ?? 'all';
$statusFilter = $_POST['status_filter'] ?? '';

$allowedTables = [
    "master_register",
    "pensioners_register",
    "contractstaff_register",
    "semicurrent_records",
    "file_diary",
    "subject_index"
];

// tables whose status is stored ONLY as status_id 
$tablesWithStatusId = ['master_register', 'contractstaff_register', 'subject_index'];

if (!in_array($table, $allowedTables)) {
    http_response_code(400);
    echo "Invalid table selected";
    exit();
}

$hasStatusId = in_array($table, $tablesWithStatusId);

try {
    // Build SELECT
    // For status_id tables: JOIN status_keywords; expose keyword as "status"; never reference t.status (dropped)
    if ($hasStatusId) {
        $sql = "SELECT t.*, sk.keyword AS status FROM `$table` t LEFT JOIN status_keywords sk ON sk.id = t.status_id";
    } else {
        $sql = "SELECT * FROM `$table` t";
    }

    $whereConditions = [];
    $params = [];

    if (!empty($statusFilter)) {
        if ($hasStatusId) {
            $whereConditions[] = "sk.keyword = ?";
            $params[] = $statusFilter;
        }
        // Tables without status (pensioners, semicurrent, file_diary) — filter ignored gracefully
    }

    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }

    if ($limit !== 'all') {
        $limitInt = intval($limit);
        if ($limitInt > 0) {
            $sql .= " LIMIT $limitInt";
        }
    }

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Database query failed: " . $conn->error);
        }
    }

    exportToCSV($result, $table, $hasStatusId);

} catch (Exception $e) {
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
}

$conn->close();

function exportToCSV($result, string $table, bool $hasStatusId): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    $fields = $result->fetch_fields();

    // Exclude raw status_id — the JOIN already surfaces keyword as "status"
    $exportFields = array_values(array_filter($fields, fn($f) => $f->name !== 'status_id'));

    // Headers
    fputcsv($output, array_map(fn($f) => ucwords(str_replace("_", " ", $f->name)), $exportFields));

    // Rows
    while ($row = $result->fetch_assoc()) {
        $csvRow = [];
        foreach ($exportFields as $field) {
            $value = (string)($row[$field->name] ?? '');
            if (strpos($field->name, 'date') !== false && $value !== '') {
                $ts = strtotime($value);
                if ($ts !== false) $value = date('d/m/Y', $ts);
            }
            $csvRow[] = $value;
        }
        fputcsv($output, $csvRow);
    }

    fclose($output);
}
