<!-- dashboardAdmin.php -->
<?php
require_once __DIR__ . '/includes/auth.php';
openregistry_require_admin_tools_access();
require_once __DIR__ . '/includes/session_jwt.php';
or_enforce_session();
include 'includes/db.php';
include 'includes/navbar.php';

$allTables = ["master_register", "pensioners_register", "contractstaff_register", "semicurrent_records", "file_diary", "subject_index"];
$contributors = [];
$totalEntries = 0;
$todayEntries = 0;

foreach ($allTables as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $res = $conn->query("SELECT COUNT(*) AS total FROM $table");
        if ($res) $totalEntries += $res->fetch_assoc()["total"];
        
        $resToday = $conn->query("SELECT COUNT(*) AS today FROM $table WHERE DATE(timestamp) = CURDATE()");
        if ($resToday) $todayEntries += $resToday->fetch_assoc()["today"];
        
        $res = $conn->query("SELECT created_by, COUNT(*) AS count FROM $table GROUP BY created_by");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $contributors[$row["created_by"]] = ($contributors[$row["created_by"]] ?? 0) + $row["count"];
            }
        }
    }
}

arsort($contributors);

// Last edit info from most recently updated record across all tables
$lastEditor = "N/A";
$lastEditTime = "N/A";
$lastEditTable = "";
$lastEditTs = 0;
foreach ($allTables as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $r = $conn->query("SELECT created_by, timestamp FROM $table ORDER BY timestamp DESC LIMIT 1");
        if ($r) {
            $row = $r->fetch_assoc();
            if ($row && !empty($row["timestamp"])) {
                $ts = strtotime($row["timestamp"]);
                if ($ts > $lastEditTs) {
                    $lastEditor = $row["created_by"];
                    $lastEditTime = $row["timestamp"];
                    $lastEditTable = $table;
                    $lastEditTs = $ts;
                }
            }
        }
    }
}

