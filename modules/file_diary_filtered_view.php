<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION["username"])) {
    header("Location: ../../userlogin.php");
    exit();
}

$username = $_SESSION["username"];
$userRole = $_SESSION["role"] ?? 'user';
$userModules = $_SESSION["assigned_modules"] ?? [];

// Get Active records from filtered registers for file diary view
$allActiveRecords = [];

// define source register tables and their key fields
$sourceTables = [
    'master_register' => [
        'fields' => ['id', 'file_number', 'full_name', 'designation', 'date_opened', 'status', 'created_by', 'timestamp'],
        'title_field' => 'full_name',
        'ref_field' => 'file_number',
        'date_field' => 'date_opened'
    ],
    'contractstaff_register' => [
        'fields' => ['id', 'file_number', 'name_of_officer', 'title', 'status', 'created_by', 'timestamp'],
        'title_field' => 'name_of_officer',
        'ref_field' => 'file_number',
        'date_field' => 'timestamp'
    ],
    'subject_index' => [
        'fields' => ['id', 'previous_file_title', 'previous_index', 'current_file_title', 'current_index', 'volume_number', 'status', 'created_by', 'timestamp'],
        'title_field' => 'current_file_title',
        'ref_field' => 'current_index',
        'date_field' => 'timestamp'
    ]
];

function tableHasColumn($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// query each source table for Active records
foreach ($sourceTables as $tableName => $config) {
    if (tableHasColumn($conn, $tableName, 'status')) {
        $sql = "SELECT *, '$tableName' as source_table FROM $tableName WHERE status = 'Active' ORDER BY timestamp DESC";
    } else {
        $sql = "SELECT *, '$tableName' as source_table FROM $tableName ORDER BY timestamp DESC";
    }
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $allActiveRecords[] = $row;
        }
    }
}

// also get existing file_diary records with Active status
if (tableHasColumn($conn, 'file_diary', 'status')) {
    $sql = "SELECT *, 'file_diary' as source_table FROM file_diary WHERE status = 'Active' ORDER BY timestamp DESC";
} else {
    $sql = "SELECT *, 'file_diary' as source_table FROM file_diary ORDER BY timestamp DESC";
}
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $allActiveRecords[] = $row;
    }
}

