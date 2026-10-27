<?php
session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);
openregistry_ensure_register_columns($conn);

$data       = json_decode(file_get_contents("php://input"), true);
$table      = $data["table"] ?? "";
$rows       = $data["rows"]  ?? [];
$created_by = $_SESSION["username"] ?? ($data["created_by"] ?? '');
$mode       = $data["mode"] ?? "new";

if ($created_by === '') {
    echo json_encode(["success" => false, "errors" => ["Unauthorized access"]]);
    exit();
}

// status_id is the only status column for these three tables.
// The dropdown sends a keyword string; we resolve it to an id here.
$tablesWithStatusId = ['master_register', 'contractstaff_register', 'pensioners_register', 'subject_index'];

// Column definitions — status_id replaces status for dynamic-status tables
$allowedTables = [
    "master_register"        => ["file_number", "full_name", "designation", "date_opened", "date_closed", "address_contact", "status_id"],
    "contractstaff_register" => ["file_number", "name_of_officer", "title", "date_opened", "address_contact", "status_id"],
    "pensioners_register"    => ["file_number", "full_name", "designation", "date_opened", "date_closed", "address_contact", "status_id"],
    "semicurrent_records"    => ["file_reference_number", "file_title", "date_opened", "date_closed", "retention_period",
                                  "action_category", "action_date", "location_no", "temp_box_no", "box_no"],
    "file_diary"             => ["file_ref_number", "file_title", "date_opened", "security_grading"],
    "subject_index"          => ["previous_file_title", "previous_index", "current_file_title", "current_index", "volume_number", "date_opened", "status_id"]
];

$requiredFields = [
    "master_register"        => ["file_number", "full_name", "designation", "status_id"],
    "contractstaff_register" => ["file_number", "name_of_officer", "title"],
    "pensioners_register"    => ["file_number", "full_name", "designation", "status_id"],
    "semicurrent_records"    => ["file_reference_number", "file_title", "date_opened", "action_date", "box_no"],
    "file_diary"             => ["file_ref_number", "file_title", "date_opened"],
    "subject_index"          => ["current_file_title", "current_index", "status_id"]
];

if (!array_key_exists($table, $allowedTables)) {
    echo json_encode(["success" => false, "errors" => ["Invalid table"]]);
    exit();
}

// Translate raw MySQL error into a human-readable message
function friendlyDbError(string $rawError, int $rowNum): string {
    if (preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/i", $rawError, $m)) {
        $value = htmlspecialchars($m[1]);
        $key   = ucwords(str_replace('_', ' ', preg_replace('/^\w+\./', '', $m[2])));
        return "Row $rowNum — A record with \"$value\" already exists. $key must be unique. Please use a different value.";
    }
    if (preg_match('/duplicate key value violates unique constraint/i', $rawError)) {
        return "Row $rowNum — The submitted value conflicts with an existing record. Please change the repeated field and try again.";
    }
    if (preg_match("/Data too long for column '(.+?)'/i", $rawError, $m)) {
        $col = ucwords(str_replace('_', ' ', $m[1]));
        return "Row $rowNum — The value for \"$col\" is too long. Please shorten it.";
    }
    if (preg_match("/Incorrect date(time)? value/i", $rawError)) {
        return "Row $rowNum — One of the date fields has an invalid format. Use YYYY-MM-DD.";
    }
    if (preg_match("/Column '(.+?)' cannot be null/i", $rawError, $m)) {
        $col = ucwords(str_replace('_', ' ', $m[1]));
        return "Row $rowNum — \"$col\" is required and cannot be empty.";
    }
    if (stripos($rawError, 'foreign key') !== false) {
        return "Row $rowNum — A linked record is missing. Please check all related fields.";
    }
    return "Row $rowNum — Could not save this record. Please check the values and try again.";
}

// Resolve keyword text → status_id integer
function resolveStatusId(PgConnection $conn, string $kwText): int {
    $kwText = trim($kwText);
    if ($kwText === '') return 0;

    $stmt = $conn->prepare("SELECT id FROM status_keywords WHERE LOWER(keyword) = ? LIMIT 1");
    if ($stmt) {
        $candidateValue = strtolower($kwText);
        $stmt->bind_param('s', $candidateValue);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int)$row['id'];
        }
    }

    if (preg_match('/^\d+$/', $kwText)) {
        return (int)$kwText;
    }

    return 0;
}

// Optional date columns — empty string must become NULL (PostgreSQL rejects '' for DATE type)
$optionalDateCols = ['date_closed', 'date_opened'];   // date_opened becomes NULL only if truly absent

