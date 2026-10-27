<?php
session_start();
include '../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$registryDashboardUrl = openregistry_dashboard_href();

$result = $conn->query("SELECT * FROM master_register ORDER BY timestamp DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>View Master Register - Open Registry</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #FCF1E3;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      margin-top: 80px;
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

    /* Enhanced Toolbar */
    .toolbar {
      background-color: #E08D3C;
      color: white;
      padding: 15px 20px;
      margin: -20px -20px 20px -20px;
      border-radius: 8px 8px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }
    
    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .toolbar-btn {
      background-color: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      padding: 10px 15px;
      border-radius: 6px;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 500;
    }
    
    .toolbar-btn:hover {
      background-color: rgba(255,255,255,0.3);
      color: white;
      text-decoration: none;
      transform: translateY(-1px);
    }
    
    .toolbar-btn i {
      font-size: 16px;
    }

    /* Excel-like Table Wrapper */
    .table-wrapper {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 70vh;
      border: 2px solid #E08D3C;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      background-color: white;
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

    /* Excel-like table styling */
    .excel-table {
      border-collapse: collapse;
      width: 100%;
      min-width: 1200px;
      background-color: white;
      table-layout: fixed;
      font-size: 14px;
    }
    
    .excel-table th {
      background:  #E08D3C;
      color: white;
      padding: 12px 10px;
      text-align: left;
      font-weight: 700;
      border: 1px solid #e9d8c1;
      position: sticky;
      top: 0;
      z-index: 10;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .excel-table td {
      border: 1px solid #faf8f5;
      padding: 12px 10px;
      background-color: white;
      vertical-align: middle;
      text-align: left;
      min-height: 42px;
    }
    
    .excel-table tr:nth-child(even) td {
      background-color: #f9f9f9;
    }
    
    .excel-table tr:hover td {
      background-color: #f8f9fa !important;
    }

    /* Search styling */
    .search-container {
      margin-bottom: 20px;
      padding: 15px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .search-input {
      border: 2px solid #E08D3C;
      border-radius: 6px;
      padding: 10px 15px;
      font-size: 14px;
      width: 100%;
      transition: border-color 0.3s ease;
    }
    
    .search-input:focus {
      outline: none;
      border-color: #c0392b;
      box-shadow: 0 0 0 0.2rem rgba(232, 73, 42, 0.25);
    }

    /* Status badges */
    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }
    
    .status-active { background-color: #d4edda; color: #155724; }
    .status-semi-active { background-color: #fff3cd; color: #856404; }
    .status-transferred { background-color: #f8d7da; color: #721c24; }
    .status-closed { background-color: #d1ecf1; color: #0c5460; }
    .status-archived { background-color: #e2e3e5; color: #383d41; }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  <nav class="navbar">
    <div class="d-flex align-items-center">
      <img src="../includes/images/mlhud jpeg 6.jpg" alt="Open Registry Logo" class="navbar-logo me-3">
      <span class="navbar-brand">View Master Register</span>
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

<div class="container mt-4" style="max-width:1400px;">
  <!-- Enhanced Toolbar -->
  <div class="toolbar">
    <div class="toolbar-left">
      <a href="<?= htmlspecialchars($registryDashboardUrl) ?>" class="toolbar-btn">
        Home
      </a>
      <button class="toolbar-btn" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Back
      </button>
    </div>
    <div class="toolbar-right">
      <button class="toolbar-btn" onclick="exportTable()">
        Export
      </button>
      <button class="toolbar-btn" onclick="refreshTable()">
        <i class="fas fa-sync"></i> Refresh
      </button>
    </div>
  </div>

  <!-- Search Container -->
  <div class="search-container">
    <input type="text" id="searchInput" class="search-input" placeholder="Search by File Number, Full Name, or Designation..." onkeyup="searchTable()">
  </div>

  <!-- Excel-like Table -->
  <div class="table-wrapper">
    <table class="excel-table" id="recordsTable">
      <thead>
        <tr>
          <th style="width: 100px;">File Number</th>
          <th style="width: 220px;">Full Name</th>
          <th style="width: 220px;">Designation</th>
          <th style="width: 120px;">Date Opened</th>
          <th style="width: 120px;">Date Closed</th>
          <th style="width: 220px;">Address Contact</th>
          <th style="width: 120px;">Status</th>
          <!-- <th style="width: 100px;">Created By</th>
          <th style="width: 150px;">Created At</th> -->
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><strong><?= htmlspecialchars($row["file_number"]) ?></strong></td>
          <td><?= htmlspecialchars($row["full_name"]) ?></td>
          <td><?= htmlspecialchars($row["designation"]) ?></td>
          <td><?= htmlspecialchars($row["date_opened"]) ?></td>
          <td><?= htmlspecialchars($row["date_closed"]) ?: '<span class="text-muted">-</span>' ?></td>
          <td><?= htmlspecialchars($row["address_contact"] ?? '') ?></td>
          <td>
            <span class="status-badge status-<?= strtolower(str_replace('-', '-', $row["status"] ?? '')) ?>">
              <?= htmlspecialchars($row["status"]) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($row["created_by"]) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($row["timestamp"])) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

  <script>
    function searchTable() {
      const input = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#recordsTable tbody tr");
      rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(input) ? "" : "none";
      });
    }

    function exportTable() {
      // Create a CSV export
      const table = document.getElementById("recordsTable");
      const rows = Array.from(table.querySelectorAll("tr"));
      const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll("th, td"));
        return cells.map(cell => `"${cell.textContent.trim()}"`).join(",");
      }).join("\n");
      
      const blob = new Blob([csvContent], { type: "text/csv" });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "master_register_export.csv";
      a.click();
      window.URL.revokeObjectURL(url);
    }

    function refreshTable() {
      location.reload();
    }

    // Add keyboard shortcuts
    document.addEventListener("keydown", function(e) {
      if (e.ctrlKey && e.key === "f") {
        e.preventDefault();
        document.getElementById("searchInput").focus();
      }
      if (e.ctrlKey && e.key === "e") {
        e.preventDefault();
        exportTable();
      }
    });
  </script>
</body>
</html>