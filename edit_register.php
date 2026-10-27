<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
include 'includes/db.php';
require_once __DIR__ . '/includes/schema_extensions.php';
openregistry_ensure_status_keywords($conn);
openregistry_ensure_register_columns($conn);

$registryDashboardUrl = openregistry_dashboard_href();

$table = $_GET["table"] ?? "";
$rows = intval($_GET["rows"] ?? 0);
$rawLimit = $_GET["limit"] ?? 50; // can be numeric or "all"
$limit = ($rawLimit === "all") ? "all" : intval($rawLimit);
$mode = $_GET["mode"] ?? "new"; // "new" or "edit"
$username = $_SESSION["username"] ?? '';
$userRole = $_SESSION["role"] ?? 'user';
$userModules = $_SESSION["assigned_modules"] ?? [];

$allowedTables = [
  "master_register"        => ["file_number", "full_name", "designation", "date_opened", "date_closed", "address_contact", "status_id"],
  "contractstaff_register" => ["file_number", "name_of_officer", "title", "date_opened", "address_contact", "status_id"],
  "pensioners_register"    => ["file_number", "full_name", "designation", "date_opened", "date_closed", "address_contact", "status_id"],
  "semicurrent_records"    => ["file_reference_number", "file_title", "date_opened", "date_closed", "retention_period",
                                "action_category", "action_date", "location_no", "temp_box_no", "box_no"],
  "file_diary"             => ["file_ref_number", "file_title", "date_opened", "security_grading"],
  "subject_index"          => ["previous_file_title", "previous_index", "current_file_title", "current_index", "volume_number", "date_opened", "status_id"]
];

// Check if table exists
if (!array_key_exists($table, $allowedTables)) {
  echo "Invalid table.";
  exit();
}

// Check if user has permission to edit this table (unless admin or system operator)
$userModules = openregistry_normalize_module_list($userModules);
if ($userRole !== 'admin' && !openregistry_is_system_operator()) {
  if (!in_array($table, $userModules, true)) {
    echo "<div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>";
    echo "<h3 style='color: #dc3545;'><i class='fas fa-ban'></i> Access Denied</h3>";
    echo "<p>You don't have permission to edit the <strong>" . ucwords(str_replace("_", " ", $table)) . "</strong> register.</p>";
    echo "<p>Contact your administrator to request access to this register.</p>";
    echo "<a href='user_dashboard.php' style='background-color: #E08D3C; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Return to Dashboard</a>";
    echo "</div>";
    exit();
  }
}

$columns = $allowedTables[$table];

// Load status keywords dynamically from the DB; fall back to hard-coded defaults
$dbKeywords = openregistry_get_status_keywords($conn);
$dynamicStatusOpts = !empty($dbKeywords) ? array_values($dbKeywords) : [
    "Active","Semi-active","Inactive","Transferred","Closed","Archived","Deceased","Resigned","Didnt Report"
];
$statusDisplayOpts = array_values(array_unique(array_merge(['N/A'], $dynamicStatusOpts)));

// Tables that use the dynamic status system (others remain unchanged)
$tablesWithDynamicStatus = ['master_register', 'contractstaff_register', 'subject_index'];

// status_id column → displayed as "Status" dropdown populated from status_keywords
$statusOptions = [
  "master_register"        => $statusDisplayOpts,
  "contractstaff_register" => $statusDisplayOpts,
  "pensioners_register"    => $statusDisplayOpts,
  "subject_index"          => $statusDisplayOpts,
];

// For new entries mode, we need rows parameter
if ($mode === "new" && $rows < 1) {
  echo "Invalid row count for new entries.";
  exit();
}

// For update records mode, we need limit parameter (unless "all")
if ($mode === "edit" && $limit !== "all" && $limit < 1) {
  echo "Invalid limit for updating records.";
  exit();
}