function sanitiseDateFields(array &$row, array $columns): void {
    foreach ($columns as $idx => $col) {
        if (!isset($row[$idx])) {
            continue;
        }
        $value = trim((string) $row[$idx]);
        if ($value === '') {
            $row[$idx] = null;
            continue;
        }
        if (strpos($col, 'date') !== false) {
            $row[$idx] = $value;
        }
    }
}

$columns          = $allowedTables[$table];
$tableHasStatusId = in_array($table, $tablesWithStatusId);
$statusIdxInCols  = $tableHasStatusId ? array_search('status_id', $columns) : false;
$saved  = 0;
$errors = [];

foreach ($rows as $rowIndex => $rowData) {

    // ── NEW ENTRIES ──────────────────────────────────────────
    if ($mode === "new") {
        $row = $rowData;

        if (count($row) > count($columns)) {
            $row = array_slice($row, 0, count($columns));
        }
        if (count($row) < count($columns)) {
            $errors[] = "Row " . ($rowIndex + 1) . ": Column count mismatch";
            continue;
        }

        // The form sends keyword text for status_id column — resolve to integer
        if ($tableHasStatusId && $statusIdxInCols !== false) {
            $kwText = trim($row[$statusIdxInCols] ?? '');
            if ($kwText === '') {
                $row[$statusIdxInCols] = null;
            } else {
                $resolvedStatusId = resolveStatusId($conn, $kwText);
                $row[$statusIdxInCols] = $resolvedStatusId > 0 ? $resolvedStatusId : null;
            }
        }

        // Provide a safe fallback for unique identifier fields when the form submits blank or repeated values.
        $uniqueFallbacks = [
            'master_register' => 'file_number',
            'contractstaff_register' => 'file_number',
            'pensioners_register' => 'file_number',
            'semicurrent_records' => 'file_reference_number',
            'file_diary' => 'file_ref_number',
            'subject_index' => 'current_file_title',
        ];
        $fallbackCol = $uniqueFallbacks[$table] ?? null;
        if ($fallbackCol !== null) {
            $fallbackIdx = array_search($fallbackCol, $columns);
            if ($fallbackIdx !== false) {
                if ($row[$fallbackIdx] === '' || $row[$fallbackIdx] === null) {
                    $row[$fallbackIdx] = $table . '-AUTO-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
                } else {
                    $existingCheck = $conn->prepare("SELECT 1 FROM {$table} WHERE {$fallbackCol} = ? LIMIT 1");
                    if ($existingCheck) {
                        $candidate = $row[$fallbackIdx];
                        $existingCheck->bind_param('s', $candidate);
                        $existingCheck->execute();
                        $duplicate = $existingCheck->get_result()->num_rows > 0;
                        $existingCheck->close();
                        if ($duplicate) {
                            $row[$fallbackIdx] = $table . '-AUTO-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
                        }
                    }
                }
            }
        }

        // Convert empty date strings → NULL so PostgreSQL DATE columns don't error
        sanitiseDateFields($row, $columns);

        // Validate required fields
        $tableRequiredFields = $requiredFields[$table] ?? [];
        $missingFields = [];
        foreach ($tableRequiredFields as $rf) {
            $fi = array_search($rf, $columns);
            if ($fi === false || ($row[$fi] === '' || $row[$fi] === null || $row[$fi] === 0)) {
                $missingFields[] = $rf === 'status_id' ? 'status' : $rf;
            }
        }
        if (!empty($missingFields)) {
            $label = implode(", ", array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $missingFields));
            $errors[] = "Row " . ($rowIndex + 1) . " — Required fields missing: $label.";
            continue;
        }

        $sequenceMap = [
            'master_register'        => ['table' => 'master_register', 'column' => 'id'],
            'pensioners_register'    => ['table' => 'pensioners_register', 'column' => 'id'],
            'contractstaff_register' => ['table' => 'contractstaff_register', 'column' => 'serial_number'],
            'semicurrent_records'    => ['table' => 'semicurrent_records', 'column' => 'serial_number'],
            'file_diary'             => ['table' => 'file_diary', 'column' => 'serial_number'],
            'subject_index'          => ['table' => 'subject_index', 'column' => 'id'],
        ];
        $seqInfo = $sequenceMap[$table] ?? null;
        $pkColumns = [
            'master_register'        => 'id',
            'pensioners_register'    => 'id',
            'contractstaff_register' => 'serial_number',
            'semicurrent_records'    => 'serial_number',
            'file_diary'             => 'serial_number',
            'subject_index'          => 'id',
        ];
        $pkCol = $pkColumns[$table] ?? null;
        $insertColumns = array_values($columns);
        $insertValues = array_values($row);

        if ($seqInfo) {
            $seqName = $conn->query("SELECT pg_get_serial_sequence('{$seqInfo['table']}', '{$seqInfo['column']}') AS seq")->fetch_assoc()['seq'] ?? null;
            if ($seqName) {
                @$conn->query("SELECT setval('{$seqName}', COALESCE((SELECT MAX({$seqInfo['column']}) FROM {$seqInfo['table']}), 1), true)");
            }
        }

        if ($pkCol) {
            $maxIdResult = $conn->query("SELECT COALESCE(MAX({$pkCol}), 0) AS max_id FROM {$table}");
            $maxId = $maxIdResult instanceof PgResult ? (int)($maxIdResult->fetch_assoc()['max_id'] ?? 0) : 0;
            $nextId = $maxId + 1;
            $insertColumns = array_merge([$pkCol], $insertColumns);
            $insertValues = array_merge([(string) $nextId], $insertValues);
        }

        $placeholders = implode(",", array_fill(0, count($insertColumns) + 1, "?"));
        $colNames     = implode(",", $insertColumns) . ",created_by";
        $sql          = "INSERT INTO $table ($colNames) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $params = array_values($insertValues);
            $params[] = $created_by;
            $bindings = [];
            foreach ($params as $value) {
                $bindings[] = $value === null ? null : (string) $value;
            }
            $types = str_repeat("s", count($bindings));
            $bindParams = array_merge([$types], $bindings);
            call_user_func_array([$stmt, 'bind_param'], makeValuesReferenced($bindParams));
            if ($stmt->execute()) { $saved++; }
            else {
                $errors[] = friendlyDbError($stmt->error, $rowIndex + 1);
            }
            $stmt->close();
        } else {
            $errors[] = friendlyDbError($conn->error, $rowIndex + 1);
        }

    // ── UPDATE RECORDS ───────────────────────────────────────
    } else {
        $rowId = $rowData['id'];
        $row   = $rowData['data'];

        if (count($row) > count($columns)) {
            $row = array_slice($row, 0, count($columns));
        }
        if (count($row) < count($columns)) {
            $errors[] = "Row " . ($rowIndex + 1) . " — Column count mismatch. Please refresh and try again.";
            continue;
        }

        // Resolve keyword text → integer for status_id
        if ($tableHasStatusId && $statusIdxInCols !== false) {
            $kwText = trim($row[$statusIdxInCols] ?? '');
            if ($kwText === '') {
                $row[$statusIdxInCols] = null;
            } else {
                $resolvedStatusId = resolveStatusId($conn, $kwText);
                $row[$statusIdxInCols] = $resolvedStatusId > 0 ? $resolvedStatusId : null;
            }
        }

        // Convert empty date strings → NULL so PostgreSQL DATE columns don't error
        sanitiseDateFields($row, $columns);

        // Validate required fields
        $tableRequiredFields = $requiredFields[$table] ?? [];
        $missingFields = [];
        foreach ($tableRequiredFields as $rf) {
            $fi = array_search($rf, $columns);
            if ($fi === false || ($row[$fi] === '' || $row[$fi] === null || $row[$fi] === 0)) {
                $missingFields[] = $rf === 'status_id' ? 'status' : $rf;
            }
        }
        if (!empty($missingFields)) {
            $label = implode(", ", array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $missingFields));
            $errors[] = "Row " . ($rowIndex + 1) . " — Required fields missing: $label.";
            continue;
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

        $setClause           = implode("=?, ", $columns) . "=?";
        $sqlWithTimestamp    = "UPDATE $table SET $setClause, timestamp = NOW() WHERE {$pkCol} = ?";
        $sqlWithoutTimestamp = "UPDATE $table SET $setClause WHERE {$pkCol} = ?";

        $stmt = $conn->prepare($sqlWithTimestamp);
        if (!$stmt) $stmt = $conn->prepare($sqlWithoutTimestamp);
        if ($stmt) {
            $params = array_values($row);
            $params[] = $rowId;
            $bindings = [];
            foreach ($params as $value) {
                $bindings[] = $value === null ? null : (string) $value;
            }
            $types = str_repeat("s", count($bindings));
            $bindParams = array_merge([$types], $bindings);
            call_user_func_array([$stmt, 'bind_param'], makeValuesReferenced($bindParams));
            if ($stmt->execute()) { $saved++; }
            else { $errors[] = friendlyDbError($stmt->error, $rowIndex + 1); }
            $stmt->close();
        } else {
            $errors[] = friendlyDbError($conn->error, $rowIndex + 1);
        }
    }
}

function makeValuesReferenced(array $arr): array {
    $refs = [];
    foreach ($arr as $key => $value) $refs[$key] = &$arr[$key];
    return $refs;
}

echo json_encode([
    "success"    => $saved > 0,
    "saved"      => $saved,
    "errors"     => $errors,
    "total_rows" => count($rows)
]);
$conn->close();