// sort all records by timestamp (most recent first)
usort($allActiveRecords, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

$totalRecords = count($allActiveRecords);

// check if user can edit records based on their role and assigned modules
$sourceModules = ['master_register', 'contractstaff_register', 'subject_index', 'file_diary'];
$canEdit = ($userRole === 'admin') || !empty(array_intersect($sourceModules, $userModules));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Diary - Active Records - Open Registry</title>
    <link rel="icon" type="image/jpeg" href="../../includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        
        body {
            background-color: #FCF1E3;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        :root {
            --navbar-primary: #E08D3C;
            --navbar-secondary: #FCF1E3;
            --navbar-dark: #c73e24;
            --table-header: #E08D3C;
            --table-header-text: #FCF1E3;
            --table-border: #ddd;
            --table-hover: #f8f9fa;
        }
        
        .navbar-brand {
            color: var(--navbar-primary) !important;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .spreadsheet-table {
            background: white;
            border: 2px solid var(--table-border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 14px;
        }

        .spreadsheet-table thead th {
            background-color: var(--table-header);
            color: var(--table-header-text);
            font-weight: 600;
            padding: 12px 8px;
            border: 1px solid var(--table-border);
            text-align: center;
            font-size: 13px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .spreadsheet-table tbody td {
            padding: 8px;
            border: 1px solid var(--table-border);
            vertical-align: middle;
            background-color: white;
            transition: background-color 0.2s ease;
        }

        .spreadsheet-table tbody tr:hover td {
            background-color: var(--table-hover);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--navbar-primary), var(--navbar-dark));
            color: var(--navbar-secondary);
            border: none;
            box-shadow: 0 2px 8px rgba(232, 73, 42, 0.3);
        }
        
        .btn-primary {
            background-color: var(--navbar-primary) !important;
            border-color: var(--navbar-primary) !important;
            color: var(--navbar-secondary) !important;
        }
        
        .btn-primary:hover {
            background-color: var(--navbar-dark) !important;
            border-color: var(--navbar-dark) !important;
        }
        
        .search-section {
            background: linear-gradient(135deg, var(--navbar-primary), var(--navbar-dark));
            color: var(--navbar-secondary);
            box-shadow: 0 2px 8px rgba(232, 73, 42, 0.3);
        }
        
        .no-records {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-records i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* status badge styling */
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }

        /* edit button styling */
        .btn-edit {
            padding: 2px 6px;
            font-size: 0.8rem;
        }

        /* column width optimization */
        .spreadsheet-table th:nth-child(1), /* ID column */
        .spreadsheet-table td:nth-child(1) {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .spreadsheet-table th:nth-child(2), /* Reference/Number */
        .spreadsheet-table td:nth-child(2) {
            width: 120px;
            min-width: 120px;
        }

        .spreadsheet-table th:nth-child(3), /* Title/Subject */
        .spreadsheet-table td:nth-child(3) {
            width: 200px;
            min-width: 200px;
        }

        .spreadsheet-table th:nth-child(4), /* Date */
        .spreadsheet-table td:nth-child(4) {
            width: 120px;
            min-width: 120px;
        }

        .spreadsheet-table th:nth-child(5), /* Register Type */
        .spreadsheet-table td:nth-child(5) {
            width: 120px;
            min-width: 120px;
        }

        .spreadsheet-table th:nth-child(6), /* Status */
        .spreadsheet-table td:nth-child(6) {
            width: 100px;
            min-width: 100px;
        }

        .spreadsheet-table th:nth-child(7), /* Actions */
        .spreadsheet-table td:nth-child(7) {
            width: 80px;
            min-width: 80px;
        }

        .spreadsheet-table th:nth-child(8), /* Created By */
        .spreadsheet-table td:nth-child(8) {
            width: 120px;
            min-width: 120px;
        }

        .spreadsheet-table th:nth-child(9), /* Last Updated */
        .spreadsheet-table td:nth-child(9) {
            width: 140px;
            min-width: 140px;
        }
    </style>
</head>
<body>
    <!-- navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
 File Diary - Active Records
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../../user_dashboard.php">
 Dashboard
                </a>
                <a class="nav-link" href="semi_current_filtered_view.php">
 Semi-Current Records
                </a>
                <a class="nav-link" href="../../viewDatabase.php">
 View Database
                </a>
                <a class="nav-link" href="../../userlogin.php">
 Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3> File Diary - Active Records</h3>
                        <p class="text-muted">Viewing all records with "Active" status from Master Register, Contract Staff Register, Subject Index Register, and File Diary.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="../../user_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <?php if ($canEdit): ?>
                        <a href="../../edit_register.php?table=file_diary&mode=new&rows=5" class="btn btn-success">
 Add New File Diary Entry
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-primary" onclick="exportActiveRecords()">
 Export Active Records
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Active Records</h6>
                        <h4><?= number_format($totalRecords) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Current User</h6>
                        <h6><?= htmlspecialchars($username) ?></h6>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Last Updated</h6>
                        <h6><?= date('H:i') ?></h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- search section -->
        <div class="card search-section mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="searchInput" class="form-label"> Search Active Records</label>
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Search by file reference, title, or other details...">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-light w-100" onclick="searchRecords()">
 Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- records table -->
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    Active File Diary Records
                    <span class="badge bg-light text-dark ms-2"><?= $totalRecords ?> records</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if ($totalRecords > 0): ?>
                    <div class="table-responsive">
                        <table class="table spreadsheet-table mb-0" id="recordsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reference/Number</th>
                                    <th>Title/Subject</th>
                                    <th>Date</th>
                                    <th>Register Type</th>
                                    <th>Status</th>
                                    <?php if ($canEdit): ?>
                                    <th>Actions</th>
                                    <?php endif; ?>
                                    <th>Created By</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allActiveRecords as $row): ?>
                                    <tr class="status-active" data-record-id="<?= $row['id'] ?>" data-source-table="<?= $row['source_table'] ?>">
                                        <td><?= $row['id'] ?? 'N/A' ?></td>
                                        <td>
                                            <strong>
                                                <?php 
                                                // get reference number based on source table
                                                $refField = '';
                                                switch ($row['source_table']) {
                                                    case 'file_diary':
                                                        $refField = $row['file_ref_number'] ?? 'N/A';
                                                        break;
                                                    case 'master_register':
                                                    case 'contractstaff_register':
                                                        $refField = $row['file_number'] ?? 'N/A';
                                                        break;
                                                    case 'subject_index':
                                                        $refField = $row['current_index'] ?? 'N/A';
                                                        break;
                                                    default:
                                                        $refField = 'N/A';
                                                }
                                                echo htmlspecialchars($refField);
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            // get title/subject based on source table
                                            $titleField = '';
                                            switch ($row['source_table']) {
                                                case 'file_diary':
                                                    $titleField = $row['file_title'] ?? 'N/A';
                                                    if (!empty($row['file_name'])) {
                                                        $titleField = ($row['file_name']) . ' — ' . $titleField;
                                                    }
                                                    break;
                                                case 'master_register':
                                                    $titleField = $row['full_name'] ?? 'N/A';
                                                    break;
                                                case 'contractstaff_register':
                                                    $titleField = $row['name_of_officer'] ?? 'N/A';
                                                    break;
                                                case 'subject_index':
                                                    $titleField = $row['current_file_title'] ?? 'N/A';
                                                    break;
                                                default:
                                                    $titleField = 'N/A';
                                            }
                                            echo htmlspecialchars($titleField);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // get date based on source table
                                            $dateField = '';
                                            switch ($row['source_table']) {
                                                case 'file_diary':
                                                case 'master_register':
                                                    $dateField = $row['date_opened'] ?? '';
                                                    break;
                                                case 'contractstaff_register':
                                                case 'subject_index':
                                                    $dateField = $row['timestamp'] ?? '';
                                                    break;
                                                default:
                                                    $dateField = '';
                                            }
                                            
                                            if ($dateField) {
                                                echo date('d/m/Y', strtotime($dateField));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php 
                                                // get register type name
                                                switch ($row['source_table']) {
                                                    case 'file_diary': echo 'File Diary'; break;
                                                    case 'master_register': echo 'Master Register'; break;
                                                    case 'contractstaff_register': echo 'Contract Staff'; break;
                                                    case 'subject_index_register': echo 'Subject Index'; break;
                                                    default: echo $row['source_table'];
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success status-badge">
                                                <?= htmlspecialchars($row['status'] ?? 'Active') ?>
                                            </span>
                                        </td>
                                        <?php if ($canEdit): ?>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-edit" 
                                                    onclick="editRecord('<?= $row['source_table'] ?>', <?= $row['id'] ?>)"
                                                    title="Edit Record">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning btn-edit" 
                                                    onclick="changeStatus('<?= $row['source_table'] ?>', <?= $row['id'] ?>, '<?= $row['status'] ?>')"
                                                    title="Change Status">
                                                <i class="fas fa-flag"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($row['created_by'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php 
                                            if ($row['timestamp']) {
                                                echo date('d/m/Y H:i', strtotime($row['timestamp']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-records">
                        <h4>No Active Records Found</h4>
                        <p class="text-muted">There are currently no active records in the file diary.</p>
                        <p class="text-muted">Records with "Active" status will appear here automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- information section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"> File Diary Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6> What's Included:</h6>
                                <ul>
                                    <li>Active records from Master Register</li>
                                    <li>Active records from Contract Staff Register</li>
                                    <li>Active records from Subject Index Register</li>
                                    <li>Active records from File Diary</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6> What's Not Included:</h6>
                                <ul>
                                    <li>Records with "Semi-active" status</li>
                                    <li>Records with "Inactive" status</li>
                                    <li>Records with "Transferred" status</li>
                                    <li>Records with "Closed" status</li>
                                    <li>Records with "Archived" status</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> This view consolidates all active records from the main source registers into one unified view. 
                            Records are sorted by timestamp (most recent first) and each record shows its source register type. 
                            When a record's status changes from "Active" to another status, it will be moved to the appropriate register view.
                            <br><br>
                            <strong>Permissions:</strong> All users can view this filtered data. Editing is limited to users assigned to the source registers (Master Register, Contract Staff Register, Subject Index Register, or File Diary).
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function searchRecords() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('recordsTable');
            const rows = table.querySelectorAll('tbody tr');
            
            let visibleCount = 0;
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // update the record count badge
            const badge = document.querySelector('.badge.bg-light');
            if (badge) {
                badge.textContent = visibleCount + ' records';
            }
            
            // show search results message
            if (searchTerm) {
                showAlert(`Found ${visibleCount} active records matching "${searchTerm}"`, 'info');
            } else {
                // reset to show all records
                rows.forEach(row => {
                    row.style.display = '';
                });
                const badge = document.querySelector('.badge.bg-light');
                if (badge) {
                    badge.textContent = '<?= $totalRecords ?> records';
                }
            }
        }
        
        function editRecord(sourceTable, recordId) {
            // redirect to edit page for the specific table and record
            window.location.href = `../../edit_register.php?table=${sourceTable}&mode=edit&id=${recordId}`;
        }
        
        function changeStatus(sourceTable, recordId, currentStatus) {
            const newStatus = prompt(`Change status for record ID ${recordId}:\nCurrent status: ${currentStatus}\n\nEnter new status (Active, Semi-active, Inactive, Transferred, Closed, Archived):`);
            
            if (newStatus && newStatus !== currentStatus) {
                // send AJAX request to update status
                fetch('../../update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        table: sourceTable,
                        record_id: recordId,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(`Status updated successfully to "${newStatus}"`, 'success');
                        // reload page to reflect changes
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(`Error: ${data.error}`, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Failed to update status. Please try again.', 'danger');
                });
            }
        }
        
        function exportActiveRecords() {
            // create form data for export
            const formData = new FormData();
            formData.append('action', 'export_filtered_active');
            formData.append('format', 'csv');
            formData.append('tables', JSON.stringify(['master_register', 'contractstaff_register', 'subject_index', 'file_diary']));
            formData.append('status_filter', 'Active');
            
            // show loading state
            const exportBtn = document.querySelector('button[onclick="exportActiveRecords()"]');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
            
            // send export request
            fetch('../../exportData.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('Export failed');
            })
            .then(blob => {
                // create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `file_diary_active_records_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showAlert('Active records exported successfully!', 'success');
            })
            .catch(error => {
                console.error('Export error:', error);
                showAlert('Export failed. Please try again.', 'danger');
            })
            .finally(() => {
                // restore button state
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            });
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
        
        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchRecords();
            }
        });
        
        // Auto-refresh every 5 minutes to show latest active records
        setInterval(() => {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>
