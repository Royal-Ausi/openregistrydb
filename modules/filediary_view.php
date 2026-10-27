<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION["username"])) {
    header("Location: ../../userlogin.php");
    exit();
}

$username = $_SESSION["username"];

// Get active records from all registers for file diary view
$allActiveRecords = [];

// Define all register tables and their key fields
$registerTables = [
    'file_diary' => ['id', 'file_ref_number', 'address_contact', 'file_title', 'file_name', 'date_opened', 'security_grading', 'created_by', 'timestamp'],
    'master_register' => ['id', 'file_number', 'full_name', 'designation', 'date_opened', 'date_closed', 'status', 'created_by', 'timestamp'],
    'pensioners_register' => ['id', 'file_number', 'full_name', 'designation', 'date_opened', 'date_closed', 'status', 'created_by', 'timestamp'],
    'contractstaff_register' => ['id', 'file_number', 'name_of_officer', 'title', 'status', 'created_by', 'timestamp'],
    'semicurrent_records' => ['id', 'file_reference_number', 'file_title', 'date_opened', 'date_closed', 'retention_period', 'action_category', 'action_date', 'location_no', 'temp_box_no', 'box_no', 'status', 'created_by', 'timestamp']
];

function tableHasColumn($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Query each table for active records
foreach ($registerTables as $tableName => $fields) {
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

// Sort all records by timestamp (most recent first)
usort($allActiveRecords, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

$totalRecords = count($allActiveRecords);
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
        /* Base styling with navbar color scheme */
        body {
            background-color: #FCF1E3;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Navbar color scheme variables */
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
        
        /* Spreadsheet-like table styling */
        .spreadsheet-table {
            background: white;
            border: 2px solid var(--table-border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 14px;
        }

        .spreadsheet-table thead th {
            background: linear-gradient(135deg, var(--table-header), #ee883e);
            color: var(--table-header-text);
            font-weight: 700;
            padding: 12px 10px;
            border: 1px solid #e9d8c1;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .spreadsheet-table tbody td {
            padding: 10px;
            border: 1px solid #faf7f4;
            vertical-align: middle;
            background-color: white;
            transition: background-color 0.2s ease;
            min-height: 42px;
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

        .spreadsheet-table th:nth-child(1), .spreadsheet-table td:nth-child(1) {
            width: 58px; min-width: 58px; max-width: 58px;
        }
        .spreadsheet-table th:nth-child(2), .spreadsheet-table td:nth-child(2) {
            width: 140px; min-width: 140px;
        }
        .spreadsheet-table th:nth-child(3), .spreadsheet-table td:nth-child(3) {
            width: 280px; min-width: 280px;
        }
        .spreadsheet-table th:nth-child(4), .spreadsheet-table td:nth-child(4) {
            width: 140px; min-width: 140px;
        }
        .spreadsheet-table th:nth-child(5), .spreadsheet-table td:nth-child(5) {
            width: 160px; min-width: 160px;
        }
        .spreadsheet-table th:nth-child(6), .spreadsheet-table td:nth-child(6) {
            width: 150px; min-width: 150px;
        }
        .spreadsheet-table th:nth-child(7), .spreadsheet-table td:nth-child(7) {
            width: 140px; min-width: 140px;
        }
        .spreadsheet-table th:nth-child(8), .spreadsheet-table td:nth-child(8) {
            width: 150px; min-width: 150px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
 File Diary - Active Records
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../../user_dashboard.php">
 Dashboard
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
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3> File Diary - Active Records from All Registers</h3>
                        <p class="text-muted">Viewing all records with "Active" status from all registers (File Diary, Master Register, Pension Register, Contract Staff, Semi Current Records).</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="../../user_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <button class="btn btn-primary" onclick="exportActiveRecords()">
 Export Active Records
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
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

        <!-- Search Section -->
        <div class="card search-section mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="searchInput" class="form-label"> Search Active Records</label>
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Search by file reference, title, or security grading...">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-light w-100" onclick="searchRecords()">
 Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Records Table -->
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
                                    <th>Created By</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allActiveRecords as $row): ?>
                                    <tr class="status-active">
                                        <td><?= $row['id'] ?? 'N/A' ?></td>
                                        <td>
                                            <strong>
                                                <?php 
                                                // Get reference number based on source table
                                                $refField = '';
                                                switch ($row['source_table']) {
                                                    case 'file_diary':
                                                        $refField = $row['file_ref_number'] ?? 'N/A';
                                                        break;
                                                    case 'master_register':
                                                    case 'pensioners_register':
                                                    case 'contractstaff_register':
                                                        $refField = $row['file_number'] ?? 'N/A';
                                                        break;
                                                    case 'semicurrent_records':
                                                        $refField = $row['file_reference_number'] ?? 'N/A';
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
                                            // Get title/subject based on source table
                                            $titleField = '';
                                            switch ($row['source_table']) {
                                                case 'file_diary':
                                                    $titleField = $row['file_title'] ?? 'N/A';
                                                    if (!empty($row['file_name'])) {
                                                        $titleField = ($row['file_name']) . ' — ' . $titleField;
                                                    }
                                                    break;
                                                case 'master_register':
                                                case 'pensioners_register':
                                                    $titleField = $row['full_name'] ?? 'N/A';
                                                    break;
                                                case 'contractstaff_register':
                                                    $titleField = $row['name_of_officer'] ?? 'N/A';
                                                    break;
                                                case 'semicurrent_records':
                                                    $titleField = $row['file_title'] ?? 'N/A';
                                                    break;
                                                default:
                                                    $titleField = 'N/A';
                                            }
                                            echo htmlspecialchars($titleField);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Get date based on source table
                                            $dateField = '';
                                            switch ($row['source_table']) {
                                                case 'file_diary':
                                                case 'master_register':
                                                case 'pensioners_register':
                                                case 'semicurrent_records':
                                                    $dateField = $row['date_opened'] ?? '';
                                                    break;
                                                case 'contractstaff_register':
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
                                                // Get register type name
                                                switch ($row['source_table']) {
                                                    case 'file_diary': echo 'File Diary'; break;
                                                    case 'master_register': echo 'Master Register'; break;
                                                    case 'pensioners_register': echo 'Pension Register'; break;
                                                    case 'contractstaff_register': echo 'Contract Staff'; break;
                                                    case 'semicurrent_records': echo 'Semi Current'; break;
                                                    default: echo $row['source_table'];
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?= htmlspecialchars($row['status'] ?? 'Active') ?>
                                            </span>
                                        </td>
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

        <!-- Information Section -->
        <!-- <div class="row mt-4">
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
                                    <li>Active records from File Diary</li>
                                    <li>Active records from Master Register</li>
                                    <li>Active records from Pension Register</li>
                                    <li>Active records from Contract Staff Register</li>
                                    <li>Active records from Semi Current Records</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6> What's Not Included:</h6>
                                <ul>
                                    <li>Records with "Closed" status</li>
                                    <li>Records with "Transferred" status</li>
                                    <li>Records with "Archived" status</li>
                                    <li>Records with "Completed" status</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> This view consolidates all active records from all registers into one unified view. 
                            Records are sorted by timestamp (most recent first) and each record shows its source register type. 
                            When a record's status changes from "Active" to another status, it will be removed from this view.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

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
            
            // Update the record count badge
            const badge = document.querySelector('.badge.bg-light');
            if (badge) {
                badge.textContent = visibleCount + ' records';
            }
            
            // Show search results message
            if (searchTerm) {
                showAlert(`Found ${visibleCount} active records matching "${searchTerm}"`, 'info');
            } else {
                // Reset to show all records
                rows.forEach(row => {
                    row.style.display = '';
                });
                const badge = document.querySelector('.badge.bg-light');
                if (badge) {
                    badge.textContent = '<?= $totalRecords ?> records';
                }
            }
        }
        
        function exportActiveRecords() {
            // Create form data for export - we'll export all active records from all tables
            const formData = new FormData();
            formData.append('action', 'export_all_active');
            formData.append('format', 'csv');
            formData.append('tables', JSON.stringify(['file_diary', 'master_register', 'pensioners_register', 'contractstaff_register', 'semicurrent_records']));
            formData.append('status_filter', 'Active');
            
            // Show loading state
            const exportBtn = document.querySelector('button[onclick="exportActiveRecords()"]');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Send export request
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
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `all_active_records_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showAlert('All active records exported successfully!', 'success');
            })
            .catch(error => {
                console.error('Export error:', error);
                showAlert('Export failed. Please try again.', 'danger');
            })
            .finally(() => {
                // Restore button state
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



