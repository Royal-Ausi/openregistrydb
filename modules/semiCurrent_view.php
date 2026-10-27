<?php
session_start();
include '../../includes/db.php';

if (!isset($_SESSION["username"])) {
    header("Location: ../../userlogin.php");
    exit();
}

$username = $_SESSION["username"];
require_once __DIR__ . '/../includes/auth.php';
$registryDashboardUrl = openregistry_dashboard_href();

// Get active records from semi-current records
$sql = "SELECT * FROM semicurrent_records WHERE status = 'Active' ORDER BY timestamp DESC";
$result = $conn->query($sql);

$records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

$totalRecords = count($records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semi-Current Records | Open Registry</title>
    <link rel="icon" type="image/jpeg" href="../../includes/images/mlhud jpeg 5.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #FCF1E3; 
            font-family: Arial, sans-serif;
            margin-top: 80px;
        }
        
        .navbar {
            background-color: #E08D3C;
            color: #FCF1E3;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 8px 20px;
        }
        
        .navbar-logo {
            max-height: 40px;
            width: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .navbar-brand {
            color: #FCF1E3;
            font-weight: bold;
            font-size: 18px;
            margin: 0;
        }
        
        .main-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .stats-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #E08D3C, #c0392b);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            min-width: 120px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h6 {
            margin: 0 0 5px 0;
            font-size: 12px;
            opacity: 0.9;
        }
        
        .stat-card .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .table-wrapper {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 70vh;
      border: 2px solid #E08D3C;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* Scrollbar Styling */
    .table-wrapper::-webkit-scrollbar {
      height: 12px;
      width: 12px;
    }
    
    .table-wrapper::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb {
      background: #E08D3C;
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: #c0392b;
    }
    
    .table-wrapper::-webkit-scrollbar-corner {
      background: #f1f1f1;
    }

    /* Navigation Bar */
    .navbar {
      background-color: #E08D3C;
      color: #FCF1E3;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      padding: 8px 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .navbar-logo {
      max-height: 40px;
      width: auto;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .navbar-brand {
      color: #FCF1E3;
      font-weight: bold;
      font-size: 18px;
      margin: 0;
    }

    /* Enhanced table wrapper */
            overflow-x: auto;
            border: 2px solid #E08D3C;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .excel-table {
            border-collapse: collapse;
            width: 100%;
            min-width: 1400px;
            background-color: white;
            table-layout: fixed;
        }
        
        .excel-table th {
            background-color: #E08D3C;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #ddd;
            position: fixed;
            top: 0;
            z-index: 10;
        }
        
        .excel-table td {
            border: 1px solid #ddd;
            padding: 10px;
            background-color: white;
            vertical-align: middle;
        }
        
        .excel-table tr:nth-child(even) td {
            background-color: #f9f9f9;
        }
        
        .excel-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background-color: #E08D3C;
            border-color: #E08D3C;
        }
        
        .btn-primary:hover {
            background-color: #c0392b;
            border-color: #c0392b;
        }
        
        .btn-success {
            background-color: #27ae60;
            border-color: #27ae60;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-semi-active {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-transferred {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-archived {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            border: 2px solid #E08D3C;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            max-width: 400px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #c0392b;
            box-shadow: 0 0 5px rgba(232, 73, 42, 0.3);
        }
        
        .no-records {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .record-count {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #E08D3C;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="d-flex align-items-center">
            <img src="../../includes/images/mlhud jpeg 6.jpg" alt="Open Registry Logo" class="navbar-logo me-3">
            <span class="navbar-brand">Semi-Current Records</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="<?= htmlspecialchars($registryDashboardUrl) ?>" class="btn btn-outline-light btn-sm">
                Home
            </a>
            <button class="btn btn-outline-light btn-sm" onclick="history.back()">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </nav>

    <div class="main-container">
        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <h6>Total Records</h6>
                <p class="stat-number"><?= $totalRecords ?></p>
            </div>
            <div class="stat-card">
                <h6>Active Records</h6>
                <p class="stat-number"><?= $totalRecords ?></p>
            </div>
            <div class="stat-card">
                <h6>User</h6>
                <p class="stat-number"><?= htmlspecialchars($username) ?></p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="semiCurrent_edit.php" class="btn btn-primary">
                Add New Records
            </a>
            <button class="btn btn-success" onclick="exportData()">
                Export Data
            </button>
            <button class="btn btn-info" onclick="refreshData()">
                <i class="fas fa-sync"></i> Refresh
            </button>
            <button class="btn btn-warning" onclick="printTable()">
                Print
            </button>
        </div>

        <!-- Search -->
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search records... (File Reference, File Title, Location, Box Number)">
        </div>

        <!-- Record Count -->
        <div class="record-count">
            <strong>Showing <?= $totalRecords ?> active records</strong>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table class="excel-table" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 250px;">File Reference Number</th>
                        <th style="width: 500px;">File Title</th>
                        <th style="width: 140px;">Date Opened</th>
                        <th style="width: 140px;">Date Closed</th>
                        <th style="width: 150px;">Retention Period</th>
                        <th style="width: 150px;">Action Category</th>
                        <th style="width: 140px;">Action Date</th>
                        <th style="width: 120px;">Location No</th>
                        <th style="width: 120px;">Temp Box No</th>
                        <th style="width: 120px;">Box No</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 100px;">Created By</th>
                        <th style="width: 120px;">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="13" class="no-records">
                                <br>
                                No active records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['file_reference_number'] ?? '') ?></td>
                                <td style="width: 500px;"><?= htmlspecialchars($record['file_title'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['date_opened'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['date_closed'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['retention_period'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['action_category'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['action_date'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['location_no'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['temp_box_no'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['box_no'] ?? '') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace('-', '-', $record['status'] ?? '')) ?>">
                                        <?= htmlspecialchars($record['status'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($record['created_by'] ?? '') ?></td>
                                <td><?= htmlspecialchars($record['timestamp'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('dataTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 2; j++) { // Excludes last 2 columns (Created By, Created At)
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });

        // Export functionality
        function exportData() {
            alert('Export functionality will be implemented soon!');
        }

        // Refresh functionality
        function refreshData() {
            location.reload();
        }

        // Print functionality
        function printTable() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>
</html>

