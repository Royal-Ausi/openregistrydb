<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_session_start();
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);
openregistry_ensure_register_columns($conn);

if (!openregistry_can_import()) {
    http_response_code(403);
    echo "Access denied.";
    exit();
}

// Helper: resolve status text → status_id for tables that use the dynamic system
function importResolveStatusId(PgConnection $conn, string $statusText): ?int {
    $statusText = trim($statusText);
    if ($statusText === '') return null;
    $stmt = $conn->prepare("SELECT id FROM status_keywords WHERE LOWER(keyword) = LOWER(?) LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $statusText);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function normalizeImportDateValue($value): ?string {
    if ($value === null) return null;
    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
}

$username = $_SESSION['username'] ?? 'admin';
$placeholder = 'NOT FILLED';

// Configuration for supported registers
$registerConfigs = [
    'master_register' => [
        'label' => 'Master Register',
        'columns' => [
            'file_number'     => 'File Number',
            'full_name'       => 'Full Name',
            'designation'     => 'Designation',
            'date_opened'     => 'Date Opened',
            'date_closed'     => 'Date Closed',
            'address_contact' => 'Address / Contact',
            'status'          => 'Status',
        ],
        'required' => ['file_number', 'full_name', 'designation'],
    ],
    'pensioners_register' => [
        'label' => 'Pensioners Register',
        'columns' => [
            'file_number'     => 'File Number',
            'full_name'       => 'Full Name',
            'designation'     => 'Designation',
            'date_opened'     => 'Date Opened',
            'date_closed'     => 'Date Closed',
            'address_contact' => 'Address / Contact',
            'status'          => 'Status',
        ],
        'required' => ['file_number', 'full_name', 'designation'],
    ],
    'semicurrent_records' => [
        'label' => 'Semi-Current Records',
        'columns' => [
            'file_reference_number' => 'File Reference Number',
            'file_title'            => 'File Title',
            'date_opened'           => 'Date Opened',
            'date_closed'           => 'Date Closed',
            'retention_period'      => 'Retention Period',
            'action_category'       => 'Action Category',
            'action_date'           => 'Action Date',
            'location_no'           => 'Location No',
            'temp_box_no'           => 'Temp Box No',
            'box_no'                => 'Box No',
        ],
        // Required for import validation; other columns optional (placeholders applied for missing required cells only).
        'required' => ['file_reference_number', 'file_title', 'action_category'],
    ],
    'file_diary' => [
        'label' => 'File Diary',
        'columns' => [
            'file_ref_number'  => 'File Ref Number',
            'file_title'       => 'File Title',
            'date_opened'      => 'Date Opened',
            'security_grading' => 'Security Grading',
        ],
        'required' => ['file_ref_number', 'file_title', 'security_grading'],
    ],
    'contractstaff_register' => [
        'label' => 'Contract Staff Register',
        'columns' => [
            'file_number'      => 'File Number',
            'name_of_officer'  => 'Name of Officer',
            'title'            => 'Title',
            'date_opened'      => 'Date Opened',
            'address_contact'  => 'Address / Contact',
            'status'           => 'Status',
        ],
        'required' => ['file_number', 'name_of_officer', 'title'],
    ],
    'subject_index' => [
        'label' => 'Subject Index',
        'columns' => [
            'previous_file_title' => 'Previous File Title',
            'previous_index'      => 'Previous Index',
            'current_file_title'  => 'Current File Title',
            'current_index'       => 'Current Index',
            'volume_number'       => 'Volume Number',
            'date_opened'         => 'Date Opened',
            'status'              => 'Status',
        ],
        'required' => ['current_file_title', 'current_index'],
    ],
];

$errors = [];
$summary = null;

function normalizeHeader($header) {
    $header = trim($header);
    $header = strtolower($header);
    $header = str_replace(['/', '-', '.'], ' ', $header);
    $header = preg_replace('/[^a-z0-9\s]/', ' ', $header);
    $header = preg_replace('/\s+/', ' ', $header);
    return trim($header);
}

function normalizeKeyStyle($header) {
    return str_replace(' ', '_', normalizeHeader($header));
}

function buildColumnAliases(array $configColumns) {
    $aliasMap = [];

    $commonAliases = [
        'file_number' => ['file no', 'file no_', 'file num', 'file #', 'fileno', 'f/no', 'f no'],
        'full_name' => ['name', 'names', 'officer name', 'employee name', 'staff name'],
        'designation' => ['title', 'position', 'rank'],
        'date_opened' => ['opening date', 'open date', 'date of opening'],
        'date_closed' => ['closing date', 'close date', 'date of closure'],
        'status' => ['state', 'record status'],
        'file_ref_number' => ['file reference', 'file reference number', 'reference number', 'ref number', 'file ref no'],
        'address_contact' => ['address', 'contact', 'address/contact', 'contact details', 'email', 'phone', 'telephone'],
        'file_title' => ['title', 'subject', 'document title'],
        'file_name' => ['file name', 'filename', 'document name', 'name of file'],
        'name_of_officer' => ['officer name', 'staff name', 'employee name', 'name'],
        'previous_file_title' => ['old file title', 'previous title'],
        'previous_index' => ['old index', 'previous ref', 'previous reference'],
        'current_file_title' => ['new file title', 'current title'],
        'current_index' => ['new index', 'current ref', 'current reference'],
        'volume_number' => ['volume', 'vol number', 'vol no'],
        'security_grading' => ['security', 'grading', 'security level'],
        'file_reference_number' => ['file ref', 'file ref number', 'file reference', 'reference number', 'ref number', 'file ref no', 'fileref'],
        'retention_period' => ['retention', 'retention period', 'retention years'],
        'action_category' => ['category', 'action', 'action type', 'disposition', 'record category'],
        'action_date' => ['actioned date', 'date of action', 'action day'],
        'location_no' => ['location', 'location number', 'loc no'],
        'temp_box_no' => ['temp box', 'temporary box', 'temp box number'],
        'box_no' => ['box', 'box number', 'box no', 'main box'],
    ];

    foreach ($configColumns as $dbColumn => $label) {
        $aliases = [];
        $aliases[] = normalizeHeader($dbColumn);
        $aliases[] = normalizeHeader($label);
        $aliases[] = str_replace('_', ' ', normalizeHeader($dbColumn));

        if (isset($commonAliases[$dbColumn])) {
            foreach ($commonAliases[$dbColumn] as $alias) {
                $aliases[] = normalizeHeader($alias);
            }
        }

        $aliases = array_values(array_unique(array_filter($aliases)));
        $aliasMap[$dbColumn] = $aliases;
    }

    return $aliasMap;
}

function mapUploadedHeaders(array $headerRow, array $configColumns) {
    $aliasMap = buildColumnAliases($configColumns);
    $mappedIndexes = []; // db_column => index in uploaded file

    foreach ($headerRow as $index => $headerText) {
        $uploadedNorm = normalizeHeader($headerText);
        if ($uploadedNorm === '') {
            continue;
        }

        foreach ($aliasMap as $dbColumn => $aliases) {
            if (isset($mappedIndexes[$dbColumn])) {
                continue;
            }

            $matched = in_array($uploadedNorm, $aliases, true);

            // Fallback: relaxed compare without spaces (e.g. "fileno" vs "file no")
            if (!$matched) {
                $uploadedCompact = str_replace(' ', '', $uploadedNorm);
                foreach ($aliases as $alias) {
                    if ($uploadedCompact === str_replace(' ', '', $alias)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if ($matched) {
                $mappedIndexes[$dbColumn] = $index;
                break;
            }
        }
    }

    return $mappedIndexes;
}

function prepareInsertStatement(PgConnection $conn, string $primarySql, ?string $fallbackSql = null) {
    $stmt = $conn->prepare($primarySql);
    if (is_object($stmt) && method_exists($stmt, 'bind_param') && method_exists($stmt, 'execute')) {
        return $stmt;
    }

    if ($fallbackSql !== null) {
        $fallbackStmt = $conn->prepare($fallbackSql);
        if (is_object($fallbackStmt) && method_exists($fallbackStmt, 'bind_param') && method_exists($fallbackStmt, 'execute')) {
            return $fallbackStmt;
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRegister = $_POST['register'] ?? '';

    if (!isset($registerConfigs[$selectedRegister])) {
        $errors[] = 'Invalid register selected.';
    }

    if (!isset($_FILES['data_file']) || $_FILES['data_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV or Excel file.';
    }

    if (!$errors) {
        $config = $registerConfigs[$selectedRegister];
        $tmpPath = $_FILES['data_file']['tmp_name'];
        $origName = $_FILES['data_file']['name'];
        $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $rawRows = [];

        if ($extension === 'csv') {
            if (($handle = fopen($tmpPath, 'r')) === false) {
                $errors[] = 'Unable to open uploaded CSV file.';
            } else {
                // Read header
                $headers = fgetcsv($handle);
                if ($headers === false) {
                    $errors[] = 'CSV file is empty or invalid.';
                } else {
                    $rawRows[] = $headers;
                    while (($data = fgetcsv($handle)) !== false) {
                        // Skip completely empty lines
                        if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                            continue;
                        }
                        $rawRows[] = $data;
                    }
                }
                fclose($handle);
            }
        } else {
            // Try Excel via PhpSpreadsheet if available
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
            if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                $errors[] = 'Excel import requires PhpSpreadsheet. Please upload a CSV file instead.';
            } else {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheetData = $sheet->toArray(null, true, true, false);
                    foreach ($sheetData as $row) {
                        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                            continue;
                        }
                        $rawRows[] = $row;
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Failed to read Excel file: ' . $e->getMessage();
                }
            }
        }

        if (!$errors && $rawRows) {
            // First row is header
            $headerRow = array_shift($rawRows);
            $mappedHeaders = mapUploadedHeaders($headerRow, $config['columns']);

            // Validate required columns present
            $missingRequired = [];
            foreach ($config['required'] as $requiredCol) {
                if (!isset($mappedHeaders[$requiredCol])) {
                    $missingRequired[] = $requiredCol;
                }
            }

            if ($missingRequired) {
                $errors[] = 'The uploaded file is missing required columns: ' . implode(', ', $missingRequired);
            } else {
                $inserted = 0;
                $completeRows = 0;
                $incompleteRows = 0;
                $incompleteDetails = [];

                // NOTE: status varchar is DROPPED from master_register, contractstaff_register, subject_index.
                //       We insert status_id (int) only. The 'status' column in registerConfigs is the
                //       user-visible field label that maps to status_id after keyword lookup.
                if ($selectedRegister === 'master_register') {
                    $supportedInsert = true;
                } elseif ($selectedRegister === 'pensioners_register') {
                    $supportedInsert = true;
                } elseif ($selectedRegister === 'semicurrent_records') {
                    $supportedInsert = true;
                } elseif ($selectedRegister === 'file_diary') {
                    $supportedInsert = true;
                } elseif ($selectedRegister === 'contractstaff_register') {
                    $supportedInsert = true;
                } elseif ($selectedRegister === 'subject_index') {
                    $supportedInsert = true;
                } else {
                    $supportedInsert = false;
                    $errors[] = 'Unsupported register selected.';
                }

                if (!$supportedInsert) {
                    $errors[] = 'Database error: ' . $conn->error;
                } else {
                    foreach ($rawRows as $rowIndex => $dataRow) {
                        $rowAssoc = [];
                        foreach ($config['columns'] as $dbColumn => $label) {
                            if (isset($mappedHeaders[$dbColumn])) {
                                $colIndex = $mappedHeaders[$dbColumn];
                                $rowAssoc[$dbColumn] = isset($dataRow[$colIndex]) ? trim((string)$dataRow[$colIndex]) : '';
                            } else {
                                $rowAssoc[$dbColumn] = '';
                            }
                        }

                        // File diary dates are optional; unfilled security defaults to OPEN.
                        if ($selectedRegister === 'file_diary' && trim((string)($rowAssoc['security_grading'] ?? '')) === '') {
                            $rowAssoc['security_grading'] = 'OPEN';
                        }

                        $rowMissingRequired = [];
                        foreach ($config['required'] as $requiredCol) {
                            if (!isset($rowAssoc[$requiredCol]) || $rowAssoc[$requiredCol] === '') {
                                $rowMissingRequired[] = $requiredCol;
                                $rowAssoc[$requiredCol] = $placeholder;
                            }
                        }

                        $hadMissing = !empty($rowMissingRequired);

                        // Prepare a fresh statement for the current row so bound values are always current.
                        $created_by = $username;
                        $status_id  = 0; // resolved below for tables with dynamic status
                        $rowStmt = null;
                        if ($selectedRegister === 'master_register') {
                            $file_number = $rowAssoc['file_number'] ?? $placeholder;
                            $full_name = $rowAssoc['full_name'] ?? $placeholder;
                            $designation = $rowAssoc['designation'] ?? $placeholder;
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $date_closed = normalizeImportDateValue($rowAssoc['date_closed'] ?? '');
                            $address_contact = $rowAssoc['address_contact'] ?? '';
                            $status = $rowAssoc['status'] ?? '';
                            $status_id = importResolveStatusId($conn, $status);
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO master_register (file_number, full_name, designation, date_opened, date_closed, address_contact, status_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                $rowStmt->bind_param("ssssssis", $file_number, $full_name, $designation, $date_opened, $date_closed, $address_contact, $status_id, $created_by);
                            }
                        } elseif ($selectedRegister === 'pensioners_register') {
                            $file_number = $rowAssoc['file_number'] ?? $placeholder;
                            $full_name = $rowAssoc['full_name'] ?? $placeholder;
                            $designation = $rowAssoc['designation'] ?? $placeholder;
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $date_closed = normalizeImportDateValue($rowAssoc['date_closed'] ?? '');
                            $address_contact = $rowAssoc['address_contact'] ?? '';
                            $status = $rowAssoc['status'] ?? '';
                            $status_id = importResolveStatusId($conn, $status);
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO pensioners_register (file_number, full_name, designation, date_opened, date_closed, address_contact, status_id, created_by)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                                "INSERT INTO pensioners_register (file_number, full_name, designation, date_opened, date_closed, address_contact, status_id)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                if ($rowStmt->param_count === 8) {
                                    $rowStmt->bind_param("ssssssis", $file_number, $full_name, $designation, $date_opened, $date_closed, $address_contact, $status_id, $created_by);
                                } else {
                                    $rowStmt->bind_param("sssssss", $file_number, $full_name, $designation, $date_opened, $date_closed, $address_contact, $status_id);
                                }
                            }
                        } elseif ($selectedRegister === 'semicurrent_records') {
                            $file_reference_number = $rowAssoc['file_reference_number'] ?? $placeholder;
                            $file_title = $rowAssoc['file_title'] ?? $placeholder;
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $date_closed = normalizeImportDateValue($rowAssoc['date_closed'] ?? '');
                            $retention_period = $rowAssoc['retention_period'] ?? '';
                            $action_category = $rowAssoc['action_category'] ?? $placeholder;
                            $action_date = $rowAssoc['action_date'] ?? '';
                            $location_no = $rowAssoc['location_no'] ?? '';
                            $temp_box_no = $rowAssoc['temp_box_no'] ?? '';
                            $box_no = $rowAssoc['box_no'] ?? '';
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO semicurrent_records (file_reference_number, file_title, date_opened, date_closed, retention_period, action_category, action_date, location_no, temp_box_no, box_no, created_by)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                "INSERT INTO semicurrent_records (file_reference_number, file_title, date_opened, date_closed, retention_period, action_category, action_date, location_no, temp_box_no, box_no)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                if ($rowStmt->param_count === 11) {
                                    $rowStmt->bind_param(
                                        "sssssssssss",
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
                                        $created_by
                                    );
                                } else {
                                    $rowStmt->bind_param(
                                        "ssssssssss",
                                        $file_reference_number,
                                        $file_title,
                                        $date_opened,
                                        $date_closed,
                                        $retention_period,
                                        $action_category,
                                        $action_date,
                                        $location_no,
                                        $temp_box_no,
                                        $box_no
                                    );
                                }
                            }
                        } elseif ($selectedRegister === 'file_diary') {
                            $file_ref_number = $rowAssoc['file_ref_number'] ?? $placeholder;
                            $file_title = $rowAssoc['file_title'] ?? $placeholder;
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $security_grading = trim((string)($rowAssoc['security_grading'] ?? '')) ?: 'OPEN';
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO file_diary (file_ref_number, file_title, date_opened, security_grading, created_by)
                                 VALUES (?, ?, ?, ?, ?)",
                                "INSERT INTO file_diary (file_ref_number, file_title, date_opened, security_grading)
                                 VALUES (?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                if ($rowStmt->param_count === 5) {
                                    $rowStmt->bind_param("sssss", $file_ref_number, $file_title, $date_opened, $security_grading, $created_by);
                                } else {
                                    $rowStmt->bind_param("ssss", $file_ref_number, $file_title, $date_opened, $security_grading);
                                }
                            }
                        } elseif ($selectedRegister === 'contractstaff_register') {
                            $file_number = $rowAssoc['file_number'] ?? $placeholder;
                            $name_of_officer = $rowAssoc['name_of_officer'] ?? $placeholder;
                            $title = $rowAssoc['title'] ?? $placeholder;
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $address_contact = $rowAssoc['address_contact'] ?? '';
                            $status = $rowAssoc['status'] ?? '';
                            $status_id = importResolveStatusId($conn, $status);
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO contractstaff_register (file_number, name_of_officer, title, date_opened, address_contact, status_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                $rowStmt->bind_param("sssssis", $file_number, $name_of_officer, $title, $date_opened, $address_contact, $status_id, $created_by);
                            }
                        } elseif ($selectedRegister === 'subject_index') {
                            $previous_file_title = $rowAssoc['previous_file_title'] ?? '';
                            $previous_index = $rowAssoc['previous_index'] ?? '';
                            $current_file_title = $rowAssoc['current_file_title'] ?? $placeholder;
                            $current_index = $rowAssoc['current_index'] ?? $placeholder;
                            $volume_number = $rowAssoc['volume_number'] ?? '';
                            $date_opened = normalizeImportDateValue($rowAssoc['date_opened'] ?? '');
                            $status = $rowAssoc['status'] ?? '';
                            $status_id = importResolveStatusId($conn, $status);
                            $rowStmt = prepareInsertStatement(
                                $conn,
                                "INSERT INTO subject_index (previous_file_title, previous_index, current_file_title, current_index, volume_number, date_opened, status_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            if (is_object($rowStmt) && method_exists($rowStmt, 'bind_param')) {
                                $rowStmt->bind_param("ssssssis", $previous_file_title, $previous_index, $current_file_title, $current_index, $volume_number, $date_opened, $status_id, $created_by);
                            }
                        }

                        if (!$rowStmt) {
                            $incompleteRows++;
                            $incompleteDetails[] = [
                                'row_number' => $rowIndex + 2,
                                'missing'    => $rowMissingRequired ?: ['Database error: failed to prepare row statement'],
                            ];
                            continue;
                        }

                        if ($rowStmt->execute()) {
                            $inserted++;
                            if ($hadMissing) {
                                $incompleteRows++;
                                $incompleteDetails[] = [
                                    'row_number' => $rowIndex + 2, // +1 for header, +1 for 1-based
                                    'missing'    => $rowMissingRequired,
                                ];
                            } else {
                                $completeRows++;
                            }
                        } else {
                            $incompleteRows++;
                            $incompleteDetails[] = [
                                'row_number' => $rowIndex + 2,
                                'missing'    => $rowMissingRequired ?: ['Database error: ' . $rowStmt->error],
                            ];
                        }

                        $rowStmt->close();
                    }

                    $summary = [
                        'register'          => $config['label'],
                        'file_name'         => $origName,
                        'total_rows'        => $inserted,
                        'complete_rows'     => $completeRows,
                        'incomplete_rows'   => $incompleteRows,
                        'incomplete_details'=> $incompleteDetails,
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Data | Open Registry</title>
    <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/app-base.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #E08D3C;
            --primary-text: #FCF1E3;
            --primary-hover: #c97a2d;
            --background-color: #ffffff;
            --text-color: #000000;
        }
        body {
            background-color: #ffffff;
            color: #000000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding-top: 70px;
        }
        .navbar {
            background-color: var(--primary-color);
            color: var(--primary-text);
        }
        .navbar-brand {
            color: var(--primary-text);
            font-weight: bold;
        }
        .container-import {
            max-width: 960px;
        }
        .card-import {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--primary-text);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        .badge-complete {
            background-color: #28a745;
        }
        .badge-incomplete {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Open Registry Admin</a>
        <button class="btn btn-outline-light btn-sm" onclick="history.back()">Back</button>
    </div>
    
</nav>

<div class="container container-import mt-4">
    <div class="card card-import">
        <div class="card-header bg-light">
            <h5 class="mb-0">Import Data into Register</h5>
        </div>
        <div class="card-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="register" class="form-label">Select Register</label>
                    <select id="register" name="register" class="form-select" required>
                        <option value="">Choose a register...</option>
                        <?php foreach ($registerConfigs as $key => $cfg): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= (isset($selectedRegister) && $selectedRegister === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cfg['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Choose a register, then open View Required Fields for the exact columns. Required columns must appear as headers in your file.
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#requiredFieldsModal">
                        View Required Fields
                    </button>
                </div>

                <div class="mb-3">
                    <label for="data_file" class="form-label">Upload CSV File</label>
                    <input type="file" name="data_file" id="data_file" class="form-control" accept=".csv,.xls,.xlsx" required>
                    <div class="form-text">
                        External spreadsheets are supported: column headings can differ slightly; the first row must list fields that map to each register (see modal). Semi-Current requires <strong>File Reference Number</strong>, <strong>File Title</strong>, and <strong>Action Category</strong> at minimum.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Upload Data
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Required Fields Modal -->
<div class="modal fade" id="requiredFieldsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Required Fields</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="requiredFieldsIntro">Select a register to see its required columns.</p>
                <ul id="requiredFieldsList" class="list-group"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if ($summary): ?>
<!-- Summary Modal -->
<div class="modal fade show" id="summaryModal" tabindex="-1" style="display:block; background: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Summary</h5>
                <button type="button" class="btn-close" onclick="closeSummaryModal()"></button>
            </div>
            <div class="modal-body">
                <p><strong>Register:</strong> <?= htmlspecialchars($summary['register']) ?></p>
                <p><strong>File:</strong> <?= htmlspecialchars($summary['file_name']) ?></p>
                <p><strong>Total rows saved:</strong> <?= (int)$summary['total_rows'] ?></p>
                <p>
                    <span class="badge badge-complete">Complete: <?= (int)$summary['complete_rows'] ?></span>
                    <span class="badge badge-incomplete ms-2">With placeholders: <?= (int)$summary['incomplete_rows'] ?></span>
                </p>

                <?php if (!empty($summary['incomplete_details'])): ?>
                    <hr>
                    <h6>Rows with missing fields (saved with placeholders)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Row Number</th>
                                    <th>Missing Required Fields / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary['incomplete_details'] as $detail): ?>
                                <tr>
                                    <td><?= (int)$detail['row_number'] ?></td>
                                    <td><?= htmlspecialchars(implode(', ', $detail['missing'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSummaryModal()">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const registerConfigs = <?= json_encode($registerConfigs) ?>;

function updateRequiredFields() {
    const select = document.getElementById('register');
    const list = document.getElementById('requiredFieldsList');
    const intro = document.getElementById('requiredFieldsIntro');
    list.innerHTML = '';

    const key = select.value;
    if (!key || !registerConfigs[key]) {
        intro.textContent = 'Select a register to see its required columns.';
        return;
    }

    const cfg = registerConfigs[key];
    intro.textContent = 'Columns for ' + cfg.label + ' (required fields are marked):';
    const requiredSet = new Set(cfg.required);
    for (const [dbName, label] of Object.entries(cfg.columns)) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.textContent = label + ' (' + dbName + ')';
        if (requiredSet.has(dbName)) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-danger';
            badge.textContent = 'Required';
            li.appendChild(badge);
        }
        list.appendChild(li);
    }
}

document.getElementById('register').addEventListener('change', updateRequiredFields);

// Initialize when modal opens
document.getElementById('requiredFieldsModal').addEventListener('show.bs.modal', updateRequiredFields);

function closeSummaryModal() {
    const modal = document.getElementById('summaryModal');
    if (modal) {
        modal.parentNode.removeChild(modal);
    }
}
</script>
</body>
</html>