// Recent activity - last 5 edits across all tables
$recentActivity = [];
foreach ($allTables as $table) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $r = $conn->query("SELECT created_by, timestamp FROM $table ORDER BY timestamp DESC LIMIT 2");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $recentActivity[] = ['user' => $row['created_by'], 'time' => $row['timestamp'], 'table' => $table];
            }
        }
    }
}
usort($recentActivity, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$recentActivity = array_slice($recentActivity, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Open Registry</title>
    <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/app-base.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Unified Color Scheme - Based on Navbar */
        :root {
            --primary-color: #E08D3C;
            --primary-text: #FCF1E3;
            --primary-hover: #ea9745ff;
            --secondary-color: #FCF1E3;
            --background-color: #f4f5f7;
            --text-color: #000000;
            --border-color: #ddd;
            --hover-color: #f5f5f5;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f5f7;
            color: #1a1a1a;
            padding-top: 56px;
        }
        
        .sidebar {
            width: 250px;
            position: fixed;
            top: 56px; left: 0; bottom: 0;
            background-color: var(--primary-color);
            overflow-y: auto;
            z-index: 1050;
            transform: translateX(-250px);
            transition: transform .28s ease;
            box-shadow: 3px 0 12px rgba(0,0,0,.15);
        }
        
        .sidebar.open {
            transform: translateX(0);
        }

        /* Always visible on large screens */
        @media (min-width: 1024px) {
            .sidebar { transform: translateX(0); }
            .main-content { margin-left: 250px !important; }
            .sidebar-overlay { display: none !important; }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 56px 0 0 0;
            background: rgba(0,0,0,.35);
            z-index: 1040;
        }
        .sidebar-overlay.show { display: block; }
        
        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 20px;
            color: #f5f5f5;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid rgba(252, 241, 227, 0.2);
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .sidebar a:hover {
            background-color: var(--primary-hover);
        }
        
        .main-content {
            padding: 24px 28px;
            min-height: calc(100vh - 56px);
            transition: margin-left .28s ease;
        }
        
        .widget {
            background-color: #fff;
            color: #262223;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        
        .widget h5 {
            margin-bottom: 15px;
            font-weight: bold;
            color: #343a40;
        }
        
        .activity-list {
            list-style: none;
            padding-left: 0;
        }
        
        .activity-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .stats-card {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .export-section {
            background-color: none;
            color: white;
        }
        
        .btn-export {
            background-color: #E08D3C;
            border-color: none;
            color: white;
        }
        
        .btn-export:hover {
            background-color: #f88348ff;
            border-color: #f87533ff;
            color: white;
        }
        
        .table-selector {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Button Styles */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--primary-text);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background-color: #F7E5D9;
            color: #262223;
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .modal-header .btn-close:hover {
            filter: invert(1) brightness(0.8);
        }
        
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        @media (max-width: 767px) {
            .main-content { padding: 14px; }
        }
    </style>
</head>
<body>
    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar Drawer -->
    <div class="sidebar" id="sidebar">
        <a href="admin_dashboard.php"><i class="#"></i> Dashboard</a>
        <a href="create_user.php"><i class="#"></i> Create User</a>
        <a href="#" onclick="showViewDatabaseModal(); return false;"><i class="#"></i> View Database</a>
        <a href="#" onclick="showEditDatabaseModal(); return false;"><i class="#"></i> Edit Database</a>
        <a href="admin_status_manager.php"><i class="#"></i> Status Manager</a>
        <a href="admin_status_manager.php?tab=keywords"><i class="#"></i> Keyword List</a>
        <a href="#" onclick="showExportSection(); return false;"><i class="#"></i> Export Data</a>
        <a href="import_data.php"><i class="#"></i> Upload Data</a>
        <a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3> Welcome, Admin</h3>
                <p class="text-muted">Here are the recent activities and Performance overview</p>
            </div>
            
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Total Entries</h6>
                        <h4><?= number_format($totalEntries) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Active Contributors</h6>
                        <h4><?= count($contributors) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Entries Today</h6>
                        <h4><?= number_format($todayEntries) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5></h5>
                        <h6>Last Editor</h6>
                        <h6><?= $lastEditor ?></h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Contributors Widget -->
        <div class="widget">
            <h5>Top Contributors</h5>
            <div class="row">
                <?php foreach (array_slice($contributors, 0, 5) as $user => $count): ?>
                    <div class="col-md-6 mb-2">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($user) ?></span>
                            <span class="badge bg-primary"><?= $count ?> entries</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- View Database Modal -->
        <div class="modal fade" id="viewDatabaseModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="#"></i> Select Database Table</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Choose a database table to view</p>
                        <select id="selectedDatabaseTable" class="form-select">
                            <option value="">Select Table</option>
                            <option value="master_register">Master Register</option>
                            <option value="pensioners_register">Pensioners Register</option>
                            <option value="contractstaff_register">Contract Staff Register</option>
                            <option value="semicurrent_records">Semi-Current Records</option>
                            <option value="file_diary">File Diary</option>
                            <option value="subject_index">Subject Index</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="viewSelectedTable()">
 View Table
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Database Modal -->
        <div class="modal fade" id="editDatabaseModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"> Edit Database Options</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">

                            <!-- Update Records Option -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-sync-alt fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">Update Records</h5>
                                        <p class="card-text">View and modify existing records, change status, dates, file numbers, etc.</p>
                                        <button class="btn btn-primary" onclick="showUpdateRecordsForm()">
 Update Records
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Make New Entries Option -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-plus-circle fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Make New Entries</h5>
                                        <p class="card-text">Add new records to any register in the system.</p>
                                        <button class="btn btn-success" onclick="showNewEntriesForm()">
 Add New Entries
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            
                        </div>
                        
                        <!-- New Entries Form Hidden by default -->
                        <div id="newEntriesForm" style="display: none;" class="mt-4">
                            <hr>
                            <h6> Add New Entries</h6>
                            <form id="newEntriesFormData">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="newEntriesRegister" class="form-label">Select Register:</label>
                                        <select id="newEntriesRegister" class="form-select" required>
                                            <option value="">Choose a register...</option>
                                            <option value="master_register">Master Register</option>
                                            <option value="pensioners_register">Pensioners Register</option>
                                            <option value="contractstaff_register">Contract Staff Register</option>
                                            <option value="semicurrent_records">Semi-Current Records</option>
                                            <option value="subject_index">Subject Index</option>
                                            <option value="file_diary">File Diary</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="newEntriesRows" class="form-label">Number of Rows:</label>
                                        <input type="number" id="newEntriesRows" class="form-control" min="1" max="100" value="5" required>
                                        <div class="form-text">Maximum 100 rows per session</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Update Records Form (Hidden by default) -->
                        <div id="updateRecordsForm" style="display: none;" class="mt-4">
                            <hr>
                            <h6> Update Records</h6>
                            <form id="updateRecordsFormData">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="updateRecordsRegister" class="form-label">Select Register:</label>
                                        <select id="updateRecordsRegister" class="form-select" required>
                                            <option value="">Choose a register</option>
                                            <option value="master_register">Master Register</option>
                                            <option value="pensioners_register">Pensioners Register</option>
                                            <option value="contractstaff_register">Contract Staff Register</option>
                                            <option value="semicurrent_records">Semi-Current Records</option>
                                            <option value="subject_index">Subject Index</option>
                                            <option value="file_diary">File Diary</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="updateRecordsLimit" class="form-label">Records to Load:</label>
                                        <select id="updateRecordsLimit" class="form-select">
                                            <option value="all" selected>All records</option>
                                            <option value="50">50 records</option>
                                            <option value="100">100 records</option>
                                            <option value="200">200 records</option>
                                            <option value="500">500 records</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="proceedNewEntries" onclick="proceedNewEntries()" style="display: none;">
 Proceed to Add
                        </button>
                        <button type="button" class="btn btn-primary" id="proceedUpdateRecords" onclick="proceedUpdateRecords()" style="display: none;">
 Proceed to Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="widget export-section" id="exportSection" style="display: none;">
            <h5> Export Data </h5>
            <p>Select table and export the data here</p>
            
            <div class="table-selector">
                <div class="row">
                    <div class="col-md-6">
                        <label for="exportTable" class="form-label">Select Table to Export:</label>
                        <select id="exportTable" class="form-select">
                            <option value="">Choose table</option>
                            <option value="master_register">Master Register</option>
                            <option value="pensioners_register">Pensioners Register</option>
                            <option value="contractstaff_register">Contract Staff Register</option>
                            <option value="semicurrent_records">Semi-Current Records</option>
                            <option value="subject_index">Subject Index</option>
                            <option value="file_diary">File Diary</option>
                        </select>
                    </div>
                    <input type="hidden" id="exportFormat" value="csv">
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="exportLimit" class="form-label">Number of Records:</label>
                        <select id="exportLimit" class="form-select">
                            <option value="all">All records</option>
                            <option value="100">100 records</option>
                            <option value="500">500 records</option>
                            <option value="1000">1000 records</option>
                            <option value="5000">5000 records</option>
                            <option value="10000">10000 records</option>
                           
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button id="exportBtn" class="btn btn-export w-100" onclick="exportData()">
 Export Data
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="exportStatus" class="mt-3" style="display: none;">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <span id="exportMessage">Preparing export...</span>
                </div>
            </div>
        </div>

        <!-- Recent Activity Widget -->
        <div class="row">
            <div class="col-12">
                <div class="widget">
                    <h5>Recent Activity</h5>
                    <ul class="activity-list">
                        <?php if (empty($recentActivity)): ?>
                            <li class="text-muted">No recent activity</li>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $act): ?>
                                <li><?= htmlspecialchars($act['user']) ?> - <?= ucwords(str_replace('_', ' ', $act['table'])) ?> on <?= date('d/m/Y H:i', strtotime($act['time'])) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sb  = document.getElementById('sidebar');
            const ov  = document.getElementById('sidebarOverlay');
            const open = sb.classList.toggle('open');
            if (ov) ov.classList.toggle('show', open);
        }
        
        function showViewDatabaseModal() {
            const modal = new bootstrap.Modal(document.getElementById('viewDatabaseModal'));
            modal.show();
        }
        
        function viewSelectedTable() {
            const table = document.getElementById('selectedDatabaseTable').value;
            if (!table) {
                alert('Please select a table to view');
                return;
            }
            window.location.href = `viewDatabase.php?table=${table}`;
        }

        function showEditDatabaseModal() {
            const modal = new bootstrap.Modal(document.getElementById('editDatabaseModal'));
            modal.show();
        }

        function showNewEntriesForm() {
            // Hide the option cards
            document.querySelector('.row .col-md-6:first-child').style.display = 'none';
            document.querySelector('.row .col-md-6:last-child').style.display = 'none';
            
            // Show the new entries form
            document.getElementById('newEntriesForm').style.display = 'block';
            
            // Show the proceed button
            document.getElementById('proceedNewEntries').style.display = 'inline-block';
        }

        function showUpdateRecordsForm() {
            // Hide the option cards
            document.querySelector('.row .col-md-6:first-child').style.display = 'none';
            document.querySelector('.row .col-md-6:last-child').style.display = 'none';
            
            // Show the update records form
            document.getElementById('updateRecordsForm').style.display = 'block';
            
            // Show the proceed button
            document.getElementById('proceedUpdateRecords').style.display = 'inline-block';
        }

        function proceedNewEntries() {
            const register = document.getElementById('newEntriesRegister').value;
            const rows = document.getElementById('newEntriesRows').value;
            
            if (!register) {
                alert('Please select a register');
                return;
            }
            
            if (!rows || rows < 1) {
                alert('Please enter a valid number of rows');
                return;
            }
            
            // Redirect to edit_register.php with parameters
            window.location.href = `edit_register.php?table=${register}&rows=${rows}&mode=new`;
        }

        function proceedUpdateRecords() {
            const register = document.getElementById('updateRecordsRegister').value;
            const limit = document.getElementById('updateRecordsLimit').value;
            
            if (!register) {
                alert('Please select a register');
                return;
            }
            
            // Redirect to edit_register.php with parameters for updating existing records
            window.location.href = `edit_register.php?table=${register}&limit=${limit}&mode=edit`;
        }
        
        function showExportSection() {
            const exportSection = document.getElementById('exportSection');
            exportSection.style.display = 'block';
            exportSection.scrollIntoView({ behavior: 'smooth' });
        }
        
        function exportData() {
            const table = document.getElementById('exportTable').value;
            const format = document.getElementById('exportFormat').value;
            const limit = document.getElementById('exportLimit').value;
            
            if (!table) {
                alert('Please select a table to export');
                return;
            }
            
            // Show export status
            document.getElementById('exportStatus').style.display = 'block';
            document.getElementById('exportMessage').textContent = 'Preparing export...';
            document.getElementById('exportBtn').disabled = true;
            
            // Create form data
            const formData = new FormData();
            formData.append('table', table);
            formData.append('format', format);
            formData.append('limit', limit);
            formData.append('action', 'export');
            
            // Send export request
            fetch('exportData.php', {
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
                a.download = `${table}_export_${new Date().toISOString().split('T')[0]}.${format === 'xlsx' ? 'xlsx' : 'csv'}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                // Update status
                document.getElementById('exportMessage').textContent = 'Export completed successfully!';
                document.getElementById('exportBtn').disabled = false;
                
                // Hide status after 3 seconds
                setTimeout(() => {
                    document.getElementById('exportStatus').style.display = 'none';
                }, 3000);
            })
            .catch(error => {
                console.error('Export error:', error);
                document.getElementById('exportMessage').textContent = 'Export failed. Please try again.';
                document.getElementById('exportBtn').disabled = false;
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'm') {
                e.preventDefault();
                toggleSidebar();
            }
        });

        // Cross-tab session heartbeat — keeps the admin session alive across tabs
        (function heartbeat() {
            fetch('admin_dashboard.php?or_heartbeat=1')
                .then(r => r.json())
                .then(d => { if (!d.alive) window.location.href = 'index.php?err=session'; })
                .catch(() => {});
            setTimeout(heartbeat, 4 * 60 * 1000); // every 4 min
        })();
    </script>
</body>
</html>