// Fetch existing data if in edit mode
$existingData = [];
if ($mode === "edit") {
  // Use correct PK column per table
  $pkColMap = [
    'master_register'        => 'id',
    'pensioners_register'    => 'id',
    'contractstaff_register' => 'serial_number',
    'semicurrent_records'    => 'serial_number',
    'file_diary'             => 'serial_number',
    'subject_index'          => 'id',
  ];
  $pkCol = $pkColMap[$table] ?? 'id';

  // For status_id tables: JOIN to resolve keyword; never select t.status (dropped)
  $tablesNeedStatusJoin = ['master_register', 'contractstaff_register', 'pensioners_register', 'subject_index'];
    if (in_array($table, $tablesNeedStatusJoin)) {
    if ($limit === "all") {
      $sql = "SELECT t.*, sk.keyword AS status_text FROM `$table` t LEFT JOIN status_keywords sk ON sk.id = t.status_id ORDER BY t.$pkCol ASC";
    } else {
      $sql = "SELECT t.*, sk.keyword AS status_text FROM `$table` t LEFT JOIN status_keywords sk ON sk.id = t.status_id ORDER BY t.$pkCol ASC LIMIT $limit";
    }
  } else {
    if ($limit === "all") {
      $sql = "SELECT * FROM `$table` ORDER BY $pkCol ASC";
    } else {
      $sql = "SELECT * FROM `$table` ORDER BY $pkCol ASC LIMIT $limit";
    }
  }
  $result = $conn->query($sql);
  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      // Normalise PK to 'id' so the rest of the template always uses $row['id']
      if (!isset($row['id']) && isset($row['serial_number'])) {
        $row['id'] = $row['serial_number'];
      }
      // Expose resolved status keyword as status_id slot so dropdown pre-selects correctly
      if (isset($row['status_text'])) {
        $row['_status_keyword'] = $row['status_text'];
      }
      $existingData[] = $row;
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit <?= ucwords(str_replace("_", " ", $table)) ?></title>
  <link rel="icon" type="image/jpeg" href="includes/images/mlhud jpeg 5.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Base styling with navbar color scheme */
    body { 
      background-color: #ffffff; 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      padding-top: 0;
    }

    /* Unified Color Scheme - Based on Navbar */
    :root {
      --primary-color: #E08D3C;
      --primary-text: #ffffff;
      --primary-hover: #c76d2c;
      --secondary-color: #ffffff;
      --background-color: #ffffff;
      --text-color: #000000;
      --border-color: #ddd;
      --hover-color: #f5f5f5;
      --success-color: #28a745;
      --warning-color: #ffc107;
      --danger-color: #dc3545;
      --info-color: #17a2b8;
    }

    /* Navigation Bar */
    .navbar {
      background: var(--primary-color);
      color: #f5f5f5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1100;
      padding: 12px 20px;
      margin: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      box-sizing: border-box;
      border-radius: 0;
    }
    
    .navbar-logo {
      max-height: 40px;
      width: auto;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .navbar-brand {
      color: #f5f5f5 !important;
      font-weight: 700;
      font-size: 18px;
      margin: 0;
      white-space: nowrap;
    }

    /* Excel-like Table Wrapper with Horizontal Scroll */
    .table-wrapper {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 70vh;
      border: 2px solid var(--primary-color);
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
      background: var(--primary-color);
      border-radius: 6px;
    }
    
    .table-wrapper::-webkit-scrollbar-thumb:hover {
      background: var(--primary-hover);
    }
    
    .table-wrapper::-webkit-scrollbar-corner {
      background: #f1f1f1;
    }

    /* Enhanced Toolbar */
    /* .toolbar {
      background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
      color: var(--primary-text);
      padding: 15px 20px;
      margin: -20px -20px 20px -20px;
      border-radius: 8px 8px 0 0;
      display: flex;
      position: fixed;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
      box-shadow: 0 2px 8px rgba(232, 73, 42, 0.3);
    } */
    
    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .toolbar-btn {
      background-color: rgba(255,255,255,0.18);
      border: 1px solid rgba(255,255,255,0.35);
      color: #f5f5f5;
      padding: 10px 14px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      min-height: 42px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    
    .toolbar-btn:hover {
      color: #f5f5f5;
      text-decoration: none;
      transform: translateY(-1px);
      box-shadow: 0 3px 8px rgba(0,0,0,0.14);
    }

    .toolbar-btn.save-primary {
      background-color: rgba(255,255,255,0.98);
      color: #8a3b0f;
      border-color: #fff;
      font-weight: 700;
    }

    .toolbar-btn.save-primary:hover {
      background-color: #ffffff;
      color: #8a3b0f;
    }
    
    .toolbar-btn span {
      font-size: 14px;
    }

    /* Checkbox styling */
    .row-selector-checkbox {
      width: 15px;
      height: 15px;
      cursor: pointer;
      accent-color: var(--primary-color);
      border-radius: 4px;
      vertical-align: middle;
      border: 2px solid var(--primary-color);
      transition: transform .12s;
    }

    .row-selector-checkbox:hover {
      transform: scale(1.15);
    }

    .row-selector-checkbox:checked {
      background-color: var(--primary-color);
    }

    /* Stats Cards */
    .stats-container {
      display: flex;
      gap: 5px;
      justify-content: center;
      margin-top: -32px;
      margin-bottom: -8.5px;
      flex-wrap: wrap;
    }
    
    .stat-card {
      background: var(--primary-color);
      color: var(--primary-text);
      padding: 6px 10px;
      border-radius: 4px;
      text-align: center;
      min-width: 120px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    
    .stat-card h6 {
      margin: 0 0 5px 0;
      font-size: 12px;
      opacity: 0.9;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .stat-card .stat-number {
      font-size: 24px;
      font-weight: bold;
      margin: 0;
    }

    /* Container and layout */
    .container {
      max-width: 100%;
      margin: 0 auto;
      padding: 10px 10px;
      padding-top: 72px; /* provide space for fixed navbar */
    }

    /* Mode indicator with navbar colors */
    .mode-indicator {
      background: var(--primary-color), var(--primary-hover);
      color: var(--primary-text);
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(232, 73, 42, 0.3);
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
      background-color: var(--primary-color);
      color: var(--primary-text);
      padding: 12px 8px;
      text-align: center;
      font-weight: bold;
      border: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 10;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .excel-table td {
      border: 1px solid #ddd;
      padding: 0;
      background-color: white;
      vertical-align: middle;
      position: relative;
      overflow: visible;
    }

    .excel-table tr:nth-child(even) td {
      background-color: #f9f9f9;
    }

    .excel-table input, .excel-table select {
      border: none;
      background-color: transparent;
      width: 100%;
      padding: 10px;
      font-size: 14px;
      outline: none;
      min-height: 40px;
      box-sizing: border-box;
      resize: none;
      overflow: hidden;
      word-wrap: break-word;
    }
    
    .excel-table input[type="text"] {
      resize: both;
      min-width: 100px;
      transition: all 0.2s ease;
    }
    
    .excel-table input[type="text"]:focus {
      min-width: 200px;
      resize: both;
    }
    
    .excel-table td:hover {
      background-color: #f8f9fa !important;
    }
    
    .cell-resize-handle {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 8px;
      height: 8px;
      background: var(--primary-color);
      cursor: se-resize;
      opacity: 0;
      transition: opacity 0.2s ease;
    }
    
    .excel-table td:hover .cell-resize-handle {
      opacity: 1;
    }
    
    .excel-table input:focus, .excel-table select:focus {
      background-color: #fff3cd;
      border: 2px solid var(--primary-color);
      box-shadow: inset 0 0 5px rgba(232, 73, 42, 0.3);
    }
    
    .excel-table select {
      cursor: pointer;
    }

    /* Read-only created by field */
    .created-by-field {
      background-color: #f8f9fa;
      border: 1px solid #dee2e6;
      color: #6c757d;
      font-weight: 500;
    }

    /* Button styling with navbar colors */
    .btn-primary, .btn-primary:focus {
      background-color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
      color: var(--primary-text) !important;
    }
    .btn-primary:hover {
      background-color: var(--primary-hover) !important;
      border-color: var(--primary-hover) !important;
    }
    
    .btn-success {
      background-color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
      color: var(--primary-text) !important;
    }
    .btn-success:hover {
      background-color: var(--primary-hover) !important;
      border-color: var(--primary-hover) !important;
    }
    
    .btn-secondary {
      background-color: #6c757d !important;
      border-color: #6c757d !important;
    }
    .btn-secondary:hover {
      background-color: #5a6268 !important;
      border-color: #5a6268 !important;
    }

    /* Status indicator cards */
    .card.bg-success {
      background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)) !important;
      color: var(--primary-text) !important;
      border: none;
      box-shadow: 0 2px 8px rgba(232, 73, 42, 0.3);
    }

    /* Status row colors */
    .status-active { 
      background-color: #d4edda !important; 
      border-left: 4px solid #28a745;
    }
    .status-semi-active { 
      background-color: #fff3cd !important; 
      border-left: 4px solid #ffc107;
    }
    .status-transferred { 
      background-color: #f8d7da !important; 
      border-left: 4px solid #dc3545;
    }

    /* Responsive table */
    .table-responsive {
      border-radius: 5px;
      overflow-x: auto;
    }

    /* Column width optimization for desktop */
    .spreadsheet-table th:nth-child(1), /* ID column */
    .spreadsheet-table td:nth-child(1) {
      width: 60px;
      min-width: 60px;
      max-width: 60px;
    }

    .spreadsheet-table th:nth-child(2), /* File/Reference number */
    .spreadsheet-table td:nth-child(2) {
      width: 120px;
      min-width: 120px;
    }

    .spreadsheet-table th:nth-child(3), /* Title/Name */
    .spreadsheet-table td:nth-child(3) {
      width: 200px;
      min-width: 200px;
    }

    .spreadsheet-table th:nth-child(4), /* Date columns */
    .spreadsheet-table td:nth-child(4) {
      width: 120px;
      min-width: 120px;
    }

    .spreadsheet-table th:nth-child(5), /* Status */
    .spreadsheet-table td:nth-child(5) {
      width: 100px;
      min-width: 100px;
    }

    /* Additional columns for semi-current records */
    .spreadsheet-table th:nth-child(n+6),
    .spreadsheet-table td:nth-child(n+6) {
      width: 120px;
      min-width: 120px;
    }
    
    /* Style for created_by field display */
    .form-control-plaintext {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 0.375rem;
      padding: 0.375rem 0.75rem;
      color: #6c757d;
      font-size: 0.875rem;
      display: block;
      width: 100%;
      height: calc(1.5em + 0.75rem + 2px);
      line-height: 1.5;
    }

    /* Save message styling */
    #saveMessage {
      margin-top: 10px;
    }

    /* Professional save result panel */
    .save-result-panel {
      border-radius: 10px;
      padding: 0;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0,0,0,.10);
      margin-top: 12px;
      animation: slideIn .25s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .save-result-panel .panel-head {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 18px; font-weight: 700; font-size: 14px;
    }
    .save-result-panel.result-success .panel-head { background: #d1fae5; color: #065f46; }
    .save-result-panel.result-danger  .panel-head { background: #fee2e2; color: #991b1b; }
    .save-result-panel.result-warning .panel-head { background: #fef9c3; color: #854d0e; }
    .save-result-panel.result-partial .panel-head { background: #fff7ed; color: #9a3412; }
    .save-result-panel .panel-body {
      background: #fff; padding: 12px 18px;
      border-top: 1px solid rgba(0,0,0,.06);
    }
    .save-result-panel .error-item {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px;
    }
    .save-result-panel .error-item:last-child { border-bottom: none; }
    .save-result-panel .error-icon { flex-shrink: 0; margin-top: 1px; }
    .save-result-panel .error-text { flex: 1; color: #374151; line-height: 1.5; }

    /* Loading state */
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }

    /* Success animation */
    @keyframes successPulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    .success-pulse {
      animation: successPulse 0.3s ease-in-out;
    }
  </style>
</head>
<body>
  <!-- Navigation -->
  
<div class="container mt-4" style="max-width:100%;">
  <!-- Enhanced Toolbar -->
  <nav class="navbar">
    <!-- <div class="toolbar"> -->
      <div class="toolbar-left">
        <a href="<?= htmlspecialchars($registryDashboardUrl) ?>" class="toolbar-btn">
          Home
        </a>
        <button class="toolbar-btn" onclick="history.back()">
          Back
        </button>
        <!-- <span class="toolbar-btn" style="background: rgba(255,255,255,0.1);">
          <i class="fas fa-<?= $mode === 'new' ? 'plus-circle' : 'edit' ?>"></i>
          <?= $mode === 'new' ? 'Adding New Entries' : 'Updating Records' ?>
        </span> -->
      </div>

      <div class="d-flex align-items-center gap-2">
        <label>Search:</label>
        <input type="text" id="searchInput" placeholder="Search…" oninput="filterTable()">
      </div>
      <!-- <div class="d-flex align-items-center">
        <img src="includes/images/mlhud jpeg 6.jpg" alt="Open Registry Logo" class="navbar-logo me-3">
        <span class="navbar-brand">Edit <?= ucwords(str_replace("_", " ", $table)) ?></span>
      </div> -->
      <div class="toolbar-right">
        <?php if ($mode === 'new'): ?>
          <button class="toolbar-btn" onclick="addMoreRows()">
            Add More Rows
          </button>
        <?php endif; ?>
        <?php if ($mode === 'edit' && $userRole === 'admin'): ?>
          <button id="deleteSelectedButton" class="toolbar-btn" style="background: rgba(220, 53, 69, 0.8);" onclick="deleteSelectedRecords()">
            Delete Selected
          </button>
        <?php endif; ?>
        <button id="saveButton" class="toolbar-btn save-primary">
          <?= $mode === 'new' ? 'Save' : 'Save' ?>
        </button>
      </div>
    <!-- </div>  -->
  </nav>
  <!-- Stats Cards -->
  <!-- <div class="stats-container">
    <div class="stat-card">
      <h6>Saved Rows</h6>
      <p class="stat-number" id="savedCount">0</p>
    </div>
    <div class="stat-card">
      <h6>Total Rows</h6>
      <p class="stat-number" id="totalRows"><?= $mode === 'new' ? $rows : count($existingData) ?></p>
    </div>
    <div class="stat-card">
      <h6>Created By</h6>
      <p class="stat-number"><?= htmlspecialchars($username) ?></p>
    </div>
    <div class="stat-card">
      <h6>Table</h6>
      <p class="stat-number"><?= ucwords(str_replace("_", " ", $table)) ?></p>
    </div>
  </div> -->
  
  <input type="hidden" id="createdBy" value="<?= htmlspecialchars($username) ?>">
  
  <!-- Save Message -->
  <div id="saveMessage"></div>
  
  <!-- Excel-like Table -->
  <div class="table-wrapper">
    <table class="excel-table spreadsheet-table">
      <thead>
        <tr>
          <?php if ($mode === 'edit'): ?>
            <th style="width: 54px;">Select</th>
          <?php endif; ?>
          <?php if ($mode === 'edit'): ?>
            <th>ID</th>
          <?php endif; ?>
          <?php foreach ($columns as $col): ?>
            <th><?= $col === 'status_id' ? 'Status' : ucwords(str_replace("_", " ", $col)) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php if ($mode === 'new'): ?>
        <!-- New entries mode - empty rows -->
        <?php for ($i = 0; $i < $rows; $i++): ?>
          <tr>
            <?php foreach ($columns as $index => $col): ?>
              <td>
                <?php if ($col === 'status_id'): ?>
                  <select class="form-select status-dropdown" data-row="<?= $i ?>" data-col="<?= $index ?>">
                    <option value="">Select Status</option>
                    <?php
                      $opts = $statusOptions[$table] ?? $statusDisplayOpts;
                      foreach ($opts as $opt) {
                        echo '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                      }
                    ?>
                  </select>
                <?php elseif ($col === 'created_by'): ?>
                  <input type="hidden" 
                         class="created-by-field" 
                         data-row="<?= $i ?>" 
                         data-col="<?= $index ?>"
                         value="<?= htmlspecialchars($username) ?>">
                  <span class="form-control-plaintext"><?= htmlspecialchars($username) ?></span>
                <?php elseif ($col === 'address_contact'): ?>
                  <input type="text"
                         class="form-control"
                         data-row="<?= $i ?>"
                         data-col="<?= $index ?>"
                         placeholder="Enter email or phone number">
                <?php elseif ($col === 'status_id'): ?>
                  <select class="form-select status-dropdown" data-row="<?= $i ?>" data-col="<?= $index ?>">
                    <option value="">Select Status</option>
                    <?php
                      $opts = $statusOptions[$table] ?? $statusDisplayOpts;
                      foreach ($opts as $opt) {
                        echo '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                      }
                    ?>
                  </select>
                <?php elseif ($col === 'security_grading'): ?>
                  <select class="form-select" data-row="<?= $i ?>" data-col="<?= $index ?>">
                    <option value="">Select Security Grading</option>
                    <option value="Open">Open</option>
                    <option value="Confidential">Confidential</option>
                    <option value="Secret">Secret</option>
                    <option value="Top Secret">Top Secret</option>
                  </select>
                <?php else: ?>
                  <input type="<?= strpos($col, 'date') !== false ? 'date' : 'text' ?>"
                         class="form-control"
                         data-row="<?= $i ?>"
                         data-col="<?= $index ?>"
                         placeholder="Enter <?= ucwords(str_replace("_", " ", $col)) ?>">
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; ?>
      <?php else: ?>
        <!-- Update records mode - populated rows -->
        <?php foreach ($existingData as $rowIndex => $row): ?>
          <?php
            $statusKw = !empty($row['status_id']) ? ($dbKeywords[(int)$row['status_id']] ?? '') : '';
            $statusCss = strtolower(str_replace([' ','-'],['','-'], $statusKw));
          ?>
          <tr class="status-<?= $statusCss ?>" data-row-id="<?= $row['id'] ?>">
            <td style="text-align: center; width: 44px;">
              <input type="checkbox" class="row-selector-checkbox" value="<?= $row['id'] ?>" data-row-index="<?= $rowIndex ?>">
            </td>
            <td><strong><?= $row['id'] ?></strong></td>
            <?php foreach ($columns as $index => $col): ?>
              <td>
                <?php if ($col === 'status_id'): ?>
                  <select class="form-select status-dropdown" data-row="<?= $rowIndex ?>" data-col="<?= $index ?>" data-id="<?= $row['id'] ?>">
                    <?php
                      $opts = $statusOptions[$table] ?? $statusDisplayOpts;
                      // Null status is displayed explicitly without changing the database value.
                      $currentStatus = ($row['status_id'] === null || $row['status_id'] === '')
                        ? 'N/A'
                        : ($row['_status_keyword'] ?? ($dbKeywords[(int)($row['status_id'] ?? 0)] ?? 'N/A'));
                      foreach ($opts as $opt) {
                        $sel = ($currentStatus === $opt) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
                      }
                    ?>
                  </select>
                <?php elseif ($col === 'created_by'): ?>
                  <input type="hidden" 
                         class="created-by-field" 
                         data-row="<?= $rowIndex ?>" 
                         data-col="<?= $index ?>"
                         data-id="<?= $row['id'] ?>"
                         value="<?= htmlspecialchars($row[$col] ?? $username) ?>">
                  <span class="form-control-plaintext"><?= htmlspecialchars($row[$col] ?? $username) ?></span>
                <?php elseif ($col === 'address_contact'): ?>
                  <input type="text"
                         class="form-control"
                         data-row="<?= $rowIndex ?>"
                         data-col="<?= $index ?>"
                         data-id="<?= $row['id'] ?>"
                         value="<?= htmlspecialchars($row[$col] ?? '') ?>"
                         placeholder="Enter email or phone number">
                <?php elseif ($col === 'status_id'): ?>
                  <select class="form-select status-dropdown" data-row="<?= $rowIndex ?>" data-col="<?= $index ?>" data-id="<?= $row['id'] ?>">
                    <?php
                      $opts = $statusOptions[$table] ?? $statusDisplayOpts;
                        $currentStatus = ($row['status_id'] === null || $row['status_id'] === '')
                          ? 'N/A'
                          : ($row['_status_keyword'] ?? ($dbKeywords[(int)($row['status_id'] ?? 0)] ?? 'N/A'));
                      foreach ($opts as $opt) {
                        $sel = ($currentStatus === $opt) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
                      }
                    ?>
                  </select>
                <?php elseif ($col === 'security_grading'): ?>
                  <select class="form-select" data-row="<?= $rowIndex ?>" data-col="<?= $index ?>" data-id="<?= $row['id'] ?>">
                    <option value="">Select Security Grading</option>
                    <option value="Open" <?= (($row[$col] ?? '') === 'Open') ? 'selected' : '' ?>>Open</option>
                    <option value="Confidential" <?= (($row[$col] ?? '') === 'Confidential') ? 'selected' : '' ?>>Confidential</option>
                    <option value="Secret" <?= (($row[$col] ?? '') === 'Secret') ? 'selected' : '' ?>>Secret</option>
                    <option value="Top Secret" <?= (($row[$col] ?? '') === 'Top Secret') ? 'selected' : '' ?>>Top Secret</option>
                  </select>
                <?php else: ?>
                  <input type="<?= strpos($col, 'date') !== false ? 'date' : 'text' ?>"
                         class="form-control"
                         data-row="<?= $rowIndex ?>"
                         data-col="<?= $index ?>"
                         data-id="<?= $row['id'] ?>"
                         value="<?= htmlspecialchars($row[$col] ?? '') ?>"
                         placeholder="Enter <?= ucwords(str_replace("_", " ", $col)) ?>">
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<script>
  document.getElementById("saveButton").addEventListener("click", saveAllRows);

  // ── Standalone helpers (available to all functions) ────────────────────────
  function escHtmlJS(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function showSaveResult(type, headHtml, errors) {
    const el = document.getElementById('saveMessage');
    if (!el) return;
    const iconMap = {
      success: 'fas fa-check-circle',
      danger:  'fas fa-exclamation-circle',
      warning: 'fas fa-exclamation-triangle',
      partial: 'fas fa-exclamation-triangle',
    };
    let body = '';
    if (errors && errors.length > 0) {
      const iconColor = (type === 'success') ? '#10b981' : (type === 'warning') ? '#d97706' : '#ef4444';
      body = `<div class="panel-body">` +
        errors.map(e => `
          <div class="error-item">
            <span class="error-icon"><i class="${iconMap[type] || 'fas fa-info-circle'}" style="color:${iconColor};"></i></span>
            <span class="error-text">${escHtmlJS(e)}</span>
          </div>`).join('') +
        `</div>`;
    }
    el.innerHTML = `<div class="save-result-panel result-${type}"><div class="panel-head">${headHtml}</div>${body}</div>`;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  // ────────────────────────────────────────────────────────────────────────────

  function saveAllRows() {
    // Confirmation dialog for update mode
    const mode = "<?= $mode ?>";
    if (mode === "edit") {
      if (!confirm("Are you sure you want to update all the selected records? This action will modify the database.")) {
        return;
      }
    }
    
    const createdBy = document.getElementById("createdBy").value;
    const rows = [];
    const tableName = "<?= $table ?>";
    const saveButton = document.getElementById("saveButton");
    const table = document.querySelector('.spreadsheet-table');
    
    // Define required fields for each table
    const requiredFields = {
      'master_register':        ['file_number', 'full_name', 'designation', 'status_id'],
      'pensioners_register':    ['file_number', 'full_name', 'designation', 'status_id'],
      'contractstaff_register': ['file_number', 'name_of_officer', 'title'],
      'semicurrent_records':    ['file_reference_number', 'file_title', 'date_opened', 'action_date', 'box_no'],
      'file_diary':             ['file_ref_number', 'file_title', 'date_opened'],
      'subject_index':          ['current_file_title', 'current_index', 'status_id']
    };
    
    // Get column names from the table headers, excluding ID and Select columns
    const allHeaders = Array.from(document.querySelectorAll('thead th'));
    const columnNames = [];
    
    allHeaders.forEach((th, index) => {
      const text = th.textContent.trim();
      if (text.includes('ID') || text.includes('Select') || text.includes('Hashtag') || text.includes('Check')) {
        return;
      }
      // Map display names to database column names
      const columnMap = {
        'File Number': 'file_number',
        'Full Name': 'full_name',
        'Name of Officer': 'name_of_officer',
        'Designation': 'designation',
        'Title': 'title',
        'Date Opened': 'date_opened',
        'Date Closed': 'date_closed',
        'Date Received': 'date_received',
        'Subject': 'subject',
        'Date Received in OpenRegistry': 'date_received_in_openregistry',
        'File Reference Number': 'file_reference_number',
        'File Title': 'file_title',
        'Action Date': 'action_date',
        'Box No': 'box_no',
        'File Ref Number': 'file_ref_number',
        'Address Contact': 'address_contact',
        'File Name': 'file_name',
        'Current File Title': 'current_file_title',
        'Current Index': 'current_index',
        'Previous File Title': 'previous_file_title',
        'Previous Index': 'previous_index',
        'Running Volume': 'volume_number',
        'Volume Number': 'volume_number',
        'Security Grading': 'security_grading',
        'Retention Period': 'retention_period',
        'Action Category': 'action_category',
        'Location No': 'location_no',
        'Temp Box No': 'temp_box_no'
      };
      let columnName = columnMap[text] || text.toLowerCase().replace(/\s+/g, '_');
      if ((text === 'Status' || text === 'Status Id') && ['master_register', 'contractstaff_register', 'pensioners_register', 'subject_index'].includes(tableName)) {
        columnName = 'status_id';
      }
      if (columnName && !columnName.includes('select')) {
        columnNames.push(columnName);
      }
    });
    
    // Add loading state
    saveButton.textContent = 'Saving...';
    saveButton.disabled = true;
    table.classList.add('loading');
    
    if (mode === "new") {
      // New entries mode
      const tableRows = Array.from(document.querySelectorAll('.excel-table tbody tr'));
      for (let i = 0; i < tableRows.length; i++) {
        const row = tableRows[i];
        const inputs = Array.from(row.querySelectorAll('input[data-col], select[data-col]'))
          .filter(input => !input.classList.contains('created-by-field'));
        const rowData = new Array(<?= count($columns) ?>).fill('');

        inputs.forEach(input => {
          const colIndex = parseInt(input.getAttribute('data-col'), 10);
          if (!Number.isNaN(colIndex) && colIndex >= 0 && colIndex < rowData.length) {
            rowData[colIndex] = input.value.trim();
          }
        });
        
        // Check only required fields for this table
        const tableRequiredFields = requiredFields[tableName] || [];
        const missingRequiredFields = [];
        
        tableRequiredFields.forEach(requiredField => {
          const fieldIndex = columnNames.indexOf(requiredField);
          if (fieldIndex !== -1 && (!rowData[fieldIndex] || rowData[fieldIndex].trim() === "")) {
            missingRequiredFields.push(requiredField);
          }
        });
        
        if (missingRequiredFields.length > 0) {
          const fieldNames = missingRequiredFields.map(field => {
            const displayNames = {
              'file_number': 'File Number',
              'full_name': 'Full Name',
              'name_of_officer': 'Name of Officer',
              'designation': 'Designation',
              'title': 'Title',
              'date_opened': 'Date Opened',
              'status': 'Status',
              'status_id': 'Status',
              'file_reference_number': 'File Reference Number',
              'file_title': 'File Title',
              'action_date': 'Action Date',
              'box_no': 'Box No',
              'file_ref_number': 'File Ref Number',
              'address_contact': 'Address/Contact',
              'date_received': 'Date Received',
              'subject': 'Subject',
              'date_received_in_openregistry': 'Date Received in OpenRegistry',
              'current_file_title': 'Current File Title',
              'current_index': 'Current Index'
            };
            return displayNames[field] || field;
          }).join(', ');
          
          showSaveResult('danger',
            '<i class="fas fa-exclamation-circle me-2"></i>Required fields missing',
            [`Row ${i + 1} — Missing: ${fieldNames}`]
          );
          resetSaveButton();
          return;
        }
        rows.push(rowData);
      }
    } else {
      // Update records mode
      // Get all table rows (excluding header)
      const tableRows = Array.from(document.querySelectorAll('tbody tr'));
      const selectedIds = new Set(Array.from(document.querySelectorAll('.row-selector-checkbox:checked')).map(cb => cb.value));
      
      for (let rowIndex = 0; rowIndex < tableRows.length; rowIndex++) {
        const row = tableRows[rowIndex];
        // Get row ID from data attribute
        const rowId = row.getAttribute('data-row-id');
        if (!rowId) continue;
        if (!selectedIds.has(String(rowId))) {
          continue;
        }
        
        // Get all inputs and selects in this row that have data-col attribute (excludes radio buttons)
        const allInputs = Array.from(row.querySelectorAll('input[data-col], select[data-col]'))
          .filter(input => input.type !== 'radio' && !input.classList.contains('created-by-field')); // Exclude radio buttons and created_by hidden field
        
        // Create an array to hold row data in the correct column order
        // data-col values correspond directly to the $columns array indices from PHP
        const maxCol = Math.max(...allInputs.map(input => parseInt(input.getAttribute('data-col'))));
        const rowData = new Array(maxCol + 1).fill('');
        
        // Fill rowData array based on data-col attribute
        allInputs.forEach(input => {
          const colIndex = parseInt(input.getAttribute('data-col'));
          if (colIndex >= 0) {
            rowData[colIndex] = input.value.trim();
          }
        });
        
        // Check only required fields for this table using columnNames array
        const tableRequiredFields = requiredFields[tableName] || [];
        const missingRequiredFields = [];
        
        tableRequiredFields.forEach(requiredField => {
          // Find the column index in the PHP $columns array
          const phpColumnIndex = columnNames.indexOf(requiredField);
          if (phpColumnIndex >= 0 && phpColumnIndex < rowData.length && (!rowData[phpColumnIndex] || rowData[phpColumnIndex].trim() === "")) {
            missingRequiredFields.push(requiredField);
          }
        });
        
        if (missingRequiredFields.length > 0) {
          const fieldNames = missingRequiredFields.map(field => {
            const displayNames = {
              'file_number': 'File Number',
              'full_name': 'Full Name',
              'name_of_officer': 'Name of Officer',
              'designation': 'Designation',
              'title': 'Title',
              'date_opened': 'Date Opened',
              'status': 'Status',
              'status_id': 'Status',
              'file_reference_number': 'File Reference Number',
              'file_title': 'File Title',
              'action_date': 'Action Date',
              'box_no': 'Box No',
              'file_ref_number': 'File Ref Number',
              'address_contact': 'Address/Contact',
              'date_received': 'Date Received',
              'subject': 'Subject',
              'date_received_in_openregistry': 'Date Received in OpenRegistry',
              'current_file_title': 'Current File Title',
              'current_index': 'Current Index'
            };
            return displayNames[field] || field;
          }).join(', ');
          
          showSaveResult('danger',
            '<i class="fas fa-exclamation-circle me-2"></i>Required fields missing',
            [`Row ${rowIndex + 1} (ID: ${rowId}) — Missing: ${fieldNames}`]
          );
          resetSaveButton();
          return;
        }
        rows.push({id: rowId, data: rowData});
      }
    }

    if (mode === "edit" && rows.length === 0) {
      showSaveResult('warning',
        '<i class="fas fa-exclamation-triangle me-2"></i>No records selected',
        ['Check at least one row using the checkboxes on the left before updating.']
      );
      resetSaveButton();
      return;
    }
    
    fetch("bulkSave.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        table: "<?= $table ?>",
        rows: rows,
        created_by: createdBy,
        mode: mode
      })
    })
    .then(res => res.json())
    .then(response => {
      console.log("Save response:", response);
      if (response.success) {
        updateSavedCount();
        const partialErrors = response.errors && response.errors.length > 0;
        if (partialErrors) {
          showSaveResult('partial',
            `<i class="fas fa-check-circle me-2"></i>${response.saved} row(s) saved — ${response.errors.length} issue(s) found`,
            response.errors
          );
        } else {
          showSaveResult('success',
            `<i class="fas fa-check-circle me-2"></i>${response.saved} row(s) saved successfully`,
            []
          );
          setTimeout(() => {
            const panel = document.getElementById('saveMessage');
            if (panel) panel.innerHTML = '';
          }, 4000);
        }
        table.classList.add('success-pulse');
        setTimeout(() => table.classList.remove('success-pulse'), 300);
        if (mode === "edit") updateRowColors();
      } else {
        showSaveResult('danger',
          `<i class="fas fa-exclamation-circle me-2"></i>Save failed — ${response.errors ? response.errors.length : 0} issue(s)`,
          response.errors || ['An unexpected error occurred. Please try again.']
        );
      }
    })
    .catch(() => {
      showSaveResult('danger',
        '<i class="fas fa-wifi me-2"></i>Connection error',
        ['Could not reach the server. Check your connection and try again.']
      );
    })
    .finally(() => {
      resetSaveButton();
    });
  }

  function resetSaveButton() {
    const saveButton = document.getElementById("saveButton");
    const table = document.querySelector('.spreadsheet-table');
    
    saveButton.textContent = 'Save';
    saveButton.disabled = false;
    table.classList.remove('loading');
  }

  function updateRowColors() {
    // Update row background colors based on status
    document.querySelectorAll('tr[class*="status-"]').forEach(row => {
      const statusSelect = row.querySelector('select[data-col]');
      if (statusSelect) {
        const status = statusSelect.value.toLowerCase().replace('-', '-');
        row.className = row.className.replace(/status-\w+/, '') + ` status-${status}`;
      }
    });
  }

  // Add event listeners for status changes
  document.addEventListener('change', function(e) {
    if (e.target.tagName === 'SELECT' && e.target.getAttribute('data-col') !== null) {
      updateRowColors();
    }
  });

  // Delete selected records function (admin only)
  function deleteSelectedRecords() {
    const selectedCheckboxes = document.querySelectorAll('.row-selector-checkbox:checked');
    
    if (!selectedCheckboxes || selectedCheckboxes.length === 0) {
      alert('Please select at least one record to delete by checking the boxes in the Select column.');
      return;
    }
    
    const recordIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    const recordCount = recordIds.length;
    
    if (!confirm(`⚠️ WARNING: Are you sure you want to delete ${recordCount} record(s)?\n\nThis action CANNOT be undone and will permanently remove these records from the database.`)) {
      return;
    }

    const deleteButton = document.getElementById('deleteSelectedButton');
    const originalContent = deleteButton.innerHTML;
    
    // Disable the button and show loading state
    deleteButton.disabled = true;
    deleteButton.textContent = 'Deleting...';

    const tableName = "<?= $table ?>";
    let deletedCount = 0;
    let failedCount = 0;
    const rowsToRemove = [];

    // Delete records one by one
    const deletePromises = recordIds.map(recordId => {
      return fetch('deleteRecord.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          record_id: recordId,
          table: tableName
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          deletedCount++;
          const checkbox = document.querySelector(`.row-selector-checkbox[value="${recordId}"]`);
          if (checkbox) {
            rowsToRemove.push(checkbox.closest('tr'));
          }
        } else {
          failedCount++;
        }
        return data;
      })
      .catch(err => {
        console.error('Delete error:', err);
        failedCount++;
        return { success: false, error: err.message };
      });
    });

    // Wait for all deletions to complete
    Promise.all(deletePromises).then(() => {
      // Remove rows with fade-out animation
      rowsToRemove.forEach((row, index) => {
        setTimeout(() => {
          row.style.transition = 'opacity 0.3s';
          row.style.opacity = '0';
          setTimeout(() => {
            row.remove();
            
            // Update total rows count
            const totalRowsElement = document.getElementById('totalRows');
            const currentTotal = parseInt(totalRowsElement.textContent) || 0;
            totalRowsElement.textContent = Math.max(0, currentTotal - 1);
          }, 300);
        }, index * 50); // Stagger animations
      });

      // Show success/failure message
      if (deletedCount > 0) {
        const message = failedCount > 0 
          ? `${deletedCount} record(s) deleted successfully. ${failedCount} failed.`
          : `${deletedCount} record(s) deleted successfully.`;
        showDeleteMessage(message, failedCount > 0 ? 'warning' : 'success');
      } else {
        showDeleteMessage('Failed to delete records. Please try again.', 'danger');
      }

      deleteButton.disabled = false;
      deleteButton.innerHTML = originalContent;
    });
  }

  function showDeleteMessage(message, type) {
    const messageDiv = document.getElementById('saveMessage');
    messageDiv.innerHTML = `<div class="alert alert-${type}"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}</div>`;
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
      const alert = messageDiv.querySelector('.alert');
      if (alert) alert.remove();
    }, 3000);
  }

  function updateSavedCount() {
    fetch("countSaved.php?table=<?= $table ?>&created_by=<?= $username ?>")
      .then(res => res.json())
      .then(data => {
        document.getElementById("savedCount").textContent = data.count;
      });
  }
  window.onload = function() {
    updateSavedCount();
    addSpreadsheetInteractivity();
    // Pre-build status option HTML for addMoreRows dynamic inserts
    window._statusKeywordOptions = <?= json_encode(implode('', array_map(
        fn($opt) => '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>',
        $statusOptions[$table] ?? $statusDisplayOpts
    ))) ?>;
  };

  // Add More Rows Function
  function addMoreRows() {
    const additionalRows = parseInt(prompt("How many more rows do you want to add?", "5"), 10);
    if (!additionalRows || additionalRows <= 0) {
      return;
    }

    const tbody = document.querySelector('.excel-table tbody');
    const currentRowCount = document.querySelectorAll("input[data-row], select[data-row]").length / <?= count($columns) ?>;
    const columns = <?= json_encode($columns) ?>;
    const columnLabels = <?= json_encode(array_map(function ($col) { return ucwords(str_replace('_', ' ', $col)); }, $columns)) ?>;
    const username = <?= json_encode($username) ?>;
    const statusOptionsHtml = window._statusKeywordOptions || '';

    for (let i = 0; i < additionalRows; i++) {
      const row = document.createElement('tr');
      const rowIndex = currentRowCount + i;

      columns.forEach((col, index) => {
        const cell = document.createElement('td');
        let cellHtml = '';

        if (col === 'status_id') {
          cellHtml = `<select class="form-select status-dropdown" data-row="${rowIndex}" data-col="${index}">
            <option value="">Select Status</option>
            ${statusOptionsHtml}
          </select>`;
        } else if (col === 'created_by') {
          cellHtml = `<input type="hidden" class="created-by-field" data-row="${rowIndex}" data-col="${index}" value="${escHtmlJS(username)}">
            <span class="form-control-plaintext">${escHtmlJS(username)}</span>`;
        } else if (col === 'security_grading') {
          cellHtml = `<select class="form-select" data-row="${rowIndex}" data-col="${index}">
            <option value="">Select Security Grading</option>
            <option value="Open">Open</option>
            <option value="Confidential">Confidential</option>
            <option value="Secret">Secret</option>
            <option value="Top Secret">Top Secret</option>
          </select>`;
        } else {
          const inputType = col.includes('date') ? 'date' : 'text';
          const placeholder = columnLabels[index] || col;
          cellHtml = `<input type="${inputType}" class="form-control" data-row="${rowIndex}" data-col="${index}" placeholder="Enter ${escHtmlJS(placeholder)}">`;
        }

        cell.innerHTML = cellHtml;
        row.appendChild(cell);
      });

      tbody.appendChild(row);
    }

    const newTotalRows = currentRowCount + additionalRows;
    document.getElementById('totalRows').textContent = newTotalRows;
    setTimeout(addSpreadsheetInteractivity, 100);
  }

  // Spreadsheet-like interactivity
  function addSpreadsheetInteractivity() {
    const table = document.querySelector('.excel-table');
    if (!table) return;

    // Auto-expand text inputs based on content
    function autoExpandInput(input) {
      const minWidth = 100;
      const maxWidth = 400;
      const textLength = input.value.length;
      
      if (textLength > 15) {
        const newWidth = Math.min(minWidth + (textLength * 8), maxWidth);
        input.style.width = newWidth + 'px';
      } else {
        input.style.width = '100%';
      }
    }

    // Add event listeners to all inputs
    function addInputListeners() {
      const inputs = table.querySelectorAll('input[type="text"], input[type="date"]');
      inputs.forEach(input => {
        // Auto-expand on input
        input.addEventListener('input', function() {
          autoExpandInput(this);
        });
        
        // Auto-expand on focus
        input.addEventListener('focus', function() {
          autoExpandInput(this);
          this.style.minWidth = '200px';
        });
        
        // Reset on blur if empty
        input.addEventListener('blur', function() {
          if (this.value.trim() === '') {
            this.style.width = '100%';
            this.style.minWidth = '100px';
          }
        });
        
        // Handle Enter key for next cell navigation
        input.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            navigateToNextCell(this);
          }
        });
        
        // Handle Tab key for cell navigation
        input.addEventListener('keydown', function(e) {
          if (e.key === 'Tab') {
            e.preventDefault();
            navigateToNextCell(this);
          }
        });
      });
    }

    // Navigate to next cell
    function navigateToNextCell(currentInput) {
      const currentCell = currentInput.closest('td');
      const currentRow = currentCell.closest('tr');
      const currentColIndex = Array.from(currentRow.children).indexOf(currentCell);
      
      let nextCell = null;
      
      // Try to find next cell in same row
      if (currentColIndex < currentRow.children.length - 1) {
        nextCell = currentRow.children[currentColIndex + 1].querySelector('input, select');
      } else {
        // Move to first cell of next row
        const nextRow = currentRow.nextElementSibling;
        if (nextRow) {
          nextCell = nextRow.querySelector('input, select');
        }
      }
      
      if (nextCell) {
        nextCell.focus();
        if (nextCell.tagName === 'INPUT' && nextCell.type === 'text') {
          nextCell.select();
        }
      }
    }

    // Add resize handles to cells
    function addResizeHandles() {
      const cells = table.querySelectorAll('td');
      cells.forEach(cell => {
        if (!cell.querySelector('.cell-resize-handle')) {
          const handle = document.createElement('div');
          handle.className = 'cell-resize-handle';
          cell.appendChild(handle);
          
          // Add resize functionality
          handle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            startResize(cell, e);
          });
        }
      });
    }

    // Cell resize functionality
    function startResize(cell, e) {
      const startX = e.clientX;
      const startWidth = cell.offsetWidth;
      
      function doResize(e) {
        const newWidth = startWidth + (e.clientX - startX);
        if (newWidth > 100) {
          cell.style.width = newWidth + 'px';
          cell.style.minWidth = newWidth + 'px';
        }
      }
      
      function stopResize() {
        document.removeEventListener('mousemove', doResize);
        document.removeEventListener('mouseup', stopResize);
      }
      
      document.addEventListener('mousemove', doResize);
      document.addEventListener('mouseup', stopResize);
    }

    // Initialize interactivity
    addInputListeners();
    addResizeHandles();
    
    // Re-initialize when new rows are added
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
          addInputListeners();
          addResizeHandles();
        }
      });
    });
    
    observer.observe(table, {
      childList: true,
      subtree: true
    });
  }
</script>
  <script>
    // Client-side search/filter for edit_register — filters rows by any visible text (name, file number, etc.)
    function filterTable() {
      const q = document.getElementById('searchInput').value.trim().toLowerCase();
      const rows = Array.from(document.querySelectorAll('.excel-table tbody tr'));
      let found = false;
      rows.forEach(r => {
        const fieldValues = Array.from(r.querySelectorAll('input, select, textarea'))
          .map(field => field.value || '')
          .join(' ')
          .toLowerCase();
        const text = (r.innerText + ' ' + fieldValues).toLowerCase();
        if (!q || text.indexOf(q) !== -1) {
          r.style.display = '';
          found = true;
        } else {
          r.style.display = 'none';
        }
      });
      if (found) {
        const first = rows.find(r => r.style.display !== 'none');
        if (first) first.scrollIntoView({behavior: 'smooth', block: 'center'});
      }
    }
  </script>
</body>
</html>